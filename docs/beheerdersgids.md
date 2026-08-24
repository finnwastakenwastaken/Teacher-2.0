# Beheerdersgids

Deze gids is voor jou als eigenaar van de website: de docent die het
lesmateriaal publiceert. Je hebt geen technische kennis nodig — alles in deze
gids doe je in de browser.

> Dit is de Nederlandse versie van [administration.md](administration.md).
> Het is dezelfde gids; een wijziging in de een hoort ook in de ander.

De twee andere gidsen zijn voor degene die de server beheert:
[installatiegids](installatiegids.md) en
[onderhoud en beveiliging](onderhoud-en-beveiliging.md).

---

## Inhoud

1. [De eerste keer inloggen](#1-de-eerste-keer-inloggen)
2. [De weg kennen](#2-de-weg-kennen)
3. [Onderwerpen: de structuur van de site](#3-onderwerpen-de-structuur-van-de-site)
4. [Pagina's](#4-paginas)
5. [De inhoud van een pagina schrijven](#5-de-inhoud-van-een-pagina-schrijven)
6. [Downloads per niveau](#6-downloads-per-niveau)
7. [De mediabibliotheek](#7-de-mediabibliotheek)
8. [Grote video's](#8-grote-videos)
9. [Niveaus beheren](#9-niveaus-beheren)
10. [Wachtwoorden](#10-wachtwoorden)
11. [Verbergen versus verwijderen](#11-verbergen-versus-verwijderen)
12. [De naam, het logo en de homepage](#12-de-naam-het-logo-en-de-homepage)
13. [De taal van de site](#13-de-taal-van-de-site)
14. [Zoeken](#14-zoeken)
15. [Back-ups](#15-back-ups)
16. [Je eigen account](#16-je-eigen-account)
17. [Veelgestelde vragen](#17-veelgestelde-vragen)

---

## 1. De eerste keer inloggen

Er is precies **één account** op deze website: dat van jou. Leerlingen maken
nooit een account aan en loggen nooit in. Er bestaat ook geen registratiescherm
— dat is er bewust uit gehaald.

De allereerste keer dat de site online staat, is dat ene account nog van
niemand. Ga naar `https://jouw-adres.nl/admin/claim` en vul je naam, e-mailadres
en een wachtwoord in. Daarna is het account van jou en is dit scherm voorgoed
weg.

> **Doe dit meteen.** Zolang het account niet is opgeëist, kan iedereen die het
> adres kent het opeisen. Degene die de server beheert kan dit venster sluiten
> met een instelvariabele (`ADMIN_SETUP_TOKEN`); vraag erom als er tijd zit
> tussen "de site staat online" en "ik ben erbij om hem op te eisen".

Daarna log je in via `https://jouw-adres.nl/login`. Dat adres staat bewust
nergens op de site: leerlingen loggen nooit in, dus een inloglink zou alleen
maar de vraag oproepen of zij ook een account nodig hebben. Zet hem in je
favorieten. Ben je ingelogd, dan verschijnt rechtsboven wél een link
**Beheer** terug naar het beheerscherm.

**Wachtwoord vergeten?** Er is geen "wachtwoord vergeten"-e-mail. Herstel loopt
via de server — zie [onderhoud en beveiliging](onderhoud-en-beveiliging.md#5-wachtwoord-kwijt).

---

## 2. De weg kennen

Na het inloggen zie je links een menu:

| Menu-item | Waarvoor |
|---|---|
| **Dashboard** | Startscherm: wat er op je site staat, en een lijstje "aan de slag" zolang er nog dingen te doen zijn |
| **Inhoud** | Onderwerpen en pagina's — de structuur van de site |
| **Media** | Alle geüploade afbeeldingen, documenten en video's |
| **Niveaus** | De niveaus waarmee je downloads labelt (VMBO-BK, HAVO, …) |
| **Wachtwoorden** | Wachtwoorden waarmee je onderwerpen of pagina's afschermt |
| **Back-ups** | Een kopie van de hele site maken en downloaden |
| **Instellingen** | De naam en het logo van de site, en de tekst op de homepage |
| **Bekijk de website** | Opent de site zoals leerlingen hem zien |

Rechtsonder staat je eigen naam. Daar vind je je profiel, je wachtwoord,
tweestapsverificatie en de licht/donker-instelling.

De site is standaard **donker**. Leerlingen kunnen dat niet omzetten; jij wel,
voor jezelf, via je eigen menu.

---

## 3. Onderwerpen: de structuur van de site

Ga naar **Inhoud**. Wat je daar ziet is de boom van de hele site.

- Een **hoofdonderwerp** is een tegel op de homepage. Bijvoorbeeld
  *Natuurkunde*, *Geschiedenis* of *Frans*.
- Daaronder kun je **subonderwerpen** hangen, en daaronder nog één laag.
  Bijvoorbeeld *Natuurkunde → Sterrenkunde → Het zonnestelsel*, of
  *Geschiedenis → De Gouden Eeuw → De VOC*.
- **Dieper dan drie lagen kan niet.** Dat is met opzet: dieper wordt voor
  leerlingen op een telefoon onvindbaar.

De site gaat nergens van uit. Wat je vak ook is, de indeling is die van jou: de
voorbeelden hieronder zijn alleen voorbeelden.

Een pagina mag op **elke** laag hangen. Je hoeft dus geen subonderwerp te
verzinnen als je er geen nodig hebt: een pagina direct onder *Natuurkunde* is
prima.

### Een onderwerp maken

Klik op **+ Nieuw hoofdonderwerp**, of op **+ Subonderwerp** bij het onderwerp
waar het onder moet hangen. Je vult in:

- **Titel** — wat leerlingen zien.
- **Slug** — het stukje adres, bijvoorbeeld `sterrenkunde` in
  `/natuurkunde/sterrenkunde`. Wordt automatisch voorgesteld op basis van de
  titel. Gebruik kleine letters en streepjes, geen spaties.
- **Icoon** — kies er een uit de lijst. Zoek op een Engels woord
  (`atom`, `flask`, `book`).
- **Omschrijving** — één of twee zinnen. Staat onder de titel op de tegel.
- **Tekst** — optioneel, en langer. Een inleiding op het onderwerp, met kopjes,
  lijstjes en links. Leerlingen zien hem bovenaan, boven de lijst met
  subonderwerpen en pagina's. Handig als een onderwerp uitleg verdient in
  plaats van meteen een rij tegels.
- **Wachtwoord** — zie [hoofdstuk 10](#10-wachtwoorden).
- **Verborgen** — zie [hoofdstuk 11](#11-verbergen-versus-verwijderen).

> **In de tekst van een onderwerp kun je geen bestanden of video's zetten.**
> Alleen op een pagina. Dat is geen beperking van het tekstvak maar van hoe de
> site bestanden vrijgeeft: een bestand wordt zichtbaar voor leerlingen doordat
> een *pagina* ernaar verwijst. Zou het hier mogen, dan zag jij het wel en een
> leerling niet.

> **De titel veranderen verandert het adres niet.** Dat is expres: links die je
> al met leerlingen hebt gedeeld blijven werken. Verander je de *slug* wél, dan
> onthoudt de site het oude adres en stuurt bezoekers automatisch door. Ook dan
> blijven oude links dus werken.

### De volgorde bepalen

Op het scherm **Inhoud** staat voor elk onderwerp en elke pagina een greepje
(⠿). Sleep daaraan om de volgorde te veranderen; die wordt meteen opgeslagen.
Precies zoals je het daar neerzet, ziet een leerling het op de site.

Liever geen muis? Ga met de tab-toets naar het greepje, druk op spatie, verplaats
met de pijltjes omhoog en omlaag, en druk weer op spatie om het neer te zetten.
Escape annuleert.

Slepen verandert **alleen de volgorde binnen dezelfde plek**: een pagina blijft
onder hetzelfde onderwerp hangen. Verhuizen naar een ander onderwerp doe je
hieronder.

### Verplaatsen

Bewerk het onderwerp en kies een ander bovenliggend onderwerp. De hele tak
verhuist mee. Kan de tak daardoor dieper dan drie lagen komen, dan weigert de
site de verplaatsing en verandert er niets. Wat je verplaatst komt onderaan zijn
nieuwe rijtje te staan; daarna kun je het op zijn plek slepen.

### Verwijderen

Een onderwerp met subonderwerpen of pagina's eronder kun je **niet**
verwijderen. De site vertelt je wat er nog in zit. Verplaats of verwijder eerst
de inhoud. Dit is met opzet: één verkeerde klik mag nooit een half hoofdstuk
meenemen.

---

## 4. Pagina's

Een pagina is waar het echte materiaal staat: uitleg, plaatjes, video's en de
downloads.

Maak er een met **+ Pagina** bij het onderwerp waar hij onder hoort. Bovenaan
het bewerkscherm staan de instellingen van de pagina:

- **Onderwerp** — waar de pagina hangt. Je kunt hem later verplaatsen.
- **Titel**, **Slug**, **Icoon**, **Omschrijving** — net als bij een onderwerp.
  De volgorde stel je niet hier in, maar door te slepen op **Inhoud**.
- **Bannerafbeelding** — een brede afbeelding boven de titel. Optioneel. Kies er
  een uit de mediabibliotheek.
- **Wachtwoord** — zie [hoofdstuk 10](#10-wachtwoorden).
- **Verborgen** — zie [hoofdstuk 11](#11-verbergen-versus-verwijderen).

Klik op **Opslaan**.

> Let op: een pagina heeft **drie** onderdelen die je apart opslaat — de
> instellingen hierboven, de inhoud (hoofdstuk 5) en de downloads (hoofdstuk 6).
> Elk heeft zijn eigen knop. De downloads worden meteen opgeslagen; de
> instellingen en de inhoud pas als je op hun knop klikt.

### Een pagina kopiëren

Op **Inhoud** staat bij elke pagina een knop **Dupliceren**. Handig voor "het
werkblad van vorig jaar, maar dan voor deze klas": je krijgt een volledige kopie
— de tekst, de bannerafbeelding, het wachtwoord én alle downloads met hun
niveaus — en je komt meteen in het bewerkscherm van die kopie terecht.

Twee dingen gaan bewust *niet* mee:

- **De kopie staat op verborgen.** Een kopie zegt precies hetzelfde als het
  origineel, dus meteen publiceren zou twee dezelfde pagina's aan leerlingen
  laten zien. Vink **Verborgen** uit zodra je klaar bent met aanpassen.
- **De downloadteller begint op nul.** Die hoort bij het origineel; het aantal
  van vorig jaar op een nieuwe pagina zetten zou een getal zijn dat nergens op
  slaat.

De kopie krijgt `-kopie` achter zijn slug en komt onderaan de lijst te staan.
Titel en slug pas je daarna gewoon aan.

---

## 5. De inhoud van een pagina schrijven

Onder de kop **Inhoud** staat de tekstverwerker. De knoppenbalk:

| Knop | Wat het doet |
|---|---|
| **Vet**, **Cursief** | Tekstopmaak |
| **Subscript**, **Superscript** | Laag- en hooggeplaatst: H₂O, m/s² |
| **Kop 2**, **Kop 3** | Tussenkoppen. De titel van de pagina is al kop 1 |
| **Links uitlijnen** … **Uitvullen** | Uitlijning van de alinea of kop |
| **Opsomming**, **Genummerde lijst** | Lijstjes |
| **Citaat** | Ingesprongen blok |
| **Link** | Maak van de geselecteerde tekst een link |
| **Bestand invoegen** | Een document of video uit de mediabibliotheek |
| **Afbeeldingen invoegen** | Eén of meer afbeeldingen als galerij |
| **YouTube-video invoegen** | Plak een YouTube-link |
| **Tabel invoegen** | Een tabel van 3 bij 3, met een kopregel |

Onder de tekstverwerker staat of er nog niet-opgeslagen wijzigingen zijn.
**Vergeet niet op "Inhoud opslaan" te klikken** — de tekst wordt niet vanzelf
bewaard.

### De drie blokken

- **Bestand invoegen.** Kies een document of video. Een document verschijnt als
  een downloadkaart met het juiste bestandsicoon. Een video verschijnt als
  speler waarin leerlingen kunnen doorspoelen.
- **Afbeeldingen invoegen.** Kies er één of meer; bij meerdere krijg je een
  raster.
- **YouTube-video invoegen.** Plak de link uit de adresbalk. Handig voor grote
  video's — zie [hoofdstuk 8](#8-grote-videos).

In allebei de keuzevensters kun je bovenaan **meteen iets uploaden**, zonder de
pagina te verlaten:

- Bij **Bestand invoegen** wordt wat je uploadt direct op de plek van de cursor
  in de pagina gezet.
- Bij **Afbeeldingen invoegen** wordt het aangevinkt en gaat het mee zodra je
  op **Invoegen** klikt — zo kun je er in één keer meerdere in een galerij
  zetten.

Alles wat je zo uploadt komt ook gewoon in de mediabibliotheek te staan. En ook
hier geldt: **klik daarna op "Inhoud opslaan"**, anders staat het bestand wel in
de bibliotheek maar niet op de pagina.

### Laag- en hooggeplaatste tekens: H₂O, m/s², 1ᵉ

Voor **subscript** (laaggeplaatst, zoals de 2 in H₂O) en **superscript**
(hooggeplaatst, zoals de 2 in m/s², of de e van 1ᵉ) staan er twee knoppen naast
*Cursief*. Zet hem aan, typ het teken, en zet hem weer uit.

Deze twee knoppen zitten ook in het kleinere tekstvak dat je gebruikt voor de
tekst van een onderwerp en van de homepage.

### Tabellen

**Tabel invoegen** zet een tabel van drie bij drie neer, met een kopregel. Zodra
je cursor in een tabel staat verschijnt er een extra regel knoppen:

| Knop | Wat het doet |
|---|---|
| **Rij erboven** / **Rij eronder** | Voegt een rij toe |
| **Rij wissen** | Haalt de rij weg waar je cursor staat |
| **Kolom links** / **Kolom rechts** | Voegt een kolom toe |
| **Kolom wissen** | Haalt de kolom weg waar je cursor staat |
| **Cellen samenvoegen** | Voegt geselecteerde cellen samen, of splitst ze weer |
| **Tabel wissen** | Haalt de hele tabel weg |

Je kunt de breedte van een kolom aanpassen door de rand ertussen te verslepen.

> Op een telefoon schuift een brede tabel horizontaal binnen zijn eigen kader.
> De rest van de pagina blijft staan waar hij staat. Houd tabellen toch liever
> smal — vier kolommen leest op een telefoon een stuk prettiger dan acht.

### Links

Selecteer eerst de tekst waarvan je een link wilt maken, klik dan op **Link**.
Voor een pagina binnen deze site kun je het pad gebruiken
(`/natuurkunde/sterrenkunde`); voor een andere site het volledige adres met
`https://`.

---

## 6. Downloads per niveau

Dit is waar de site voor gebouwd is. Onderaan elke pagina staat een sectie
**Downloads**: dezelfde opdracht in meerdere varianten, elk gelabeld met de
niveaus waarvoor hij bedoeld is.

Onder de kop **Downloads** in het bewerkscherm vink je eerst de **niveaus** aan
waarvoor het bedoeld is — meerdere mag, en niets aanvinken mag ook. Daarna kun
je twee kanten op:

- **Een nieuw bestand uploaden.** Sleep het in het vak *Nieuw bestand
  uploaden*, of klik op **Bestanden kiezen**. Het bestand komt in de
  mediabibliotheek én staat meteen als download op deze pagina, met de niveaus
  die je hierboven hebt aangevinkt. Je hoeft er dus niet eerst voor naar
  **Media**.
- **Een bestand kiezen dat er al staat.** Kies het bij **Bestand**, geef het
  eventueel een andere **naam op de pagina**, en klik op **Toevoegen**.

Meerdere bestanden tegelijk uploaden kan: ze krijgen allemaal dezelfde niveaus
en houden hun eigen bestandsnaam. Klik daarna op **Bewerken** achter een
download om de naam of de niveaus van dat ene bestand aan te passen.

Elke wijziging hier wordt **meteen** opgeslagen. Uploaden is niet hetzelfde als
de pagina opslaan — dat hoeft hier niet.

### Hoe leerlingen het zien

De downloads staan gegroepeerd per niveau: een kopje *HAVO* met daaronder de
bestanden voor HAVO, een kopje *VWO*, enzovoort. Een bestand dat je voor zowel
HAVO als VWO hebt aangevinkt staat onder **beide** kopjes.

Vink je **geen enkel niveau** aan, dan komt het bestand in een groep **Voor
iedereen** bovenaan te staan. Gebruik dat voor materiaal dat voor de hele klas
hetzelfde is; je hoeft dan niet alle hokjes aan te vinken.

Leerlingen kunnen bovendien hun eigen niveau kiezen. Dat wordt in hun browser
onthouden en zet hun groep bovenaan. Het **verbergt nooit iets** — een leerling
die per ongeluk het verkeerde niveau kiest mist daardoor geen materiaal. Van die
keuze wordt niets op de server bewaard.

### Hetzelfde bestand op meerdere pagina's

De niveaulabels horen bij de *koppeling*, niet bij het bestand zelf. Hetzelfde
PDF kan op de ene pagina "HAVO + VWO" heten en op de andere alleen "VWO". De ene
pagina verandert nooit wat de andere zegt.

### Downloadteller

Bij elke download staat hoe vaak hij is opgehaald. Dat is een simpele teller,
verder wordt er niets over bezoekers vastgelegd. Je eigen downloads tellen niet
mee zolang je bent ingelogd.

---

## 7. De mediabibliotheek

Onder **Media** staan al je bestanden, in twee lijsten: **Afbeeldingen** en
**Bestanden** (documenten en video's).

### Uploaden

Sleep bestanden naar het vak bovenaan, of klik op **Bestanden kiezen**. Grote
bestanden worden automatisch in stukken geüpload; je ziet per bestand de
voortgang. Onderbreek de pagina niet tijdens het uploaden.

Je hoeft hier niet per se te beginnen: in het bewerkscherm van een pagina kun
je ook uploaden, en dan staat het bestand meteen op die pagina. Zie
[hoofdstuk 5](#5-de-inhoud-van-een-pagina-schrijven) en
[hoofdstuk 6](#6-downloads-per-niveau). Alles komt in beide gevallen hier
terecht — dit blijft de plek waar je álles terugvindt.

### Afbeeldingen worden automatisch omgezet

Je hoeft je niets aan te trekken van bestandsformaten of bestandsgrootte. Elke
afbeelding die je uploadt wordt automatisch omgezet naar **WebP**, een formaat
dat alle browsers begrijpen en dat veel kleiner is.

Dat is vooral handig voor **foto's van je telefoon**. Een iPhone maakt HEIC-
bestanden, en die kan geen enkele browser laten zien — zonder deze omzetting
zou zo'n foto op de site een leeg vlak zijn. Nu kun je een foto gewoon van je
telefoon slepen en werkt hij.

Meteen daarbij:

- **Grote afbeeldingen worden kleiner gemaakt.** Een foto van 20 MB wordt
  onder de 2 MB gebracht en tot maximaal 2560 pixels breed. Op een pagina is
  dat nog altijd scherper dan het scherm van een leerling.
- **Locatiegegevens verdwijnen.** Een telefoonfoto bevat vaak de exacte plek
  waar hij gemaakt is. Die informatie wordt weggegooid, zodat je hem niet per
  ongeluk publiceert.
- **De foto blijft rechtop staan.** Ook als je telefoon hem gedraaid heeft
  opgeslagen.

Uitzonderingen: **logo's in SVG** en **bewegende GIF's** blijven precies zoals
ze zijn. De naam van het bestand krijgt wel de juiste uitgang: `vakantie.HEIC`
heet daarna `vakantie.webp`.

**Elke afbeelding heeft alt-tekst nodig.** Dat is de beschrijving die wordt
voorgelezen aan wie de afbeelding niet ziet, en die verschijnt als het plaatje
niet laadt. De site accepteert een afbeelding zonder alt-tekst simpelweg niet.
Beschrijf wat er te zien is ("Een grafiek van snelheid tegen tijd"), niet wat
het bestand is ("plaatje 3").

Je kunt alt-tekst later aanpassen met **Alt-tekst bewerken**.

### Uploaden is nog niet publiceren

Een bestand dat je uploadt is **niet** bereikbaar voor bezoekers zolang het
nergens op een pagina staat. Pas als je het invoegt in een pagina, als download
koppelt, als bannerafbeelding kiest of als logo instelt, kan iemand erbij. Je
kunt dus rustig alvast van alles uploaden.

Staat een bestand op een pagina met een wachtwoord, dan geldt dat wachtwoord ook
voor het bestand zelf: het adres van het bestand raden helpt niet.

### Verwijderen

Een bestand dat ergens in gebruik is kun je **niet** verwijderen. De site
vertelt je op welke pagina's het staat. Haal het daar eerst weg.

---

## 8. Grote video's

Video's tot ongeveer 2 GB kun je gewoon uploaden en op een pagina zetten; de
speler ondersteunt doorspoelen.

Toch is voor grote video's **een niet-vermelde YouTube-video meestal beter**:

- De verbinding waarmee deze site online staat is niet bedoeld voor het
  uitserveren van veel grote video. Een klas van dertig die tegelijk kijkt legt
  hem plat.
- Een niet-vermelde ("unlisted") video staat niet in het zoekresultaat van
  YouTube en is alleen te zien via de link — dus via jouw pagina.

Gebruik dan de knop **YouTube-video invoegen**.

### Een bestand dat te groot is om te uploaden

Lukt uploaden via de browser niet, dan kan degene die de server beheert het
bestand er rechtstreeks op zetten; zie
[onderhoud en beveiliging](onderhoud-en-beveiliging.md#6-een-groot-bestand-erop-zetten-zonder-de-browser).
Het bestand verschijnt daarna gewoon in je mediabibliotheek.

---

## 9. Niveaus beheren

Onder **Niveaus** staan de niveaus waarmee je downloads labelt. Bij de
installatie staan VMBO-BK, VMBO-T, HAVO en VWO klaar, maar dat is alleen een
startpunt: hernoem ze, verwijder ze of voeg er andere toe zoals het jou uitkomt.

- **Nieuw niveau** — vul een naam in. Het komt onderaan de lijst te staan.
- **Volgorde** — sleep aan het greepje (⠿). De volgorde die je hier maakt is de
  volgorde waarin de kopjes op een pagina onder *Downloads* staan. Werkt ook met
  het toetsenbord: spatie om op te pakken, pijltjes om te verplaatsen, spatie om
  neer te zetten.
- **Bewerken** — de naam aanpassen. Bestaande downloads houden hun label.
- **Verwijderen** — kan alleen als het niveau nergens gebruikt wordt. Achter elk
  niveau staat bij hoeveel downloads het in gebruik is.

### Een niveau opheffen dat wél in gebruik is

Gebruik **Samenvoegen met**. Alle downloads die het oude niveau droegen krijgen
het nieuwe, en daarna verdwijnt het oude. Dat is de nette manier om bijvoorbeeld
VMBO-BK en VMBO-T samen te trekken tot één "VMBO". Er gaat geen enkel bestand
verloren.

---

## 10. Wachtwoorden

Met een wachtwoord scherm je materiaal af voor wie de code niet heeft — een
proefwerk, of materiaal dat alleen voor jouw klas is.

### Hoe het werkt

Onder **Wachtwoorden** maak je een wachtwoord met een **naam** en de code zelf.
De naam is bedoeld om ze uit elkaar te houden: "5 VWO", "Practicum groep 2".

Daarna kies je dat wachtwoord bij een onderwerp of bij een losse pagina:

- Zet je het bij een **onderwerp**, dan is alles eronder beveiligd — alle
  subonderwerpen en pagina's.
- Zet je het bij een **pagina**, dan geldt het alleen voor die pagina.
- Heeft een pagina binnen een beveiligde tak zijn **eigen** wachtwoord, dan
  telt die: het dichtstbijzijnde wachtwoord wint.

Een leerling die het wachtwoord invoert, kan daarmee **alles** openen dat met
datzelfde wachtwoord beveiligd is — ook pagina's in een heel ander onderwerp.
Zo deel je één code met een klas in plaats van een code per pagina. Dat blijft
dertig dagen gelden.

### Wat leerlingen zien

Een beveiligde pagina toont alleen het invulveld. Er wordt niets van de inhoud
meegestuurd, dus er valt ook niets te "wegklikken". Beveiligde pagina's staan
ook niet in de zoekresultaten van wie ze niet heeft ontgrendeld.

**De naam van het wachtwoord staat bij het invulveld**, zodat iemand die twee
codes heeft weet welke wordt gevraagd. Zet er dus niets vertrouwelijks in — niet
de code zelf, en geen namen van leerlingen.

### Een wachtwoord veranderen

Verander je de code, dan zijn alle leerlingen die hem al hadden ingevoerd
**meteen** weer buitengesloten en moeten ze de nieuwe invoeren. Dat is precies
de manier om toegang in te trekken aan het eind van een periode.

### Jij ziet altijd alles

Zolang je bent ingelogd hoef je zelf nooit een wachtwoord in te voeren om je
eigen pagina's te bekijken.

---

## 11. Verbergen versus verwijderen

Elk onderwerp en elke pagina heeft een vinkje **Verborgen**.

Een verborgen item:

- staat **niet** in het menu, niet op de homepage en niet in de zoekresultaten;
- is **wel** gewoon te openen via de directe link.

Gebruik het voor iets waar je nog aan werkt, of voor materiaal van vorig jaar
dat je niet weg wilt gooien. Wil je iets écht afschermen, gebruik dan een
wachtwoord — verborgen is *niet* geheim.

Verwijderen is definitief, en wordt geweigerd zolang er nog iets aan hangt.

---

## 12. De naam, het logo en de homepage

Onder **Instellingen** staat alles wat de site van jou maakt.

**De site**

- **Naam van de site** — staat in de titelbalk van de browser en naast het logo.
- **Logo** — verschijnt linksboven op elke pagina. Laat leeg voor alleen de
  naam.
- **Favicon** — het kleine icoontje in het tabblad. Een vierkant PNG van 32 × 32
  pixels werkt het best.

**Homepage**

- **Kop** en **Ondertitel** — de tekst bovenaan.
- **Banner** — een brede afbeelding boven de kop.
- **Tekst** — een stukje vrije tekst, bijvoorbeeld een welkom of een
  mededeling.

De tegels met hoofdonderwerpen staan altijd onder deze tekst en kun je niet
weghalen — anders zou de homepage een doodlopende weg kunnen worden.

In het tekstvak van de homepage kun je geen bestanden of video's invoegen: die
horen op een pagina. Wil je vanaf de homepage naar een bestand verwijzen, maak
dan een link naar de pagina waar het staat.

Kies logo, favicon en banner uit de mediabibliotheek — upload ze dus eerst onder
**Media**.

Onderaan hetzelfde scherm staat nog één kopje, **Zoeken**, met de taal waarin
jij schrijft. Dat is iets anders dan de taal van de knoppen; zie
[hoofdstuk 13](#13-de-taal-van-de-site).

---

## 13. De taal van de site

De site spreekt Nederlands en Engels. Rechtsonder in het menu — en op de
openbare site naast het zoekveld — staat een keuzelijstje.

**Alleen de knoppen en de teksten van de site veranderen mee.** Alles wat jij
schrijft blijft staan zoals je het geschreven hebt: titels van onderwerpen en
pagina's, omschrijvingen, de namen van je niveaus, de naam van de site en de
tekst op de homepage. Er is dus geen tweede versie van je lesmateriaal die je
bij moet houden — dat zou het werk verdubbelen, en dat is niet wat dit is.

Een bezoeker die nog nooit gekozen heeft, krijgt de taal die zijn browser
vraagt, en anders Nederlands. De keuze wordt in een cookie op zijn eigen
computer bewaard; de site houdt er niets over bij.

### Taal van je lesmateriaal

Onderaan **Instellingen** staat, onder het kopje *Zoeken*, één keuzelijstje:
**Taal van je lesmateriaal**. Dat is iets anders dan het lijstje hierboven.

Het gaat over de taal waarin **jij** schrijft, en het bepaalt hoe de
zoekfunctie woorden herkent. In het Nederlands vindt *krachten* dan ook
*kracht*; in het Engels vindt *forces* ook *force*. Zet je het verkeerd, dan
werkt zoeken nog steeds, maar minder goed — vervoegingen worden dan niet meer
herkend.

Als je het wijzigt, wordt de zoekindex meteen opnieuw opgebouwd. Bij een site
met veel pagina's duurt dat een paar tellen; je hoeft verder niets te doen.

Schrijf je in beide talen door elkaar, kies dan de taal waarin je het meeste
schrijft. Er kan er maar één tegelijk gelden: de site slaat per pagina één
zoekversie op, en die wordt gemaakt op het moment dat je de pagina opslaat.

---

## 14. Zoeken

Bovenaan de site staat een zoekveld (`/zoeken`). Er wordt gezocht in de titel,
de omschrijving en de tekst van pagina's. Vervoegingen worden meegenomen, dus
*bewegingen* vindt ook *beweging* — in de taal die je onder **Instellingen**
hebt gekozen, zie [hoofdstuk 13](#13-de-taal-van-de-site).

Je kunt een woordgroep tussen aanhalingstekens zetten (`"wet van Ohm"`) en een
woord uitsluiten met een minteken (`energie -kern`).

Verborgen pagina's staan nooit in de resultaten. Beveiligde pagina's alleen voor
wie het wachtwoord al heeft ingevoerd.

### Gevonden worden door Google

De site geeft zoekmachines een lijst van zijn pagina's, op `/sitemap.xml`. Die
hoef je nergens in te stellen en nooit bij te werken: hij wordt bij elke
aanvraag opnieuw opgebouwd uit wat er op dat moment op de site staat.

Wat er **niet** in staat, is net zo belangrijk:

- verborgen onderwerpen en pagina's, en alles wat daaronder hangt;
- alles met een wachtwoord, ook de pagina's binnen zo'n beveiligde tak.

Dat geldt ook wanneer jíj de lijst opvraagt terwijl je ingelogd bent. Je krijgt
precies te zien wat een willekeurige bezoeker krijgt — anders zou je eigen
overzicht je een verkeerd beeld geven van wat er buiten staat.

Wil je juist *niet* gevonden worden, zet de betreffende onderwerpen dan op
verborgen of achter een wachtwoord. Er is bewust geen aparte knop voor
"onzichtbaar voor Google": dat zou een derde manier zijn om iets te verstoppen,
naast twee die er al zijn.

---

## 15. Back-ups

Onder **Back-ups** in het menu links maak je met één knop een kopie van de hele
site: alle onderwerpen en pagina's, alle instellingen, en elk bestand dat je ooit
hebt geüpload. Eén bestand, één knop.

### Er een maken

Klik op **Nu een back-up maken**. Bij een site met veel video's kan dat een paar
minuten duren — laat het tabblad open staan. Daarna staat hij in de lijst, met de
datum en hoe groot hij is.

### Er een bewaren

**Download hem naar je eigen computer.** Dat is de belangrijkste stap. Een
back-up die alleen op de server staat helpt tegen een vergissing van jou, maar
niet tegen een server die stukgaat. Zet hem op je laptop, en het liefst ook op
een USB-stick of in de cloud.

Ga daar wel voorzichtig mee om: in zo'n bestand zit *alles*, ook de
wachtwoorden van de pagina's. Behandel het als een sleutelbos.

### Er een terugzetten

Terugzetten kan niet vanuit dit scherm, met opzet: het wist alles wat er nu
staat, en dat is geen knop die per ongeluk ingedrukt mag kunnen worden. Het
gebeurt op de server. Geef het back-upbestand aan degene die de server beheert;
voor die persoon staat het in
[onderhoud en beveiliging](onderhoud-en-beveiliging.md#3-terugzetten).

Datzelfde bestand is ook hoe de site naar een nieuwe server verhuist. Alles komt
mee, inclusief je eigen account en wachtwoord.

### Hoe vaak

Vraag de serverbeheerder om het automatisch elke nacht te laten doen — dat kan,
en dan hoef jij er nooit aan te denken. Doe daarnaast zelf een back-up vlak
vóórdat je iets groots gaat veranderen: een hoop pagina's verplaatsen, een
niveau opheffen, of een grote opruimactie in de mediabibliotheek.

---

## 16. Je eigen account

Via je naam linksonder kom je bij:

- **Profiel** — je naam en e-mailadres.
- **Beveiliging** — je wachtwoord veranderen en tweestapsverificatie
  (authenticator-app of passkey) instellen. Aanrader: dit is het enige account
  op de site.
- **Weergave** — licht of donker, alleen voor jou.

Je account kun je niet verwijderen. Er is er maar één, en zonder zou niemand er
nog bij kunnen.

---

## 17. Veelgestelde vragen

**Kunnen leerlingen een account maken?**
Nee. Er bestaat geen registratiescherm en geen tweede account.

**Wordt er bijgehouden wie wat downloadt?**
Nee. Er is één teller per download, en verder geen enkele bezoekersgegevens. Er
staan geen analytics of externe scripts op de site.

**Ik heb een pagina hernoemd — werken oude links nog?**
Ja. De titel veranderen raakt het adres niet, en verander je het adres wél, dan
wordt er automatisch doorverwezen.

**Ik zie mijn nieuwe bestand niet op de site.**
Uploaden alleen publiceert niets. Voeg het in op een pagina of koppel het als
download.

**Een leerling zegt dat een download niet werkt.**
Staat de pagina achter een wachtwoord dat de leerling niet heeft? Of is de
pagina verborgen, zodat de leerling er alleen via een oude link bij kan? Bekijk
de pagina zelf terwijl je uitgelogd bent — of in een privévenster — om te zien
wat de leerling ziet.

**Ik ben mijn wachtwoord kwijt.**
Zie [onderhoud en beveiliging](onderhoud-en-beveiliging.md#5-wachtwoord-kwijt).
Dat kan alleen op de server, niet per e-mail.
