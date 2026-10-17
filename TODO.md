# TODO

Plan, decisions and things to do by hand (cPanel, the server).
Feature wishlist is in `notes.md`. How-to for local development and deploying is in `deploy/DEPLOY.md`.

## Release plan

1. **Full release: Laravel 13 upgrade** (packages changed → *Full release* in `deploy/DEPLOY.md`).
   Contains the upgrade, tests, backups, maintenance page, and the `symfony/html-sanitizer`
   package needed later for slogans (added now so the next releases don't change packages).
2. **Quick release 1: bug fixes** (only code → usual cPanel Git deploy). See *Decided fixes*.
3. **Quick release 2: invite-only registration** (needs a migration → run it from `/admin/maintenance`).
4. **Redesign** with Inertia + Vue 3 + Tailwind 4, page by page. This adds frontend assets
   (`public/build`, not in git), so those releases will need the archive again.

## Now (you)

- [ ] Review the changes and commit them (nothing has been committed)
- [ ] Deploy Laravel 13 with the **Full release** steps in `deploy/DEPLOY.md`, not the usual
      cPanel Git deploy (packages changed). Don't forget to delete the old files in
      `public_html/database/migrations/`

## On the server (during / after the first deploy)

- [ ] cPanel → *Gestione MultiPHP* → PHP 8.4
- [ ] cPanel → *Processi cron* → change the command to the PHP 8.4 binary
      (`/usr/local/bin/ea-php84 /home/bptheboi/public_html/artisan schedule:run >> /dev/null 2>&1`)
- [ ] The next morning: check that `/admin/maintenance` lists a new backup made at 03:00
      (if not, the cron is still using the old PHP)
- [ ] `/admin/maintenance`: check that all PHP extensions are green
- [ ] Add to the server `.env` to get a backup by email every Sunday:
      ```
      BACKUP_MAIL_TO=matteo.gavoni@gmail.com
      ```
      (optional: `BACKUP_KEEP=30` = how many backups to keep on the server)
- [ ] After a few days without problems: delete `public_html/vendor_old`
- [ ] File Manager: tell Claude where `public_html/public/storage` points (is it a link? to which path?)
      and which files are in `public_html/storage/logs/`. This explains why images work with
      `/storage/app/public/...` URLs and whether logs could be reachable from the web.
- [ ] Ask Serverplan whether they keep their own backups of the account, and for how long

## On this PC

- [ ] Something already uses port 3306 (probably an old MySQL installed on the system).
      If you don't need it, uninstall it; Sail's MySQL is on port 3307
- [ ] Optional: install VS Code from the Microsoft `.deb` instead of Flatpak,
      so its terminal can run `sail` and `docker`
- [ ] Keep `~/Downloads/bptheboi_theboise.sql` private (it has emails and password hashes)
      and delete it when you don't need it anymore

## Decided fixes (quick release 1)

Each fix gets a test.

1. **Deleting a forum post without attachments crashes** (same when deleting a category whose
   posts have none) → posts without attachments must be deletable.
2. **Editing a post can't change "private" or "can edit"** → make it work, and the edit form must
   show the current state of the checkboxes.
3. **Forum post comment emails** (album comments are fine): today the author gets up to 3 emails,
   even for their own comments, and one says the author wrote it → send one email to each of:
   earlier commenters + post author, minus the person commenting, minus users the post is hidden from.
4. **Unticking everyone in "hide from" doesn't unhide** a post or category → fix.
5. **Only user #1 can save a category edit** → creator or admin can save.
6. **Phone photos are never rotated** (EXIF orientation never read) → fix.
7. **Privacy on edit:** only the post author and admins can change "private", "can edit" and
   "hide from". Others allowed by "can edit" can only change the content.
8. **"Notify" button** (emails everyone who can see a forum post) → only the post author and admins.
9. **Slogans are shown as raw HTML** → allow only safe formatting (links, bold, italic) with
   `symfony/html-sanitizer`, strip everything else. Keeps the existing slogan with a link working.
10. **Admin checks:** some code checks "user #1", other code checks `isAdmin` → always `isAdmin`.
11. **Splitwise:** remove the broken page, route and controller (copied forum code, nothing reusable).
    Splitwise stays wanted: rebuild it properly after the redesign (see `notes.md`).

## Decided: registration (quick release 2)

- **Invite links only.** On `/admin/maintenance` the admin creates an invite link, sends it
  (e.g. WhatsApp). Single use, expires after a few days. `/register` only opens with a valid link.
- No public registration, no approval queue.

## Decided: redesign

- Stack: **Inertia + Vue 3 + Tailwind 4**, migrated page by page (layout and drawer first,
  then albums, then forum).
- Look: **keep the current colors** (familiar to the friends), modernize style and add modern
  features, no complete redesign. Simple, clean, dynamic, functional.
- Replace the crowded top navbar with a **sidebar / drawer**.

## Still open

- [ ] Maps: Google Maps (needs an API key with billing) or Leaflet + OpenStreetMap (free)?
- [ ] Database table `slogan` is empty and duplicates `slogans`: drop it?
