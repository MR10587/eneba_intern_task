# Deployment guide

This repository is configured for this target setup:

```text
Vercel: React/Vite SPA
Render: Laravel API in a Docker container
Neon:   managed PostgreSQL
```

No secret values live in the repository. Every credential is supplied through the hosting dashboards.

## What is in the repository

| File | Purpose |
| --- | --- |
| `Dockerfile` | Multi-stage PHP 8.4 image: Composer dependencies, PHP extensions, nginx, PHP-FPM, supervisor |
| `.dockerignore` | Keeps `.env`, Git data, local dependencies, and caches out of the image |
| `docker/nginx.conf.template` | nginx site config; listens on Render's `PORT` and serves Laravel's `public/` directory |
| `docker/supervisord.conf` | Keeps nginx and PHP-FPM running together |
| `docker/entrypoint.sh` | Maps Neon/Render database variable names, validates config, migrates, optionally seeds, caches config, starts servers |
| `config/cors.php` | Environment-driven CORS allowlist; no wildcard in production |
| `frontend/vercel.json` | SPA rewrite so `/products/...` and other client routes work on Vercel |
| `frontend/src/api/client.ts` | Uses `VITE_API_URL` when set, otherwise keeps local `/api` proxy behavior |
| `frontend/src/vite-env.d.ts` | Type declaration for `VITE_API_URL` |

## How the container starts

1. Defaults the web port to `10000`, which is Render's expected port.
2. Accepts either `DB_URL` or Neon's `DATABASE_URL`.
3. Optionally uses `MIGRATION_DB_URL` for migrations only. Neon recommends a direct connection for migrations and a pooled connection for web traffic.
4. Verifies that `APP_KEY` and database settings exist.
5. Makes `storage/` and `bootstrap/cache/` writable by PHP-FPM.
6. Generates the nginx config from `PORT`.
7. Runs `php artisan migrate --force --no-interaction`.
8. If `SEED_DEMO_DATA=true`, runs the idempotent demo seeders.
9. Runs `php artisan config:cache` after environment variables are available.
10. Starts nginx and PHP-FPM under supervisor.

The API health route is Laravel's built-in `/up`. Set Render's **Health Check Path** to `/up`.

---

## 1. Create the Neon database

Do this manually in the Neon dashboard:

1. Create a Neon project and note its region.
2. Create a database and role, or use the defaults.
3. Click **Connect** and select the branch, database, and role.
4. Copy the **pooled** connection string. It has `-pooler` in the hostname. This becomes `DATABASE_URL` in Render.
5. Also copy the **direct** connection string without `-pooler`. This becomes `MIGRATION_DB_URL` in Render.

Neon connection strings include `sslmode=require`; keep the full string.

Things to verify in Neon:

- The free plan's current compute, storage, and branch limits.
- That the chosen PostgreSQL version supports `pg_trgm` — recent Neon versions do. This project enables that extension during migration for typo-tolerant search.
- Neon free compute may scale to zero or pause when idle; the first request after idle can be slower.

No application files need to be changed for Neon.

---

## 2. Configure the Render API service

Create a **Web Service** from the repository:

| Setting | Value |
| --- | --- |
| Runtime | Docker |
| Dockerfile Path | `Dockerfile` |
| Root Directory | repository root |
| Health Check Path | `/up` |
| Plan | Free is acceptable for a demo; verify current Render free limits |

Render provides `PORT`; the container also defaults to `10000`.

### Render environment variables

Enter these in the Render dashboard. Values below are placeholders, not secrets.

| Variable | Value / meaning |
| --- | --- |
| `APP_NAME` | `Eneba` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | `base64:GENERATE_A_NEW_KEY_HERE` |
| `APP_URL` | `https://YOUR_API_SERVICE.onrender.com` |
| `CORS_ALLOWED_ORIGINS` | `https://YOUR_FRONTEND.vercel.app` |
| `DB_CONNECTION` | `pgsql` |
| `DATABASE_URL` | `postgresql://USER:PASSWORD@HOST-POOLER:5432/DATABASE?sslmode=require` |
| `MIGRATION_DB_URL` | `postgresql://USER:PASSWORD@HOST:5432/DATABASE?sslmode=require` |
| `DB_SSLMODE` | `require` |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `info` |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `FILESYSTEM_DISK` | `local` |
| `SEED_DEMO_DATA` | `true` for the demo dataset, or `false` to skip seeding |
| `PORT` | Usually omit; Render sets it. Locally the container defaults to `10000`. |

