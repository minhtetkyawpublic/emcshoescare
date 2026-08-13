# EMC production deployment

This guide deploys the React build with the plain PHP API and MySQL. Use a staging site first. The public site and API must share the same HTTPS origin.

## 1. Hosting requirements

- Apache 2.4 with `mod_rewrite` and `mod_headers`
- PHP 8.2 or newer with PDO MySQL, mbstring, fileinfo, and OpenSSL
- MySQL 8 or MariaDB 10.6 or newer with `utf8mb4`
- HTTPS certificate with automatic renewal
- A scheduled-task facility for backups and photo retention
- A private, encrypted backup destination outside the web root

Node.js is only required to build the React files; it is not required on the live server.

## 2. Database

Create a dedicated database and migration account, then import every file in `database/migrations` in numeric order. Create a separate runtime account with only the permissions the application needs:

```sql
CREATE USER 'emc_app'@'localhost' IDENTIFIED BY 'replace-with-a-long-random-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON emc_shoes_care.* TO 'emc_app'@'localhost';
FLUSH PRIVILEGES;
```

Do not run the application as MySQL `root`. Keep schema-changing permissions on the migration account only.

## 3. Build and upload

Run `npm ci`, `npm run lint`, and `npm run build` from a clean checkout. The public document root should contain:

```text
index.html
assets/
manifest.webmanifest
sw.js and PWA image/offline assets
.htaccess
api/
storage/.htaccess
storage/order-photos/
```

Copy the contents of `dist`, the repository root `.htaccess`, `api`, and `storage`. Do not publish `src`, `database`, `.git`, `.env.example`, tests, documentation, backups, or package files. Give the web-server account write permission only to `storage/order-photos`; application code should be read-only.

## 4. Server environment

Set these outside the document root in the Apache virtual host or hosting control panel:

```text
EMC_APP_ENV=production
EMC_APP_KEY=<at least 32 random characters>
EMC_DB_HOST=127.0.0.1
EMC_DB_PORT=3306
EMC_DB_NAME=emc_shoes_care
EMC_DB_USER=emc_app
EMC_DB_PASS=<database password>
EMC_ALLOWED_ORIGINS=https://your-real-domain.example
EMC_COOKIE_NAME=emc_session
EMC_ADMIN_COOKIE_NAME=emc_admin_session
EMC_COOKIE_PATH=/
EMC_SESSION_DAYS=30
EMC_UPLOAD_MAX_BYTES=5242880
EMC_ORDER_PHOTO_RETENTION_DAYS=0
```

For an app installed in a subdirectory, set `EMC_COOKIE_PATH` to that path with a trailing slash. Keep photo retention at `0` until EMC approves its privacy policy and retention period. Production startup intentionally fails for a weak app key, a root/passwordless database account, or an HTTP allowed origin.

Set PHP `upload_max_filesize` to at least `6M`, `post_max_size` to at least `55M`, and `max_file_uploads` to at least `10`. The application itself accepts no more than ten compressed files of 5 MB each.

## 5. HTTPS and headers

Redirect all HTTP traffic to HTTPS. Once HTTPS is confirmed, add this in the TLS virtual host:

```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

Only add `includeSubDomains` when every subdomain supports HTTPS. The included `.htaccess` already supplies CSP, clickjacking, MIME-sniffing, referrer, permissions, SPA-routing, and service-worker cache headers.

## 6. Administrator and smoke check

Create the single administrator from the command line with `api/cli/create-admin.php`. Do not paste the password into tickets or source control.

On staging, confirm:

1. `/api/health` returns API version 5 or newer.
2. Customer registration, remembered login, order creation, and private photo viewing work.
3. The administrator can update every valid pickup and drop-off status with bilingual notes.
4. Another customer cannot access the order or photo URL.
5. The manifest and service worker load with correct MIME types over HTTPS.
6. Installation works from Android Chrome and iOS Safari.

Use `RELEASE_CHECKLIST.md` for the final approval record.

## 7. Rollback

Before every release, take a verified database/photo backup and retain the previous application build. To roll back, place the site in maintenance mode, restore the matching database and photo snapshot when the schema changed, restore the previous build/API, clear only the `emc-` service-worker caches through a versioned worker, and run the smoke check again.
