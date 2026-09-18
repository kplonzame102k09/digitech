#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
DEST="$ROOT/infinityfree"

echo "==> Building InfinityFree upload tree at $DEST"

rm -rf "$DEST"
mkdir -p "$DEST"

# App source (hardlinked for speed; safe because nothing edits in place)
for item in app bootstrap config database public resources routes; do
  cp -al "$ROOT/$item" "$DEST/$item"
done

# composer.json / composer.lock are NOT hardlinked: composer config + install
# mutate them, which must never reach back into the working tree
cp -p "$ROOT/composer.json" "$DEST/composer.json"
cp -p "$ROOT/composer.lock" "$DEST/composer.lock"
cp -p "$ROOT/artisan" "$DEST/artisan"
cp -a "$ROOT/.env.example" "$DEST/.env.example" 2>/dev/null || true

# public ships a symlink storage -> storage/app/public; drop it (INF: no symlinks,
# the .htaccess alias handles /storage/* instead)
rm -f "$DEST/public/storage"

# Drop the dev sqlite artifact
rm -f "$DEST/database/database.sqlite"

# Fresh, writable storage skeleton
mkdir -p "$DEST/storage/logs"
mkdir -p "$DEST/storage/app/public" "$DEST/storage/app/private"
mkdir -p "$DEST/storage/framework/cache/data" "$DEST/storage/framework/sessions" \
         "$DEST/storage/framework/testing" "$DEST/storage/framework/views"
echo '*' > "$DEST/storage/.gitignore"
echo '*' > "$DEST/storage/app/.gitignore"
echo '*' > "$DEST/storage/app/public/.gitignore"
echo '*' > "$DEST/storage/app/private/.gitignore"
echo '*' > "$DEST/storage/logs/.gitignore"
echo '*' > "$DEST/storage/framework/.gitignore"
echo '*' > "$DEST/storage/framework/cache/.gitignore"
echo '*' > "$DEST/storage/framework/sessions/.gitignore"
echo '*' > "$DEST/storage/framework/testing/.gitignore"
echo '*' > "$DEST/storage/framework/views/.gitignore"

echo "==>   tree assembled"

echo "==> Pruning dev packages + regenerating lightweight autoloader"
composer install --no-dev --no-progress --working-dir="$DEST" >/dev/null
composer config optimize-autoloader false --working-dir="$DEST"
composer dump-autoload --no-dev --working-dir="$DEST" >/dev/null

echo "==> Locating any PHP file over 1 MB or any file over 10 MB (INF hard drops)"
find "$DEST" -type f \( -name '*.php' -size +1M \) -printf 'BIG-PHP %s %p\n'
find "$DEST" -type f -size +10M -printf 'BIG-ANY %s %p\n'

echo "==> Done. Sanity:"
du -sh "$DEST"