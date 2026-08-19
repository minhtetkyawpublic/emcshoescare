# Reusable AI prompt — deploy EMC Shoes Care to Hostinger

Copy everything below the line into a new AI/Codex conversation when I am
ready to deploy. This is a handoff prompt, not a shell script. Never put real
passwords or `.env` contents into Git or a public chat.

---

You are helping me deploy and verify my existing EMC Shoes Care application on
Hostinger shared hosting. Do not redesign or recreate the application, and do
not split the frontend and backend. Inspect the repository and work with its
current architecture before suggesting changes.

## Repository and hosting target

- GitHub repository: `https://github.com/minhtetkyawpublic/emcshoescare.git`
- Branch: `main`
- Hostinger SSH application directory:
  `/home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare`
- Expected application URL:
  `https://k2softwarestudio.com/emcshoescare/`
- Expected admin URLs:
  - `https://k2softwarestudio.com/emcshoescare/admin`
  - `https://k2softwarestudio.com/emcshoescare/admin/orders`
  - `https://k2softwarestudio.com/emcshoescare/admin/packages`
  - `https://k2softwarestudio.com/emcshoescare/admin/reports`
- Health URL:
  `https://k2softwarestudio.com/emcshoescare/api/health`

## Current architecture — preserve this

This is one unified Laravel 12 + React + Vite project:

- PHP 8.2+ and Laravel 12
- React SPA built by Vite and served by Laravel
- MySQL in hosting
- Installable PWA for Android and iOS home screens
- Customer and administrator interfaces in the same Laravel project
- API routes and SPA routes in `routes/web.php`
- React source in `resources/js/`
- Blade SPA entry in `resources/views/app.blade.php`
- production bundle in `public/build/`
- private shoe photos in `storage/app/private/order-photos/`
- no separate `frontend/`, `backend/`, `dist/`, PHP bridge, or second project

The production Vite bundle is committed to Git. Hostinger does **not** need
Node.js and should not run `npm run build` after a normal `git pull`. The build
must be created, tested, committed, and pushed from the development computer.

## Critical route portability requirement

The React application must work from the domain root or any nested server
folder. Do not introduce a hardcoded API base URL and do not make React depend
on `env.APP_URL`, `VITE_APP_URL`, the Hostinger username, domain, or
`/emcshoescare`.

The current implementation derives the runtime app/API base path from the
compiled JavaScript module URL in `resources/js/api/baseUrl.js`. Preserve it.
Laravel's `APP_URL` is still set correctly for framework/CLI URL generation,
but React requests must remain deployment-directory-aware.

The repository root contains `index.php` and `.htaccess` for shared hosting:

- requests for `build/*` and PWA assets are mapped to `public/`
- all other public requests go to Laravel's root front controller
- Laravel/API source, `.env`, `.git`, Composer files, migrations, `storage/`,
  `vendor/`, and private photos must not be directly downloadable
- SPA refreshes must work for `/admin/orders`, `/admin/packages`,
  `/admin/reports`, customer pages, and any nested hosting directory

If Hostinger allows the document root to point directly to this project's
`public/` directory, that is preferred. If it does not, use the included root
front controller and root `.htaccess`; do not copy files into a second release
tree and do not recreate the old split-project deployment script.

## Safety and scope

Before changing production:

1. Inspect `README.md`, `docs/DEPLOYMENT.md`, `.env.production.example`,
   `.htaccess`, `index.php`, `routes/web.php`, `config/database.php`,
   `config/session.php`, `config/emc.php`, and all migrations.
2. Ask me to confirm the final domain/path and whether this is a fresh install
   or an update of an existing database.
3. Back up the current database and private photo directory before migrations.
4. Never run `migrate:fresh`, `db:wipe`, destructive SQL, or delete the old
   database unless I explicitly approve it after seeing the backup location.
5. Never print or commit passwords, `.env`, cookies, customer data, or private
   photo contents.
6. Do not push, deploy, change DNS, or alter production until I explicitly say
   to proceed.

If tables already exist but Laravel says the migration table is missing, stop
and inspect the schema/backup. Do not blindly rerun the initial schema migration
or delete tables. Reconcile the existing database safely and explain the exact
plan first.

## Pre-deployment release checks on the development computer

Before pushing a release, run from the repository root:

```powershell
npm install
npm run lint
npm run build
vendor\bin\pint --test
php artisan test
git diff --check
git status --short
```

Confirm that `public/build/manifest.json` and its referenced hashed assets are
tracked and included in the commit. Do not ignore `public/build/`.

## Hostinger MySQL setup

Guide me through hPanel if the database does not exist:

1. Create a dedicated MySQL database for EMC Shoes Care.
2. Create a dedicated non-root MySQL user with a strong unique password.
3. Assign that user to the database with the required privileges.
4. Use the complete Hostinger-prefixed database/user names shown by hPanel.
5. Use Hostinger's provided database host; it is commonly `localhost`.

Do not guess credentials. Ask me to enter them directly into `.env` over SSH.

## Production `.env`

Create `.env` from `.env.production.example` only if `.env` does not already
exist. Preserve the existing `APP_KEY` on updates. A new `APP_KEY` must be
generated only for a genuinely new installation because changing it invalidates
encrypted sessions/cookies and other encrypted values.

Use this shape, replacing placeholders directly on the server:

```dotenv
APP_NAME="EMC Shoes Care Myanmar"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://k2softwarestudio.com/emcshoescare
APP_TIMEZONE=Asia/Yangon

LOG_CHANNEL=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=HOSTINGER_FULL_DATABASE_NAME
DB_USERNAME=HOSTINGER_FULL_DATABASE_USER
DB_PASSWORD=HOSTINGER_DATABASE_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=43200
SESSION_ENCRYPT=true
SESSION_COOKIE=emc_shoes_session
SESSION_PATH=/emcshoescare/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
EMC_ADMIN_REMEMBER_DAYS=30

CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

EMC_ALLOWED_ORIGINS=https://k2softwarestudio.com
EMC_UPLOAD_MAX_BYTES=5242880
EMC_ORDER_PHOTO_RETENTION_DAYS=0
```

For a different domain/folder, update `APP_URL`, `SESSION_PATH`, and
`EMC_ALLOWED_ORIGINS`. `EMC_ALLOWED_ORIGINS` contains the HTTPS origin only,
without a path. Web requests auto-detect the session cookie folder, while
`SESSION_PATH` is the CLI/config fallback.

Protect the file after editing:

```bash
chmod 600 .env
```

## Fresh Hostinger installation commands

Use non-interactive, production-safe commands. Do not place the database
password directly in the shell history.

```bash
cd /home/u608908096/domains/k2softwarestudio.com/public_html
git clone https://github.com/minhtetkyawpublic/emcshoescare.git emcshoescare
cd emcshoescare

composer install --no-dev --optimize-autoloader --no-interaction
test -f .env || cp .env.production.example .env
nano .env
php artisan key:generate
chmod 600 .env
chmod -R ug+rw storage bootstrap/cache
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --seed --force
read -rp "Admin username: " EMC_ADMIN_USER
read -srp "Admin password: " EMC_ADMIN_PASSWORD; echo
read -rp "Admin display name: " EMC_ADMIN_NAME
export EMC_ADMIN_USER EMC_ADMIN_PASSWORD EMC_ADMIN_NAME
php artisan emc:create-admin
unset EMC_ADMIN_USER EMC_ADMIN_PASSWORD EMC_ADMIN_NAME
php artisan optimize
```

The hidden `read -s` entry keeps the password out of shell history. Use a
10–72 character strong unique password. Do not use example passwords in
production and do not expose the password in chat. The command can also
reset/update the existing single administrator safely.

Current migrations include the unified schema, single-language package fields,
complete pickup-fee removal, and indexed admin order/report queries. Run all
pending migrations; do not manually recreate those columns or tables.

## Updating an existing deployment

First back up MySQL and `storage/app/private/order-photos/`. Then:

```bash
cd /home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare
git status --short
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
chmod 600 .env
chmod -R ug+rw storage bootstrap/cache
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --force
php artisan optimize
```

If `git status` shows server-side modifications, do not discard them. Identify
whether they are secrets, uploads, generated caches, or accidental source edits
and ask before resolving the conflict.

## Database and application verification

After installation/update, verify:

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan config:show database
tail -n 100 storage/logs/laravel.log
```

Do not paste `config:show` or logs if they reveal secrets or customer data.

Then test over HTTPS:

- `/api/health` returns a successful JSON response and MySQL is reachable
- `/` loads the customer SPA
- `/admin`, `/admin/orders`, `/admin/packages`, and `/admin/reports` load and
  survive a browser refresh
- admin can sign in and **Remember me** persists for up to 30 days
- packages load from MySQL
- a customer can register, submit an order, and upload shoe photos
- admin can filter/paginate orders, view photos in the in-app slider, update
  status without a required note, and view reports
- customer sees status history
- PWA manifest/service worker/icons load under the nested folder
- Android installation and iOS Add to Home Screen work over HTTPS

Verify protected paths. None may return file contents or HTTP 200:

```bash
for path in .env composer.json artisan app/Models/Customer.php database/migrations/2026_08_14_000000_create_emc_schema.php storage/logs/laravel.log .git/config; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "https://k2softwarestudio.com/emcshoescare/$path")
  echo "$path -> $code"
done
```

A 403 response is expected. A safe 404 is acceptable; HTTP 200 is a release
blocker. Also confirm that `storage/app/private/order-photos/` is not directly
browsable and that authenticated photo responses use `Cache-Control: no-store,
private`.

## Reliability and operations

- Keep `storage/` and `bootstrap/cache/` writable by the PHP process, but do not
  use world-writable `777` unless Hostinger support proves it is required.
- Keep `.env` private and never commit it.
- Keep private uploads outside `public/`; do not run `storage:link` for order
  photos.
- Configure regular off-site MySQL and private-photo backups and test a restore.
- Monitor HTTPS expiry, `/api/health`, disk space, MySQL, Laravel logs, and
  backup completion.
- If a photo-retention policy is approved, first test
  `php artisan emc:purge-order-photos --days=NUMBER` in its dry-run/default-safe
  mode according to `docs/OPERATIONS.md`, then schedule it through Hostinger
  cron. Do not enable deletion without the approved retention period.
- Use `php artisan emc:create-admin` to reset lost admin access; do not edit
  password hashes manually.

## How to collaborate with me

Lead with the exact next command and explain what successful output should look
like. Work one safe stage at a time: inspect → back up → configure → install →
migrate → optimize → verify. If a command fails, diagnose that failure before
giving unrelated commands. Keep a short record of commands run, migrations
applied, URLs tested, backup locations, and any remaining release blockers.

---

End of reusable prompt.
