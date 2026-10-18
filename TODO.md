# TODO

Plan, decisions and things to do by hand (cPanel, the server).
Feature wishlist is in `notes.md`. How-to for local development and deploying is in `deploy/DEPLOY.md`.

## Release plan

1. ~~**Full release: Laravel 13 upgrade**~~ ✅ deployed 2026-10-17. Contains the upgrade, tests,
   backups, maintenance page, and the `symfony/html-sanitizer` package needed later for slogans.
2. **Quick release 1: bug fixes** (only code → usual cPanel Git deploy). See *Decided fixes*.
   ✅ Done locally, 58 tests passing, ready to commit and deploy. After deploying you can delete
   `public_html/app/Http/Controllers/SplitwiseController.php` (deploys never delete files).
3. **Quick release 2: invite-only registration** (needs a migration → run it from `/admin/maintenance`).
   ✅ Done locally, 69 tests passing, rehearsed on a copy of the production database.
   Deploy: quick release, then `/admin/maintenance` → **Backup + run migrations**.
4. **Redesign** with Inertia + Vue 3 + Tailwind 4, page by page. This adds built frontend assets
   (`public/build`, not in git): decide how to ship them (commit them, or build on the server).

## On the server (after the Laravel 13 deploy)

- [x] cPanel → *Gestione MultiPHP* → PHP 8.4
- [ ] cPanel → *Processi cron* → the regular job must use the PHP 8.4 binary
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

Also done in the same release:
- The same HTML cleaning as slogans (#9) for album comments, album descriptions, post content and
  the album comment email: TinyMCE formatting kept, scripts removed. Checked on all real content:
  nothing visible changes.
- Other people's private posts are no longer listed (they only gave "forbidden" when opened).
- Posts in a category hidden from someone can't be opened by them with a direct link anymore.
- "Notify all" is now a button that sends a form (POST), so it can't be triggered just by opening a link.

## Decided: registration (quick release 2)

- **Invite links only.** On `/admin/maintenance` the admin creates an invite link, sends it
  (e.g. WhatsApp). Single use, expires after a few days. `/register` only opens with a valid link.
- No public registration, no approval queue.
- Built: optional note ("for Zecca"), valid 1/7/30 days, link shown once (the database keeps only
  a hash of the code), list of open/used/expired invites, revoke for open ones. The admin email
  about a new user says which invite was used.

## Decided: redesign

- Stack: **Inertia + Vue 3 + Tailwind 4**, migrated page by page (layout and drawer first,
  then albums, then forum).
- Look: **keep the current colors** (familiar to the friends), modernize style and add modern
  features, no complete redesign. Simple, clean, dynamic, functional.
- Replace the crowded top navbar with a **sidebar / drawer**.

## Found later (to plan)

- **Black bars in album previews (and in emails):** 60 of 202 album previews have black bars
  on the sides, baked into the image. Cause: the BulkResize link on the album form uses
  `type=exact` 800×600, which pads vertical photos. Plan (redesign phase): the site resizes and
  crops the preview itself (fill 4:3, no bars, no external tool), and auto-trim the 60 existing
  ones. Also make email images responsive (now fixed `width="800" height="600"`).

## Still open

- [ ] Maps: Google Maps (needs an API key with billing) or Leaflet + OpenStreetMap (free)?
- [ ] Database table `slogan` is empty and duplicates `slogans`: drop it?
