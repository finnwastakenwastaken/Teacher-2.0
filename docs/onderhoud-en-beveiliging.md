# Onderhoud en beveiliging

Deze gids is voor degene die de server beheert. Hij gaat over updaten,
back-uppen, herstellen en over de afspraken die de site veilig houden.

> Dit is de Nederlandse versie van [maintenance.md](maintenance.md). Het is
> dezelfde gids; een wijziging in de een hoort ook in de ander.

De installatie zelf staat in de [installatiegids](installatiegids.md); het
dagelijks gebruik in de [beheerdersgids](beheerdersgids.md).

Alle commando's draai je vanuit de map waarin de site staat, meestal
`/opt/teacher`.

---

## Inhoud

1. [Dagelijkse commando's](#1-dagelijkse-commandos)
2. [Back-ups](#2-back-ups)
3. [Terugzetten](#3-terugzetten)
4. [Updaten](#4-updaten)
5. [Wachtwoord kwijt](#5-wachtwoord-kwijt)
6. [Een groot bestand erop zetten zonder de browser](#6-een-groot-bestand-erop-zetten-zonder-de-browser)
7. [Schijfruimte](#7-schijfruimte)
8. [Logboeken](#8-logboeken)
9. [Hoe de afscherming werkt](#9-hoe-de-afscherming-werkt)
10. [Wat je nooit moet doen](#10-wat-je-nooit-moet-doen)
11. [Problemen oplossen](#11-problemen-oplossen)

---

## 1. Dagelijkse commando's

| Wat | Commando |
|---|---|
| Status van de stack | `docker compose ps` |
| Logboek van de applicatie | `docker compose logs -f app` |
| Herstarten | `docker compose restart` |
| Stoppen | `docker compose down` |
| Starten | `docker compose --profile tunnel up -d` |
| Een artisan-commando | `docker compose exec app php artisan <commando>` |

`docker compose down` verwijdert de containers, **niet** de gegevens. Die staan
in Docker-volumes en blijven bestaan. Gebruik nooit `down -v`: dat wist ze wél.

---

## 2. Back-ups

De site maakt zijn eigen back-ups. Eén commando levert één bestand op waar
alles in zit:

```bash
docker compose exec app php artisan backup:run
```

Dat schrijft `teacher-backup-JJJJ-MM-DD-UUMMSS.tar.gz` en vertelt waar het staat.
Erin zitten de database — pagina's, onderwerpen, instellingen, het
beheerdersaccount — en elke geüploade afbeelding, elk document en elke video. De
docent kan hetzelfde doen via **Back-ups** in het beheerpaneel, inclusief het
bestand naar de eigen computer downloaden.

Twee dingen zitten er bewust **niet** in:

- **`.env`.** Daar staan het databasewachtwoord en de `APP_KEY` in, en een
  back-up is juist een bestand dat je op een laptop en een USB-stick zet. Bewaar
  `.env` apart, en net zo zorgvuldig als een wachtwoord.
- **De code.** Die staat in de repository.

```bash
cp /opt/teacher/.env /var/backups/teacher-env-$(date +%F)
```

`.env` kwijtraken is te overleven — een nieuwe `APP_KEY` maakt alleen
ontgrendel-cookies ongeldig die leerlingen al hebben — maar je hebt het
databasewachtwoord nodig om de stack op een bestaand volume te starten.

### Automatisch laten draaien

```bash
printf '#!/bin/sh\ncd /opt/teacher || exit 1\ndocker compose exec -T app php artisan backup:run --prune\ndocker compose exec -T app php artisan media:prune-uploads\n' > /usr/local/bin/teacher-backup.sh
```

```bash
chmod +x /usr/local/bin/teacher-backup.sh
```

Elke nacht om half drie:

```bash
echo '30 2 * * * root /usr/local/bin/teacher-backup.sh' > /etc/cron.d/teacher-backup
```

De tweede regel ruimt de resten op van uploads die wel begonnen maar nooit
afgemaakt zijn. Dat gebeurt ook elke keer dat de container opstart, en op een
machine die af en toe herstart is dat genoeg — maar een server die maanden
ongestoord draait start nooit op, en een afgebroken upload van twee gigabyte
blijft dan in het media-volume staan waar hij elke nacht mee wordt geback-upt.

`--prune` bewaart de nieuwste paar back-ups en gooit de rest weg, zodat het
volume niet oneindig groeit. Hoeveel dat er zijn staat in `BACKUP_KEEP` in
`.env`, standaard zeven. Zonder die vlag wordt er nooit iets verwijderd — een
taak die 's nachts ongevraagd de enige kopie van een jaar werk weggooit doet
deze site niet namens jou.

### Zet ze ergens anders neer

De back-ups staan in het Docker-volume `teacher_backups`, op dezelfde schijf als
al het andere. Een back-up naast het origineel beschermt tegen een vergissing,
niet tegen een kapotte schijf of een gestolen server. Haal ze eraf:

```bash
mkdir -p /var/backups/teacher && docker compose cp app:/var/www/html/storage/app/backups/. /var/backups/teacher/
```

Synchroniseer `/var/backups/teacher` daarna naar een andere machine, of download
de back-up via het Back-ups-scherm.

### Controleer je back-up

Een back-up die nooit is teruggezet is een aanname, geen back-up. Zet er één keer
per jaar één terug op een testmachine en kijk of de site opkomt. §3 is precies
die procedure, en dezelfde die je in nood zou draaien.

---

## 3. Terugzetten

```bash
sudo ./restore.sh /var/backups/teacher/teacher-backup-2026-08-10-023000.tar.gz
```

Dat is alles. Het script maakt eerst een veiligheidsback-up van wat er nu staat,
vervangt de database en elk geüpload bestand door die uit de back-up, herstart en
wacht tot de site weer antwoordt. Was het het verkeerde bestand, dan staat die
veiligheidsback-up op het Back-ups-scherm en gaat hij op dezelfde manier terug.

Het **vervangt**, het voegt niet samen. De inhoud van twee sites is niet te
verenigen zonder vragen die een script niet kan stellen, dus er is geen
tussenweg.

### Naar een nieuwe machine

Zo verhuist de site. Op de nieuwe machine:

```bash
sudo ./install.sh
```

```bash
sudo ./restore.sh /pad/naar/teacher-backup-2026-08-10-023000.tar.gz
```

Het beheerdersaccount komt met de database mee, dus er is geen claim-scherm om
voor te zijn en geen setup-token om op te zoeken — log in met het wachtwoord dat
je al had. Heb je ook de oude `.env` bewaard, zet die dan neer vóór je
`install.sh` draait; dan houdt de site ook zijn oorspronkelijke `APP_KEY`.

De installer kan beide stappen in één keer doen:

```bash
sudo TEACHER_RESTORE=/root/teacher-backup-2026-08-10-023000.tar.gz ./install.sh
```

### Staat de back-up op je laptop

Download hem van het Back-ups-scherm, zet hem op de server en ga verder:

```bash
scp teacher-backup-2026-08-10-023000.tar.gz root@jouw-server:/root/
```

### Met de hand

`restore.sh` is een omhulsel. Draait de site al en wil je alleen de stap zelf:

```bash
docker compose cp teacher-backup-2026-08-10-023000.tar.gz app:/tmp/restore.tar.gz
```

```bash
docker compose exec app php artisan backup:restore /tmp/restore.tar.gz
```

Zonder `--force` vraagt het commando eerst om bevestiging en vertelt het wat er
in de back-up zit.

---

## 4. Updaten

```bash
cd /opt/teacher && sudo ./update.sh
```

Dat script doet, in deze volgorde:

1. een databasedump naar `/var/backups`, vóór alles;
2. `git pull --ff-only`, en het toont welke wijzigingen binnenkomen;
3. de containers opnieuw bouwen en starten (migraties draaien in de
   entrypoint, dus dat is de hele uitrol);
4. wachten tot de site weer antwoordt, met de logregels erbij als dat niet
   lukt;
5. losse oude image-lagen opruimen.

Het is veilig om opnieuw te draaien, en het doet niets meer dan opnieuw bouwen
als er niets te halen valt. `TEACHER_SKIP_BACKUP=1` slaat de dump over —
gebruik dat alleen als je er net zelf een hebt gemaakt.

Met de hand is het:

```bash
cd /opt/teacher && git pull && docker compose --profile tunnel up -d --build
```

**Maak dan eerst zelf een back-up van de database.** Een update kan de structuur
van de database aanpassen, en dat is de enige stap in het hele onderhoud die je
niet zomaar terugdraait.

De site is tijdens het bouwen gewoon bereikbaar; alleen tijdens het omwisselen
van de containers is er een onderbreking van enkele seconden.

Controleer daarna:

```bash
docker compose logs --tail=40 app
```

De laatste regel hoort `[entrypoint] Ready.` te zijn. Open daarna de site en
haal één download op — dat is de snelste controle dat zowel de applicatie als
het uitserveren van bestanden nog werkt.

### Docker en het besturingssysteem

```bash
apt update && apt upgrade -y
```

Beveiligingsupdates gaan automatisch als je bij de installatie
`unattended-upgrades` hebt aangezet. Herstart de machine na een kernel-update;
de stack komt vanzelf weer op, want alle containers staan op
`restart: unless-stopped`.

**Dat commando werkt de machine bij, niet de site.** Alles wat de site echt
draait — nginx, PHP, PostgreSQL, ImageMagick, OpenSSL, de tunnel — komt niet
uit de apt van deze machine; het zit in containers die op hun eigen
basis-images zijn gebouwd. `update.sh` is wat díe bijwerkt: hij haalt bij elke
run alle basis-images opnieuw op en bouwt daar tegenaan. Hem af en toe draaien
is dus ook zinvol als er niets nieuws te halen valt, en een server die een jaar
met rust wordt gelaten loopt op al die onderdelen een jaar achter — hoe groen
`apt upgrade` er ook uitziet.

---

## 5. Wachtwoord kwijt

Er is met opzet **geen** "wachtwoord vergeten"-e-mail. De site verstuurt geen
post, dus die weg zou een extra afhankelijkheid zijn die precies één keer per
paar jaar gebruikt wordt en dan stuk blijkt te zijn.

Herstel loopt via de server:

```bash
docker compose exec app php artisan admin:reset-password
```

Het commando toont om welk account het gaat, vraagt twee keer om het nieuwe
wachtwoord en laat het niet op het scherm zien. Er is bewust geen optie om het
wachtwoord als argument mee te geven: dat zou in de shell-geschiedenis en in
`ps` terechtkomen.

Is het account nog helemaal niet aangemaakt, dan zegt het commando dat en
verwijst het naar het opeisscherm.

---

## 6. Een groot bestand erop zetten zonder de browser

Voor bestanden die te groot zijn om te uploaden — of als de upload om een andere
reden niet lukt — kun je ze direct op de server zetten.

1. Kopieer het bestand naar de map `storage-import` naast het compose-bestand:

```bash
scp grote-video.mp4 root@server:/opt/teacher/storage-import/
```

2. Registreer het:

```bash
docker compose exec app php artisan media:import
```

Het bestand verschijnt daarna gewoon in de mediabibliotheek en gedraagt zich
verder als elk ander bestand.

Nuttige opties:

- `--alt="Beschrijving"` — alt-tekst voor afbeeldingen. Zonder alt-tekst wordt
  een afbeelding geweigerd, net als in de browser.
- `--prune` — verwijdert het bronbestand na het importeren.

Geïmporteerde afbeeldingen worden net als geüploade afbeeldingen automatisch
omgezet naar WebP en verkleind. Grote video's blijven ongemoeid — daar gebeurt
niets mee.

### Afbeeldingen verkleinen die er al stonden

Het omzetten gebeurt op het moment dat een bestand binnenkomt. Alles wat er al
stond blijft dus zoals het was. Eén commando zoekt die op en vertelt wat het
zou schelen:

```bash
docker compose exec app php artisan media:optimise
```

Er verandert **niets** zolang je er geen `--force` bij zet. Met `--all` kijkt
het naar alle afbeeldingen, niet alleen die boven de 2 MB. Maak eerst een
back-up: de oude bestanden worden vervangen en de originelen worden niet
bewaard.

Standaard wordt er **gekopieerd** en blijft het origineel staan; die map is op
de host van root en de container mag er niet in schrijven. Ruim `storage-import`
zelf op als je klaar bent, anders staat het bestand er twee keer.

---

## 7. Schijfruimte

Video's lopen hard op. Controleren:

```bash
df -h && docker system df -v | grep teacher_
```

Ruimte terugwinnen:

- **Oude images opruimen** na een paar updates:
  `docker image prune -a` — dit raakt geen gegevens.
- **Afgebroken uploads** worden bij elke start automatisch opgeruimd. Handmatig:
  `docker compose exec app php artisan media:prune-uploads`.
- **Ongebruikte bestanden** verwijder je in de beheeromgeving onder *Media*.
  Bestanden die nog ergens gebruikt worden weigert de site te verwijderen, dus
  je kunt er niet per ongeluk een pagina mee slopen.
- **Oude back-ups.** Elke back-up bevat een volledige kopie van de
  mediabibliotheek en groeit dus het hardst van alles. Verwijderen doe je op het
  Back-ups-scherm — maar haal ze eerst van de machine af (§2). Met de nachtelijke
  taak uit §2 speelt dit niet: `--prune` ruimt bij elke nieuwe back-up de oudere
  op.

---

## 8. Logboeken

| Wat | Waar |
|---|---|
| Applicatie | `docker compose logs app`, en `storage/logs/laravel.log` in het volume `teacher_logs` |
| Webserver | `docker compose logs web` |
| Database | `docker compose logs database` |
| Tunnel | `docker compose logs tunnel` |

Het logniveau staat op `warning`. Zet `LOG_LEVEL=debug` in `.env` als je een
probleem onderzoekt, en zet het daarna weer terug — op `debug` groeit het
logbestand snel.

Er wordt niets over bezoekers vastgelegd behalve wat de webserver zelf in zijn
toegangslog schrijft. De site heeft geen analytics en laadt geen externe
scripts.

---

## 9. Hoe de afscherming werkt

Handig om te weten voordat je iets aanpast.

**Er is één account.** Registratie bestaat niet — niet als scherm, niet als
route, niet als controller. Het account kan ook niet verwijderd worden, op drie
plaatsen tegelijk geblokkeerd. Dat is geen dubbel werk maar opzet: één
onvoorzichtige wijziging mag geen gat maken.

**Geen enkel geüpload bestand staat op een publieke map.** Alles staat op een
privéschijf. Voor elk bestand beslist de applicatie apart of deze bezoeker het
mag zien:

- ben je ingelogd, dan mag alles;
- ben je dat niet, dan mag je een bestand alleen zien als er een pagina is die
  het toont én die je zelf mag openen.

Een bestand dat alleen in de mediabibliotheek staat is dus voor niemand
bereikbaar. Een bestand op een pagina met een wachtwoord vereist datzelfde
wachtwoord. Het adres van een bestand raden helpt niet.

**Het uitserveren zelf doet nginx.** De applicatie beslist alleen; daarna geeft
hij nginx een interne verwijzing naar het bestand. Die interne locatie is van
buiten niet aan te roepen. Zo blijft de controle staan terwijl één PHP-proces
niet de hele video lang bezet is.

**Wachtwoorden op pagina's** zijn losse, herbruikbare records. Wie er een
invoert krijgt een cookie waarin een afdruk van het huidige wachtwoord zit. Als
de docent het wachtwoord wijzigt, zijn alle uitgedeelde cookies daarmee meteen
ongeldig. Dat is de manier om toegang in te trekken.

**Verborgen is niet geheim.** Een verborgen pagina staat niet in menu's en niet
in de zoekresultaten, maar blijft via een directe link bereikbaar. Afschermen
doe je met een wachtwoord.

**Snelheidslimieten** staan op inloggen (5 per minuut), op het opeisen van het
account (5 per minuut) en op het invoeren van een paginawachtwoord. Die laatste
telt per IP-adres, wat alleen klopt omdat de applicatie de proxy vertrouwt voor
het echte adres van de bezoeker.

**HSTS zet je bij Cloudflare, niet hier.** De webserver van de site ziet alleen
gewoon HTTP — Cloudflare regelt de versleuteling en de tunnel brengt het verzoek
daarvandaan binnen — en kan een browser dus niet beloven dat dit domein altijd
HTTPS is. Cloudflare kan dat wel: zet **SSL/TLS → Edge Certificates → HTTP
Strict Transport Security** aan zodra de site draait en werkt. Doe dat *daarna*
en niet ervoor: browsers onthouden HSTS, dus aanzetten terwijl er over HTTPS nog
iets stuk is, is lastig terug te draaien.

De rest — `X-Frame-Options`, `Referrer-Policy`, het content-securitybeleid —
stuurt de site zelf mee en vraagt niets van jou.

---

## 10. Wat je nooit moet doen

- **`FILESYSTEM_DISK` naar de publieke schijf zetten.** Dan staan alle
  geüploade bestanden rechtstreeks op het web en is elke wachtwoordcontrole
  zinloos.
- **Een tweede nginx-locatie toevoegen die `storage/app/private` uitserveert.**
  Zelfde effect.
- **`MEDIA_X_ACCEL=false` in productie.** De site blijft werken, maar één klas
  die tegelijk een video kijkt legt hem plat.
- **`docker compose down -v`.** Dat verwijdert de volumes: database en alle
  geüploade bestanden.
- **`.env` in de repository zetten of doorsturen.** Er staan de
  applicatiesleutel, het databasewachtwoord en het tunnel-token in.
- **Een back-upbestand ergens neerzetten waar de webserver bij kan.** Er zit de
  hele database in, inclusief de wachtwoord-hash van de beheerder. Hij is
  bewust alleen te downloaden vanuit het beheerpaneel en nergens anders. Bewaar
  kopieën buiten de server, niet in `public/`.
- **`APP_DEBUG=true` in productie.** Dan komen bij een fout interne gegevens op
  het scherm van de bezoeker.
- **Het wachtwoord van de docent als argument meegeven** aan een commando. Het
  herstelcommando vraagt er daarom ook naar in plaats van het te accepteren.

---

## 11. Problemen oplossen

### Afbeeldingen en downloads doen het niet meer

Symptoom: inloggen en de pagina's werken, maar elke afbeelding, download en
video geeft een foutmelding.

Bijna altijd betekent dit dat nginx de bestanden niet meer kan lezen die PHP
heeft weggeschreven. Die twee draaien als verschillende gebruikers, en nginx
heeft de map alleen gekoppeld om te lezen. Dit gebeurt na een wijziging in de
schijfinstellingen of in het webserver-image.

Controleren:

```bash
docker compose exec web ls -ld /var/www/html/storage/app/private/images
```

Kan nginx er niet in, dan staan de rechten verkeerd. Herstart de
applicatiecontainer; die zet de rechten bij elke start goed. Blijft het staan,
dan is de configuratie van de privéschijf aangepast en moet die terug.

De testsuite vangt dit niet: die draait zonder nginx.

### De site is niet bereikbaar, maar de containers draaien

Kijk naar de tunnel:

```bash
docker compose logs --tail=50 tunnel
```

Een verlopen of ingetrokken token is de gebruikelijke oorzaak. Maak in
Cloudflare een nieuw token aan, zet het in `.env` en herstart:
`docker compose up -d tunnel`.

### Alles is traag

Kijk eerst naar geheugen en schijf:

```bash
free -h && df -h
```

Een volle schijf uit zich vaak eerst als traagheid en pas daarna als fouten.

### Een migratie is misgegaan tijdens een update

`update.sh` bouwt eerst, migreert daarna en vervangt de containers pas als
laatste — in die volgorde en om precies deze reden: elk van die stappen stopt
de update terwijl de oude versie nog draait. Een mislukte bouw of een mislukte
migratie laat uw site dus gewoon draaien op wat er al stond, en het script
zegt dat er ook bij.

Meld de foutmelding in beide gevallen — dit hoort niet te gebeuren en is een
fout in de software, niet in uw server.

Als de migratie zelf misging, kan de database halverwege twee versies staan:
PostgreSQL zet elke migratie apart in een transactie, maar de hele reeks niet.
Zet hem terug uit de dump die `update.sh` vooraf heeft weggeschreven. **Die
dump is platte SQL en geen back-uparchief, dus `restore.sh` neemt hem niet
aan** — u leest hem zo terug, met het nieuwste bestand uit `/var/backups`:

```bash
docker compose stop app
```

```bash
gunzip -c /var/backups/teacher-db-pre-update-2026-08-24-1130.sql.gz | docker compose exec -T database sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
```

```bash
docker compose start app
```

`update.sh` drukt deze drie regels zelf af zodra hij ergens stopt, dus u hoeft
deze pagina daarvoor niet eerst te zoeken.

De zeven nieuwste dumps blijven staan, oudere worden bij elke run opgeruimd;
met `TEACHER_KEEP_DUMPS` verandert u dat aantal.
