# EMC Shoes Care Myanmar

Mobile-first React Progressive Web App for EMC Shoes Care Myanmar. The planned production stack is React, plain PHP, and MySQL.

Phase 1 contains the bilingual public landing page and frontend-only demo order form. Phase 2 adds phone/password customer accounts using a plain PHP API and MySQL. See [ROADMAP.md](./ROADMAP.md) for the full delivery plan.

## Run locally

```bash
npm install
npm run dev
```

The Vite development server forwards `/api` requests to the local XAMPP Apache server at `http://127.0.0.1/emcshoecare/api`.

## Phase 2 database setup

Import `database/migrations/001_create_customer_accounts.sql` using phpMyAdmin, or run it with the XAMPP MySQL client. The migration creates only the `emc_shoes_care` database and its account/session tables and can be run more than once safely.

Local XAMPP defaults work without a configuration file (`root` with an empty password). For a different setup, copy the array shape from `api/config.php` into an ignored `api/config.local.php`, or set the variables documented in `.env.example` at the web-server level. The PHP API deliberately does not parse `.env` files or commit secrets.

For production, HTTPS is required and `EMC_APP_KEY` must be replaced with a unique random value of at least 32 characters. Database credentials and `EMC_ALLOWED_ORIGINS` must also be configured for the production host.

## Verify a production build

```bash
npm run lint
npm run build
```

The interface text for both English and Myanmar is kept in `src/i18n/translations.js`. Phase 1 package names and demonstration prices are also defined there for easy replacement.
