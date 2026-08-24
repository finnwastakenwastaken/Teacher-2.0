# Live demo draaiboek

Zo run je een publieke demo van dit project met:

1. een echte gehoste applicatie (Laravel + PostgreSQL + Docker), en
2. een statische GitHub Pages-portaalpagina die daarnaar linkt.

> English version: [live-demo.md](live-demo.md)

---

## 1) Demo-opzet

- **Echte app**: deploy deze repository op een VM/containerhost met Docker
  Compose.
- **Demoportaal**: publiceer `docs/demo/` via GitHub Pages.

GitHub Pages is alleen voor statische bestanden. Het kan de Laravel-runtime of
database niet hosten.

---

## 2) Echte demo-app deployen

Op de doelsserver:

```bash
sudo git clone https://github.com/finnwastakenwastaken/Teacher-2.0.git /opt/teacher
cd /opt/teacher
sudo ./install.sh
```

Voor updates:

```bash
cd /opt/teacher
sudo ./update.sh
```

Volg de volledige productie-instructies in
[deployment.md](deployment.md) en [maintenance.md](maintenance.md).

---

## 3) Alleen veilige demodata gebruiken

- Gebruik nooit echte leerlinggegevens in de demo.
- Maak demo-only onderwerpen/pagina's/media.
- Bewaar een schone basis-back-up nadat je de voorbeeldinhoud hebt gemaakt:

```bash
cd /opt/teacher
docker compose exec app php artisan backup:run
```

Bewaar dat archief apart als je **gouden demo-resetsnapshot**.

---

## 4) Reset- en herstelroutine

Wanneer de demo-omgeving vervuild raakt (of na publieke tests), herstel je vanaf
de gouden snapshot:

```bash
cd /opt/teacher
sudo ./restore.sh /var/backups/teacher/teacher-backup-YYYY-MM-DD-HHMMSS.tar.gz
```

Dit vervangt de huidige data door de gearchiveerde demobaseline en brengt de
site terug naar een bekende toestand.

---

## 5) GitHub Pages-demoportaal

De portaalbestanden staan in `docs/demo/`:

- `index.html` — statische landingspagina
- `demo-config.json` — wijzigbare metadata (live URL, toegangstekst, highlights)
- `assets/` — screenshots/placeholders

Werk `demo-config.json` bij wanneer demo-URL of toegangsinfo wijzigt.

---

## 6) Automatisering in deze repository

- `.github/workflows/demo-pages.yml`
  - Deployt `docs/demo/` naar GitHub Pages bij pushes naar `main` die demo/docs
    bestanden raken.
- `.github/workflows/demo-uptime.yml`
  - Draait elke 15 minuten en controleert de live demo-URL met `curl`.
  - Gebruikt repositoryvariabele `DEMO_URL` (of handmatige workflow-input).
  - Mislukte runs fungeren als uptimewaarschuwingen via GitHub Actions-meldingen.

Zet repositoryvariabele:

- `DEMO_URL`: volledige publieke URL van de live demo (bijvoorbeeld
  `https://demo.example.com`)

Optioneel:

- Zet `statusPageUrl` in `docs/demo/demo-config.json` naar je publieke
  statuspagina.

---

## 7) Betrouwbaarheid en patchen

- Houd de demohost up-to-date met normale OS-updates.
- Draai regelmatig `sudo ./update.sh` zodat containerimages en
  app-afhankelijkheden bijgewerkt blijven.
- Houd automatische back-ups aan (zie [maintenance.md](maintenance.md#2-backups)).
- Doe af en toe een hersteltest zodat demorecovery voorspelbaar blijft.
