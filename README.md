# EMC Shoes Care Myanmar

[![Quality checks](https://github.com/minhtetkyawpublic/emcshoescare/actions/workflows/quality.yml/badge.svg)](https://github.com/minhtetkyawpublic/emcshoescare/actions/workflows/quality.yml)

Mobile-first React Progressive Web App with a Laravel 12 API and MySQL. Customers can register, choose an administrator-managed service package, submit pickup or drop-off orders with up to ten shoe photos, and follow the repair timeline. A separate administrator interface manages packages, fees, orders, notes, and guarded status transitions.

Current packaged release: `1.0.0-rc.2`. Final `v1.0.0` remains gated by real shop content, production setup, and physical-device/staff acceptance.

## Architecture

- `src/`, `public/`: React/Vite installable PWA
- `backend/`: private Laravel application, Artisan migrations, commands, tests, and photo storage
- `dist/`: committed, Node-free public package for Hostinger
- `scripts/deploy-release.php`: deploys `dist` and connects its protected API bridge to the private Laravel checkout

The Laravel checkout, `.env`, Composer dependencies, migrations, and uploaded photos stay outside `public_html`. Only React assets and `dist/api/index.php` are public.

## Local setup

```bash
npm install
composer install --working-dir=backend
```

Copy `backend/.env.example` to `backend/.env`, configure MySQL, then run:

```bash
php backend/artisan key:generate
php backend/artisan migrate --seed
php backend/artisan emc:create-admin emcadmin "choose-a-strong-password" "EMC Administrator"
php backend/artisan serve
npm run dev
```

The frontend runs at `http://127.0.0.1:5173` and its Vite `/api` proxy targets Laravel at `http://127.0.0.1:8000`.

Photos are stored under `backend/storage/app/private/order-photos` and are returned only through an authenticated, non-cacheable Laravel endpoint. Order retries use a customer-bound UUID so an uncertain upload cannot create a duplicate order.

## Hostinger release

Node.js is not required on Hostinger. Composer is required because the Laravel runtime remains private in the SSH checkout:

```bash
git pull --ff-only origin main
composer install --working-dir=backend --no-dev --optimize-autoloader --no-interaction
php backend/artisan migrate --force
php backend/artisan optimize
php scripts/deploy-release.php /absolute/path/to/public_html/emcshoescare
```

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for first installation, the exact Hostinger `.env`, database creation, permissions, tests, updates, and rollback.

## Verification

```bash
npm run lint
npm run build
composer validate --working-dir=backend --strict
cd backend && php artisan test
```

On Windows, `scripts/release-check.ps1` runs the complete frontend, Laravel, PWA, security, dependency, packaging, and nested-folder deployment audit. `tests/e2e-local.ps1` exercises the full Laravel/MySQL workflow against a non-production database. GitHub Actions repeats both suites on every push.
