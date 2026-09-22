<div align="center">

# Baradox's Stuff

**A private, single-user personal hub.**

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?logo=tailwindcss&logoColor=white)
![Turbo](https://img.shields.io/badge/Turbo-8-5CD8E5)
![Vite](https://img.shields.io/badge/Vite-646CFF?logo=vite&logoColor=white)
![Sail](https://img.shields.io/badge/Laravel_Sail-Docker-2496ED?logo=docker&logoColor=white)
![Vanilla JS](https://img.shields.io/badge/JavaScript-vanilla-F7DF1E?logo=javascript&logoColor=black)
![License: MIT](https://img.shields.io/badge/License-MIT-green)

[Pages](#pages) · [How it's built](#how-its-built) · [Architecture](#architecture) · [Run it yourself](#running-it-yourself) · [Security](#security-and-scope)

</div>

---

## At a glance

| Page | What it's for |
|---|---|
| 🔐 **Login** | An animated dusk sky with a flock of birds — and a one-click Guest preview |
| 🏠 **Home** | Greeting, recently visited pages, and a track to play |
| 🎵 **Music** | Your library, plus a player that follows you across the whole site |
| 🖼️ **Photos** | Grid, lightbox, albums, and three levels of protection |
| 🎬 **Videos** | Multi-gigabyte files registered from the drive, with poster frames |
| 📔 **Diary** | Entries you can lock, grouped by month, filterable by person |
| 💬 **Extra Notes** | A chat-style scratchpad with tags, pins and video link chips |
| 🗂️ **Projects** | Future / In work / Done, with a read-only file browser |
| 🛠️ **Future Updates** | A checklist of planned improvements, cross-linked with Projects |

---

## What it is

One private site that holds everything personal in one place. It is built for exactly one person
and has no sign-up page. There are exactly two accounts:

- **The owner's account**, which holds all the content.
- **A Guest preview account.** A single button on the login page signs you in as Guest. Guest can
  open every page, but owns no content, so every page shows its empty state. Every query in the
  app is scoped to the signed-in user — that scoping is what makes the preview safe.

---

## Pages

### 🔐 Login

The only page a visitor sees first, and the only one with its own look: a dusk sky, a sagging
power line and a treeline, with the sign-in form on the dark ground below.

- A flock of birds — a **boids simulation** on a canvas — wheels across the sky, ripples when an
  unseen predator cuts through it, and scatters away from the cursor.
- While you type, part of the flock settles onto the wire.
- The flock only scatters and the page only moves on **after the server has accepted the
  sign-in**, so a wrong password never gets the send-off.
- With reduced motion turned on, the page shows a single still frame instead of the animation.
  Without JavaScript, the form still works on its own.

### 🏠 Home

- A greeting that follows the time of day, with the current time and date.
- **Recently visited** — your last few pages, each removable, with the full visit history behind it.
- **Something to hear?** — the newest track, playable with one click.

### 🎵 Music

- A compact track list, one row per song: cover slot, title and artist, release date, length,
  date added, favourite toggle, edit and delete.
- Click a row to play it in the **sitewide player** — a small floating pill that **keeps playing
  while you move around the site**. It remembers the track, position and volume across reloads,
  moves to the next track automatically, and works with your keyboard's media keys and the
  operating system's media controls.
- Instant search and sort with **Arabic-aware matching**: diacritics are ignored and common
  spelling variants are treated as equal. Right-to-left titles display correctly.
- Seeking works because audio is streamed with HTTP Range support (see [Architecture](#architecture)).

### 🖼️ Photos

- A responsive grid with the actions on each tile, and **drag-and-drop upload** with per-file
  progress bars. A rejected file gets an error that names it and says why.
- A **full-screen lightbox** you can step through with the arrow keys, showing your position
  ("7 of 43").
- **Albums** with cover images and photo counts, plus an automatic *Favorite* album.
- **Multi-select** with shift-click ranges, for bulk add to album, remove from album and delete.

**Three protection levels per photo:**

| Level | What it means |
|---|---|
| **Open** | Shown to anyone signed in to the account. |
| **Gated** | *Access control only* — the file on disk is **not** encrypted. The server won't show the photo without the correct key, and its thumbnail is heavily pixelated. |
| **Encrypted** | The file itself is encrypted at rest. It is decrypted in memory for a single view, and the decrypted version is never written back to disk. |

> [!IMPORTANT]
> Keys are only ever stored as hashes. If you lose a key, the content is gone for good — by design.

### 🎬 Videos

- A grid of cards: poster frame, title, length, description, albums and protection badge.
- **Nothing is uploaded through the browser.** Multi-gigabyte files are copied onto the drive by
  hand and then **registered** in the app, with a picker listing the files not yet registered.
  At registration, FFmpeg reads the length and **takes a poster frame** — from about 10% in,
  because the first frame of a recording is usually black.
- The page has exactly **one** video player, inside a dialog, and it loads nothing until you open
  it. Opening the page never starts a request against a large file.
- Albums with covers, gated protection with a pixelated poster, and a **shareable link** per
  video that survives renaming the title, with a copy button.
- A file missing from the drive is flagged on its card and can't be played. If the whole drive is
  disconnected, a single banner says so instead of every card reporting a missing file.

### 📔 Diary

- Entries with a title, text, an optional date, the people involved, and an optional photo —
  either one you already have or a new upload.
- Entries are **grouped by month**. Tap a person chip to filter the list; search covers titles
  and the text of unlocked entries.
- **Locking:** an entry can be locked with a key, **choosing from several ciphers**. Opening,
  unlocking or deleting a locked entry needs both the key and the cipher it was locked with. A
  locked card shows only its title and date.
- The app can **generate a key** for you. It's shown **once**, and the dialog won't close until
  you confirm you've saved it.
- A revealed entry's text never goes into the session or a URL, and is removed from the page as
  soon as the dialog closes.

### 💬 Extra Notes

A chat-style scratchpad, like Telegram's *Saved Messages*: one running stream, newest at the
bottom, input box underneath. It's for getting things out of your head, not for filing them.

- Send, edit, delete, copy, **pin** (pinned notes sit in a rail no filter hides), and **tag** from
  a small fixed set.
- Server-side search and tag filtering. Older notes load 50 at a time as you scroll up, and the
  view stays where you were.
- **Multi-select** with shift-ranges for bulk delete and bulk tagging.
- **Code formatting only** — inline code and fenced blocks. Nothing else is treated as markup, so
  pasted text appears exactly as pasted.
- **Links to videos become chips** showing the video's real title.
- Nothing on the page ever reloads: every action is answered with a Turbo Stream.

### 🗂️ Projects

- **Future / In work / Done** tabs. Projects move between phases in either direction, and moving
  never deletes data.
- Each phase has its own required fields, enforced on save — a *done* project needs a finish date,
  a folder and a line count, for example.
- A GitHub link, people, languages and dates per project. Dates mean "expected" or "actual"
  depending on the phase.
- A **read-only file browser** over the project's folder, loaded one level at a time. It can't read
  outside that folder, and offers *open in VS Code* and *copy path*.

### 🛠️ Future Updates

- A checklist of planned improvements, each either sitewide or tied to a project.
- A one-line input bar; view grouped by project or flat; show or hide finished items; open/done
  counts.
- **Cross-linked with Projects:** each group links to its project's card, and each project card
  shows its count of open updates, linking back to the list filtered to it.
- Deleting a project keeps its planned updates, labelled with the project they belonged to
  (*was: …*) — never quietly moved to sitewide.

---

## How it's built

| Layer | Tools |
|---|---|
| **Backend** | Laravel 13, PHP 8.5, MySQL 8.4 |
| **Environment** | Laravel Sail (Docker Compose), developed under WSL2 |
| **Front end** | Blade and **vanilla JavaScript** (no framework); Tailwind CSS v4 for layout, plain scoped CSS for components |
| **Navigation** | Turbo Drive 8 (no full page reloads); Turbo Streams for Extra Notes |
| **Build** | Vite, running inside the container |
| **Media** | Intervention Image v4 (thumbnails, pixelation); FFmpeg / ffprobe (video length, poster frames) |
| **Icons & type** | Font Awesome; fonts self-hosted through Vite |

---

## Architecture

<details>
<summary><strong>Media lives on an external drive and never touches <code>public/</code></strong></summary>

<br>

Photos and videos sit on an external SSD, bind-mounted into the app container and exposed to
Laravel as its own filesystem disk. No public symlink points at it. Every file — photo,
thumbnail, poster, video or song — is served by a controller route that checks who owns it and
whether a key is needed.

Those routes support **HTTP Range requests**, so video and audio can seek. That matters because
PHP's built-in server, which Sail uses, ignores Range for static files — media served through a
plain `/storage` symlink could never seek.

</details>

<details>
<summary><strong>A player that survives navigation</strong></summary>

<br>

The whole app runs on Turbo Drive: moving between pages replaces the page body instead of
reloading. The player sits in an element marked `data-turbo-permanent`, so the same `<audio>`
element carries over from page to page and the music never stops.

Pages talk to the player through a small interface: any element with a `data-player-play`
attribute starts a track, and the player dispatches a `bs-player:change` event whenever the track
or play state changes, so each page can highlight what's playing.

</details>

<details>
<summary><strong>Turbo Streams instead of redirects</strong></summary>

<br>

Most forms save and then redirect, which is what Turbo Drive expects. Extra Notes can't: a
redirect would throw away the scroll position, the older pages already loaded, and the current
selection. So every action there answers with a `text/vnd.turbo-stream.html` response that
replaces, adds or removes only the notes it changed. Messages go into a slot on the page, since
there's no redirect to carry them. This is done by hand, without the turbo-laravel package.

</details>

<details>
<summary><strong>Link chips resolved in one query per link type</strong></summary>

<br>

Before a page of notes is drawn, a `LinkResolver` scans all of it for links. Each link type has a
handler (videos, so far), and each handler looks up every key it found in a **single** query —
fifty notes cost one query per link type, not one per note. A link that no longer points
anywhere renders as plain text rather than a broken chip.

</details>

<details>
<summary><strong>Other details</strong></summary>

<br>

- Keys are stored only as hashes, never in plain text.
- Every controller action checks that the record belongs to the signed-in user before touching it.
- The Projects file browser uses `realpath()` and a prefix check to stay inside the project's own
  folder, and the projects directory is mounted read-only.
- Video posters are cached by video id rather than filename, so two entries pointing at the same
  file never share a poster showing the wrong protection.

</details>

---

## Running it yourself

> [!IMPORTANT]
> This was built for me on my machine — Windows with WSL2 and an external drive. It runs
> anywhere Docker does, but a few paths must be changed to match your machine first.

### Requirements

- **Docker** (Docker Desktop on Windows/macOS). On Windows, clone the repo **inside the WSL
  filesystem** (e.g. `~/projects`), not under `/mnt/c`.
- **A folder for photos and videos** — an external drive, or any directory.
- Nothing else on the host. PHP, Composer, Node, MySQL and FFmpeg all run in the containers.

### 1 · Clone and configure

```bash
git clone <this-repo-url> baradox-stuff
cd baradox-stuff
cp .env.example .env
```

Every project-specific variable in `.env` has a comment explaining it. Before the first start:

- set **`DB_PASSWORD`** — compose creates the MySQL user from it on first start;
- keep **`VITE_PORT`** set — 5299 by default, any free port works.

### 2 · Adapt the machine-specific paths

These are hard-coded for the original machine:

| File | Value | What it is |
|---|---|---|
| `compose.yaml` → `laravel.test.volumes` | `/mnt/e/photos:/photos-ssd` | Your media folder on the host. Keep the right-hand side (`/photos-ssd`) as it is. |
| `compose.yaml` → `laravel.test.volumes` | `/home/baradox/projects:/projects-root:ro` | The folder whose sub-folders the Projects file browser shows. Keep `:ro`. |
| `config/projects.php` | `'wsl_root' => '/home/baradox/projects'` | The same folder as seen from your editor, used for *open in VS Code* and *copy path*. |
| `config/projects.php` | `'wsl_distro' => 'Ubuntu'` | Your WSL distro name, used in the `vscode://` link. |

Inside the media folder, create a sub-folder called **`videos`** (lowercase) for video files.
Photos, thumbnails and posters get their own folders automatically.

> [!NOTE]
> The original setup uses `Videos`, which only works because an NTFS drive is case-insensitive.
> On a Linux filesystem the name must be exactly `videos`.

### 3 · Install dependencies and start

A fresh clone has no `vendor/` — and so no `sail` command yet — so install Composer dependencies
once with a throwaway container. The app itself runs on PHP 8.5 inside Sail; this image is only
used for the install ([Sail docs](https://laravel.com/docs/sail#installing-composer-dependencies-for-existing-projects)):

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs
```

Then:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
```

### 4 · Create the two accounts

There's no sign-up page, so create both accounts with tinker. Passwords are hashed automatically
when the account is saved.

> [!TIP]
> Keep each command on a **single line** — tinker's line editor mangles multi-line pastes.

```bash
./vendor/bin/sail artisan tinker --execute="App\Models\User::create(['username' => 'you', 'display_name' => 'Your Name', 'password' => 'a-long-password', 'is_preview' => false]);"
```

```bash
./vendor/bin/sail artisan tinker --execute="App\Models\User::create(['username' => 'guest', 'display_name' => 'Guest', 'password' => 'another-long-password', 'is_preview' => true]);"
```

The second account is the Guest preview. The login page's Guest button signs into whichever
account has `is_preview = true`, so there must be exactly one. These commands end up in your shell
history — clear them afterwards if that matters to you.

*Alternatively, uncomment and fill in `ADMIN_*` and `PREVIEW_*` in `.env` and run
`./vendor/bin/sail artisan db:seed`, which creates the same two accounts.*

### 5 · Run the front end and open the site

```bash
./vendor/bin/sail npm run dev
```

Leave it running and open **http://localhost**. Vite runs **inside** the container and
deliberately refuses to start outside Sail. Prefer built assets? Use
`./vendor/bin/sail npm run build` instead.

---

## Security and scope

> [!WARNING]
> This is a personal project that runs on `localhost`. It is **not hardened for public
> deployment** as it stands.

- The Guest button signs anyone who can reach the login page into the preview account — and that
  account isn't read-only: it can create its own content.
- The login form has no rate limiting, and the defaults are development settings
  (`APP_DEBUG=true`, PHP's built-in server).
- The protection features guard against casual access on a machine you already control. They
  haven't been audited as cryptography, and *Gated* in particular is access control, not
  encryption.

If you want to put it on the internet, treat it as a starting point, not something ready to deploy.

---

## License

Released under the [MIT License](LICENSE).

---

> [!IMPORTANT]
> **A note on running this yourself.** This project was built for me personally, so a lot of
> things need adjusting before anyone else can run it on their own machine. If I see that people
> want it — and want to enjoy what I made too — I'll build a **v2.0** that's much easier for anyone
> to set up.

---

<p align="center">
  <strong>Made by Baradox</strong> · Crow-developers
</p>
