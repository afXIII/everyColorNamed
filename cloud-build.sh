#!/usr/bin/env bash
# Laravel Cloud build script — run from repo root (or set ROOT).
# Dashboard → Environment → Deployments → Build commands:
#   bash cloud-build.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
LARAVEL="$ROOT/everyColorNamed"
WEB="$ROOT/web"

echo "==> Composer install"
cd "$LARAVEL"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Nuxt generate (SPA → Laravel public/)"
cd "$WEB"
npm ci
NUXT_PUBLIC_API_BASE=/api npm run generate

echo "==> Copy SPA assets into Laravel public/ (preserve index.php)"
# Remove previous SPA bits only
rm -rf "$LARAVEL/public/_nuxt" "$LARAVEL/public/index.html" "$LARAVEL/public/200.html" "$LARAVEL/public/404.html"
cp -R "$WEB/.output/public/." "$LARAVEL/public/"

if [[ ! -f "$LARAVEL/public/index.php" ]]; then
  echo "ERROR: public/index.php missing after SPA copy" >&2
  exit 1
fi

echo "==> Build complete"
