#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
DEST="$ROOT/infinityfree"
LIVE_ENV="$ROOT/live.env"
FTP_INF="$ROOT/.ftp-inf"

PRUNE=0
DRY=0
for arg in "$@"; do
    case "$arg" in
        --prune) PRUNE=1 ;;
        --dry-run) DRY=1 ;;
        -h|--help)
            cat <<'HELP'
dev/infinityfree-sync.sh - incremental InfinityFree deploy via lftp mirror.

Usage:
  dev/infinityfree-sync.sh             rebuild + upload ONLY changed files
  dev/infinityfree-sync.sh --dry-run   preview what would change (no upload)
  dev/infinityfree-sync.sh --prune     also delete remote files absent locally

Safety:
  - Always rebuilds the tree from current source (infinityfree-prepare.sh)
  - Overlays live.env so DB creds / APP_KEY are never regenerated
  - NEVER touches htdocs/storage (server uploads/data excluded)
  - Removed files are only deleted from the server with --prune
  - Requires lftp (sudo apt install lftp) and .ftp-inf with real credentials
HELP
            exit 0 ;;
        *) echo "unknown option: $arg"; exit 1 ;;
    esac
done

for bin in php lftp; do
    command -v "$bin" >/dev/null 2>&1 || { echo "ERROR: missing '$bin'"; exit 1; }
done

echo "==> [1/4] Rebuilding upload tree from current source"
"$ROOT/dev/infinityfree-prepare.sh"

echo "==> [2/4] Overlaying live.env (keeps DB creds + APP_KEY stable)"
[ -f "$LIVE_ENV" ] || { echo "ERROR: $LIVE_ENV missing"; exit 1; }
cp "$LIVE_ENV" "$DEST/.env"

echo "==> [3/4] Loading FTP credentials"
[ -f "$FTP_INF" ] || { echo "ERROR: $FTP_INF missing"; exit 1; }
set -a; source "$FTP_INF"; set +a
if [[ -z "${INF_FTP_HOST:-}" || "${INF_FTP_HOST:-}" == *XXXX* \
      || -z "${INF_FTP_USER:-}" || "${INF_FTP_USER:-}" == *XXXX* \
      || -z "${INF_FTP_PASS:-}" || "${INF_FTP_PASS:-}" == *CHANGE_ME* \
      || -z "${INF_FTP_DIR:-}" ]]; then
    echo "ERROR: FTP credentials in $FTP_INF are still placeholders"; exit 1
fi

# Paths relative to the mirror root. storage/ and public/storage must NEVER be
# overwritten or deleted on the server (user uploads + runtime data).
EXCLUDES=(
    --exclude '^storage(/.*)?$'
    --exclude '^\.git(/.*)?$'
    --exclude '^public/storage(/.*)?$'
)

MIRROR_OPTS=(-R -n --continue -v)
[ "$PRUNE" = 1 ] && MIRROR_OPTS+=(--delete)
[ "$DRY" = 1 ] && MIRROR_OPTS+=(--dry-run)

echo "==> [4/4] Mirroring $DEST -> ftp://$INF_FTP_HOST/$INF_FTP_DIR"
lftp -u "$INF_FTP_USER,$INF_FTP_PASS" "$INF_FTP_HOST" \
    -e "set net:max-retries 3; set cmd:fail-exit yes; mirror ${MIRROR_OPTS[*]} ${EXCLUDES[*]} '$DEST' '$INF_FTP_DIR'; bye"

echo ""
echo "==> Sync complete. If this was the first push, verify the site"
echo "    (hard-refresh, test a profile-photo upload). Migrations still"
echo "    require: open .../setup_seed.php?token=<SETUP_TOKEN> then delete it."