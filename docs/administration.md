# Administration

This guide is for you as the owner of the website: the teacher who publishes
the course material. You do not need any technical knowledge — everything in
this guide happens in the browser.

> This is the English version of [beheerdersgids.md](beheerdersgids.md). They
> are the same guide.

Setting the server up in the first place: [deployment](deployment.md).
Day-to-day running of the server afterwards — backups, updates and recovery:
[maintenance](maintenance.md).

**A note on labels.** The interface itself switches between Dutch and
English — whoever visits chooses, in the language switcher described in
[chapter 13](#13-the-language-of-the-site). This guide names screens and
buttons the way they read once the interface is set to English. The Dutch
interface uses different words for the same buttons in the same places, and
nothing described here changes depending on which one is showing. Anything
you write yourself — titles, descriptions, page text, download names, level
names, the site's own name — is never translated and looks identical either
way.

---

## Contents

1. [Signing in for the first time](#1-signing-in-for-the-first-time)
2. [Finding your way around](#2-finding-your-way-around)
3. [Topics: the structure of the site](#3-topics-the-structure-of-the-site)
4. [Pages](#4-pages)
5. [Writing the content of a page](#5-writing-the-content-of-a-page)
6. [Downloads per level](#6-downloads-per-level)
7. [The media library](#7-the-media-library)
8. [Large videos](#8-large-videos)
9. [Managing levels](#9-managing-levels)
10. [Passwords](#10-passwords)
11. [Hidden versus deleted](#11-hidden-versus-deleted)
12. [The name, the logo and the homepage](#12-the-name-the-logo-and-the-homepage)
13. [The language of the site](#13-the-language-of-the-site)
14. [Search](#14-search)
15. [Backups](#15-backups)
16. [Your own account](#16-your-own-account)
17. [Frequently asked questions](#17-frequently-asked-questions)

---

## 1. Signing in for the first time

There is exactly **one account** on this website: yours. Students never
create an account and never sign in. There is no registration screen either
— it was deliberately left out.

The very first time the site is online, that one account belongs to nobody
yet. Go to `https://your-address/admin/claim` and fill in your name, email
address and a password. After that the account is yours and this screen is
gone for good.

> **Do this straight away.** Until the account is claimed, anyone who knows
> the address can claim it. Whoever manages the server can close this window
> with an environment variable (`ADMIN_SETUP_TOKEN`); ask for it if there is
> a gap between "the site is online" and "I am there to claim it".

After that you sign in at `https://your-address/login`. That address is
deliberately not linked from anywhere on the site: students never sign in,
so a sign-in link would only raise the question of whether they need an
account too. Bookmark it. Once you are signed in, an **Admin** link appears
in the top right, back to the admin panel.

**Forgotten your password?** There is no "forgot password" email. Recovery
happens on the server — see
[maintenance](maintenance.md#5-lost-password).

---

## 2. Finding your way around

After signing in you see a menu on the left:

| Menu item | What it is for |
|---|---|
| **Dashboard** | Home screen: an overview of what is on your site, and a *Getting started* checklist for as long as there is anything left to do |
| **Content** | Topics and pages — the structure of the site |
| **Media** | All uploaded images, documents and videos |
| **Levels** | The levels you tag downloads with (Foundation, Higher, …) |
| **Passwords** | Passwords that protect a topic or a page |
| **Backups** | Make and download a copy of the whole site |
| **Settings** | The name and logo of the site, and the text on the homepage |
| **View the website** | Opens the site the way students see it |

Your own name is in the bottom left. That is where you find your profile,
your password, two-factor authentication and the light/dark setting.

The site is **dark** by default. Students cannot change that; you can, for
yourself, through your own menu.

---

## 3. Topics: the structure of the site

Go to **Content**. What you see there is the tree of the whole site.

- A **top-level topic** is a tile on the homepage. For example *History*,
  *Geography* or *French*.
- Underneath it you can hang **subtopics**, and below that one more layer.
  For example *Geography → Europe → The Alps*, or *History → The Golden
  Age → The Dutch East India Company*.
- **No deeper than three layers.** That is deliberate: anything deeper
  becomes unfindable for a student on a phone.

The site assumes nothing about what you teach. Whatever your subject, the
structure is yours to decide — the examples above are only examples.

A page may hang at **any** layer. You do not have to invent a subtopic if
you do not need one: a page directly under *Geography* is fine.

### Creating a topic

Click **+ New top-level topic**, or **+ Subtopic** on the topic it should
hang under. You fill in:

- **Title** — what students see.
- **Slug** — the bit of the address, for example `europe` in
  `/geography/europe`. Suggested automatically from the title. Use
  lowercase letters and hyphens, no spaces.
- **Icon** — pick one from the list. Search for an English word (`book`,
  `star`, `map`).
- **Description** — one or two sentences. Appears under the title on the
  tile.
- **Text** — optional, and longer. An introduction to the topic, with
  headings, lists and links. Students see it at the top, above the list of
  subtopics and pages. Useful when a topic deserves some explanation
  rather than jumping straight into a row of tiles.
- **Password** — see [chapter 10](#10-passwords).
- **Hidden** — see [chapter 11](#11-hidden-versus-deleted).

> **You cannot put files or videos in a topic's text.** Only on a page.
> That is not a limitation of the text box but of how the site releases
> files: a file becomes visible to students because a *page* points to it.
> If it were allowed here, you would see it and a student would not.

> **Changing the title does not change the address.** That is deliberate:
> links you have already shared with students keep working. If you do
> change the *slug*, the site remembers the old address and redirects
> visitors automatically. So old links keep working then too.

### Setting the order

On the **Content** screen, every topic and page has a handle (⠿) next to
it. Drag it to change the order; it is saved immediately. Exactly as you
place it there is how a student sees it on the site.

Prefer not to use a mouse? Tab to the handle, press space to pick it up,
move it with the up and down arrow keys, and press space again to drop it.
Escape cancels.

Dragging only changes **the order within the same place**: a page stays
under the same topic. Moving to a different topic is done below.

### Moving

Edit the topic and choose a different parent topic. The whole branch moves
with it. If that would push the branch deeper than three layers, the site
refuses the move and nothing changes. Whatever you move lands at the end of
its new list; from there you can drag it into place.

### Deleting

A topic that still has subtopics or pages underneath it **cannot** be
deleted. The site tells you what is still inside. Move or delete the
content first. This is deliberate: one wrong click must never take out
half a chapter.

---

## 4. Pages

A page is where the actual material lives: explanation, images, videos and
the downloads.

Create one with **+ Page** on the topic it belongs under. At the top of the
edit screen are the page's settings:

- **Topic** — where the page hangs. You can move it later.
- **Title**, **Slug**, **Icon**, **Description** — the same as for a
  topic. You do not set the order here, but by dragging on **Content**.
- **Banner image** — a wide image above the title. Optional. Choose one
  from the media library.
- **Password** — see [chapter 10](#10-passwords).
- **Hidden** — see [chapter 11](#11-hidden-versus-deleted).

Click **Save**.

> Note: a page has **three** parts you save separately — the settings
> above, the content ([chapter 5](#5-writing-the-content-of-a-page)) and
> the downloads ([chapter 6](#6-downloads-per-level)). Each has its own
> button. The downloads save immediately; the settings and the content
> only when you click their own button.

### Copying a page

On **Content**, every page has a **Duplicate** button. Useful for "last
year's worksheet, but for this class": you get a complete copy — the text,
the banner image, the password and every download with its levels — and
land straight in the edit screen of that copy.

Two things deliberately do *not* carry over:

- **The copy is hidden.** A copy says exactly the same thing as the
  original, so publishing it immediately would show students two identical
  pages. Untick **Hidden** once you are done editing.
- **The download counter starts at zero.** That belongs to the original;
  putting last year's number on a new page would be a figure that means
  nothing.

The copy gets `-copy` appended to its slug and lands at the end of the
list. Adjust the title and slug afterwards as you like.

---

## 5. Writing the content of a page

Under the heading **Content** is the text editor. The toolbar:

| Button | What it does |
|---|---|
| **Bold**, **Italic** | Text formatting |
| **Subscript (H₂O)**, **Superscript (m/s²)** | Lowered and raised characters |
| **Heading 2**, **Heading 3** | Subheadings. The page's title is already a level-1 heading |
| **Align left** … **Justify** | Alignment of the paragraph or heading |
| **Bulleted list**, **Numbered list** | Lists |
| **Quote** | Indented block |
| **Link** | Turn the selected text into a link |
| **Insert a file** | A document or video from the media library |
| **Insert images** | One or more images as a gallery |
| **Insert a YouTube video** | Paste a YouTube link |
| **Insert a table** | A 3 by 3 table with a header row |

Under the editor it shows whether there are unsaved changes. **Do not
forget to click "Save content"** — the text is not saved on its own.

### The three blocks

- **Insert a file.** Choose a document or video. A document appears as a
  download card with the right file icon. A video appears as a player
  students can scrub through.
- **Insert images.** Choose one or more; with more than one you get a
  grid.
- **Insert a YouTube video.** Paste the link from the address bar. Useful
  for large videos — see [chapter 8](#8-large-videos).

In both dialogs you can **upload something straight away**, at the top,
without leaving the page:

- In **Insert a file**, whatever you upload is placed directly at the
  cursor's position in the page.
- In **Insert images**, it is ticked and comes along as soon as you click
  **Insert** — so you can put several into one gallery in a single go.

Anything you upload this way also ends up in the media library, same as
always. And here too: **click "Save content" afterwards**, or the file
sits in the library but not on the page.

### Subscript and superscript: H₂O, m/s², 1st

For **subscript** (lowered, like the 2 in H₂O) and **superscript**
(raised, like the 2 in m/s², or the "st" in 1st), there are two buttons
next to *Italic*. Turn it on, type the character, and turn it off again.

Both of these buttons also sit in the smaller text box used for a topic's
text and for the homepage.

### Tables

**Insert a table** places a 3 by 3 table with a header row. As soon as
your cursor is in a table, an extra row of buttons appears:

| Button | What it does |
|---|---|
| **Row above** / **Row below** | Adds a row |
| **Delete row** | Removes the row the cursor is in |
| **Column left** / **Column right** | Adds a column |
| **Delete column** | Removes the column the cursor is in |
| **Merge cells** | Merges the selected cells, or splits them again |
| **Delete table** | Removes the whole table |

You can adjust a column's width by dragging the border between columns.

> On a phone, a wide table scrolls horizontally inside its own frame. The
> rest of the page stays where it is. Keep tables narrow anyway — four
> columns reads a lot more comfortably on a phone than eight.

### Links

First select the text you want to turn into a link, then click **Link**.
For a page on this site you can use the path (`/geography/europe`); for
another site the full address with `https://`.

---

## 6. Downloads per level

This is what the site was built for. At the bottom of every page is a
**Downloads** section: the same assignment in several variants, each
labelled with the levels it is intended for.

Under the heading **Downloads** in the edit screen you first tick the
**levels** it is intended for — several is fine, and none is fine too.
After that you can go two ways:

- **Upload a new file.** Drag it into the *Upload a new file* box, or
  click **Choose files**. The file lands in the media library *and* is
  immediately a download on this page, with the levels ticked above. You
  do not have to go to **Media** first.
- **Choose a file that already exists.** Pick it under **File**, optionally
  give it a different **name on the page**, and click **Add**.

Uploading several files at once works: they all get the same levels and
keep their own filename. Click **Edit** behind a download afterwards to
change the name or the levels of that one file.

Every change here is saved **immediately**. Uploading is not the same as
saving the page — that is not needed here.

### How students see it

The downloads are grouped by level: a heading *Higher* with the files for
Higher underneath, a heading *Foundation*, and so on. A file you have
ticked for both, say, *Higher* and *Foundation* appears under **both**
headings.

Tick **no level at all**, and the file lands in a group **For everybody**
at the top. Use that for material that is the same for the whole class; you
do not have to tick every box.

Students can also choose their own level. It is remembered in their
browser and moves their group to the top. It **never hides anything** — a
student who accidentally picks the wrong level does not miss material
because of it. Nothing about that choice is stored on the server.

### The same file on more than one page

The level tags belong to the *attachment*, not to the file itself. The
same PDF can be called "Foundation + Higher" on one page and only "Higher"
on another. Neither page ever changes what the other says.

### Download counter

Every download shows how often it has been fetched. That is a simple
counter; nothing else about visitors is recorded. Your own downloads do
not count while you are signed in.

---

## 7. The media library

Under **Media** are all your files, in two lists: **Images** and **Files**
(documents and videos).

### Uploading

Drag files into the box at the top, or click **Choose files**. Large files
are uploaded in parts automatically; you see the progress for each file.
Do not close the page while it is uploading.

You do not have to start here: from a page's edit screen you can also
upload, and the file lands on that page straight away. See
[chapter 5](#5-writing-the-content-of-a-page) and
[chapter 6](#6-downloads-per-level). Either way, everything ends up here
too — this stays the place where you find *everything* again.

### Images are converted automatically

You do not need to worry about file formats or file size. Every image you
upload is converted automatically to **WebP**, a format every browser
understands and that is a lot smaller.

That matters most for **photos from your phone**. An iPhone produces HEIC
files, which no browser can display — without this conversion such a
photo would be a blank box on the site. Now you can simply drag a photo
off your phone and it works.

Alongside that:

- **Large images are made smaller.** A 20 MB photo is brought under 2 MB
  and to at most 2560 pixels wide. On a page that is still sharper than a
  student's screen.
- **Location data disappears.** A phone photo often carries the exact
  place it was taken. That information is discarded, so you do not
  publish it by accident.
- **The photo stays upright.** Even if your phone saved it rotated.

Exceptions: **SVG logos** and **animated GIFs** are left exactly as they
are. The filename does get the correct extension: `holiday.HEIC` becomes
`holiday.webp`.

**Every image needs alt text.** That is the description read aloud to
someone who cannot see the image, and it appears if the image fails to
load. The site simply refuses an image without alt text. Describe what is
visible ("A map of Western Europe"), not what the file is ("image 3").

You can edit alt text later with **Edit alt text**.

### Uploading is not the same as publishing

A file you upload is **not** reachable by visitors as long as it does not
sit on any page. Only once you insert it into a page, attach it as a
download, choose it as a banner image or set it as the logo can anyone
reach it. So feel free to upload things ahead of time.

If a file sits on a page with a password, that password also applies to
the file itself: guessing the file's address does not help.

### Deleting

A file that is in use somewhere **cannot** be deleted. The site tells you
which pages it is on. Remove it there first.

---

## 8. Large videos

You can upload videos up to about 2 GB and put them on a page as normal;
the player supports scrubbing.

Still, for large videos **an unlisted YouTube video is usually better**:

- The connection this site runs on is not meant for serving a lot of
  large video. A class of thirty watching at once will bring it down.
- An "unlisted" video does not show up in YouTube's own search results and
  is only reachable through the link — that is, through your page.

Use the **Insert a YouTube video** button for that.

### A file too large to upload

If uploading through the browser does not work, whoever manages the server
can put the file on directly; see
[maintenance](maintenance.md#6-putting-a-huge-file-on-the-site).
The file then simply appears in your media library.

---

## 9. Managing levels

Under **Levels** are the levels you tag downloads with. On installation,
VMBO-BK, VMBO-T, HAVO and VWO are set up as a starting point — Dutch
secondary-school tracks — but that is only a starting point: rename them,
delete them or add others to suit however your own school works.

- **New level** — fill in a name. It lands at the end of the list.
- **Order** — drag the handle (⠿). The order you make here is the order
  the headings appear in under **Downloads** on a page. Also works with
  the keyboard: space to pick up, arrows to move, space to drop.
- **Edit** — change the name. Existing downloads keep their label.
- **Delete** — only possible while the level is not used anywhere. Next to
  each level is how many downloads it is attached to.

### Retiring a level that is still in use

Use **Merge into**. Every download carrying the old level gets the new
one, and then the old one disappears. That is the tidy way to, for
example, combine two tracks into one. No file is ever lost.

---

## 10. Passwords

A password shields material from anyone who does not have the code — a
test, or material meant only for your class.

### How it works

Under **Passwords** you create a password with a **name** and the code
itself. The name is there to tell them apart: "Year 11", "Practical group
2".

Then you choose that password on a topic or on a single page:

- Set it on a **topic**, and everything underneath is protected — every
  subtopic and page.
- Set it on a **page**, and it applies to that page only.
- If a page inside a protected branch has its **own** password, that one
  counts: the nearest password wins.

A student who enters the password can then open **everything** protected
by that same password — including pages in a completely different topic.
That is how you share one code with a class instead of a code per page. It
lasts thirty days.

### What students see

A protected page shows only the entry field. Nothing of the content is
sent along, so there is nothing to "click away" either. Protected pages
also do not appear in the search results of anyone who has not unlocked
them.

**The password's name is shown next to the entry field**, so someone
holding two codes knows which one is being asked for. So do not put
anything confidential in it — not the code itself, and no students'
names.

### Changing a password

Change the code, and every student who had already entered it is
**immediately** locked out again and has to enter the new one. That is
exactly how you revoke access at the end of a term.

### You always see everything

As long as you are signed in you never have to enter a password yourself
to view your own pages.

---

## 11. Hidden versus deleted

Every topic and every page has a **Hidden** checkbox.

A hidden item:

- does **not** appear in the menu, on the homepage or in the search
  results;
- **can** still be opened normally through its direct link.

Use it for something you are still working on, or for last year's material
you do not want to throw away. If you want to truly restrict something,
use a password instead — hidden is *not* secret.

Deleting is permanent, and is refused as long as anything still hangs off
the item.

---

## 12. The name, the logo and the homepage

Under **Settings** is everything that makes the site yours.

**The site**

- **Name of the site** — appears in the browser's title bar and next to
  the logo.
- **Logo** — appears at the top left of every page. Leave empty for the
  name alone.
- **Favicon** — the small icon in the browser tab. A square 32 by 32
  pixel PNG works best.

**Homepage**

- **Heading** and **Subheading** — the text at the top.
- **Banner** — a wide image above the heading.
- **Text** — a piece of free text, for example a welcome or an
  announcement.

The tiles with top-level topics always sit below this text and cannot be
removed — otherwise the homepage could become a dead end.

You cannot insert files or videos in the homepage's text box: those belong
on a page. To point from the homepage to a file, make a link to the page
where it lives instead.

Choose the logo, favicon and banner from the media library — so upload
them under **Media** first.

At the bottom of the same screen is one more heading, **Search**, with the
language you write in. That is a different thing from the language of the
buttons; see [chapter 13](#13-the-language-of-the-site).

---

## 13. The language of the site

The site speaks Dutch and English. Bottom left of the menu — and on the
public site, next to the search field — is a small choice list.

**Only the buttons and the site's own text change with it.** Everything
you write stays exactly as you wrote it: topic and page titles,
descriptions, the names of your levels, the site's own name and the text
on the homepage. There is no second version of your course material to
keep up to date — that would double the work, and that is not what this
is.

A visitor who has never chosen gets the language their browser asks for,
and otherwise Dutch. The choice is kept in a cookie on their own computer;
the site records nothing else about it.

### Language of your course material

At the bottom of **Settings**, under the heading *Search*, is one choice
list: **Language of your material**. That is a different thing from the
list above.

It is about the language **you** write in, and it decides how the search
function recognises words. Written in Dutch, *krachten* also finds
*kracht*; written in English, *forces* also finds *force*. Set it wrong,
and search still works, just less well — inflections are then no longer
recognised.

Changing it rebuilds the search index immediately. On a site with a lot of
pages that takes a few seconds; you do not have to do anything else.

If you write in both languages, choose the one you write in most. Only one
can apply at a time: the site stores one search version per page, made at
the moment you save the page.

---

## 14. Search

At the top of the site is a search field (`/zoeken` — the address stays
in Dutch, the same as the rest of the visitor-facing site's technical
paths). It searches the title, description and text of pages. Inflections
are taken into account, so *worksheets* also finds *worksheet* — in the
language you chose under **Settings**, see
[chapter 13](#13-the-language-of-the-site).

You can put a phrase in quotation marks (`"the golden age"`) and exclude a
word with a minus sign (`worksheet -answers`).

Hidden pages never appear in the results. Protected pages only for
whoever has already entered the password.

### Being found by Google

The site gives search engines a list of its pages, at `/sitemap.xml`. You
never have to configure or update it: it is rebuilt on every request from
whatever is currently on the site.

What it **does not** contain matters just as much:

- hidden topics and pages, and everything underneath them;
- anything with a password, including the pages inside a protected
  branch.

That holds even when *you* request the list while signed in. You see
exactly what any visitor gets — otherwise your own overview would give
you a false picture of what is out there.

If you specifically want something *not* found, set the topic in question
to hidden or behind a password. There is deliberately no separate
"invisible to Google" switch: that would be a third way to hide something,
alongside the two that already exist.

---

## 15. Backups

Under **Backups** in the menu on the left you make a copy of the whole
site with one button: every topic and page, every setting, and every file
you have ever uploaded. One file, one button.

### Making one

Click **Make a backup now**. On a site with a lot of video this can take a
few minutes — leave the tab open. Afterwards it appears in the list, with
the date and its size.

### Keeping one

**Download it to your own computer.** That is the most important step. A
backup that only lives on the server protects against a mistake by you,
but not against a server that breaks. Put it on your laptop, and
preferably also on a USB stick or in the cloud.

Handle it carefully: such a file contains *everything*, including the
passwords on your pages. Treat it like a set of keys.

### Restoring one

Restoring cannot be done from this screen, deliberately: it wipes
everything currently there, and that is not a button anyone should be
able to press by accident. It happens on the server. Hand the backup file
to whoever manages the server; for that person it is documented in
[maintenance](maintenance.md#3-restoring).

The same file is also how the site moves to a new server. Everything comes
along, including your own account and password.

### How often

Ask the server administrator to have it run automatically every night —
that is possible, and then you never have to think about it. Beyond that,
make your own backup right before you change something big: moving a
batch of pages, retiring a level, or a big clear-out in the media library.

---

## 16. Your own account

Through your name in the bottom left you reach:

- **Profile** — your name and email address.
- **Security** — change your password and set up two-factor
  authentication (an authenticator app or a passkey). Recommended: this is
  the only account on the site.
- **Appearance** — light or dark, for you only.

You cannot delete your account. There is only one, and without it nobody
could get in any more.

---

## 17. Frequently asked questions

**Can students create an account?**
No. There is no registration screen and no second account.

**Is it tracked who downloads what?**
No. There is one counter per download, and no visitor data at all beyond
that. There are no analytics or external scripts on the site.

**I renamed a page — do old links still work?**
Yes. Changing the title does not touch the address, and if you do change
the address, visitors are redirected automatically.

**I do not see my new file on the site.**
Uploading alone does not publish anything. Insert it into a page or
attach it as a download.

**A student says a download does not work.**
Is the page behind a password the student does not have? Or is the page
hidden, so the student can only reach it through an old link? Look at the
page yourself while signed out — or in a private window — to see what the
student sees.

**I have lost my password.**
See [maintenance](maintenance.md#5-lost-password). That can only be done
on the server, not by email.
