#!/usr/bin/env bash
# Builds the ready-to-run site (backend + vendor/ + built frontend) into a folder.
# Used by .github/workflows/deploy-build.yml; can also be run by hand (needs composer, node, git, tar).
#   SITE_URL=https://house.dhakablinds.shop deploy/assemble.sh /tmp/site
set -euo pipefail

OUT="${1:?usage: assemble.sh <output-dir>}"
SITE_URL="${SITE_URL:?set SITE_URL, e.g. https://house.dhakablinds.shop}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

rm -rf "$OUT"; mkdir -p "$OUT"

# 1. the backend: tracked files only, minus everything a live server has no use for
git -C "$ROOT" archive HEAD | tar -x -C "$OUT" --anchored     --exclude='android-app' --exclude='frontend' --exclude='tests' --exclude='scripts'     --exclude='.github' --exclude='.claude' --exclude='deploy' --exclude='phpunit.xml'     --exclude='find-database.bat' --exclude='*.md' --exclude='.editorconfig'     --exclude='package.json' --exclude='vite.config.js'

# the repo's .gitignore files would stop vendor/ and storage/ from being committed to the deploy branch
find "$OUT" -name .gitignore -delete

# 2. PHP dependencies, production only
composer install --working-dir="$OUT" --no-dev --optimize-autoloader --no-interaction --no-progress

# 3. the React app, pointed at this site's own API
# (CI always installs clean; by hand, reuse node_modules - a running dev server locks them on Windows)
( cd "$ROOT/frontend" && { [ -d node_modules ] && [ -z "${CI:-}" ] || npm ci --no-audit --no-fund; }     && VITE_API_BASE_URL="${SITE_URL%/}/api" npm run build )
cp -R "$ROOT/frontend/dist/." "$OUT/public/"

# 4. web-server rules
cp "$ROOT/deploy/htaccess-root" "$OUT/.htaccess"
cp "$ROOT/deploy/htaccess-public" "$OUT/public/.htaccess"
cp "$ROOT/deploy/env.production.example" "$OUT/.env.example"

# 5. folders Laravel writes to must exist (git does not keep empty folders)
for d in storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
         storage/app/public storage/app/private storage/app/dompdf-font-cache bootstrap/cache public/downloads; do
    mkdir -p "$OUT/$d"; touch "$OUT/$d/.gitkeep"
done
rm -f "$OUT/.env" "$OUT"/public/downloads/*.apk

echo "assembled: $(du -sh "$OUT" | cut -f1) in $OUT"
