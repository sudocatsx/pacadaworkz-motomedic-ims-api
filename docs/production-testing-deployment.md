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

The existing workflow deploys pushes from `dev` over FTP in two passes: application files go to `../`, while the contents of `public/` go to the website root. FTP cannot run Artisan commands, so deployments intentionally do not run migrations or clear Laravel caches. Run required migrations manually from Hostinger after deploying a schema change:

```bash
php artisan migrate --force
php artisan optimize:clear
```

The database backup and restore workflows additionally require a GitHub environment named `production-testing` containing:

- `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_PORT`, `HOSTINGER_SSH_USER`
- `HOSTINGER_SSH_PRIVATE_KEY`
- `HOSTINGER_APP_PATH`: absolute Laravel application root containing `artisan`
- `SUPABASE_DB_URL`: percent-encoded Session-pooler URI with `sslmode=require`

Add the corresponding SSH public key to Hostinger only if those database-maintenance workflows will be used.

The scheduled backup workflow must exist on GitHub's default branch because scheduled Actions run from that branch. It dumps only the Laravel-owned `public` schema, uploads backups to `storage/app/private/backups`, and retains seven files.

Hostinger does not provide `pg_dump` or `pg_restore`. Consequently, the application reports those two server tools as unavailable. Externally generated dumps still appear in the Database screen and remain downloadable or deletable. Use the guarded `Restore Supabase database` workflow for restoration.

## 4. Configure Cloudflare Pages and local Vite

Set the Cloudflare Pages Production variable:

```dotenv
VITE_API_BASE_URL=https://pacadaworkz.bscs3a.com/api
```

Vite uses `.env.development` for the local Laravel API and `.env.production` for Cloudflare builds. The ignored `.env.development.local` intentionally points local React at Hostinger for integration testing; remove that file to return to the local API.

Only `VITE_GOOGLE_CLIENT_ID` belongs in the frontend environment. Google client secrets must remain in the Laravel/Hostinger environment.

## 5. Acceptance checks

1. `GET https://pacadaworkz.bscs3a.com/api/v1/health` returns HTTP 200 with database and storage checks set to `ok`.
2. Invalid login returns HTTP 401 instead of 500, and a seeded demo account can authenticate.
3. CORS succeeds from localhost port `3001` and the exact Cloudflare Pages origin only.
4. An FTP deployment preserves `storage` and `.env`; required migrations are run manually afterward.
5. The daily backup appears in the Database screen and `pg_restore --list` validates it in the workflow.
