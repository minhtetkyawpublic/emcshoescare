# Hostinger deployment

## Layout

The repository is one Laravel + React + Vite application and may be checked out
directly into:

```text
/home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare
```

The root `.htaccess` sends compiled/PWA files to `public/` and everything else
to Laravel's root front controller. Requests cannot directly read `.env`, Git,
Composer files, application source, migrations, storage, or vendor code.

The preferred server configuration is still a document root pointing at the
Laravel `public/` directory. The included root front controller exists for
shared hosting where the document root cannot be changed.

## First installation

```bash
cd /home/u608908096/domains/k2softwarestudio.com/public_html
git clone https://github.com/minhtetkyawpublic/emcshoescare.git emcshoescare
cd emcshoescare

composer install --no-dev --optimize-autoloader --no-interaction
test -f .env || cp .env.production.example .env
nano .env
php artisan key:generate
chmod 600 .env
chmod -R ug+rw storage bootstrap/cache
php artisan migrate --seed --force
php artisan emc:create-admin emcadmin 'replace-with-a-long-unique-password' 'EMC Administrator'
php artisan optimize
```

Configure the full Hostinger-provided MySQL database name, username, and
password in `.env`. `DB_HOST` is normally `localhost`.

The production frontend is already committed in `public/build`; `npm run build`
is not required on Hostinger after pulling a release.

## URLs

- App: `https://k2softwarestudio.com/emcshoescare/customer/home`
- Admin: `https://k2softwarestudio.com/emcshoescare/admin/orders`
- Health: `https://k2softwarestudio.com/emcshoescare/api/health`

The shorter `/emcshoescare/` and `/emcshoescare/admin` URLs redirect to the
correct scoped PWA entry points.

Verify private material is blocked:

```bash
for path in .env composer.json artisan app/Models/Customer.php .git/config; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "https://k2softwarestudio.com/emcshoescare/$path")
  echo "$path -> $code"
done
```

No private URL may return file contents or HTTP 200. A 403 response is expected.

## Updating

```bash
cd /home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
chmod -R ug+rw storage bootstrap/cache
php artisan optimize
```

No copy/deployment bridge command is needed. `git pull` updates the unified app
and its tracked `public/build` assets together.

## One-time upgrade from the old `backend/` layout

After pulling the first unified release, preserve the existing environment and
private order photos before retiring the old directory:

```bash
cd /home/u608908096/domains/k2softwarestudio.com/public_html/emcshoescare
git pull origin main

test -f .env || cp backend/.env .env
mkdir -p storage/app/private/order-photos
if [ -d backend/storage/app/private/order-photos ]; then
  cp -a backend/storage/app/private/order-photos/. storage/app/private/order-photos/
fi

composer install --no-dev --optimize-autoloader --no-interaction
chmod 600 .env
chmod -R ug+rw storage bootstrap/cache
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

Confirm that the app, admin area, existing orders, and photos work. Then move
the legacy directory outside the public web root as a recoverable backup:

```bash
test ! -e /home/u608908096/emcshoescare-backend-legacy-backup
mv backend /home/u608908096/emcshoescare-backend-legacy-backup
```

The root `.htaccess` denies requests to `backend/` during this transition.

## Diagnostics

```bash
php artisan about
php artisan migrate:status
php artisan route:list
tail -n 100 storage/logs/laravel.log
```

Never share `.env`, passwords, cookies, customer information, or full production
logs publicly.
