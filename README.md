# theboise.it

Laravel 13 + Inertia/Vue 3 site. Locally everything (PHP 8.4, MySQL 8.0, Mailpit) runs in Docker
with Laravel Sail, so a machine only needs Docker and git.

- Daily use, useful commands, frontend build, deploying: [deploy/DEPLOY.md](deploy/DEPLOY.md)
- Setting up a new machine: below. Do the steps for your OS, then [Common steps](#common-steps).

## Linux

1. Install Docker Engine and the compose plugin
   ([docs](https://docs.docker.com/engine/install/)), then let your user run it without `sudo`
   and log out and back in:
   ```sh
   sudo usermod -aG docker $USER
   ```
2. Clone the repository:
   ```sh
   git clone https://github.com/muuuto/theboiseapp.git ~/theboiseapp
   cd ~/theboiseapp
   ```

Run the commands from a normal system terminal: the terminal inside the Flatpak version of
VS Code is sandboxed and can't see Docker.

## Windows

Windows runs the project inside Ubuntu with WSL2, so after this setup it works exactly like on
Linux. **Keep the project inside Ubuntu** (`~/theboiseapp`), not in a Windows folder such as
`C:\Users\...`: through `/mnt/c` Docker is very slow and `npm run dev` doesn't see file changes.

1. Install [Git for Windows](https://git-scm.com/download/win) (it brings the credential manager
   used below) and VS Code with the **WSL** extension (`ms-vscode-remote.remote-wsl`).
2. In PowerShell as administrator, install WSL with Ubuntu, then reboot:
   ```powershell
   wsl --install -d Ubuntu
   ```
   Open *Ubuntu* from the Start menu and choose a username and password when asked.
   If Ubuntu was already installed and opens as `root`, create the user yourself (in Ubuntu, as root):
   ```sh
   useradd -m -u 1000 -s /bin/bash -G sudo matteo && passwd matteo
   printf '\n[user]\ndefault=matteo\n' >> /etc/wsl.conf
   ```
   then in PowerShell `wsl --terminate Ubuntu` and open Ubuntu again. Check with `id`: it must
   say `uid=1000`.
3. Make sure systemd is on (needed for Docker to start by itself): `/etc/wsl.conf` must contain
   ```
   [boot]
   systemd=true
   ```
   (new installs already have it; if you add it, run `wsl --shutdown` in PowerShell).
4. In Ubuntu, install Docker Engine (no Docker Desktop needed), then close and reopen Ubuntu:
   ```sh
   sudo apt-get update && sudo apt-get install -y ca-certificates curl
   sudo install -m 0755 -d /etc/apt/keyrings
   sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
   echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" | sudo tee /etc/apt/sources.list.d/docker.list
   sudo apt-get update && sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
   sudo usermod -aG docker $USER
   ```
5. In Ubuntu, set up git (it reuses the GitHub login saved in Windows) and clone:
   ```sh
   git config --global user.name "Your Name"
   git config --global user.email "you@example.com"
   git config --global credential.helper "/mnt/c/Program\ Files/Git/mingw64/bin/git-credential-manager.exe"
   git clone https://github.com/muuuto/theboiseapp.git ~/theboiseapp
   cd ~/theboiseapp
   code .    # opens VS Code connected to Ubuntu
   ```

Good to know on Windows:

- Run every command (`sail`, `git`, `npm`) in the Ubuntu terminal, for example the one inside
  VS Code once it's connected to WSL (bottom-left corner says *WSL: Ubuntu*).
- Open the site from the Windows browser as usual: `localhost` is forwarded to Ubuntu.
- In Explorer the project is at `\\wsl.localhost\Ubuntu\home\<user>\theboiseapp`, and Windows
  files are under `/mnt/c/...` from Ubuntu (e.g. the dump in
  `/mnt/c/Users/<you>/Downloads/bptheboi_theboise.sql`).
- When no Ubuntu window is open, WSL shuts down after a short while and the containers with it:
  just run `./vendor/bin/sail up -d` again.

## Common steps

From the project folder (`~/theboiseapp`):

1. Install the PHP packages with a temporary container:
   ```sh
   docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install
   ```
2. `cp .env.example .env` and change these values:
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
   Optional, to try the demo account locally: `DEMO_LOGIN_SECRET=local-demo-secret`, then open
   http://localhost/demo/local-demo-secret
3. Start everything, create the app key, install the frontend packages and turn on the build
   reminder hook:
   ```sh
   ./vendor/bin/sail up -d --build
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail npm install
   git config core.hooksPath .githooks
   ```
   Tip: add `alias sail='./vendor/bin/sail'` to `~/.bashrc` to type `sail` instead of `./vendor/bin/sail`.
4. Import the database: see
   [Refresh the data from production](deploy/DEPLOY.md#refresh-the-data-from-production).
5. Uploaded images: in cPanel File Manager compress `public_html/storage/app/public`, download it
   and extract its content into `storage/app/public`. Then create the links that serve them
   (the second one makes the `/storage/app/public/...` URLs used by the views work locally):
   ```sh
   ln -s ../storage/app/public public/storage
   mkdir -p storage/app/public/app && ln -s .. storage/app/public/app/public
   ```
6. Check: http://localhost opens the site, http://localhost:8025 opens Mailpit, and
   `./vendor/bin/sail artisan test` passes.
