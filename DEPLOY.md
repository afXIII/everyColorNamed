# Deploying everyColorNamed to Laravel Cloud

## Architecture

Single Laravel Cloud app rooted at `everyColorNamed/`:

- **API** — `/api/*` (manifest, browse windows, color detail)
- **SPA** — Nuxt static build copied into `public/` at build time (`cloud-build.sh`)
- **Catalog data** — ~5.7GB SQLite (`browse.sqlite` + 256 shards). **Not in git.**

Catalog files live in **[Laravel Object Storage](https://cloud.laravel.com/docs/resources/object-storage)** (a Cloud bucket you attach in the dashboard). Compute disk is ephemeral and small; the bucket is the durable home for the release. After deploy, sync the release onto the instance so SQLite can open local files.

## One-time setup

1. Push this repo to GitHub/GitLab and create a Laravel Cloud application.
2. Set **application root** to `everyColorNamed` (monorepo picker).
3. **Build command** (from app root):
   ```bash
   bash ../cloud-build.sh
   ```
4. On the environment canvas, **Add bucket** → Laravel Object Storage (private). Pick a disk name (e.g. `catalog`) or make it the default disk. Re-deploy so credentials are injected ([docs](https://cloud.laravel.com/docs/resources/object-storage)).
5. Environment variables:
   | Key | Value |
   |-----|--------|
   | `APP_KEY` | generated |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `COLOR_DATA_PATH` | e.g. `/var/www/html/storage/app/color-data` |
   | (bucket vars) | Injected by Cloud when the bucket is attached |
6. Upload `data/releases/v1` into the bucket (prefix `releases/v1/`). From your machine, use the bucket credentials under **Resources → Object storage → ⋯ → View credentials** (Cyberduck, `aws` CLI with that endpoint, etc.).
7. On the instance (Cloud → Commands):
   ```bash
   php artisan colors:sync-catalog --disk=catalog --prefix=releases/v1
   ```
   Use whatever disk name you chose in step 4 (`--disk=s3` only if that is the disk name Cloud assigned).
8. Ensure compute has enough ephemeral disk for the sync (~6GB+ → roughly ≥12GB RAM on Cloud’s sizing). Redeploys wipe local disk; re-run sync (or add it to a deploy hook) after each deploy.

## Local release checklist

```bash
cd everyColorNamed
php artisan colors:rebuild-jump-nav
php artisan colors:verify-unique
php artisan colors:release {build_id} --public-version=1
# upload data/releases/v1 into the Cloud bucket under releases/v1/
```

## Jump nav

Nav items scroll to the **middle row** of each hue bucket’s range in `browse.sqlite`. Recompute with:

```bash
php artisan colors:rebuild-jump-nav
```
