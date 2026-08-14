# EMC Laravel deployment on Hostinger

The production URL for the current Hostinger folder is `https://k2softwarestudio.com/emcshoescare/`. Laravel stays in a private Git checkout outside `public_html`; the deployment command publishes only the React build and a protected API bridge.

## 1. Requirements

- PHP 8.2 or newer with PDO MySQL, mbstring, fileinfo, OpenSSL, tokenizer, XML, and ctype
- Composer 2
- MySQL 8 or compatible MariaDB using `utf8mb4`
- Apache `.htaccess`, HTTPS, and SSH

Node.js is not required on Hostinger because the compiled frontend is committed under `dist`.

## 2. Private checkout

```bash
cd /home/u608908096
git clone https://github.com/minhtetkyawpublic/emcshoescare.git emcshoescare-repo
cd /home/u608908096/emcshoescare-repo
git checkout main
git pull --ff-only origin main
composer install --working-dir=backend --no-dev --optimize-autoloader --no-interaction
```

Do not clone the repository into `public_html`.

## 3. Database and environment

In hPanel, open the website dashboard, then **Databases → Management**. Create a database/user and retain the complete Hostinger-prefixed names and password. The host is normally `localhost`.

```bash
cd /home/u608908096/emcshoescare-repo
cp backend/.env.production.example backend/.env
nano backend/.env
```

Set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://k2softwarestudio.com/emcshoescare

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u608908096_actual_database
DB_USERNAME=u608908096_actual_user
DB_PASSWORD=actual-database-password

SESSION_PATH=/emcshoescare/
SESSION_SECURE_COOKIE=true
EMC_ALLOWED_ORIGINS=https://k2softwarestudio.com
```

`EMC_ALLOWED_ORIGINS` contains only the HTTPS origin, not `/emcshoescare`. Generate the Laravel key after saving the database values:

```bash
php backend/artisan key:generate
chmod 600 backend/.env
chmod -R ug+rw backend/storage backend/bootstrap/cache
```

## 4. Migrations and administrator

```bash
php backend/artisan migrate --seed --force
php backend/artisan migrate:status
php backend/artisan emc:create-admin emcadmin 'replace-with-a-long-unique-password' 'EMC Administrator'
```

Artisan records applied versions in Laravel's `migrations` table. Re-running `migrate --force` applies only pending migrations. Do not import the retired plain-PHP SQL files.

## 5. Deploy the public package

```bash
php scripts/deploy-release.php /home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare
php backend/artisan optimize
```

The command creates `api/runtime.php` with the absolute private Laravel path and protects it with Apache rules. It does not publish `.env`, `vendor`, migrations, or photos.

## 6. Test

1. Open `https://k2softwarestudio.com/emcshoescare/api/health`; expect successful JSON naming `EMC Laravel API`.
2. Open `https://k2softwarestudio.com/emcshoescare/`.
3. Open `https://k2softwarestudio.com/emcshoescare/admin` and sign in.
4. Register a test customer, place an order with photos, and complete the status workflow.
5. Confirm `https://k2softwarestudio.com/emcshoescare/api/runtime.php` is forbidden.

If health returns 500:

```bash
cd /home/u608908096/emcshoescare-repo
php backend/artisan about
php backend/artisan migrate:status
tail -n 100 backend/storage/logs/laravel.log
```

Never paste `.env`, cookies, customer information, or full production logs into a public issue.

## 7. Updates

Take a database/photo backup, then:

```bash
cd /home/u608908096/emcshoescare-repo
git pull --ff-only origin main
composer install --working-dir=backend --no-dev --optimize-autoloader --no-interaction
php backend/artisan migrate --force
php backend/artisan optimize
php scripts/deploy-release.php /home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare
```

No `npm run build` is required on Hostinger.
