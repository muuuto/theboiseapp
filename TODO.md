# TODO

Plan, decisions and things to do by hand (cPanel, the server).
Feature wishlist is in `notes.md`. How-to for local development and deploying is in `deploy/DEPLOY.md`.

## Release plan

1. ~~**Full release: Laravel 13 upgrade**~~ ✅ deployed 2026-10-17. Contains the upgrade, tests,
   backups, maintenance page, and the `symfony/html-sanitizer` package needed later for slogans.
2. ~~**Quick release 1: bug fixes**~~ ✅ deployed 2026-10-18 (commit `6e798fe`). See *Decided fixes*.
   - [ ] On the server you can delete `public_html/app/Http/Controllers/SplitwiseController.php`
         (deploys never delete files)
3. **Quick release 2: invite-only registration** (commit `c1457f9`), needs a migration.
   - [ ] Push, quick release, then `/admin/maintenance` → **Backup + run migrations**
4. **Redesign**, see *Decided: redesign* below for the steps.

## On the server (after the Laravel 13 deploy)

- [x] cPanel → *Gestione MultiPHP* → PHP 8.4
- [x] cPanel → *Processi cron* → the regular job must use the PHP 8.4 binary
      (`/usr/local/bin/ea-php84 /home/bptheboi/public_html/artisan schedule:run >> /dev/null 2>&1`)
- [x] The next morning: check that `/admin/maintenance` lists a new backup made at 03:00
      (if not, the cron is still using the old PHP)
- [ ] `/admin/maintenance`: check that all PHP extensions are green
- [x] Add to the server `.env` to get a backup by email every Sunday:
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

- Stack: **Inertia + Vue 3 + Tailwind 4** built with Vite. Laravel keeps routes, controllers and
  permission checks, pages become Vue components. Old Blade pages and new Vue pages live side by
  side: one section per release, the site always works.
- Look: **keep the current colors** (red `#ef3b2d`, teal, forum green, light slate background),
  modernize style: rounded cards, soft shadows, more spacing, mobile-first. Simple, clean,
  dynamic, functional. **Dark mode** saved per user (`users.dark_mode` already exists), following
  the device setting by default.
- Layout: **sidebar** on desktop, **drawer** on phones (Albums, Forum, Memories/New, Account, admin
  group). Slim top bar (logo, search, notifications, avatar). The bottom bar's buttons move into
  each page's header.
- **Built files are committed:** `npm run build` before committing, `public/build` goes in git, so
  the usual Git deploy keeps working (shared hosting has no Node.js). ⚠️ Reminder: forgetting the
  build = the server shows the old frontend. To be documented in `deploy/DEPLOY.md` in step 1.
