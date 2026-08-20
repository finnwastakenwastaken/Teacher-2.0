# Teacher 2.0

A self-hosted course-materials website for a single teacher, whatever they
teach.

Students browse and download. They never register, never log in, and are never
tracked. There is **one admin account, ever** — no public registration exists
anywhere in the application.

Nothing in it is tied to a subject or a school. The topics, the education
levels, the site's name and logo are all defined by whoever owns the
installation; the examples in the guides are only examples.

The feature that justifies building this rather than adopting Moodle or
WordPress: **downloads tagged by education level**. One page carries one
worksheet in several track-specific variants, grouped per level, so each student
picks the version matching their track.

The interface is **Dutch**. Code, comments and the technical reference are
English.

---

## Documentation

| Guide | For | Language |
|---|---|---|
| [Deployment](docs/deployment.md) | Getting it onto a server, start to finish | English |
| [Maintenance](docs/maintenance.md) | Backups, updates, recovery, troubleshooting | English |
| [Beheerdersgids](docs/beheerdersgids.md) | The teacher who publishes the material | Dutch |
| [Installatiegids](docs/installatiegids.md) | Same as *Deployment* | Dutch |
| [Onderhoud en beveiliging](docs/onderhoud-en-beveiliging.md) | Same as *Maintenance* | Dutch |
| [Technical reference](docs/technical-reference.md) | Developers | English |

> The Dutch and English server guides are translations of each other. A change
> to one belongs in the other.

---

## What it does

**For students** — no account, no login, nothing tracked.

- A homepage of category tiles, then up to three levels of topics.
- Pages with rich text, image galleries, embedded documents, self-hosted video
  with seeking, and YouTube embeds.
- A downloads section grouped by education level, plus a "my level" preference
  that reorders the groups. It never hides anything.
- Full-text search in Dutch, including word forms.
- Class passwords on a page or a whole branch, remembered for 30 days.

**For the teacher** — one admin panel, everything editable in the browser.

- Topics and pages with icons, ordering, and a hidden toggle for drafts.
- Two media libraries (images, documents/videos) with chunked upload for large
  files, and an import command for files too big for a browser.
- Education levels the teacher can rename, reorder and merge.
- Reusable named passwords, applied to a topic or a single page.
- Site name, logo, favicon, and the homepage heading, banner and introduction.

**Deliberately absent** — student accounts, comments, analytics of any kind, and
any third-party script or font. The only counter in the system is a per-download
tally with no visitor data attached.

---

## Stack

Laravel 13 · PHP 8.4 · Inertia v3 · React 19 · TypeScript · Tailwind 4 ·
shadcn/ui · PostgreSQL 17.

Runs as **nginx + PHP-FPM** in Docker, typically behind a **Cloudflare Tunnel**
so no inbound port has to be open. nginx rather than an all-in-one PHP server
because gated media is streamed by nginx itself — a class of thirty watching a
video would otherwise exhaust the PHP worker pool.

No Redis, no queue worker, no object storage, no Elasticsearch. That is a
decision, not an omission.

---

## Requirements

- A machine running **Debian 13 (Trixie)** — a VPS or a home-lab VM.
- **4 GB RAM.** A hard requirement: the frontend is built on the server.
- **20 GB disk**, more if you publish video.
- A domain name, and a Cloudflare account if you use the tunnel.

---

## Deploying it

On a fresh machine, clone the repository and run the installer. It installs
Docker, generates `.env` with its own secrets, builds and starts everything, and
closes inbound 80/443 in the firewall:

```bash
sudo git clone https://github.com/finnwastakenwastaken/Teacher-2.0.git /opt/teacher
```

```bash
cd /opt/teacher && sudo ./install.sh
```

Then claim the single admin account at `https://your-domain/admin/claim`.

Updating later:

```bash
cd /opt/teacher && sudo ./update.sh
```

Both scripts are safe to re-run. The [deployment guide](docs/deployment.md)
walks through the whole thing, including the manual route.

---

## Developing it

Everything runs in Docker. No local PHP or Node is required.

```bash
cp .env.example .env
```

Generate an application key and put it in `.env` as `APP_KEY=base64:…`:

```bash
docker compose -f compose.yaml -f compose.dev.yaml run --rm app php artisan key:generate --show
```

Bring the stack up:

```bash
docker compose -f compose.yaml -f compose.dev.yaml up -d
```

The site is at `http://localhost:8080`, Vite at `5173`, PostgreSQL at
`127.0.0.1:55432`. Claim the admin account at
`http://localhost:8080/admin/claim`.

| Task | Command (prefix `docker compose -f compose.yaml -f compose.dev.yaml`) |
|---|---|
| Logs | `logs -f app` |
| Artisan | `exec app php artisan <cmd>` |
| Tests | `exec app php artisan test` |
| Format PHP | `exec app ./vendor/bin/pint` |
| Type check | `exec vite npm run types:check` |
| Lint | `exec vite npm run lint` |
| Production build | `exec vite npm run build` |
| Rebuild icon catalogue | `exec vite npm run icons:build` |

Tests run against real PostgreSQL, never SQLite — the schema needs `tsvector`,
GIN indexes and CHECK constraints.

---

## Repository layout

```
app/
  Http/Controllers/        Public: content, media, downloads, search, unlock
    Admin/                 The admin panel
  Models/                  Topic, Page, Image, MediaFile, AccessPassword, …
  Services/MediaLibrary    Upload, chunk assembly, import
  Support/                 The decisions that must live in one place:
                             MediaAccess       who may read a file
                             AccessControl     which password guards a page
                             PageContent       the rich-text whitelist
                             SiteSettings      branding and homepage
resources/js/
  pages/                   One file per screen, resolved by Inertia
  components/content/      The public renderer
  components/editor/       TipTap and its custom blocks
docker/                    nginx config, PHP image, entrypoint
docs/                      The guides above
install.sh · update.sh     Server setup and updates
compose.yaml               Production;  compose.dev.yaml overlays development
```

---

## Security model in one paragraph

Every uploaded file lives on a private disk and is served only after
`App\Support\MediaAccess` says yes — authenticated, or reachable through a page
this visitor may open, or currently used as site branding. Nothing is on a
public path, so guessing a URL gains nothing. Page bodies are stored as
structured JSON and rendered through a whitelist, never as HTML, which removes
stored XSS as a category. There is no registration, the single account cannot be
deleted, and password recovery is a command on the server rather than an email.
The details, and the reasoning, are in the [technical
reference](docs/technical-reference.md).

---

## Licence

[MIT](LICENSE). Use it, fork it, deploy it for your own school — keep the
copyright notice.

The bundled icon catalogue is third-party work under its own terms, which the
MIT licence here does not override — lucide (ISC), Tabler and Tabler Filled
(MIT), and MDI (Apache-2.0). All three permit redistribution with attribution;
see [`database/data/LICENCES.md`](database/data/LICENCES.md).
