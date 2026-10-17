# theboise.it: local development and deployment

## Local development

Everything (PHP 8.4, MySQL 8.0, Mailpit) runs in Docker containers with Laravel Sail,
so the only thing the PC needs is Docker.

Run the commands from a normal system terminal in the project folder. The terminal inside the
Flatpak version of VS Code is sandboxed and can't see Docker.

Tip: add `alias sail='./vendor/bin/sail'` to `~/.bashrc` to type `sail` instead of `./vendor/bin/sail`.

### Every day

```sh
./vendor/bin/sail up -d      # start (the first time after a reboot it takes a few seconds)
./vendor/bin/sail down       # stop
```

| What | Where |
| --- | --- |
| Site | http://localhost (log in with your usual password) |
| Mailpit (every email the app sends) | http://localhost:8025 |
| Database (e.g. DBeaver) | `127.0.0.1` port `3307`, user `sail`, password `password`, database `theboise` |

Locally **no email ever reaches real people**: they all end up in Mailpit.

Useful commands:

```sh
./vendor/bin/sail artisan test                    # run all the tests
./vendor/bin/sail artisan notifications:birthday  # run a command (emails go to Mailpit)
./vendor/bin/sail artisan tinker                  # PHP console inside the app
./vendor/bin/sail composer require some/package   # add a package
./vendor/bin/sail artisan optimize:clear          # if something looks cached
```

### Refresh the data from production

1. phpMyAdmin on cPanel → select the database → *Export* → save the `.sql` file
2. Replace the local database with it:

```sh
docker compose exec -T mysql mysql -uroot -ppassword -e "DROP DATABASE theboise; CREATE DATABASE theboise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON theboise.* TO 'sail'@'%'"
docker compose exec -T mysql mysql -usail -ppassword theboise < ~/Downloads/bptheboi_theboise.sql
```

The dump has emails and password hashes: never put it inside the project folder or in git.

### Setting up a new PC (already done on this one)

1. Install Docker and clone the repository.
2. Install the PHP packages with a temporary container:
   ```sh
   docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install
   ```
3. `cp .env.example .env` and change these values:
   ```
   APP_URL=http://localhost
   FORCE_HTTPS=false
   DB_HOST=mysql
   DB_DATABASE=theboise
   DB_USERNAME=sail
   DB_PASSWORD=password
   MAIL_MAILER=smtp
   MAIL_HOST=mailpit
   MAIL_PORT=1025
   FORWARD_DB_PORT=3307
   WWWUSER=1000
   WWWGROUP=1000
   ```
4. Start everything and create the app key:
   ```sh
   ./vendor/bin/sail up -d --build
   ./vendor/bin/sail artisan key:generate
   ```
5. Import the database (see *Refresh the data from production*).
6. Uploaded images: in cPanel File Manager compress `public_html/storage/app/public`, download it
   and extract its content into `storage/app/public`. Then create the links that serve them
   (the second one makes the `/storage/app/public/...` URLs used by the views work locally):
   ```sh
   ln -s ../storage/app/public public/storage
   mkdir -p storage/app/public/app && ln -s .. storage/app/public/app/public
   ```


## Deploying

The site runs on Serverplan shared hosting (cPanel, no SSH) from `/home/bptheboi/public_html`.

Always commit and push to `main` first, so what is live is exactly a commit.

### Which kind of release?

`vendor/` (the PHP packages) is not in git: it stays on the server from the last full release.