Generate `APP_KEY` locally without printing it into documentation:

```powershell
& 'C:\php\php.exe' artisan key:generate --show
```

Paste the output only into the Render dashboard.

If you need preview frontend domains, add them as a comma-separated list in `CORS_ALLOWED_ORIGINS`, for example:

```text
https://YOUR_FRONTEND.vercel.app,https://PROJECT-GITBRANCH.vercel.app
```

Do not use `*` for `CORS_ALLOWED_ORIGINS`. The application config removes wildcards in production, but an explicit origin is clearer and safer.

### Migrations

Nothing extra is needed on Render Free: the container runs migrations at startup. Do **not** configure `migrate:fresh`, resets, or destructive commands.

Render Free does not support paid-only one-off jobs or shell access, so if a migration fails, read the deploy logs, fix the code or environment, and redeploy.

Free services spin down after inactivity and have ephemeral local files. The database is in Neon, so application data survives; generated local files in the container do not. Always store durable state in the database.

---

## 3. Configure the Vercel frontend

Import the repository in Vercel with:

| Setting | Value |
| --- | --- |
| Root Directory | `frontend` |
| Framework Preset | Vite |
| Build Command | `npm run build` |
| Output Directory | `dist` |
| Install Command | `npm install` |

`frontend/vercel.json` adds the SPA rewrite for deep links such as `/products/minecraft` and `/orders/7`.

### Vercel environment variable

| Variable | Value |
| --- | --- |
| `VITE_API_URL` | `https://YOUR_API_SERVICE.onrender.com/api` |

Include `/api` at the end. `client.ts` trims trailing slashes, so `.../api/` is also fine.

Vite embeds `VITE_API_URL` at build time. After changing it in the dashboard, redeploy the frontend.

Local development is unchanged: with `VITE_API_URL` unset, the SPA keeps using `/api` and the Vite dev proxy forwards to `http://127.0.0.1:8000`.

---

## 4. Deploy order

1. Create the Neon database and copy pooled and direct connection strings.
2. Generate a fresh `APP_KEY`.
3. Create the Render Docker web service and add the Render variables above.
4. Deploy Render. Confirm `/up` and `/api/home` respond.
5. Create the Vercel project with Root Directory `frontend`.
6. Add `VITE_API_URL` pointing to the Render API.
7. Deploy Vercel.
8. Add the final Vercel domain to Render's `CORS_ALLOWED_ORIGINS`, then redeploy Render if that value changed.
9. Verify the full browser flow.

---

## 5. Verify the deployment

Use placeholders with your real URLs:

```powershell
Invoke-WebRequest -Uri 'https://YOUR_API_SERVICE.onrender.com/up'
Invoke-RestMethod -Uri 'https://YOUR_API_SERVICE.onrender.com/api/home'
```

Expected:

- `/up` returns `200` and reports `up`.
- `/api/home` returns JSON with homepage blocks. If `SEED_DEMO_DATA=true`, blocks and products are present.
- The browser network tab shows requests going to `https://YOUR_API_SERVICE.onrender.com/api/...`.
- Guest add-to-cart works.
- Checkout redirects anonymous visitors to login.
- Demo login uses `test@example.com` / `password` only if seeding was enabled.
- Successful simulated payment: `4242 4242 4242 4242`.
- Failed simulated payment: `4242 4242 4242 0002`.

If the browser reports CORS errors, confirm the exact Vercel origin — including `https://` and no trailing slash — is in `CORS_ALLOWED_ORIGINS`.

---

## Manual steps you must do in dashboards

- Neon: create project, database, role, and copy both connection strings.
- Render: create the service, choose Docker, add all environment variables, set Health Check Path to `/up`, and deploy.
- Vercel: import the repository, set Root Directory to `frontend`, add `VITE_API_URL`, and deploy.
- Vercel/Render: after Vercel assigns the final domain, update `CORS_ALLOWED_ORIGINS` if needed and redeploy Render.

Nothing in this repository deploys or creates accounts by itself.
