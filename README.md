# EMC Shoes Care Myanmar

[![Quality checks](https://github.com/minhtetkyawpublic/emcshoescare/actions/workflows/quality.yml/badge.svg)](https://github.com/minhtetkyawpublic/emcshoescare/actions/workflows/quality.yml)

Mobile-first React Progressive Web App for EMC Shoes Care Myanmar. The planned production stack is React, plain PHP, and MySQL.

Phase 1 contains the bilingual public landing page. Phase 2 adds phone/password customer accounts. Phase 3 adds the admin dashboard, database-managed packages, optional pickup fees, real orders, private photo storage, and customer order history. Phase 4 adds guarded status transitions, bilingual admin notes, customer timelines, and unread in-app updates. Phase 5 completes the installable PWA experience, safe offline fallback, recoverable uploads, and production hardening. The backend remains plain PHP and MySQL. See [ROADMAP.md](./ROADMAP.md) for the full delivery plan.

Phase 6 release preparation is tracked in [RELEASE_CHECKLIST.md](./RELEASE_CHECKLIST.md). Production setup, backup/retention operations, and staff/customer instructions are in `docs/`.
The real shop details, packages, and privacy wording can be supplied with `docs/CONTENT_WORKSHEET.md` before the final release.

## Run locally

```bash
npm install
npm run dev
```

The Vite development server forwards `/api` requests to the local XAMPP Apache server at `http://127.0.0.1/emcshoecare/api`.

## Phase 2 database setup

Import the SQL files in `database/migrations` in numeric order using phpMyAdmin or the XAMPP MySQL client. Migration `001` creates the dedicated `emc_shoes_care` database and customer accounts; migration `002` adds the administrator, packages, settings, orders, and private photo metadata; migration `003` adds status history and customer read-state tracking; migration `004` adds retry-safe order request identifiers. The migrations can be run more than once safely.

Local XAMPP defaults work without a configuration file (`root` with an empty password). For a different setup, copy the array shape from `api/config.php` into an ignored `api/config.local.php`, or set the variables documented in `.env.example` at the web-server level. The PHP API deliberately does not parse `.env` files or commit secrets.

For production, HTTPS is required and `EMC_APP_KEY` must be replaced with a unique random value of at least 32 characters. Database credentials and `EMC_ALLOWED_ORIGINS` must also be configured for the production host.

## Administrator setup

Create or reset the single administrator from the command line. The password must contain at least 10 characters:

```bash
D:\xampp\php\php.exe api\cli\create-admin.php emcadmin "choose-a-strong-password" "EMC Administrator"
```

Run the frontend and open `http://127.0.0.1:5173/admin`. The customer site remains at `http://127.0.0.1:5173/`.

Order photos are written under `storage/order-photos` with random filenames. Apache is explicitly denied direct access by `storage/.htaccess`; authenticated customers and the administrator receive photos through guarded PHP endpoints. The web-server user needs write permission only for `storage/order-photos`.

For a production build, place the contents of `dist` at the web-app document root alongside the root `.htaccess`, `api`, and `storage`. Keep the source, `database` directory, and database credentials outside public download access.

## Install and interrupted uploads

Build and serve the app over HTTPS. Android users can use the in-app **Install app** action or their browser menu. On iPhone/iPad, open EMC in Safari, tap **Share**, then **Add to Home Screen**. The final 192 px, 512 px, maskable, and Apple touch icons are generated from `emcicon.jpg`.

If an order upload is interrupted, its form details and compressed shoe photos remain only in that browser's IndexedDB until the customer successfully retries or discards the draft. The stable request identifier also lets the PHP API return the original order instead of creating a duplicate after an uncertain network response. Account data and live order responses are never stored by the service worker.

The included Apache rules provide SPA routing, disable directory listings, deny direct database-file access, and set restrictive browser security headers. Production still requires HTTPS, private secrets, database backups, and a reviewed retention policy before launch.

## Verify a production build

```bash
npm run lint
npm run build
```

On Windows/XAMPP, `scripts/release-check.ps1` repeats lint, build, PHP syntax, translation parity, PWA icon/manifest, private-cache, documentation, and whitespace checks.

With local XAMPP Apache/MySQL running and the local administrator configured, the complete API workflow can be repeated with:

```powershell
.\tests\e2e-local.ps1 -AdminPassword '<local-admin-password>'
```

The test restores the pickup fee and removes its temporary customers, order, package, sessions, and photo. Never run this local cleanup test against production.

The interface text for both English and Myanmar is kept in `src/i18n/translations.js`. Phase 1 package names and demonstration prices are also defined there for easy replacement.
