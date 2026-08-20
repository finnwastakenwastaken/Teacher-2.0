# Installatiegids

Deze gids is voor degene die de server beheert. Aan het einde draait de site en
kan de docent het beheerdersaccount opeisen.

> Dit is de Nederlandse versie van [deployment.md](deployment.md). Het is
> dezelfde gids; een wijziging in de een hoort ook in de ander.

Voor het dagelijks gebruik van de site: [beheerdersgids](beheerdersgids.md).
Voor updates, back-ups en herstel:
[onderhoud en beveiliging](onderhoud-en-beveiliging.md).

---

## Inhoud

1. [Wat je nodig hebt](#1-wat-je-nodig-hebt)
2. [De snelle weg: `install.sh`](#2-de-snelle-weg-installsh)
3. [Docker installeren](#3-docker-installeren)
4. [De code op de server zetten](#4-de-code-op-de-server-zetten)
5. [Het `.env`-bestand](#5-het-env-bestand)
6. [De Cloudflare Tunnel](#6-de-cloudflare-tunnel)
7. [Starten](#7-starten)
8. [Het beheerdersaccount opeisen](#8-het-beheerdersaccount-opeisen)
9. [De firewall dichtzetten](#9-de-firewall-dichtzetten)
10. [Controleren of alles werkt](#10-controleren-of-alles-werkt)
11. [Als er iets misgaat](#11-als-er-iets-misgaat)

Hoofdstuk 2 doet in één commando wat de hoofdstukken 3 tot en met 9 met de hand
beschrijven. Lees die hoofdstukken toch even door — als er later iets misgaat,
wil je weten wat er is neergezet.

---

## 1. Wat je nodig hebt

- Een machine met **Debian 13 (Trixie)** — een VPS of een virtuele machine in
  een thuisserver. Andere Linux-distributies werken ook, maar de commando's
  hieronder zijn voor Debian.
- **4 GB RAM.** Dit is een harde eis, geen aanbeveling: de frontend wordt op de
  server gebouwd en dat past niet in 2 GB.
- **20 GB schijfruimte**, meer als er veel video op komt.
- **Root-toegang** (of `sudo`).
- Een **domeinnaam** en een Cloudflare-account, als je de tunnel gebruikt.

De site draait volledig in Docker. Je hoeft geen PHP, Node of PostgreSQL op de
machine zelf te installeren, en je moet dat ook niet doen.

### Waarom een tunnel

De opzet gaat uit van een **Cloudflare Tunnel**: de server maakt zelf verbinding
naar buiten, en er hoeft geen enkele poort open te staan. Dat is precies wat je
wilt in een thuisnetwerk.

Twee gevolgen om te kennen, allebei van Cloudflare en niet van deze software:

- **Uploads groter dan 100 MB worden geweigerd.** Daarom uploadt de site in
  stukken van 20 MB. Aan de instellingen van PHP of nginx sleutelen helpt niet.
- **Grote video via een tunnel uitserveren is niet waar Cloudflare voor bedoeld
  is.** De ingebouwde speler werkt, maar voor lange video's is een
  niet-vermelde YouTube-video de betere route. Dat staat ook zo in de
  beheerdersgids.

Heb je een VPS met een eigen publiek IP-adres, dan kun je in plaats van de
tunnel je eigen reverse proxy voor de stack zetten: de webserver luistert op
`127.0.0.1:8080`. Beide beperkingen hierboven vervallen dan.

---

## 2. De snelle weg: `install.sh`

De repository bevat een installatiescript dat alles hieronder in één keer doet.
Haal de code op en draai het:

```bash
git clone https://github.com/finnwastakenwastaken/Teacher-2.0.git /opt/teacher
```

```bash
cd /opt/teacher && sudo ./install.sh
```

Er staat met opzet geen `curl … | bash` in deze gids: het script installeert
Docker, schrijft geheimen en past de firewall aan. Lees het eerst — het is
becommentarieerd en leest van boven naar beneden.

Het script:

1. controleert het besturingssysteem, geheugen en schijfruimte (te weinig is een
   waarschuwing, geen weigering);
2. installeert de basispakketten en zet automatische beveiligingsupdates aan;
3. installeert Docker Engine en de Compose-plugin uit de bron van Docker zelf;
4. schrijft `.env`, met een **zelf gegenereerde** applicatiesleutel en een
   **zelf gegenereerd** databasewachtwoord — die worden nergens gevraagd,
   nergens getoond en staan alleen in dat bestand (`chmod 600`);
5. vraagt om de domeinnaam en, als je een tunnel gebruikt, om het tunnel-token
   (die typ je in, hij komt niet op het scherm en niet in `ps`);
6. maakt een `ADMIN_SETUP_TOKEN` aan zodat het opeisscherm niet openstaat;
7. bouwt de containers, start ze en wacht tot de site antwoordt;
8. zet SSH open en inkomend 80 en 443 dicht in `ufw`;
9. drukt het adres af, plus de code die je bij het opeisen nodig hebt.

**Het script is veilig om opnieuw te draaien.** Het slaat over wat al staat en
het weigert een bestaand `.env` te overschrijven — de applicatiesleutel
vervangen zou alle bestaande sessies onleesbaar maken.

Alles is ook zonder vragen te draaien, bijvoorbeeld voor een geautomatiseerde
uitrol:

```bash
TEACHER_DOMAIN=les.example.nl TEACHER_TUNNEL_TOKEN=eyJhIjoi… TEACHER_ASSUME_YES=1 sudo -E ./install.sh
```

De overige instelbare variabelen staan bovenaan het script.

Sla na afloop door naar [hoofdstuk 8](#8-het-beheerdersaccount-opeisen): het
account moet nog opgeëist worden, en dat is de enige stap die het script niet
voor je kan doen.

### Een bestaande site hierheen verhuizen

Vervangt deze machine er een die de site al draaide, zet dan de back-up erop en
geef hem aan de installer mee:

```bash
sudo TEACHER_RESTORE=/root/teacher-backup-2026-08-10-023000.tar.gz ./install.sh
```

Het script installeert alles zoals hierboven en zet daarna die back-up terug. De
site komt op met zijn inhoud, zijn instellingen én zijn beheerdersaccount.
Hoofdstuk 8 vervalt dan: er valt niets op te eisen, want het account kwam met de
back-up mee. Log in met het wachtwoord dat de oude site had.

Een back-upbestand komt van **Back-ups** in het beheerpaneel, of van
`php artisan backup:run` op de oude machine. Later terugzetten in plaats van
tijdens de installatie werkt net zo goed — zie
[onderhoud en beveiliging](onderhoud-en-beveiliging.md#3-terugzetten).

> Draait het script vast, dan zegt het waar. De hoofdstukken hierna beschrijven
> dezelfde stappen met de hand, zodat je verder kunt vanaf het punt waar het
> misging.

---

## 3. Docker installeren

*Alleen nodig als je `install.sh` niet gebruikt.*

Log in als root, of zet `sudo` voor elk commando.

Eerst het systeem bijwerken en de benodigdheden installeren:

```bash
apt update && apt upgrade -y && apt install -y ca-certificates curl git ufw
```

De sleutel van Docker ophalen:

```bash
install -m 0755 -d /etc/apt/keyrings && curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc && chmod a+r /etc/apt/keyrings/docker.asc
```

De pakketbron toevoegen. Debian 13 gebruikt het nieuwere deb822-formaat:

```bash
printf 'Types: deb\nURIs: https://download.docker.com/linux/debian\nSuites: trixie\nComponents: stable\nSigned-By: /etc/apt/keyrings/docker.asc\n' > /etc/apt/sources.list.d/docker.sources
```

En installeren:

```bash
apt update && apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Controleren:

```bash
docker compose version
```

Zet ook automatische beveiligingsupdates aan:

```bash
apt install -y unattended-upgrades && dpkg-reconfigure -f noninteractive unattended-upgrades
```

---

## 4. De code op de server zetten

```bash
git clone https://github.com/finnwastakenwastaken/Teacher-2.0.git /opt/teacher && cd /opt/teacher
```

Alles hierna gebeurt vanuit `/opt/teacher`.

---

## 5. Het `.env`-bestand

Alle instellingen staan in één bestand. Begin met het voorbeeld:

```bash
cp .env.example .env && chmod 600 .env
```

Open `.env` en vul in wat hieronder staat. Het voorbeeldbestand legt elke
instelling ter plekke uit; hier staat alleen wat je écht moet aanpassen.

### De applicatiesleutel

Deze versleutelt sessies en cookies. Genereer hem — typ er nooit zelf iets:

```bash
docker compose run --rm app php artisan key:generate --show
```

Zet de uitvoer (`base64:…`) achter `APP_KEY=` in `.env`.

### Het databasewachtwoord

Genereer er een en zet hem achter `DB_PASSWORD=`:

```bash
openssl rand -hex 24
```

Hexadecimaal, niet base64: deze waarde wordt door Docker Compose doorgegeven aan
PostgreSQL, en hex bevat gegarandeerd geen teken dat Compose als syntaxis leest.

Dit wachtwoord typ je nergens anders in; alleen `.env` kent het.

### Het adres

```
APP_URL=https://lesmateriaal.jouwdomein.nl
```

Gebruik `https://`, ook met een tunnel — anders komen links en cookies verkeerd
uit.

### Het beheerdersaccount

Laat `ADMIN_NAME`, `ADMIN_EMAIL` en `ADMIN_PASSWORD` **leeg** als de docent het
account zelf via de browser gaat opeisen. Dat is de normale route.

Zit er tijd tussen "de site staat online" en "de docent eist het account op",
vul dan `ADMIN_SETUP_TOKEN` in met een willekeurige tekenreeks:

```bash
openssl rand -hex 16
```

Het opeisscherm vraagt dan óók om deze code. Geef hem door aan de docent langs
een ander kanaal dan het adres van de site.

Wil je het account liever vooraf aanmaken, vul dan alle drie de `ADMIN_`-velden
in. Haal `ADMIN_PASSWORD` na de eerste start weer uit `.env`.

### Overige instellingen

De rest van `.env` kun je laten staan. Twee waarschuwingen:

- **`FILESYSTEM_DISK=local` moet zo blijven.** Alle geüploade bestanden staan op
  een privéschijf en worden alleen uitgeserveerd nadat de applicatie heeft
  gecontroleerd of de bezoeker erbij mag. Wijs dit naar de publieke schijf en je
  omzeilt elke wachtwoordcontrole op de site.
- **`MEDIA_X_ACCEL=true` moet zo blijven.** Hiermee laat de applicatie nginx het
  daadwerkelijke uitserveren doen. Zet je dit uit, dan bezet elke download een
  PHP-werkproces zolang hij duurt en legt één klas die tegelijk een video kijkt
  de site plat.

---

## 6. De Cloudflare Tunnel

Sla dit hoofdstuk over als je een eigen reverse proxy gebruikt.

1. Ga in het Cloudflare-dashboard naar **Zero Trust → Networks → Tunnels** en
   maak een tunnel aan.
2. Kies bij de installatiemethode **Docker**. Je krijgt een token te zien.
3. Voeg bij *Public hostname* je domein toe en laat het verwijzen naar
   `http://web:80`. Dat is de naam van de webserver binnen het Docker-netwerk.
4. Zet het token in `.env`:

```
CLOUDFLARE_TUNNEL_TOKEN=eyJhIjoi…
```

Het token staat alleen in `.env` en wordt als omgevingsvariabele aan de
container gegeven — nooit als commandoregel-argument, want die zijn zichtbaar
voor iedereen die `ps` kan draaien.

---

## 7. Starten

Bouwen en starten:

```bash
docker compose --profile tunnel up -d --build
```

Zonder tunnel laat je `--profile tunnel` weg.

De eerste keer duurt het bouwen enkele minuten; de frontend wordt op de server
gebouwd. Daarna start de stack in een paar seconden.

Bij elke start doet de applicatiecontainer zelf:

- wachten tot PostgreSQL echt vragen beantwoordt;
- de databasemigraties draaien;
- het beheerdersaccount aanmaken als de `ADMIN_`-velden zijn ingevuld;
- de standaardniveaus plaatsen als er nog geen enkel niveau is;
- restanten van afgebroken uploads opruimen;
- de configuratie-, route- en viewcaches opbouwen.

Alles daarvan is herhaalbaar: een herstart is geen nieuwe installatie en gooit
niets weg.

Meekijken:

```bash
docker compose logs -f app
```

De laatste regel hoort `[entrypoint] Ready.` te zijn.

---

## 8. Het beheerdersaccount opeisen

Heb je een back-up teruggezet, sla dit hoofdstuk dan over: het account kwam
daarmee mee.

Ga naar `https://jouw-adres.nl/admin/claim` en vul naam, e-mailadres en
wachtwoord in (en het `ADMIN_SETUP_TOKEN`, als je dat hebt ingesteld).

> **Doe dit meteen na de eerste start**, of laat de docent het meteen doen.
> Zolang het account niet is opgeëist kan iedereen die het adres kent het
> opeisen. Met een `ADMIN_SETUP_TOKEN` is dat venster gesloten.

Er is precies één account, en er komt er nooit een tweede bij: registratie
bestaat niet in deze software.

---

## 9. De firewall dichtzetten

Met een tunnel hoeft er niets van buiten naar binnen te kunnen. Leg dat ook vast
in plaats van erop te vertrouwen:

```bash
ufw allow OpenSSH && ufw deny 80/tcp && ufw deny 443/tcp && ufw --force enable
```

Controleren:

```bash
ufw status verbose
```

De webserver van de stack luistert alleen op `127.0.0.1:8080`, dus hij is
sowieso niet vanaf het netwerk bereikbaar.

---

## 10. Controleren of alles werkt

Loop dit lijstje één keer af. Het duurt vijf minuten en vangt precies de dingen
die pas weken later opvallen.

1. **De site opent** op je domein en toont de homepage.
2. **Inloggen werkt** met het zojuist opgeëiste account.
3. **Een afbeelding uploaden** onder *Media* lukt, met alt-tekst.
4. **Die afbeelding is nog niet publiek**: open het adres van de afbeelding in
   een privévenster. Je hoort een foutmelding te krijgen, geen plaatje. Dat is
   goed — een bestand dat nergens op een pagina staat, hoort niet bereikbaar te
   zijn.
5. **Zet de afbeelding op een pagina** en herhaal stap 4. Nu hoort hij wél te
   laden.
6. **Upload een video van meer dan 100 MB.** Die hoort te slagen: hij wordt in
   stukken verstuurd. Zou hij in één keer gaan, dan zou Cloudflare hem weigeren.
7. **Spoel door naar het midden van die video** in de speler. Hij hoort te
   springen, niet vanaf het begin te bufferen.
8. **Zet een wachtwoord op een onderwerp** en open een pagina eronder in een
   privévenster. Je hoort alleen het invulveld te zien, en de download eronder
   hoort geweigerd te worden zolang je het wachtwoord niet hebt ingevoerd.

Gaat stap 4, 5 of 8 niet zoals beschreven, ga dan niet verder en zoek eerst uit
waarom — dat zijn de stappen die de afscherming bewaken.

---

## 11. Als er iets misgaat

**De container blijft herstarten en de log zegt `FATAL: APP_KEY is not set`.**
`APP_KEY` staat niet of leeg in `.env`. Zie [hoofdstuk 5](#5-het-env-bestand).

**`FATAL: PostgreSQL did not become available`.**
De database start niet. Bekijk `docker compose logs database`. Meestal een leeg
`DB_PASSWORD` of een schijf die vol is.

**De site geeft 500 en de log is leeg.**
Vaak een rechtenprobleem op de opslagmap. Herstart de applicatiecontainer
(`docker compose restart app`); de container zet de rechten bij elke start
goed. Blijft het, kijk dan in `docker compose logs app`.

**Afbeeldingen en downloads geven allemaal een foutmelding, terwijl inloggen
werkt.**
Dan kan nginx de bestanden niet lezen die PHP heeft weggeschreven. Dit is
gebeurd na een wijziging in de schijfconfiguratie of het webserver-image. Zie
[onderhoud en beveiliging](onderhoud-en-beveiliging.md#afbeeldingen-en-downloads-doen-het-niet-meer).

**Uploaden mislukt bij grote bestanden.**
Controleer of `MEDIA_CHUNK_BYTES` niet is verhoogd. Boven de 100 MB weigert
Cloudflare het verzoek, ongeacht wat de server toestaat.

**Het bouwen faalt met een geheugenfout.**
De machine heeft minder dan 4 GB RAM. Voeg geheugen toe, of geef de machine
tijdelijk swap.