- **Did you change packages** since the last deploy (`composer require / update / remove`,
  so `composer.lock` changed)? → [Full release](#full-release-packages-changed)
- **Only your code changed?** → [Quick release](#quick-release-only-code-changed)

### Quick release (only code changed)

1. Commit and push to `main`.
2. `/admin/maintenance` → **Backup database now**.
3. cPanel → *Git Version Control* → *Pull or Deploy* → **Update from Remote** → **Deploy HEAD Commit**.
4. `/admin/maintenance` → **Clear caches**. If there are pending migrations: **Backup + run migrations**.
5. Open the pages you changed.

To undo a quick release: `git revert` the commit, push, and deploy again.

### Full release (packages changed)

Needed for the Laravel 13 upgrade. Git deploy alone would put the new code next to the old
`vendor/` and break the site, so here the code and `vendor/` are uploaded together as an archive.

Pick a quiet moment: between steps 3 and 6 the site is down for a few minutes.

#### 1. Build the release (on your PC)

```sh
./deploy/build-release.sh           # packages the last commit (HEAD)
./deploy/build-release.sh 1a2b3c4   # or a specific commit
```

Only committed files are packaged: uncommitted work in progress is never included.

This creates `release/theboise-<date>-<commit>.tar.gz`. It contains the code and the production `vendor/`.
It never contains `.env`, uploaded photos, logs, backups or the root `.htaccess`
(cPanel MultiPHP manages the PHP version written in that file).

#### 2. Back up (on the server)

- **Database:** if the site already has the maintenance page, open `/admin/maintenance` →
  *Backup database now* and download the file. Otherwise export it from phpMyAdmin.
- **Files:** in File Manager select `public_html` → *Compress* → download the archive.

Don't continue until both are safely on your PC.

#### 3. PHP version (first deploy of Laravel 13 only)

cPanel → *Gestione MultiPHP* → set the domain to **PHP 8.4**.

#### 4. Prepare the folder

In File Manager, inside `public_html`:

- rename `vendor` to `vendor_old` (that's your quick rollback)
- in `bootstrap/cache/` delete every `.php` file (old caches can crash the new version)
- **first deploy of Laravel 13 only:** in `database/migrations/` delete all the `.php` files
  (the old migrations, replaced by `database/schema/mysql-schema.sql`; keep the folder)

#### 5. Upload and extract

Upload the `.tar.gz` into `public_html`, right click → *Extract* (overwrite existing files),
then delete the `.tar.gz`.

#### 6. Check

1. Open the site and log in.
2. Go to `/admin/maintenance` (also linked from *Monitor*):
   - PHP and Laravel versions are the expected ones
   - all checks are green; if one is red, enable that extension in cPanel's PHP settings
   - press **Clear caches**
   - if there are pending migrations: **Backup + run migrations**
3. Open an album, a forum post and your account page.

#### 7. Cron (first deploy of Laravel 13 only)

cPanel → *Processi cron* → edit the existing job so it uses PHP 8.4, keeping it every minute (`* * * * *`):

```
/usr/local/bin/ea-php84 /home/bptheboi/public_html/artisan schedule:run >> /dev/null 2>&1
```

If that path doesn't exist on the server, ask Serverplan for the PHP 8.4 CLI path.
The old `/usr/local/bin/php` may still be PHP 8.1, and then emails and backups silently stop.

To check it works: the next morning `/admin/maintenance` must list a new backup made at 03:00.

Optional: add `BACKUP_MAIL_TO=your@email` to the server `.env` to receive a backup every Sunday.

#### 8. Clean up

- After a few days without problems, delete `vendor_old`.
- cPanel → *Git Version Control* → **Update from Remote** (only that, don't deploy), so the
  next quick release starts from this commit.

#### Rollback

The code and `vendor` must always come from the same version, so restore both:

1. *Gestione MultiPHP* → back to the previous PHP version (if you changed it).
2. Delete `vendor` and rename `vendor_old` to `vendor`.
3. Delete the `.php` files in `bootstrap/cache/`.
4. Upload the archive downloaded in step 2 and extract it over `public_html` (this puts the old code back).
5. Only if data got damaged: restore the database backup with phpMyAdmin → Import.

### Good to know

- **Deleted files stay on the server** with both methods: neither the Git deploy nor extracting
  an archive removes anything. If you delete a file that matters (a migration, a route file...),
  delete it in File Manager too.
- **The Git deploy copies the root `.htaccess`**, including the PHP version block that MultiPHP
  writes. The repo says PHP 8.4: if you ever change the version in MultiPHP, update that block
  in the repo too, or the next Git deploy switches the site back.
