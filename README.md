# EMC Shoes Care Myanmar

A single Laravel 12 application with a React SPA, Vite build pipeline, MySQL,
and an installable PWA. Customer ordering, administrator management, API
controllers, migrations, React source, and production assets live in one
project tree.

## Stack

- Laravel 12 / PHP 8.2+
- React with Vite and `laravel-vite-plugin`
- MySQL in production; SQLite for fast feature tests
- Installable PWA with offline shell and update handling

## Structure

- `app/`, `config/`, `database/`, `routes/`: Laravel application
- `resources/js/`: React customer and administrator SPA
- `resources/views/app.blade.php`: Laravel/Vite SPA document
- `public/build/`: committed Vite production bundle
- `public/`: PWA manifest, service worker, icons, and Laravel front controller
- `storage/app/private/order-photos/`: protected customer uploads
- `index.php` and `.htaccess`: safe shared-hosting entry from any nested folder

There is no separate frontend project, `backend/` application, `dist/` release,
or PHP bridge. Laravel serves both `/api/*` and the React SPA.

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

In another terminal:

```bash
npm run dev
```

Open the Laravel URL. Vite provides React hot reload; API requests stay on the
Laravel origin under `/api`.

## Production build and checks

```bash
npm run lint
npm run build
php artisan test
```

The build is written to `public/build` and tracked so Hostinger does not need
Node.js during deployment. React derives the application/API/PWA base path from
the compiled module URL, so the same bundle works at `/` or any nested folder.

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for the complete Hostinger process.
