#!/usr/bin/env bash
# Run from Laravel Cloud build commands (app root = everyColorNamed):
#   bash build.sh
#
# Do NOT also leave Cloud's default "composer install" lines — this script
# handles Composer + the Nuxt SPA.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$APP_ROOT/.." && pwd)"
WEB="$REPO_ROOT/web"

if [[ ! -d "$WEB" ]]; then
  echo "ERROR: expected Nuxt app at $WEB (monorepo sibling of everyColorNamed)" >&2
  echo "PWD=$(pwd) APP_ROOT=$APP_ROOT REPO_ROOT=$REPO_ROOT" >&2
  ls -la "$REPO_ROOT" >&2 || true
  exit 1
fi

# Artisan scripts during composer need a key; Cloud injects APP_KEY at runtime.
if [[ -z "${APP_KEY:-}" ]]; then
  export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "==> Generated temporary APP_KEY for build"
fi

echo "==> Composer install"
cd "$APP_ROOT"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Nuxt generate (SPA → public/)"
cd "$WEB"
npm ci --audit false
NUXT_PUBLIC_API_BASE=/api npm run generate

echo "==> Copy SPA into Laravel public/ (keep index.php)"
rm -rf "$APP_ROOT/public/_nuxt" "$APP_ROOT/public/index.html" "$APP_ROOT/public/200.html" "$APP_ROOT/public/404.html"
cp -R "$WEB/.output/public/." "$APP_ROOT/public/"

if [[ ! -f "$APP_ROOT/public/index.php" ]]; then
  echo "ERROR: public/index.php missing after SPA copy" >&2
  exit 1
fi

echo "==> Build complete"
