#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
DEST="$ROOT/infinityfree"
ZIP="$ROOT/infinityfree-deploy.zip"
LIVE_ENV="$ROOT/live.env"
FTP_INF="$ROOT/.ftp-inf"

echo "==> InfinityFree live deploy"

# ---- 0. Preflight ---------------------------------------------------------
for bin in php zip curl composer; do
    command -v "$bin" >/dev/null 2>&1 || { echo "ERROR: missing '$bin'"; exit 1; }
done

# ---- 1. Rebuild upload tree from current local source ----------------------
"$ROOT/dev/infinityfree-prepare.sh"

# ---- 2. Overlay the authoritative live .env (keeps real DB creds) ---------
if [ -f "$LIVE_ENV" ]; then
    cp "$LIVE_ENV" "$DEST/.env"
    echo "==> using live.env (DB creds preserved)"
else
    cp "$DEST/.env" "$LIVE_ENV"
    chmod 600 "$LIVE_ENV"
    echo "==> created live.env -> EDIT IT NOW with your real InfinityFree DB"
    echo "    creds (DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD)."
    echo "    It will be used for every future deploy and never committed."
fi

SETUP_TOKEN="$(grep -E '^SETUP_TOKEN=' "$DEST/.env" | cut -d= -f2-)"

# ---- 3. Pristine storage (no stale compiled views / dev logs) --------------
rm -f "$DEST"/storage/framework/views/*.php
rm -f "$DEST"/storage/logs/laravel.log
for d in storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs; do
    mkdir -p "$DEST/$d"
done

# ---- 4. Flat deploy zip (extracts directly into htdocs/) -------------------
rm -f "$ZIP"
( cd "$DEST" && zip -rq -y "$ZIP" . )
echo "==> built $(du -h "$ZIP" | cut -f1) $ZIP"

# ---- 5. FTP upload (single file; extract via INF File Manager) -------------
FTP_OK=0
if [ -f "$FTP_INF" ]; then
    set -a; source "$FTP_INF"; set +a
else
    cat > "$FTP_INF" << 'EOF'
# InfinityFree FTP login for dev/live-deploy.sh (never committed).
INF_FTP_HOST=ftpupload.net
INF_FTP_USER=if0_XXXXXXX
INF_FTP_PASS=CHANGE_ME
INF_FTP_DIR=htdocs
EOF
    chmod 600 "$FTP_INF"
    echo "==> created .ftp-inf -> EDIT it with your InfinityFree FTP login"
fi

if [[ "${INF_FTP_HOST:-}" == *XXXX* || "${INF_FTP_USER:-}" == *XXXX* || \
      "${INF_FTP_PASS:-}" == *CHANGE_ME* || -z "${INF_FTP_HOST:-}" ]]; then
    echo "==> FTP credentials still placeholders -> upload SKIPPED (zip below)"
else
    echo "==> uploading to ftp://$INF_FTP_HOST/$INF_FTP_DIR/infinityfree-deploy.zip"
    curl -sS --fail \
        -T "$ZIP" \
        "ftp://$INF_FTP_HOST/$INF_FTP_DIR/infinityfree-deploy.zip" \
        --user "$INF_FTP_USER:$INF_FTP_PASS"
    FTP_OK=1
    echo "==> upload OK"
fi

# ---- 6. Next steps ---------------------------------------------------------
echo ""
echo "==> To go live:"
echo "  1. INF File Manager: upload $ZIP"
[ "$FTP_OK" = 1 ] && echo "     (already on the server at htdocs/infinityfree-deploy.zip)"
echo "  2. Right-click it inside htdocs/ -> Extract (overwrites code, keeps storage/)"
echo "  3. If DB tables are new/missing, open:"
echo "     https://digitech-portal.freedev.app/setup_seed.php?token=$SETUP_TOKEN"
echo "     (expect MIGRATE OK / SEED OK / DONE)"
echo "  4. Delete public/setup_seed.php afterwards"
echo ""
echo "==> Done."