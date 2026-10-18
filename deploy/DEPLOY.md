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
The code is deployed with cPanel *Git Version Control*: it copies the repository into
`public_html` (see `.cpanel.yml`).

`vendor/` (the PHP packages) is not in git and **can't be uploaded**: the server's malware scanner
rejects any archive of PHP libraries (false positives on normal Laravel/Symfony code). So when
packages change, the server installs them itself with Composer, run by a one-time cron job.

Always commit and push to `main` first.

### Which kind of release?

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

Pick a quiet moment: from step 3 to step 6 the site is down for a few minutes.

1. Commit and push to `main`.
2. Back up:
   - `/admin/maintenance` → **Backup database now** → download the file
   - File Manager → select `public_html` → *Compress* → download the archive (optional, but it
     makes a rollback trivial)
3. File Manager, inside `public_html`: rename `vendor` to `vendor_old` (quick rollback).
4. cPanel → *Git Version Control* → *Pull or Deploy* → **Update from Remote** → **Deploy HEAD Commit**.
5. Install the packages: cPanel → *Processi cron* → add a **new** job, every minute (`* * * * *`):
   ```
   cd /home/bptheboi && mkdir composer-ran 2>/dev/null && { curl -sSL -o composer.phar https://getcomposer.org/download/latest-stable/composer.phar; cd public_html && /usr/local/bin/ea-php84 -d memory_limit=1G ../composer.phar install --no-dev --optimize-autoloader --no-interaction; } > /home/bptheboi/composer-install.log 2>&1
   ```
   It runs only once (the `composer-ran` folder blocks the next runs). After 3–4 minutes open
   `/home/bptheboi/composer-install.log`: it must end with *"Generating optimized autoload files"*
   and the list of discovered packages. If it shows an error (e.g. a missing PHP extension), fix
   that, delete the `composer-ran` folder and it runs again the next minute.
6. **Delete the cron job** you added and the `composer-ran` folder.
7. `/admin/maintenance`: versions as expected, all checks green, **Clear caches**, and if there
   are pending migrations **Backup + run migrations**. Then open an album, a forum post and your
   account page.
8. After a few days without problems, delete `vendor_old`.

#### Rollback

1. Delete `vendor` and rename `vendor_old` to `vendor`.
2. Delete the `.php` files in `bootstrap/cache/`.
3. Deploy the previous commit: `git revert` the release commit(s), push, *Update from Remote* →
   *Deploy HEAD Commit* (or extract the `public_html` archive from step 2).
4. Only if data got damaged: restore the database backup with phpMyAdmin → Import.

### Good to know

- **Never extract an archive over `public_html`** that contains a top-level `./` entry: it can
  change the permissions of `public_html` itself and the whole site answers "403 Forbidden".
  `public_html` must be **750**.
- **Deleted files stay on the server**: the Git deploy copies files but never removes any. If you
  delete a file that matters (a migration, a route file...), delete it in File Manager too.
- **The Git deploy copies the root `.htaccess`**, including the PHP version block that MultiPHP
  writes. The repo says PHP 8.4: if you ever change the version in MultiPHP, update that block
  in the repo too, or the next deploy switches the site back.
- **Cron jobs must use** `/usr/local/bin/ea-php84`: `/usr/local/bin/php` is a different
  (older) PHP version.
