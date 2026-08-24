# Live demo runbook

How to run a public demo of this project with:

1. a real hosted application (Laravel + PostgreSQL + Docker), and
2. a static GitHub Pages portal that links to it.

> Dutch version: [live-demo-nl.md](live-demo-nl.md)

---

## 1) Demo architecture

- **Real app**: deploy the repository to a VM/container host that supports Docker
  Compose.
- **Demo portal**: publish `docs/demo/` to GitHub Pages.

GitHub Pages is only for static files. It cannot host the Laravel runtime or the
database.

---

## 2) Deploy the real demo application

On the target server:

```bash
sudo git clone https://github.com/finnwastakenwastaken/Teacher-2.0.git /opt/teacher
cd /opt/teacher
sudo ./install.sh
```

For updates:

```bash
cd /opt/teacher
sudo ./update.sh
```

Follow the full production instructions in [deployment.md](deployment.md) and
[maintenance.md](maintenance.md).

---

## 3) Use safe demo content only

- Never use real student data in the demo.
- Create demo-only topics/pages/media.
- Keep a clean baseline backup after setting up sample content:

```bash
cd /opt/teacher
docker compose exec app php artisan backup:run
```

Store that archive separately as your **golden demo reset snapshot**.

---

## 4) Reset and restore routine

When the demo environment drifts (or after public testing), restore from the
golden snapshot:

```bash
cd /opt/teacher
sudo ./restore.sh /var/backups/teacher/teacher-backup-YYYY-MM-DD-HHMMSS.tar.gz
```

This replaces current data with the archived demo baseline and brings the site
back to known state.

---

## 5) GitHub Pages demo portal

The portal files live in `docs/demo/`:

- `index.html` — static landing page
- `demo-config.json` — editable metadata (live URL, credentials note, highlights)
- `assets/` — screenshots/placeholders

Update `demo-config.json` whenever demo URL or access details change.

---

## 6) Automation in this repository

- `.github/workflows/demo-pages.yml`
  - Deploys `docs/demo/` to GitHub Pages on pushes to `main` affecting demo/docs
    files.
- `.github/workflows/demo-uptime.yml`
  - Runs every 15 minutes and checks the live demo URL with `curl`.
  - Uses repository variable `DEMO_URL` (or a manual workflow input override).
  - Failing runs serve as uptime alerts via GitHub Actions notifications.

Set repository variable:

- `DEMO_URL`: full public URL of the live demo (for example
  `https://demo.example.com`)

Optional:

- Set `statusPageUrl` in `docs/demo/demo-config.json` to your public status page.

---

## 7) Reliability and patching

- Keep the demo host patched with normal OS updates.
- Run `sudo ./update.sh` regularly so container images and app dependencies stay
  current.
- Keep automated backups enabled (see [maintenance.md](maintenance.md#2-backups)).
- Perform an occasional restore drill so demo recovery remains predictable.
