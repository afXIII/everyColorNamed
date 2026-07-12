#!/usr/bin/env bash
# Local helper: rebuild SPA into everyColorNamed/public for Cloud deploys.
# Cloud build should NOT run this — only composer install (see DEPLOY.md).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/web"
NUXT_PUBLIC_API_BASE=/api npm run generate
cd "$ROOT"
rm -rf everyColorNamed/public/_nuxt everyColorNamed/public/index.html everyColorNamed/public/200.html everyColorNamed/public/404.html
cp -R web/.output/public/. everyColorNamed/public/
test -f everyColorNamed/public/index.php
test -f everyColorNamed/public/index.html
echo "SPA copied into everyColorNamed/public — commit those files before deploying."
