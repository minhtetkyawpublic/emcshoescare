# Unified application verification

## Automated release suite

`scripts/release-check.ps1` verifies:

- React lint and the Laravel Vite production build
- npm and Composer production dependency audits
- Laravel Pint, PHP syntax, route loading, feature tests, and production optimization
- the unified `/api/*` route group and React SPA fallback
- root, nested-folder, API, asset, and service-worker URL derivation
- English/Myanmar translation parity
- PWA manifest identity, service worker privacy rules, and icon dimensions
- authenticated photo `no-store` behavior
- required Laravel/React files and absence of `backend`, `dist`, bridge, and old release files
- a deterministic, tracked `public/build` manifest and bundle
- shared-hosting front-controller protection and Git whitespace checks

## Laravel feature workflow

`php artisan test` covers the SPA document, unknown API JSON behavior, health,
CSRF bootstrapping, customer registration, package retrieval, private-photo
orders, idempotent retries, administrator login, guarded status transitions,
unread state, and authenticated photo delivery.

## MySQL end-to-end workflow

`tests/e2e-local.ps1` exercises the full application against MySQL and the real
database session driver: package/settings management, pickup and drop-off
orders, ten-photo upload, duplicate retry, timelines, access control, logout,
and cleanup.

## Manual gates

Android, iOS, tablet, desktop, actual business content, privacy wording,
production backups, and staff acceptance remain documented in
`docs/ACCEPTANCE_TEST.md` and `RELEASE_CHECKLIST.md`.
