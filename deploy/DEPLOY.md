# theboise.it: local development and deployment

## Local development

Everything (PHP 8.4, MySQL 8.0, Mailpit) runs in Docker containers with Laravel Sail,
so the only thing the PC needs is Docker.

Run the commands from a normal system terminal in the project folder (on Windows: the Ubuntu
terminal). The terminal inside the Flatpak version of VS Code is sandboxed and can't see Docker.

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

### Frontend (Vue pages, `resources/js` and `resources/css`)

The Vue pages are compiled by Vite into `public/build`. While working on them:

```sh
./vendor/bin/sail npm run dev     # keep it running: every change shows up instantly at http://localhost
```

Before committing, **build** and commit the result: the server can't build it (shared hosting has
no Node.js), it uses exactly the files in `public/build` from git.

```sh
./vendor/bin/sail npm run build
git add public/build
```

You don't have to remember it: a git hook (`.githooks/pre-commit`) refuses a commit that changes
frontend files without `public/build`, and prints the command above.

Committing while `npm run dev` is running is fine: the file it creates (`public/hot`) is ignored
by git, so it never reaches the server.

### Refresh the data from production

1. phpMyAdmin on cPanel → select the database → *Export* → save the `.sql` file
2. Replace the local database with it:

```sh
docker compose exec -T mysql mysql -uroot -ppassword -e "DROP DATABASE theboise; CREATE DATABASE theboise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON theboise.* TO 'sail'@'%'"
docker compose exec -T mysql mysql -usail -ppassword theboise < ~/Downloads/bptheboi_theboise.sql
```

The dump has emails and password hashes: never put it inside the project folder or in git.

### Setting up a new PC

See the [README](../README.md) (Linux and Windows).


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

1. If the frontend changed: `./vendor/bin/sail npm run build`. Commit (with `public/build`) and
   push to `main`.
2. `/admin/maintenance` → **Backup database now**.
3. cPanel → *Git Version Control* → *Pull or Deploy* → **Update from Remote** → **Deploy HEAD Commit**.
4. `/admin/maintenance` → **Clear caches**. If there are pending migrations: **Backup + run migrations**.
5. Open the pages you changed.

To undo a quick release: `git revert` the commit, push, and deploy again.

### Full release (packages changed)

Pick a quiet moment: from step 3 to step 6 the site is down for a few minutes.

1. If the frontend changed: `./vendor/bin/sail npm run build`. Commit (with `public/build`) and
   push to `main`.
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

### If the site crashes right after a deploy ("Table ... doesn't exist")

The new code needs a migration that hasn't run yet, and the maintenance page can't be reached.
Run the migrations with a one-time cron job (it makes a database backup first):

1. cPanel → *Processi cron* → add a **new** job, every minute (`* * * * *`):
   ```
   cd /home/bptheboi/public_html && mkdir /home/bptheboi/migrate-ran 2>/dev/null && { /usr/local/bin/ea-php84 artisan backup:database; /usr/local/bin/ea-php84 artisan migrate --force; } > /home/bptheboi/migrate.log 2>&1
   ```
2. After 1–2 minutes, `/home/bptheboi/migrate.log` must show the backup and the migrations `DONE`.
3. Delete the cron job and the `migrate-ran` folder.

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