- **Maps: Leaflet + OpenStreetMap** (free, no API key). The current `location` text stays as the
  label on the album card (it's free text, not always a real place). Albums get separate
  coordinates of the real place, set with a map picker in the album form. For the existing 194
  albums: suggest coordinates from the location text, the admin confirms or fixes them.
  Idea: a page with all albums on one map.
- **Editor: Tiptap** instead of TinyMCE (TinyMCE cloud needs an API key and has a monthly limit).
  It must keep showing the existing formatted content.
- **Demo account** (now hard-coded as user id 28 "Guest", for recruiters reading the CV) becomes a
  proper demo mode (`is_demo` flag instead of id 28):
  - logs in with a **secret demo link** for the CV (e.g. `/demo/<secret>`, secret in `.env`,
    changing it disables old links), no password
  - **forum hidden entirely** (menu and pages), no slogans, neutral page title and texts
  - sees only the albums it is a member of (as today)
  - **read-only**: no comments, no likes, no account changes
  - never receives emails (reminders, anniversaries, notifications)

Steps (one release each):

1. **Foundation**: Inertia + Vue + Tailwind setup, new layout with sidebar/drawer, dark mode,
   flash messages, demo mode; first Vue page: the album home. Document the build in `DEPLOY.md`.
   Adds a PHP package (Inertia) → full release.
   ✅ Deployed 2026-10-18 (commit `662e136`), with quick release 2 (invites).
   After deploying:
   - [ ] **Full release** (Composer cron job), then **Backup + run migrations**
   - [ ] Server `.env`: add `DEMO_LOGIN_SECRET=` followed by a long random secret, then put
         `https://theboise.it/demo/<that secret>` in the CV (changing the secret disables old links)
   - [ ] Optional: delete on the server the files removed from the repo:
         `resources/views/listings/index.blade.php`, `resources/views/components/listing-card.blade.php`,
         `resources/js/bootstrap.js`
2. **Albums**: album page, comments with **likes** and **emoji picker**, previews cropped by the
   site (fixes the black bars, trim the 60 existing), **maps**, responsive email images.
   Built: album page (YouTube players, small map, people), comments as plain text + emoji picker,
   likes, album form with Tiptap editor, people picker, tags with suggestions, map picker with place
   search, covers resized by the site keeping their shape, a **Map** page for every user (clusters),
   admin review page for the guessed locations (farthest first, confirm all).
   The "strips" in emails were transparent padding from BulkResize (not black bars): 125 previews.
   Committed as `99a001c`. Added after that commit (deploy together with step 3):
   - **Likes in the notification bell** ("Zecca liked your comment", never emailed)
   - **Several places per album** (e.g. "Londra e Dublino": two pins). The coordinates moved
     to their own table `listing_places`; suggestions: 186 albums, 195 places
   - padding trimmed on new uploads too, emoji button in the editor
3. **Forum**: categories, posts, post page, Tiptap editor, likes and emojis on comments.
   ✅ Done locally, 119 tests passing. Forum pages in Vue (cards with edit/delete instead of the
   "manage" pages), post form with privacy options and attachments removable one by one, comments
   with emojis and likes, photo viewer. Fixed: WebP uploads crashed (logos, covers, attachments).
   Removed: the " XD" added to every forum comment.

   Step 2 was already deployed (bars removed, locations imported, review started): the new
   version keeps the review (migration moves each album's location to `listing_places`).

   **To deploy the rest** (quick release, no new PHP packages):
   - [ ] Push, then cPanel Git deploy
   - [ ] `/admin/maintenance` → **Backup + run migrations** (notifications, places)
   - [ ] `/admin/maintenance` → **Import suggested album locations** again: it only adds the extra
         places (Dublin next to London, Passo Gardena next to Carezza...: 7 albums), never brings
         back removed guesses. Rehearsed on a copy of the production state.
   - [ ] **Review album locations**: remove the new wrong guess "Cologna" → Köln (top of the list),
         add by hand the places of "Passo Pennes & Lago di Braies & Seit" (it had none)
   - [ ] Check an email with an album preview (new album / reminder) in your mail app
   - [ ] On the server you can delete the old Blade views removed from the repo (deploys never
         delete files): `resources/views/forum/`, `resources/views/partials/`,
         `resources/views/listings/show|create|edit.blade.php`, `components/posts-card`,
         `categories-card`, `forum-tags`, `listing-tags`, `googleAutocomplete`. Harmless if left.
4. **The rest**: account, login/password pages, invite page, admin pages.
   ✅ Done locally, 132 tests passing: every page is Vue now, the old Blade layout is gone (with the
   Tailwind CDN, Font Awesome, Alpine and TinyMCE scripts). New: "Remember me" on login, birthday
   and change password on the account page, readable password-reset messages. Fixed: WebP profile
   pictures crashed. The Zecca page stays public. `/listings/manage` redirects to the albums.
   Also includes the fix for the crash before migrations (the bell checks its table exists).

   **To deploy** (quick release, no new PHP packages, no migrations):
   - [ ] Push, then cPanel Git deploy, then `/admin/maintenance` → **Clear caches**
   - [ ] Try: logout and login (with "Remember me"), the account page (set your birthday),
         the maintenance page, an invite link in a private window
   - [ ] Optional: delete on the server the old Blade views removed from the repo:
         `resources/views/account`, `monitor`, `maintenance`, `slogan`, `users`, `listings`,
         `components` (everything in `resources/views` except `app.blade.php` and `mails`)
5. **Per-person album link and description**: an album has a general photos link and a general
   description, used for everyone, except the people for whom a specific link and/or description
   is set. ✅ Done locally, 137 tests passing. In the album form: "Different version for someone".
   Nobody can tell their version is different (nothing in the page says so); only admins see
   "N people see a different link or description". The demo account is back in the album people
   list (it was missing since step 2), so recruiters can get their own description too.
   **To deploy** (quick release, one migration):
   - [ ] Push, cPanel Git deploy, then `/admin/maintenance` → **Backup + run migrations**
     (until then album pages just show the general link and description)
6. **Nicer emails**: ✅ Done locally, 147 tests passing. All 12 emails (password reset included)
   share one design: teal header with the logo, white card, album cards with preview, dates,
   place and a clear button, readable on phones and in dark mode. Same texts and humor. Album
   previews are sent as JPEG (Outlook can't show WebP) without the old transparent bars, through
   a signed link. Links use the site's address instead of a hard-coded `https://theboise.it`.
   `/admin/maintenance` → **Test emails**: pick an email and it's sent only to you, with your
   name and your albums (also `php artisan mails:preview you@example.com` sends all of them).
   The empty `slogan` table is dropped by a migration (only if still empty).
   **To deploy** (quick release, one migration):
   - [x] Check that the server `.env` has `APP_URL=https://theboise.it` (the scheduled emails
         build their links and images from it)
   - [ ] Push, cPanel Git deploy, then `/admin/maintenance` → **Backup + run migrations**
   - [ ] `/admin/maintenance` → **Test emails**: send yourself a few (New album, On this day,
         Happy birthday) and look at them in Gmail on the phone and on the computer
   ⚠️ Today the emails only link to the album page on the site (which shows each person their own
   version). If an email ever includes the photos link or the description directly, it must use
   the recipient's personal version when there is one (`listing_user.custom_album_link` /
   `custom_description`).
7. **Splitwise**: ✅ Done locally, 172 tests passing. *Expenses* in the sidebar (not for the demo
   account). Only a group's members see it, admins included. Whoever creates a group (and admins)
   edits its name, album and members; people with expenses or payments can't be removed; a group
   can be deleted only once everyone is settled. Any member adds expenses; who added or paid an
   expense (and the group creator) can edit or delete it. The album page shows a link to its group.
   Emails: everyone with a share in a new expense (except who added it), and the other person of a
   payment. Both are in **Test emails** on the maintenance page.
   **To deploy** (quick release, one migration):
   - [ ] Push, cPanel Git deploy, then `/admin/maintenance` → **Backup + run migrations**
     (until then the Expenses page gives an error; albums and the rest work)
   - [ ] Try it with a small real group (e.g. you and one friend) before the next trip
   Decided:
   - **groups** (e.g. "Sicilia 2026", "Casa") with their members, optionally linked to an album
   - expenses split **equally**, by **exact amounts** or by **shares** (2 nights vs 3 nights)
   - **euro only**
   - **simplified debts**: the fewest payments to settle everyone ("Zecca pays Anna €12")
   - **settle up payments**: record "I paid Anna €20" anytime (also with a "Paid ✓" button on each
     simplified debt), so balances go back to zero
   - **email notifications**: when someone adds an expense that includes you (who paid, total,
     your share) and when someone records a payment to you. In the new email design
   - no receipt photos (not wanted for now)
8. **Other people can post albums**: ✅ Done locally, 183 tests passing. **Off until you switch it
   on**: `/admin/maintenance` → *Members posting albums* → the site switch, plus a switch for each
   person (kept even while the site switch is off).
   - Allowed people get "Post album" in the menu, tag anyone (not the demo account), and edit the
     albums they posted. They're always in their own albums. Only admins delete albums and set the
     per-person versions (posters never see that section, and an edit by them keeps the versions).
   - Without the permission (or with the site switch off) they can't edit their albums any more;
     the albums stay.
   - "Posted by Zecca" on every album page. New album emails say who posted it (the poster gets
     none); admins not in the album get a heads-up email. Admins can open any album (read only,
     "Admin view", no comments) to check it.
   - **History** (admins only): created, edited (each field before → after), people added/removed,
     deleted, permission given/removed, site switch on/off. On the album page and on the
     maintenance page.
   - **"Only albums I posted"** toggle on the album list and the map, remembered on the account,
     shown to whoever posted at least one album. Memories and reminder emails still include everything.
   **To deploy** (quick release, one migration):
   - [ ] Push, cPanel Git deploy, then `/admin/maintenance` → **Backup + run migrations**
     (before it, everything works as today; rehearsed locally)
   - [ ] When you decide: switch it on and allow one person to try it
9. **QR codes and forum order**: ✅ Done locally, 152 tests passing.
   - **QR code** button on every album and forum post (everyone): the code has the logo in the
     middle and the title under it, to download, share (phones) or copy the link. Scanning opens
     the page (after the login if needed); people still need an account that can see it.
   - **Reorder** button for admins on the forum and in each category: drag the cards by the
     handle (or the arrows), then Save. Everyone sees that order. Until the first reorder nothing
     changes (categories A-Z, posts newest first); after it, new categories/posts appear at the
     top so you can place them.
   **To deploy** (quick release, one migration):
   - [ ] Push, cPanel Git deploy, then `/admin/maintenance` → **Backup + run migrations**
     (until then the forum works as today, without the Reorder button)

10. **The app on the phone and phone notifications**: ✅ Deployed (full release), tested on Matteo's phone.
   - "Add to Home Screen" makes TheBoise an app: its own TB icon (redrawn sharp from the logo),
     full screen, a "No connection" page when offline. Account page → *App & notifications*:
     install button (Android, computers) or the 3 steps for iPhone (Safari → Share → Add to Home
     Screen; notifications on iPhone only inside the app, iOS 16.4+).
   - Notifications on the phone for: new albums you're in, comments on them, forum replies and
     shared posts, likes on your comments, shared expenses, "On this day" memories. Each person
     chooses phone and/or email for each (account page); without a choice: emails as before and
     phone notifications on the devices where they turned them on. Short texts (no comment text:
     they can show on a locked screen). Logging out stops them on that device. Never the demo
     account. "Send a test" on the account page.
   - The keys that sign the notifications are made on the server the first time and kept in the
     database: nothing to add to `.env`.
   - Checked: delivery and encryption end to end with a stand-in push service, the notification
     shown and opened by the app in a real browser, the offline page. Only the last step (Apple's
     and Google's real services) can be tried on a real phone.
   **To deploy: FULL release** (new package `minishlink/web-push`, see `deploy/DEPLOY.md` →
   *Full release*). Before starting:
   - [ ] If `public_html/vendor_old` from the last full release is still there, delete it first
   - [ ] If the `/home/bptheboi/composer-ran` folder is still there, delete it (or the install
     job won't run)
   - [x] Then the full release steps; at step 7 check **curl (phone notifications)** is green, and
     **Backup + run migrations**
   - [ ] After a few days without problems: delete `public_html/vendor_old`
   - [x] On your phone: open theboise.it, add it to the home screen, open it from the icon,
     Account → *Turn on notifications* → *Send a test*
12. **TheBoise Wrapped**: ✅ Done locally. `/wrapped`: your year as story slides (albums, days
    away, countries with flags, km as the crow flies from Bolzano and back, farthest place,
    longest trip, busiest month, your crew, your most liked comment), ending with a card to save
    as an image or share. The current year opens on 1 December (admins anytime), past years
    always. 27 December 10:00: email + phone notification to everyone with albums that year;
    banner on the home page in December and January. Not for people not in an album for a year.
13. **Upcoming trips**: ✅ Done locally. *Trips* in the menu (not the demo account): a trip with
    who's going, fixed dates or a date poll (everyone ticks what works, anyone suggests other
    dates, the organiser chooses), a checklist (who brings what), shared expenses in one click,
    a live countdown, notifications (invited, dates decided, one week and one day before). After
    the start, who can post albums gets "Make it an album": the form comes filled in, and the
    album, the trip and its expenses stay linked.
14. **Automatic notifications skip inactive people** (in no album and not on the site in the last
    18 months; admins always active. "Last login" now also counts visits of who stays logged in,
    once a day): "On this day" memories, unseen album reminders, birthday email,
    "Notify all" forum posts, Wrapped. Direct ones (tagged in an album, comments, likes,
    expenses, trips) still reach them.
    **To deploy 12–14** (quick release, one migration): push, cPanel Git deploy, then
    `/admin/maintenance` → **Backup + run migrations**. Try `/wrapped/2025` and plan a test trip.
16. **Home page decorations**: ✅ Done locally, 216 tests passing. Things falling on the home
    page: snow at Christmas, 🎃☠️⚰️👻🦇 at Halloween, 🏳️‍🌈 for Pride Month, and New Year, Befana,
    Valentine's, Carnival and Easter (moving dates calculated), Ferragosto, Oktoberfest (its real
    dates); Women's Day, St Patrick's, Liberation Day, Festa della Repubblica off by default.
    Birthdays first (🥳🎂🎉🎁 and "Today is Anna's birthday!", only people still around, never for
    the demo account). Two at once: the shorter wins. A badge in the banner ("Halloween in 9 days")
    with ✕ to hide it. `/admin/maintenance` → *Decorations*: on/off, birthdays, each holiday on/off
    and weeks before, Preview links. No motion for who asked their device for less.
    **To deploy** (quick release, no migration): push, cPanel Git deploy, **Clear caches**.
    Halloween is already within its 2 weeks: it shows right away.
17. **"All albums | My albums" default and position**: ✅ Done locally. `/admin/maintenance` → *Members posting
    albums* → "Only albums I posted on by default" (for who hasn't chosen; who flips the switch
    keeps their choice), and **Allow everyone** / **No one** buttons above the list of people.
    On the album list and the map the choice ("All albums | My albums", was "Only mine") sits next to the
    title instead of its own row. **To deploy** (quick release, one migration): push, cPanel Git
    deploy, **Backup + run migrations**.
18. **Italian**: ✅ Done locally, 225 tests passing. English stays the default; *English | Italiano*
    on the login pages and on Account → *Preferences*, saved on the account (the registration
    page's choice becomes the new person's). Everything is translated (961 texts in
    `lang/it.json`, plus `lang/it/` for form errors, login and password reset): pages, messages,
    emails and phone notifications in each recipient's own language, dates and money the Italian
    way. People's own writing (albums, comments, posts) stays as written. A test fails if a text
    has no Italian. To add a text: write it in English with `t('...')` in the pages or `__('...')`
    on the server, and its Italian in `lang/it.json`.
19. **Account → Preferences**: theme, language, and "Album list and map: All albums | My albums"
    (for who posted albums; without a choice: all albums). That's the default: the switch on the
    album list and the map only changes the current view (searching keeps it), a reload goes back
    to the default. The default switch on the maintenance page is gone.
20. **Pinned post on the home page**: admins open a forum post → *Pin on the home page* (one at a
    time, *Unpin from home* to remove it). Everyone who can see the post gets a slim "📌 title →"
    link at the top of the home page, with ✕ to close it (until another post is pinned). Never
    for the demo account. Perfect for the announcement.
    **To deploy 17–20** (quick release, two migrations): push, cPanel Git deploy, then
    `/admin/maintenance` → **Backup + run migrations**. Then post the announcement and pin it.
21. **Gym tracker**: ✅ Done locally, 235 tests passing. Only for the people the admins switch on in
    `/admin/maintenance` → *Gym tracker* (never the demo account). *Gym* in the menu: workouts
    (date, notes, exercises with sets of kg × reps, saved as you type, "last time" hints, 🏆 on a
    new record), progress per exercise (chart, heaviest, estimated 1-rep max, most reps, history),
    and a records board (private unless each person shares). Ready list of 48 exercises by muscle
    group with start/end photos (public domain, `public/images/exercises/CREDITS.md`), English and
    Italian; anyone can add their own, with a photo. **To deploy** (quick release, one migration):
    push, cPanel Git deploy, then `/admin/maintenance` → **Backup + run migrations**, then switch
    people on in *Gym tracker*.
22. **Emoji reactions on albums and forum posts**: ✅ Done locally, 239 tests passing. Under the
    album title and under the post: ❤️ 😂 😮 😢 🔥 👏 one tap away, any other emoji from the picker.
    One each: another emoji changes it, the same one again takes it back. Tap the names to see who
    reacted with what. The poster / post author gets it in the bell and on the phone (setting
    "Likes on your comments, reactions to your albums and posts"); changing the emoji before they
    look updates the same notification, taking it back removes it. People in the album only (admins
    who just look can't), anyone who can see the post; never the demo account. **To deploy**
    (quick release, one migration): push, cPanel Git deploy, `/admin/maintenance` → **Backup + run
    migrations**.
23. **Hometown**: ✅ Done locally, 240 tests passing. Account → Preferences → *Hometown*: search a
    town (OpenStreetMap, like the album places) and pick it; *Back to Bolzano* resets it. Wrapped
    counts the kilometres (home → places → home) and the farthest place from there, and the slide
    says "from <hometown> and back". Without one: Bolzano, as before. Never the demo account.
    **To deploy** (quick release, one migration): push, cPanel Git deploy, **Backup + run migrations**.
24. **Trip documents, foreign currency, passkeys, altitude in Wrapped, @mentions**: ✅ Done
    locally, 259 tests passing, tried in a real browser (phone and computer, before and after the
    migrations).
    - **Trip documents**: a *Documents* card on the trip: PDFs and photos of tickets and bookings,
      optionally someone's ("Yours" / "For Anna", who's told). Private files in
      `storage/app/trip-documents` (never in `public`), opened only by the people going; deleted
      with the trip.
    - **Foreign currency**: a currency next to the amount; converted at the ECB rate of the
      expense's day (frankfurter.app, cached) or at what the card charged ("Charged in euro").
      Keeps "£45.00" next to the euro; balances in euro as before.
    - **Passkeys** (Face ID / fingerprint): turned on from the phone in Account → under *Change
      password*; the login page shows *Sign in with Face ID / fingerprint* only on phones and
      tablets (computers keep the password, as Matteo prefers). Password always works.
      Package `lbuchs/webauthn` (tiny, no dependencies).
    - **Altitude**: album places get their altitude from Open-Meteo when saved (nightly job
      `albums:import-elevations` at 04:00 fills the rest); Wrapped shows *The highest you got*
      from 500 m up.
    - **@mentions** in album and forum comments: "@" suggests who can see it, bold in the comment;
      they get "Muto mentioned you" in the bell and on the phone (new notification setting
      *Mentions*).
    **To deploy: FULL release** (new package `lbuchs/webauthn`, see `deploy/DEPLOY.md` → *Full
    release*), then **Backup + run migrations** (4 migrations), then *One-time album jobs* →
    **Look up the altitude of album places** (fills the old albums right away instead of
    waiting for the night). Then try Face ID on your phone: Account → *Turn on for this phone*.
25. **Replies to comments** (albums and forum): ✅ Done locally, 261 tests passing. *Reply*
    under any comment opens a box there; replies show indented under it, oldest first, one level
    deep (a reply to a reply joins the same thread and starts with "@Name"). Who's answered gets
    "Muto replied to your comment" (bell and phone; setting renamed *Replies to your comments and
    mentions (@you)*). **To deploy** (quick release, one migration): push, cPanel Git deploy,
    **Backup + run migrations**.
26. **Album tags library + albums without a photos link**: ✅ Done locally, 266 tests passing.
    - Every tag once, one spelling (`tags` table, made from the tags the albums already use).
      The album form picks from it; other spellings (aliases, e.g. "Matte") become the tag; a new
      name is checked first ("did you mean Matteo, Matt?") and, if confirmed, added marked *new*.
      A tag can be linked to an account: choosing the person adds the tag and vice versa.
    - `/admin/tags` (Admin menu → *Tags*): rename (every album changes), merge ("Sarà" into
      "Sara"), other spellings, account, *New tags to check*, *The same person?* suggestions
      (with *Not the same*). Forum tags are unchanged (still free).
    - An album needs the photos link, a video link, or both ("Photos link" label now generic:
      Google Photos, Drive, Amazon Photos…). No photos link: the album page hides its buttons.
    **To deploy** (quick release, one migration): push, cPanel Git deploy, **Backup + run
    migrations**. Then open *Tags*: merge the duplicates (at least Sarà → Sara) and press *Not
    the same* on the pairs that are different people (Matteo · Matt…); link the tags of people
    with an account.
27. **Tags A to Z, one tag per person**: ✅ Done locally, 267 tests passing. Tags are shown and
    saved A to Z everywhere (album cards and page, album form, admin Tags, forum posts and
    categories; capitals and accents don't count). An album needs at least as many tags as people
    in it (the demo account and the automatic Youtube don't count); the form shows "5 people in
    the album: at least 5 tags (3 so far)". Old albums with fewer tags must get them when edited.
    Quick release, no migration.
28. **Albums of the same trip**: ✅ Done locally. Several people post photos of the same trip:
    in **Create / Edit album** → *Same trip*, pick another album you're in (the ones with dates
    near this album's first; search by title); it's linked on saving. Editing a linked album shows
    its links with *Unlink* and *Link one more album*. The album page shows *Same trip* (only the
    linked albums each viewer is in). Linking to an album already linked joins its group.
    **To deploy** (quick release, one migration): push, cPanel Git deploy, **Backup + run
    migrations**.
29. **Events, polls, weather, calendar link, people pages and badges**: ✅ Done locally, 273 tests
    passing, tried on a phone before and after the migration.
    - **Events** live with the trips (menu *Trips & events*), shown apart and lighter: trips first,
      then *Events*. An event is one day with an optional time (or a vote on days), *Who's invited*
      (with *Everyone*), RSVP (*I'm in / Maybe / Can't come*), a countdown to the time, the list,
      documents and shared expenses; never an album. Reminder the day before (not to who said no).
    - **Polls** on trips and events: any question, options to tick, the bars show the votes.
    - **Weather**: trips and events within two weeks show the forecast for their *Where*
      (Open-Meteo); the day-before reminder says "☀️ 24° / 15°".
    - **Calendar link**: Account → *Your calendar*: Google Calendar / iPhone / Outlook subscribe to
      trips, events (not the ones said no to) and everyone's birthdays; new link / turn off.
    - **People** (menu): everyone, and each person's page: albums, countries (flags), badges with
      progress, and "You and Anna" (albums together, since, countries, map, the albums). Names on
      the album page open them. 12 badges (Explorer, Globetrotter, Mountaineer, Veteran…, *Never
      skips leg day* only on your own page or if you share your gym records). Wrapped has a slide
      with the badges unlocked that year (only when there are some).
    **To deploy** (quick release, one migration): push, cPanel Git deploy, **Backup + run
    migrations**.
30. **Fixes and the workout clock**: ✅ Done locally, 275 tests passing. Gym kg/sets/reps boxes
    readable in dark mode (they turned white when typing); the notifications panel closes when
    you go to another page (phones); snowflakes with a thin dark outline in light mode; workouts
    record their start (New workout) and end (Done) time, editable, with "47 min so far" while
    it's going (Italian time, also abroad) and the duration in the list. **To deploy** (quick
    release, one migration): push, cPanel Git deploy, **Backup + run migrations**.
15. **Announcement post** (done, written in the chat): write for Matteo an announcement of all these changes, to
    publish as a new post in the forum (what's new, in the site's tone: redesign, maps, likes,
    emails, QR codes, expenses, the app and notifications...).

## Found later (to plan)

- **Black bars in album previews (and in emails):** 60 of 202 album previews have black bars
  on the sides, baked into the image. Cause: the BulkResize link on the album form uses
  `type=exact` 800×600, which pads vertical photos. Plan (redesign phase): the site resizes and
  crops the preview itself (fill 4:3, no bars, no external tool), and auto-trim the 60 existing
  ones. Also make email images responsive (now fixed `width="800" height="600"`).

- **Gym → Garmin Connect** (asked by Matteo, not decided): a "Download for Garmin" button on a
  finished workout making a .fit file (strength activity with the sets, reps and weights, the
  start time and duration), imported by hand on connect.garmin.com → Import data. Automatic sync
  would need Garmin's developer program (for companies): not realistic.

## Still open

- [x] Database table `slogan` is empty and duplicates `slogans`: dropped (migration, only if empty)
- [x] The `/zeccaGay` page stays public (decided)
