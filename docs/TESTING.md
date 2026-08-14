# Laravel release verification evidence

Last local verification: 2026-08-14 on Windows/XAMPP with PHP 8.2, Composer 2, Apache, and MySQL.

## Automated checks

The release suite verifies:

- ESLint and the Vite production build
- npm and Composer production dependency audits
- strict Composer metadata validation
- Laravel route loading, PHP syntax, Pint formatting, and feature tests
- exact English/Myanmar translation-key parity
- PWA identity, offline behavior, and icon dimensions
- exclusion of API and mutation traffic from service-worker caches
- authenticated private-photo `no-store` responses
- no Laravel `.env`, runtime path, Composer vendor tree, migrations, or photos in `dist`
- byte parity for public Apache rules and the Laravel bridge
- deployment into a multi-level folder with the private runtime path generated correctly
- deterministic, committed `dist` output and Git whitespace checks

## Laravel feature workflow

`php artisan test` passes 32 assertions covering health, CSRF bootstrapping, customer registration, package retrieval, a private-photo order, duplicate-request replay, administrator login, order retrieval, every pickup status through Done, and authenticated photo delivery.

## Full MySQL workflow

`tests/e2e-local.ps1` passed against a fresh isolated MySQL database and Laravel's real database-session driver. It proved:

- administrator login and remembered customer registration
- package create/edit/archive and pickup-fee update/restoration
- pickup and drop-off orders, including the ten-photo maximum
- duplicate upload retry returns the original order
- all pickup and drop-off status transitions with timeline notes
- unread status and mark-as-read behavior
- administrator/owner photo access with `no-store`
- 404 denial for another customer attempting order or photo access
- logout and cleanup of temporary database rows and private files

The isolated audit database and local server were removed after verification.

## Arbitrary-folder Apache deployment

The committed release was deployed at a deep local path containing an `api-project` parent. Customer home, `/admin`, API health, unauthenticated session/CSRF bootstrap, hashed assets, and PWA files worked. `/admin/` returned a correct URL-only 308 redirect, and direct access to `api/runtime.php` returned 403. The generated runtime file pointed to the private `backend` checkout rather than exposing framework code under the web root.

## Human gates

Android, iOS, tablet, desktop, real shop content, privacy wording, production backups, and staff acceptance remain recorded as manual launch gates in `RELEASE_CHECKLIST.md` and `docs/ACCEPTANCE_TEST.md`.
