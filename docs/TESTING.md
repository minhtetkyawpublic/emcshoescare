# Phase 6 verification evidence

Last local verification: 2026-08-14 on the project XAMPP environment.

## Automated release checks

`scripts/release-check.ps1` passed:

- ESLint including JSX accessibility rules
- Vite production build (about 297 KB JavaScript / 87 KB gzip and 52 KB CSS / 11 KB gzip)
- syntax validation for every PHP file
- exact English/Myanmar translation-key parity (252 keys per language at the time of review)
- PWA identity, manifest, regular/maskable/Apple icon dimensions
- service-worker exclusion of API and non-GET traffic
- authenticated private-photo responses use `no-store`
- arbitrary nested-folder cookie/API routing and root-relative asset rejection
- exact source-to-`dist` API/migration/Apache-rule parity
- production rejection of shipped app-key, database, and origin placeholders
- required migration and release-document presence
- Git whitespace validation

`npm audit --omit=dev` reported zero known vulnerabilities during this Phase 6 verification.

The committed `1.0.0-rc.1` shared-host package was deployed into a multi-level local Apache path. The customer page, `/admin`, API health, hashed assets, and PWA manifest returned successfully; `/admin/` canonicalized with HTTP 308; configuration, CLI, packaged migration, release metadata, and private-photo paths returned 403/404. A second deployment preserved an existing `api/config.local.php` and uploaded-photo sentinel byte-for-byte.

The CLI migration runner was also exercised against a new isolated database from both the source checkout and packaged `dist` tree. Dry-run listed four pending versions, migrate created 11 tables/three seed packages and recorded all four versions, status showed each applied, and a repeated migrate reported the database up to date.

## Full local API workflow

`tests/e2e-local.ps1` passed and proved:

- single-admin login and remembered customer registration without OTP
- session reload returns the same customer ID and a persistent remember cookie
- package create, edit, and archive
- optional pickup-fee update and restoration
- pickup and customer drop-off orders with private photos and correct fixed-price totals
- the pickup order accepts and preserves the maximum of ten photos
- same request ID replay returns the same order rather than a duplicate
- administrator and owning customer can view private photos without browser caching
- a different signed-in customer receives 404 for both the order and its photo
- all pickup statuses from Submitted through Done and the shorter drop-off path, with English/Myanmar note fields
- eight-entry pickup and six-entry drop-off timelines, unread notifications, and mark-as-read behavior
- logout and complete removal of test data/files

After the run, customers, orders, photos, histories, customer sessions, and admin sessions were all zero; the one real administrator and three seed packages remained.

The same database-backed workflow is now a required GitHub Actions job. It starts a clean MySQL 8.4 service and PHP 8.2 API on an Ubuntu runner, previews/applies/rechecks the CLI migrations, creates an ephemeral administrator, and runs the portable PowerShell test. This complements the separate Windows static-release job and catches platform-specific PHP/MySQL regressions on every push and pull request.

## Backup and retention

The backup command created a MySQL/photo ZIP and matching SHA-256 file. The checksum passed. Its SQL was restored into a new isolated database; the restored result contained 11 tables, four recorded migrations, one administrator, and three packages. The UTF-8 bytes of a Myanmar package name matched the source database exactly. The scratch database and test archive were then removed.

The retention command was tested first in dry-run mode and then execute mode against one isolated 40-day-old Done order. It selected one order/one photo/18,219 bytes, deleted only the photo and its metadata, removed the empty private directory, and retained the order. The test order/customer were then removed.

## Production guards and HTTP security

- Insecure production configuration exits before startup when the app key is weak.
- A strong key, HTTPS origin, non-root user, and non-empty database password pass configuration validation.
- Apache returns CSP and restrictive browser headers, routes `/admin` to the SPA, denies direct database/package access, and marks the service worker `no-store`.
- API responses use `no-store, private`.

## Evidence still requiring people/devices

No controllable browser was available in the workspace during Phase 5 or this Phase 6 pass. Responsive CSS and accessibility behavior were reviewed statically, and dialogs now trap keyboard focus and restore it on close, but this is not a substitute for the Android, iOS, tablet, and desktop acceptance checks listed in `RELEASE_CHECKLIST.md`. Real shop details and Myanmar wording also require EMC approval.
