# Deployment

How to get this website running on a server, from nothing to a working site.

This guide assumes you can log into a server over SSH and nothing more. Every
command is written out. You do not need to understand Docker, PHP or Linux
administration.

> This is the English version of [installatiegids.md](installatiegids.md). They
> are the same guide.

Day-to-day running of the server afterwards: [maintenance](maintenance.md).
Using the site as a teacher: [beheerdersgids](beheerdersgids.md) (Dutch).

---

## Contents

1. [What you need](#1-what-you-need)
2. [How this is going to work](#2-how-this-is-going-to-work)
3. [Log in and become root](#3-log-in-and-become-root)
4. [Get the code](#4-get-the-code)
5. [Set up the Cloudflare Tunnel](#5-set-up-the-cloudflare-tunnel)
6. [Run the installer](#6-run-the-installer)
7. [Claim the admin account](#7-claim-the-admin-account)
8. [Check that it works](#8-check-that-it-works)
9. [Without a tunnel](#9-without-a-tunnel)
10. [Doing it by hand](#10-doing-it-by-hand)
11. [When something goes wrong](#11-when-something-goes-wrong)

---

## 1. What you need

- A server running **Debian 13 (Trixie)**. A cheap VPS is fine; so is a virtual
  machine at home. When you order a VPS, pick Debian 13 as the image.
- **4 GB of memory.** This is a real requirement, not a suggestion — part of the
  site is compiled on the server and it will not fit in less.
- **20 GB of disk**, more if you plan to publish video.
- The **SSH login details** your provider gave you: an address, a username
  (usually `root`), and a password or key.
- A **domain name**, and a free **Cloudflare** account.

Set aside about half an hour.

---

## 2. How this is going to work

The site does not open any port to the internet. Instead a small program on your
server, the **Cloudflare Tunnel**, dials out to Cloudflare and holds that
connection open. Visitors reach Cloudflare, and Cloudflare passes them down the
tunnel. Nothing has to reach your server from the outside, which is why this
works from a home network as well as a VPS.

Two consequences of that choice, both from Cloudflare and neither something this
software can change:

- **Uploads larger than 100 MB are rejected.** The site works around this by
  uploading big files in 20 MB pieces. There is nothing to configure.
- **Serving large video through a tunnel is not what Cloudflare is for.** The
  built-in video player works, but for long videos an unlisted YouTube video is
  the better route. The teacher's guide says so too.

An installer script does the rest: it installs the software the server needs,
creates the site's passwords and keys for you, starts everything, and closes the
firewall.

---

## 3. Log in and become root

From your own computer, open a terminal and connect. Replace the address with
the one your provider gave you:

```bash
ssh root@203.0.113.10
```

If your provider gave you a username other than `root`, use that instead and put
`sudo ` in front of every command in this guide.

Bring the system up to date first:

```bash
apt update && apt upgrade -y
```

If it asks anything, accept the default by pressing Enter.

---

## 4. Get the code

You need `git` to fetch the code:

```bash
apt install -y git
```

Then download the project into `/opt/teacher`. Replace the URL with the address
of your repository:

```bash
git clone https://github.com/finnwastakenwastaken/Teacher-2.0.git /opt/teacher
```

Move into that directory. **Every command from here on assumes you are in it.**

```bash
cd /opt/teacher
```

---

## 5. Set up the Cloudflare Tunnel

Do this in a browser, not on the server.

1. Add your domain to Cloudflare, if it is not already there, and follow their
   instructions to point the domain's nameservers at Cloudflare.
2. In the Cloudflare dashboard open **Zero Trust → Networks → Tunnels** and
   click **Create a tunnel**. Choose **Cloudflared**, give it a name such as
   `lesmateriaal`, and save.
3. On the install screen, pick **Docker**. You will see a long command with a
   token in it. **Copy only the token** — the long string of letters and digits,
   not the whole command. Keep it on your clipboard.
4. Open the **Public Hostname** tab and add a hostname:
   - *Subdomain*: for example `lesmateriaal`
   - *Domain*: your domain
   - *Type*: `HTTP`
   - *URL*: `web:80`

   That last value is the name of the web server inside the site's own private
   network. It is not a mistake that it looks nothing like an address.
5. Save.

Your site will live at `https://lesmateriaal.yourdomain.com`. Note it down; you
need it in the next step.

---

## 6. Run the installer

Still in `/opt/teacher`, run:

```bash
sudo ./install.sh
```

It will ask you three things:

- **Public hostname** — the address from the previous step, for example
  `lesmateriaal.yourdomain.com`. Type it without `https://`.
- **Use a Cloudflare Tunnel?** — answer `j` for yes.
- **Tunnel token** — paste the token you copied. Nothing appears on screen while
  you paste; that is deliberate. Press Enter.

Then it runs on its own for several minutes. It installs Docker, creates the
site's secret keys, builds the site and starts it.

You do not have to invent or remember any password here. The installer generates
the application key and the database password itself, writes them into a file
called `.env`, and locks that file so only root can read it. Nothing secret is
ever printed to the screen or stored in your command history.

When it finishes you will see a summary:

```
  De site draait.

    Adres            https://lesmateriaal.yourdomain.com
    Map              /opt/teacher
    Instellingen     /opt/teacher/.env  (mode 600 — back-uppen)

  Eis het beheerdersaccount nu op:
    https://lesmateriaal.yourdomain.com/admin/claim

    Het opeisscherm vraagt ook om deze code:
      3f9a1c4e7b2d8065fa13c9e2b7d40518
```

**Copy that code somewhere safe now.** It is shown once. It is also in
`/opt/teacher/.env` if you lose it.

If something fails, the script says where, and you can fix that one thing and
run it again — it is safe to re-run and will skip what it already did.

### Moving an existing site here

If this machine is replacing one that already ran the site, put the backup
archive on it first and hand it to the installer:

```bash
sudo TEACHER_RESTORE=/root/teacher-backup-2026-08-10-023000.tar.gz ./install.sh
```

It installs everything as above and then restores that archive, so the site
comes up with its content, its settings and its admin account already in place.
The rest of §7 does not apply — there is no account to claim, because the backup
brought one. Log in with the password the old site had.

An archive comes from **Back-ups** in the admin panel, or from
`php artisan backup:run` on the old machine. Restoring later instead of during
the install works exactly as well — see
[maintenance](maintenance.md#3-restoring).

---

## 7. Claim the admin account

Skip this if you restored a backup in the previous step; the account came with
it.

This is the one step the installer cannot do for you.

The site starts with **no account at all**. The first person to visit
`/admin/claim` creates it, and after that the screen disappears forever. That is
why the installer generated a code: without it, anyone who found the address
before you did could take the account.

In a browser, open:

```
https://lesmateriaal.yourdomain.com/admin/claim
```

Fill in the teacher's name, their email address, a password, and the code from
the summary. Submit.

You are now logged in as the site's only administrator. There is no second
account and no way to make one.

**Choose a real password.** The site requires at least 12 characters with upper
and lower case, a digit and a symbol, and refuses passwords that appear in known
breach lists. Use a password manager.

There is no "forgot password" email. If it is lost, recovery is a command on the
server — see [maintenance](maintenance.md#5-lost-password).

---

## 8. Check that it works

Ten minutes now saves a confusing afternoon later. Log in as the teacher and:

1. **Open the site.** The homepage loads.
2. **Upload an image** under *Media*. It asks for a description (alt text); that
   is required and cannot be skipped.
3. **Check that it is still private.** Open a private browsing window and paste
   the image's address into it. You should get an error, not a picture. A file
   nothing points at is not public — that is the whole design.
4. **Put the image on a page**, then repeat step 3. Now it should load.
5. **Upload a video larger than 100 MB.** It should succeed. If it were sent in
   one piece Cloudflare would refuse it, so this proves the chunked upload is
   working.
6. **Drag to the middle of that video.** It should jump there, not restart.
7. **Put a password on a topic**, then open a page beneath it in a private
   window. You should see only the password box, and the download beneath it
   should be refused until you enter the password.

If steps 3, 4 or 7 behave differently from the description, stop and find out
why before publishing anything. Those are the steps that protect the material.

---

## 9. Without a tunnel

If your server has its own public IP address and you would rather run your own
reverse proxy, answer `n` when the installer asks about the tunnel.

The site listens on `127.0.0.1:8080` — reachable from the server itself but not
from the network. Point your proxy (Caddy, nginx, Traefik) at that address and
handle HTTPS there. The installer will leave ports 80 and 443 alone in the
firewall so your proxy can use them; enable the firewall yourself once your
proxy rules are in place.

Both Cloudflare limitations in [chapter 2](#2-how-this-is-going-to-work)
disappear if you do this.

---

## 10. Doing it by hand

You only need this if the installer fails partway and you want to finish the
job yourself. It performs exactly these steps.

**Install Docker.**

```bash
apt install -y ca-certificates curl git ufw openssl
```

```bash
install -m 0755 -d /etc/apt/keyrings && curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc && chmod a+r /etc/apt/keyrings/docker.asc
```

```bash
printf 'Types: deb\nURIs: https://download.docker.com/linux/debian\nSuites: trixie\nComponents: stable\nSigned-By: /etc/apt/keyrings/docker.asc\n' > /etc/apt/sources.list.d/docker.sources
```

```bash
apt update && apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

**Create the settings file.**

```bash
cp .env.example .env && chmod 600 .env
```

Open `.env` in an editor (`nano .env`) and set four things:

| Setting | Value |
|---|---|
| `APP_KEY` | the output of the command below, including the `base64:` prefix |
| `DB_PASSWORD` | the output of `openssl rand -hex 24` |
| `APP_URL` | `https://lesmateriaal.yourdomain.com` |
| `CLOUDFLARE_TUNNEL_TOKEN` | the token from Cloudflare |

```bash
docker compose run --rm app php artisan key:generate --show
```

Leave `ADMIN_EMAIL` and `ADMIN_PASSWORD` **empty** — you will claim the account
in the browser. Setting only one of them stops the site from starting at all.

Set `ADMIN_SETUP_TOKEN` to the output of `openssl rand -hex 16` so the claim
screen requires a code.

Leave everything else as it is. In particular `FILESYSTEM_DISK=local` and
`MEDIA_X_ACCEL=true` must stay as they are; changing them breaks the file
protection and the video serving respectively.

**Start it.**

```bash
docker compose --profile tunnel up -d --build
```

The first build takes several minutes. Watch it with:

```bash
docker compose logs -f app
```

Wait for `[entrypoint] Ready.`, then press `Ctrl+C` to stop watching.

**Close the firewall.**

```bash
ufw allow OpenSSH && ufw deny 80/tcp && ufw deny 443/tcp && ufw --force enable
```

Always allow SSH before enabling the firewall, or you will lock yourself out.

---

## 11. When something goes wrong

**The installer stops on the memory check.**
The server has less than 4 GB. Answer `n`, resize the machine, and run it again.

**`FATAL: APP_KEY is not set`.**
`.env` has no application key. Generate one and put it in:

```bash
docker compose run --rm app php artisan key:generate --show
```

**`FATAL: PostgreSQL did not become available`.**
The database did not start. Look at why:

```bash
docker compose logs database
```

Usually an empty `DB_PASSWORD` in `.env`, or a full disk.

**The site does not load, but the server looks fine.**
Check the tunnel:

```bash
docker compose logs --tail=50 tunnel
```

An expired or revoked token is the usual cause. Create a new one in Cloudflare,
put it in `.env`, and run `docker compose up -d tunnel`.

**Every image and download fails, but the pages work.**
See [maintenance](maintenance.md#images-and-downloads-stopped-working).

**Uploading large files fails.**
Check that nobody has raised `MEDIA_CHUNK_BYTES` in `.env`. Above 100 MB
Cloudflare refuses the request no matter what the server allows.

**I need to start over.**
This deletes the database and every uploaded file — only do it before the site
is in real use:

```bash
cd /opt/teacher && docker compose down -v && rm .env
```

Then run `sudo ./install.sh` again.
