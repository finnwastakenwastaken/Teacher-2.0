# Maintenance

Running the server after the site is live: backups, updates, recovery, and what
to do when something breaks.

This guide assumes you can log into the server over SSH and nothing more.

> This is the English version of
> [onderhoud-en-beveiliging.md](onderhoud-en-beveiliging.md). They are the same
> guide.

Setting the server up in the first place: [deployment](deployment.md). Using the
site as a teacher: [beheerdersgids](beheerdersgids.md) (Dutch).

---

## Contents

1. [The five commands you need](#1-the-five-commands-you-need)
2. [Backups](#2-backups)
3. [Restoring](#3-restoring)
4. [Updating](#4-updating)
5. [Lost password](#5-lost-password)
6. [Putting a huge file on the site](#6-putting-a-huge-file-on-the-site)
7. [Disk space](#7-disk-space)
8. [How the protection works](#8-how-the-protection-works)
9. [Things never to do](#9-things-never-to-do)
10. [When something breaks](#10-when-something-breaks)

Every command assumes you are in the site's directory:

```bash
cd /opt/teacher
```

---

## 1. The five commands you need

| What | Command |
|---|---|
| Is it running? | `docker compose ps` |
| What is it doing? | `docker compose logs -f app` |
| Restart it | `docker compose restart` |
| Stop it | `docker compose down` |
| Start it | `docker compose --profile tunnel up -d` |

Press `Ctrl+C` to stop watching logs; it does not stop the site.

`docker compose down` removes the running programs but **not** the data. The
database and the uploaded files live in Docker volumes and survive. The one
command that would delete them is `docker compose down -v` — never use it.

---

## 2. Backups

The site backs itself up. One command produces one file that holds everything
the site is:

```bash
docker compose exec app php artisan backup:run
```

That writes `teacher-backup-YYYY-MM-DD-HHMMSS.tar.gz` and prints where it went.
Inside it are the database — pages, topics, settings, the admin account — and
every uploaded image, document and video. The teacher can do the same thing from
**Back-ups** in the admin panel, including downloading the file to their own
computer.

Two things are deliberately **not** in the archive:

- **`.env`.** It holds the database password and the application key, and an
  archive is a file you are encouraged to copy onto a laptop and a USB stick.
  Back `.env` up separately, and keep it somewhere you would keep a password.
- **The code.** It comes from the repository.

```bash
cp /opt/teacher/.env /var/backups/teacher-env-$(date +%F)
```

Losing `.env` is survivable — a new `APP_KEY` only invalidates unlock cookies
students already hold — but you need the database password to start the stack
against an existing volume, so keep it.

### Making it automatic

```bash
printf '#!/bin/sh\ncd /opt/teacher && docker compose exec -T app php artisan backup:run --prune\n' > /usr/local/bin/teacher-backup.sh
```

```bash
chmod +x /usr/local/bin/teacher-backup.sh
```

Run it every night at half past two:

```bash
echo '30 2 * * * root /usr/local/bin/teacher-backup.sh' > /etc/cron.d/teacher-backup
```

`--prune` keeps the newest few archives and deletes the rest, so the volume does
not grow forever. How many it keeps is `BACKUP_KEEP` in `.env`, seven by
default. Without that flag nothing is ever deleted — an unattended job that
quietly discards the only copy of a year's work is not something this site does
on your behalf.

### Copy them off this machine

Archives live in the Docker volume `teacher_backups`, which is on the same disk
as everything else. A backup sitting next to the original protects you from a
mistake, not from a dead disk or a stolen server. Copy them somewhere else:

```bash
mkdir -p /var/backups/teacher && docker compose cp app:/var/www/html/storage/app/backups/. /var/backups/teacher/
```

Then sync `/var/backups/teacher` to another machine, or download the archive
from the Back-ups screen.

### Check it once a year

A backup you have never restored is a hope, not a backup. Once a year, restore
one onto a spare machine and confirm the site comes up. §3 is exactly that
procedure, and it is the same one you would run in an emergency.

---

## 3. Restoring

```bash
sudo ./restore.sh /var/backups/teacher/teacher-backup-2026-08-10-023000.tar.gz
```

That is the whole thing. It makes a safety archive of whatever is on the site
right now, replaces the database and every uploaded file with the archive's,
restarts, and waits until the site answers again. If you restored the wrong
file, the safety archive is on the Back-ups screen and goes back the same way.

It **replaces**, it does not merge. Two sites' content cannot be reconciled
without asking questions a script cannot ask, so there is no half-way mode.

### Onto a brand new machine

This is how the site moves house. On the new machine:

```bash
sudo ./install.sh
```

```bash
sudo ./restore.sh /path/to/teacher-backup-2026-08-10-023000.tar.gz
```

The admin account comes back with the database, so there is no claim screen to
race and no setup token to look up — log in with the password you already had.
If you also kept `.env` from the old machine, put it in place before running
`install.sh` and the site keeps its original application key too.

The installer can do both steps at once:

```bash
sudo TEACHER_RESTORE=/root/teacher-backup-2026-08-10-023000.tar.gz ./install.sh
```

### If the archive is on your laptop instead

Download it from the Back-ups screen, copy it up, and restore from there:

```bash
scp teacher-backup-2026-08-10-023000.tar.gz root@your-server:/root/
```

### Restoring by hand

`restore.sh` is a wrapper. If you need to drive it yourself — the container is
already running and you only want the artisan step:

```bash
docker compose cp teacher-backup-2026-08-10-023000.tar.gz app:/tmp/restore.tar.gz
```

```bash
docker compose exec app php artisan backup:restore /tmp/restore.tar.gz
```

Without `--force` it asks first and tells you what is in the archive.

---

## 4. Updating

```bash
sudo ./update.sh
```

That is the whole thing. The script takes a database dump first, fetches the new
version, rebuilds, restarts, waits until the site answers again, and cleans up
old files. It is safe to run at any time and safe to run twice.

The site stays reachable while it builds. There is a gap of a few seconds when
the new version takes over.

If it fails, it prints the last lines of the log and tells you where the dump it
just took is. The site keeps running on the old version until the new one builds
successfully.

Afterwards, open the site and download one file. That single click confirms both
that the application works and that file serving works — the two halves that can
fail independently.

### The server itself

```bash
apt update && apt upgrade -y
```

Security updates install themselves if the installer set up
`unattended-upgrades`, which it does. Reboot after a kernel update; the site
comes back on its own.

---

## 5. Lost password

There is deliberately no "forgot password" email — the site sends no mail at
all, so that route would be one more thing to break once every three years.

Recovery is a command on the server:

```bash
docker compose exec app php artisan admin:reset-password
```

It tells you which account it is resetting, asks for a new password twice, and
does not show what you type. There is no way to pass the password as part of the
command, on purpose: that would leave it in your shell history.

---

## 6. Putting a huge file on the site

For a file too large to upload through the browser, put it on the server
directly.

From your own computer, copy it up:

```bash
scp big-video.mp4 root@203.0.113.10:/opt/teacher/storage-import/
```

Then, on the server, register it:

```bash
docker compose exec app php artisan media:import
```

It now appears in the teacher's media library like any other file.

Two useful options:

- `--alt="A description"` — required for images, same as in the browser.
- `--prune` — delete the original after importing it.

Imported images are converted to WebP and shrunk exactly as uploaded ones are.
Large videos are left alone; nothing happens to them.

By default the file is **copied**, so the original stays in `storage-import`.
Delete it yourself when you are done, or it sits there taking up space twice.

### Shrinking images that are already in the library

Conversion happens as a file arrives, so anything uploaded before this existed
is still whatever it was. One command finds those and says what it would save:

```bash
docker compose exec app php artisan media:optimise
```

It **changes nothing** until you add `--force`. Add `--all` to consider every
image rather than only those over 2 MB. Take a backup first — the old files are
replaced, and the originals are not kept.

---

## 7. Disk space

Video fills a disk fast. Check:

```bash
df -h
```

To reclaim space:

- **Old Docker images**, after a few updates. This touches no data:

  ```bash
  docker image prune -a
  ```

- **Abandoned uploads** are cleared automatically every time the site starts. By
  hand:

  ```bash
  docker compose exec app php artisan media:prune-uploads
  ```

- **Unused files** are deleted by the teacher in the admin panel under *Media*.
  The site refuses to delete a file that is still used somewhere, so this cannot
  accidentally break a page.

- **Old backup archives.** Each one holds a full copy of the media library, so
  they are the fastest-growing thing on the disk. Delete them from the Back-ups
  screen — but copy them off the machine first (§2). The nightly job in §2 keeps
  this from happening at all, because `--prune` clears the older ones each time
  it makes a new one.

---

## 8. How the protection works

Worth understanding before you change anything.

**There is exactly one account.** Registration does not exist — not as a screen,
not as a hidden address. The account cannot be deleted either, blocked in three
separate places so that one careless change cannot open a hole.

**No uploaded file sits in a public folder.** Everything is on a private disk,
and for each request the site decides whether *this* visitor may see *this*
file:

- logged in — everything;
- not logged in — only a file shown by a page they are allowed to open.

So a file sitting in the media library, used nowhere, is visible to nobody.
A file on a password-protected page needs that password. Guessing the address
achieves nothing.

**nginx does the actual sending.** The site only decides; it then hands nginx an
internal reference to the file. That internal address cannot be requested from
outside. This is what keeps a video from occupying a PHP process for its whole
length.

**Page passwords** are reusable records. Entering one gives the visitor a cookie
containing a fingerprint of that password. Change the password and every cookie
issued under the old one stops working immediately — that is how you withdraw
access at the end of a term.

**Hidden is not secret.** A hidden page is absent from menus and search but still
opens for anyone with the link. Use a password for real protection.

**Rate limits** apply to logging in, to claiming the account, and to entering a
page password.

---

## 9. Things never to do

- **`docker compose down -v`** — deletes the database and every uploaded file.
- **Change `FILESYSTEM_DISK` to the public disk** — puts every uploaded file
  directly on the web and makes every password check meaningless.
- **Add a second web server rule that serves `storage/app/private`** — same
  effect.
- **Set `MEDIA_X_ACCEL=false` in production** — the site keeps working, but one
  class watching a video at once will take it down.
- **Set `APP_DEBUG=true` in production** — internal details end up on visitors'
  screens when something errors.
- **Share or commit `.env`** — it holds the application key, the database
  password and the tunnel token.
- **Put a backup archive anywhere the web server can reach it** — it contains
  the whole database, the admin password hash included. It is downloadable from
  the admin panel and nowhere else on purpose. Keep copies off the server, not
  in `public/`.
- **Type a password as part of a command** — it stays in your shell history and
  is visible to anyone who can list running processes.

---

## 10. When something breaks

### Images and downloads stopped working

Pages load and login works, but every image, download and video errors.

This almost always means the web server can no longer read the files the
application wrote. They run as different users, and the web server has the
folder read-only. It happens after a change to the storage settings or the web
server image.

Check:

```bash
docker compose exec web ls -ld /var/www/html/storage/app/private/images
```

If that errors, restart the application container — it repairs the permissions
every time it starts:

```bash
docker compose restart app
```

If it comes back, the storage configuration has been changed and needs putting
back. Note that the automated tests do not catch this: they run without the web
server.

### The site is unreachable but the containers are running

```bash
docker compose logs --tail=50 tunnel
```

An expired or revoked token is the usual cause. Make a new one in Cloudflare,
put it in `.env`, and run:

```bash
docker compose up -d tunnel
```

### Everything is slow

```bash
free -h && df -h
```

A full disk shows up as slowness long before it shows up as errors.

### An update failed partway

The application container stops with an error in the log, and the site keeps
running on the previous version until a build succeeds. Restore the database
from the dump `update.sh` took just before it started, and report the error —
this should not happen, and it is a fault in the software rather than in your
server.

### Where the logs are

| What | Command |
|---|---|
| The site | `docker compose logs app` |
| The web server | `docker compose logs web` |
| The database | `docker compose logs database` |
| The tunnel | `docker compose logs tunnel` |

Add `--tail=50` for just the recent lines, or `-f` to follow them live.

For a stubborn problem, set `LOG_LEVEL=debug` in `.env`, restart, reproduce it,
then **put it back to `warning`** — debug logging fills the disk quickly.

Nothing about visitors is recorded beyond the web server's own access log. The
site has no analytics and loads no third-party scripts.
