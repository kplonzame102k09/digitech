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

# Drop vite dev-server artifacts (a stale public/hot would break asset URLs on INF)
rm -f "$DEST/public/hot" "$DEST/public/fonts-manifest.dev.json"

# Web-root .htaccess: root index.php hands off to public/, alias /storage/*,
# deny dotfiles
cat > "$DEST/.htaccess" << 'EOF'
DirectoryIndex index.php
Options -Indexes

RewriteEngine On

# Deny direct access to dotfiles (e.g. .env, .git, backups)
RewriteRule (^\.|/\.) - [F,L]


# Static assets live under public/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(css|js|build|fonts|images|favicon\.ico)(/.*)?$ public/$0 [L]

# Laravel front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [L]
EOF

# Web-root front controller: satisfies INF's "index file in htdocs" check and
# boots Laravel through its normal public/index.php (paths stay /public)
cat > "$DEST/index.php" << 'EOF'
<?php

require __DIR__.'/public/index.php';
EOF

# Temporary, token-guarded one-shot seeder (migrate --force + db:seed over HTTPS)
cat > "$DEST/public/setup_seed.php" << 'EOF'
<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = $_GET['token'] ?? '';
if ($token === '' || ! hash_equals((string) env('SETUP_TOKEN', ''), $token)) {
    http_response_code(403);
    exit('forbidden');
}

set_time_limit(600);
header('Content-Type: text/plain');

try {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    echo "MIGRATE OK\n";
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
    echo "SEED OK\n";
    echo "DONE\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: '.$e->getMessage()."\n";
}
EOF

# INF-ready .env: real APP_KEY + SETUP_TOKEN, production URL, placeholder DB
# (user fills in their InfinityFree MySQL details), LiveKit + mail from the
# working .env so video & password resets keep working, sync queue (INF has no
# job worker/cron).
getenv() {
    grep -E "^$1=" "$ROOT/.env" 2>/dev/null | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'
}

APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
SETUP_TOKEN="$(php -r 'echo bin2hex(random_bytes(16));')"

livekit_url="$(getenv LIVEKIT_URL)"
livekit_key="$(getenv LIVEKIT_API_KEY)"
livekit_secret="$(getenv LIVEKIT_API_SECRET)"

mail_host="$(getenv MAIL_HOST)"
mail_port="$(getenv MAIL_PORT)"
mail_user="$(getenv MAIL_USERNAME)"
mail_pass="$(getenv MAIL_PASSWORD)"
mail_enc="$(getenv MAIL_ENCRYPTION)"
mail_from="$(getenv MAIL_FROM_ADDRESS)"
mail_name="$(getenv MAIL_FROM_NAME)"

cat > "$DEST/.env" << EOF
APP_NAME=Laravel
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=https://digitech-portal.freedev.app

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

# Admin account seeded by db:seed. Leave blank to use the config defaults.
ADMIN_SEED_USER_ID=ADMIN-000001
ADMIN_SEED_EMAIL=admin@gmail.com
ADMIN_SEED_PASSWORD=password

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Guard for the one-shot seeder at public/setup_seed.php?token=
SETUP_TOKEN=$SETUP_TOKEN

# --- InfinityFree MySQL: fill in the values from your INF control panel ---
DB_CONNECTION=mysql
DB_HOST=sql213.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_42951315_digitech_db
DB_USERNAME=if0_42951315
DB_PASSWORD=WtRLbwoNF0t

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

# Self-hosted LiveKit SFU for per-classroom video (Meet-style, no recording).
LIVEKIT_URL=$livekit_url
LIVEKIT_API_KEY=$livekit_key
LIVEKIT_API_SECRET=$livekit_secret

CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# SMTP via the working gmail sender. If InfinityFree blocks outbound mail,
# switch MAIL_MAILER back to log.
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST="$mail_host"
MAIL_PORT="$mail_port"
MAIL_USERNAME="$mail_user"
MAIL_PASSWORD="$mail_pass"
MAIL_ENCRYPTION="$mail_enc"
MAIL_FROM_ADDRESS="$mail_from"
MAIL_FROM_NAME="\${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="\${APP_NAME}"
EOF

echo "==>   tree assembled (APP_KEY + SETUP_TOKEN generated)"

echo "==> Pruning dev packages + regenerating lightweight autoloader"
composer install --no-dev --no-progress --working-dir="$DEST" >/dev/null
composer config optimize-autoloader false --working-dir="$DEST"
composer dump-autoload --no-dev --working-dir="$DEST" >/dev/null

echo "==> Locating any PHP file over 1 MB or any file over 10 MB (INF hard drops)"
find "$DEST" -type f \( -name '*.php' -size +1M \) -printf 'BIG-PHP %s %p\n'
find "$DEST" -type f -size +10M -printf 'BIG-ANY %s %p\n'

echo "==> Done. Sanity:"
du -sh "$DEST"