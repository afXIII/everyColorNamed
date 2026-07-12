# everyColorNamed

A catalog of every RGB color with static, unique, human-readable names.

- **Live:** [https://everycolornamed-production-iou7og.laravel.cloud/](https://everycolornamed-production-iou7og.laravel.cloud/)
- **Repo:** [github.com/afXIII/everyColorNamed](https://github.com/afXIII/everyColorNamed)
- **Laravel app:** `everyColorNamed/` — build pipeline + JSON API
- **Herd:** [http://everycolornamed.test](http://everycolornamed.test)
- **Web UI:** `web/` — Nuxt browse app (`npm run dev` → http://localhost:3000)
- **Data:** `data/` — seeds, builds, releases
- **Plan:** [PLAN.md](./PLAN.md)

## Quick start

```bash
cd everyColorNamed

# Import seed color names (~30k+ from open sources)
php artisan colors:import-seeds

# Reports
php artisan colors:report-aliases
php artisan colors:report-name-conflicts

# Generate catalog (level 0 = seeds only, level 5 ≈ 33k colors)
php artisan colors:generate-catalog --level=0
php artisan colors:verify-unique

# Promote a draft build to a public release
php artisan colors:release 20260711-084256-808e --public-version=1

# API (after a build exists)
curl http://everycolornamed.test/api/manifest
curl 'http://everycolornamed.test/api/colors/window?from=0&limit=10'
curl http://everycolornamed.test/api/colors/FF6347
```

## Web UI

```bash
cd web
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000). Share a color via `?hex=ff6347`.

## Herd

Site linked as **everycolornamed.test** pointing at `everyColorNamed/`.

Set `COLOR_DATA_PATH` in `.env` if needed (defaults to `../data`).

## Deploy (Laravel Cloud)

Production: [https://everycolornamed-production-iou7og.laravel.cloud/](https://everycolornamed-production-iou7og.laravel.cloud/)

See [DEPLOY.md](./DEPLOY.md) for Object Storage, build script, and jump-nav notes.
