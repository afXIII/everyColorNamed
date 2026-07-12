# Deploying everyColorNamed to Laravel Cloud

## Architecture

Single Laravel Cloud app rooted at `everyColorNamed/`:

- **API** — `/api/*` (manifest, browse windows, color detail)
- **SPA** — prebuilt Nuxt output committed into `everyColorNamed/public/` (Cloud cannot see `../web`)
- **Catalog data** — ~5.7GB SQLite. **Not in git.** Lives in Laravel Object Storage; sync onto the instance after deploy.

## One-time setup

1. Push this repo and create a Laravel Cloud application.
2. App root: **`everyColorNamed`**.
3. **Build commands** — only Composer (no Node / no `cd ../web`):
   ```bash
   set -euo pipefail
   if [ -z "${APP_KEY:-}" ]; then export APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="; fi
   rm -rf vendor
   composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --no-cache
   ```
4. Attach a **private** Laravel Object Storage bucket. Laravel Cloud creates a disk with the name you choose (e.g. `colors`) — **not** `s3`. Redeploy after attaching.
5. Env vars: `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `COLOR_DATA_PATH=/var/www/html/storage/app/color-data`, `FILESYSTEM_DISK=colors` (use your disk name). Optional if sync errors on region: `AWS_DEFAULT_REGION=auto` — only needed when using the generic `s3` disk, not your attached bucket disk.
6. Compute: **Pro 16 GiB** (need ~6GB+ local disk for catalog sync).
7. Upload `data/releases/v1` to the bucket under `releases/v1/`.
8. Sync — **must use the Cloud disk name** (bucket credentials live there, not on `s3`):
   ```bash
   php artisan colors:sync-catalog --disk=colors --prefix=releases/v1
   ```
   Or omit `--disk` if you set `FILESYSTEM_DISK=colors`.

## Updating the UI

Rebuild the SPA locally, then commit `everyColorNamed/public/`:

```bash
cd web
NUXT_PUBLIC_API_BASE=/api npm run generate
cd ..
rm -rf everyColorNamed/public/_nuxt everyColorNamed/public/index.html everyColorNamed/public/200.html everyColorNamed/public/404.html
cp -R web/.output/public/. everyColorNamed/public/
```

## Jump nav

Middle of each hue bucket. Recompute with `php artisan colors:rebuild-jump-nav`.
