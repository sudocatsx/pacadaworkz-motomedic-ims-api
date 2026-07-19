# Production-testing deployment

This environment keeps the React application on Cloudflare Pages and the Laravel API on Hostinger, with Supabase providing PostgreSQL. Hostinger and Supabase must both use Singapore.

## 1. Create the Supabase database

1. Create a Free Supabase project in **Southeast Asia (Singapore)**.
2. Open **Connect** and select the **Session pooler** connection on port `5432`.
3. Keep Supabase Auth, Storage, and the Data API out of the application. Laravel remains the API and authentication boundary.
4. Copy the connection fields into the Hostinger API `.env`. Do not commit them.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pacadaworkz.bscs3a.com
CORS_ALLOWED_ORIGINS=http://localhost:3001,https://YOUR-CLOUDFLARE-PAGES-ORIGIN

DB_CONNECTION=pgsql
DB_HOST=YOUR-SINGAPORE-SESSION-POOLER-HOST
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_PROJECT_REF
DB_PASSWORD=YOUR_DATABASE_PASSWORD
DB_SSLMODE=require

CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
FILESYSTEM_DISK=local
USER_DEFAULT_PASSWORD=YOUR_UNIQUE_DEMO_PASSWORD
```

After editing Hostinger `.env`, run `php artisan optimize:clear`. Verify both `pgsql` and `pdo_pgsql` remain enabled for the website and CLI PHP runtimes.

## 2. Bootstrap the empty database once

Run these commands once from the Hostinger application root after the Supabase connection is configured:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
```

Do not add `db:seed` to routine deployments. The demo user seeder updates users by email and would reset their credentials.

## 3. Configure GitHub deployment

Add these repository secrets for the existing FTP deployment:

- `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`

The existing workflow deploys pushes from `dev` over FTP in two passes: application files go to `../`, while the contents of `public/` go to the website root. It excludes `vendor/` to keep FTP deployments fast. FTP cannot run Composer or Artisan commands, so run Composer only when `composer.lock` changes, and run required migrations after deploying a schema change:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

The database backup and restore workflows additionally require a GitHub environment named `production-testing` containing:

- `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_PORT`, `HOSTINGER_SSH_USER`
- `HOSTINGER_SSH_PRIVATE_KEY`
- `HOSTINGER_APP_PATH`: absolute Laravel application root containing `artisan`
- `SUPABASE_DB_URL`: percent-encoded Session-pooler URI with `sslmode=require`
- `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`

Add the corresponding SSH public key to Hostinger only if those database-maintenance workflows will be used.

The two database workflows must exist on GitHub's default branch (`master`) so the Hostinger API can dispatch them. They are manual-only: the Database tab queues an operation and GitHub Actions uses PostgreSQL 17 tools to dump or restore only the Laravel-owned `public` schema. Dumps and operation metadata are stored in a private Standard Cloudflare R2 bucket; no database dump is copied to Hostinger or retained as a GitHub artifact.

Hostinger does not provide `pg_dump` or `pg_restore`, so the GitHub runner remains required even though the Database tab controls the operation. Complete the R2, GitHub, and Hostinger setup in [Manual database recovery with Cloudflare R2](manual-database-recovery-r2.md).

## 4. Link public storage on Hostinger

Hostinger may disable PHP's `symlink()` and `exec()` functions, causing `php artisan storage:link` to fail. Create the symbolic link directly from the Hostinger terminal instead.

From the Laravel application root:

```bash
cd /home/u896434489/domains/pacadaworkz.bscs3a.com
ls -ld public_html
mkdir -p storage/app/public
```

Before creating anything, check whether the destination already exists:

```bash
ls -la public_html/storage
```

If `public_html/storage` does not exist, create the link:

```bash
ln -s \
  /home/u896434489/domains/pacadaworkz.bscs3a.com/storage/app/public \
  /home/u896434489/domains/pacadaworkz.bscs3a.com/public_html/storage
```

Verify the result:

```bash
ls -ld public_html/storage
readlink public_html/storage
```

The link must resolve to:

```text
/home/u896434489/domains/pacadaworkz.bscs3a.com/storage/app/public
```

Do not delete `public_html/storage` if it already exists. Inspect it first. If the direct `ln -s` command returns `Operation not permitted`, enable symlink support in Hostinger if the plan allows it; otherwise, serve public files through Laravel as a temporary fallback.

## 5. Configure Cloudflare Pages and local Vite

Set the Cloudflare Pages Production variable:

```dotenv
VITE_API_BASE_URL=https://pacadaworkz.bscs3a.com/api
```

Vite uses `.env.development` for the local Laravel API and `.env.production` for Cloudflare builds. An ignored `.env.development.local` can temporarily override the development API URL when integration testing against Hostinger.

Only `VITE_GOOGLE_CLIENT_ID` belongs in the frontend environment. Google client secrets must remain in the Laravel/Hostinger environment.

## 6. Acceptance checks

1. `GET https://pacadaworkz.bscs3a.com/api/v1/health` returns HTTP 200 with database and storage checks set to `ok`.
2. Invalid login returns HTTP 401 instead of 500, and a seeded demo account can authenticate.
3. CORS succeeds from localhost port `3001` and the exact Cloudflare Pages origin only.
4. An FTP deployment preserves `storage` and `.env`; required migrations are run manually afterward.
5. `/storage/...` URLs resolve through the `public_html/storage` symbolic link.
6. A manually queued backup reaches `succeeded`, appears in R2 history, and is validated by `pg_restore --list` in the workflow.
