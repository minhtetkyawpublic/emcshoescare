# EMC Hostinger shared-hosting deployment

This release supports a domain root or any nested folder, for example `https://example.com/emc`, without rebuilding. Keep the Git checkout outside `public_html`; deploy the committed `dist` package into the chosen public folder after each SSH pull.

## 1. Hostinger requirements

- PHP 8.2 or newer with PDO MySQL, mbstring, fileinfo, and OpenSSL
- MySQL 8 or compatible MariaDB with `utf8mb4`
- Apache `.htaccess` support (`mod_rewrite` and `mod_headers`)
- HTTPS enabled for the domain
- SSH access for `git pull`, migrations, administrator setup, and deployment
- Cron access for backups and approved photo retention

Node.js is not required on the hosting account. The production React files and PHP deployment package are committed under `dist` and verified by GitHub Actions.

## 2. Clone privately and choose a public folder

In SSH, use `pwd` to confirm your account paths. Clone the source somewhere outside the web root:

```bash
cd ~
git clone https://github.com/minhtetkyawpublic/emcshoescare.git
cd emcshoescare
git pull --ff-only origin main
```

Run `php -v` in SSH and select Hostinger's PHP 8.2+ CLI binary if the default differs from the website's configured version.

Choose the actual Hostinger public path shown for the website in hPanel. These are examples only:

```text
/home/account/domains/example.com/public_html
/home/account/domains/example.com/public_html/emc
/home/account/domains/example.com/public_html/projects/shoe-care
```

Deploy to that exact folder:

```bash
php scripts/deploy-release.php /absolute/path/to/public_html/emc
```

The command creates missing subfolders and copies the complete `dist` package. It never deletes the target’s `api/config.local.php` or `storage/order-photos` contents. The package includes subfolder-safe relative assets, PHP API, protected migrations, PWA files, and Apache rules.

Do not point the web server at the repository root: it contains source and development files. Only the selected deployment target should be public.

## 3. Create the Hostinger database

Create a MySQL database and database user in hPanel, assign the user to that database, and retain the displayed database host/name/user/password. The migrations intentionally do not issue `CREATE DATABASE` or `USE`, because shared-host database names are controlled by hPanel.

In the deployed public folder, create the ignored local configuration:

```bash
cd /absolute/path/to/public_html/emc
cp api/config.production.example.php api/config.local.php
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
nano api/config.local.php
```

Put the generated key, final HTTPS origin, and hPanel database values in `config.local.php`. `allowed_origins` contains only scheme and host—never the installation folder:

```php
'allowed_origins' => ['https://example.com'],
```

The cookie path is detected automatically from the deployed folder. Set `cookie_path` explicitly only if Hostinger uses an unusual proxy mapping; valid examples are `/`, `/emc/`, or `/projects/shoe-care/`.

Protect the configuration as mode `600` where the hosting setup permits it:

```bash
chmod 600 api/config.local.php
chmod 750 storage/order-photos
```

The included Apache rules deny configuration, CLI, migration, source, and private-photo paths over HTTP.

## 4. Run database migrations

Preview and apply the idempotent migrations over SSH:

```bash
php api/cli/migrate.php --dry-run
php api/cli/migrate.php
php api/cli/migrate.php --status
```

The runner obtains a database lock, applies only pending numeric migrations, and records each completed version in `schema_migrations`. Running it again is safe and prints `Database is up to date.` A failed migration exits nonzero; do not continue deployment until its cause is corrected.

Create or reset the one administrator after migrations:

```bash
php api/cli/create-admin.php emcadmin 'replace-with-a-long-unique-password' 'EMC Administrator'
```

Shell history can retain command arguments. A safer option is to set `EMC_ADMIN_USER`, `EMC_ADMIN_PASSWORD`, and `EMC_ADMIN_NAME` temporarily, run the command without arguments, and then unset them.

## 5. PHP and upload settings

Configure the site’s PHP version and limits in hPanel or the supported per-directory PHP configuration:

```text
upload_max_filesize = 6M
post_max_size = 55M
max_file_uploads = 10
memory_limit = 256M
max_execution_time = 120
```

The frontend compresses photos before upload. The API independently accepts one to ten valid JPG, PNG, or WebP images, each no larger than the configured 5 MB limit.

## 6. First production smoke test

Replace the example origin/folder below with the real URL:

1. Open `https://example.com/emc/api/health`; it must return an EMC API success response.
2. Open the customer page and `/emc/admin`; both must load without missing assets.
3. Confirm `/emc/admin/` canonicalizes to `/emc/admin`.
4. Confirm `database/migrations/...`, `api/config.local.php`, `api/cli/migrate.php`, and `storage/order-photos` cannot be downloaded.
5. Register, close/reopen the browser with **Remember me**, and confirm the session persists.
6. Submit pickup and drop-off orders with photos and complete the administrator status workflow.
7. Install from Android Chrome and iOS Safari and confirm the icon/name are EMC.

Record results in `docs/ACCEPTANCE_TEST.md` and `RELEASE_CHECKLIST.md`.

## 7. Updating from GitHub

Take a verified database/photo backup before every update, then run from the private checkout:

```bash
git status --short
git pull --ff-only origin main
php scripts/deploy-release.php /absolute/path/to/public_html/emc
cd /absolute/path/to/public_html/emc
php api/cli/migrate.php --dry-run
php api/cli/migrate.php
php api/cli/migrate.php --status
```

The deployment command does not delete old hashed assets. They are harmless and allow safe rollback; periodically remove only assets proven unused by the current and retained rollback builds.

## 8. Backups, cron, and rollback

Configure the commands in `docs/OPERATIONS.md` with absolute Hostinger paths. Store encrypted backups outside `public_html` and preferably off-account. Keep photo cleanup disabled until EMC approves a retention period.

For rollback, place the site in maintenance mode, restore the previous public package, and restore the matching database/photo backup if the schema or data changed. Then repeat the smoke test. Never roll database structure backward by manually deleting tables or migration records.
