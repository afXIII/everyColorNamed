#!/usr/bin/env bash
# Manual Laravel Cloud → Deployments → Build commands
# Paste ONLY the block between the markers (no wrapper script — Cloud was truncating script names).
set -euo pipefail

# --- BEGIN PASTE INTO CLOUD BUILD COMMANDS ---
set -euo pipefail
export APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32 | tr -d '\n')}"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
cd ../web
npm ci --audit false
NUXT_PUBLIC_API_BASE=/api npm run generate
cd ../everyColorNamed
rm -rf public/_nuxt public/index.html public/200.html public/404.html
cp -R ../web/.output/public/. public/
test -f public/index.php
# --- END PASTE ---
