# EMC Shoes Care Myanmar

Mobile-first React Progressive Web App for EMC Shoes Care Myanmar. The planned production stack is React, plain PHP, and MySQL.

Phase 1 contains the bilingual public landing page. Phase 2 adds phone/password customer accounts. Phase 3 adds the admin dashboard, database-managed packages, optional pickup fees, real orders, private photo storage, and customer order history. The backend remains plain PHP and MySQL. See [ROADMAP.md](./ROADMAP.md) for the full delivery plan.

## Run locally

```bash
npm install
npm run dev
```

The Vite development server forwards `/api` requests to the local XAMPP Apache server at `http://127.0.0.1/emcshoecare/api`.

## Phase 2 database setup

Import the SQL files in `database/migrations` in numeric order using phpMyAdmin or the XAMPP MySQL client. Migration `001` creates the dedicated `emc_shoes_care` database and customer accounts; migration `002` adds the administrator, packages, settings, orders, and private photo metadata. Both can be run more than once safely.

Local XAMPP defaults work without a configuration file (`root` with an empty password). For a different setup, copy the array shape from `api/config.php` into an ignored `api/config.local.php`, or set the variables documented in `.env.example` at the web-server level. The PHP API deliberately does not parse `.env` files or commit secrets.

For production, HTTPS is required and `EMC_APP_KEY` must be replaced with a unique random value of at least 32 characters. Database credentials and `EMC_ALLOWED_ORIGINS` must also be configured for the production host.

## Administrator setup

Create or reset the single administrator from the command line. The password must contain at least 10 characters:

```bash
D:\xampp\php\php.exe api\cli\create-admin.php emcadmin "choose-a-strong-password" "EMC Administrator"
```

Run the frontend and open `http://127.0.0.1:5173/admin`. The customer site remains at `http://127.0.0.1:5173/`.

Order photos are written under `storage/order-photos` with random filenames. Apache is explicitly denied direct access by `storage/.htaccess`; authenticated customers and the administrator receive photos through guarded PHP endpoints. The web-server user needs write permission only for `storage/order-photos`.

For a production build, place the contents of `dist` at the web-app document root alongside the `api` and `storage` directories. Keep the source and database credentials outside public download access.

## Verify a production build

```bash
npm run lint
npm run build
```

The interface text for both English and Myanmar is kept in `src/i18n/translations.js`. Phase 1 package names and demonstration prices are also defined there for easy replacement.
