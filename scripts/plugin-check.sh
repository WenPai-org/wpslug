#!/usr/bin/env bash
set -euo pipefail

# Release Plugin Check for WenPai self-hosted plugins.
# Default: candidate zip → extract into WP plugins → check THAT tree.
# Do NOT run the WP admin UI checker against the full git worktree
# (tests/scripts/dist/.dotfiles flood false positives).
#
# Ignore ONLY (not submitted to WordPress.org):
#   plugin_updater_detected  — WenPai updater + Update URI
#   trademarked_term         — "wp" in WPSlug / wpslug brand
#
# Usage:
#   bash scripts/plugin-check.sh [--path=<WP根>] [--zip=dist/foo.zip]
#   WPSLUG_SKIP_ZIP=1 bash scripts/plugin-check.sh --path=<WP根>

PLUGIN_SLUG="wpslug"
WP_PATH=""
ZIP_PATH=""
EXTRA=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    wpslug) PLUGIN_SLUG="$1"; shift ;;
    --path=*) WP_PATH="${1#--path=}"; shift ;;
    --path) WP_PATH="${2:-}"; shift 2 ;;
    --zip=*) ZIP_PATH="${1#--zip=}"; shift ;;
    --zip) ZIP_PATH="${2:-}"; shift 2 ;;
    *) EXTRA+=("$1"); shift ;;
  esac
done

if command -v wp >/dev/null 2>&1; then
  WP=(wp)
elif [[ -f /var/www/html/wp-cli.phar ]]; then
  WP=(php /var/www/html/wp-cli.phar)
else
  echo "wp-cli is required" >&2
  exit 127
fi

ROOT_ARGS=()
if [[ "$(id -u)" -eq 0 ]]; then
  ROOT_ARGS+=(--allow-root)
fi

IGNORE_CODES="plugin_updater_detected,trademarked_term"
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_PATH="${WP_PATH:-${WENPAI_PLUGIN_CHECK_WP:-${WPSLUG_WP_PATH:-}}}"
if [[ -z "$WP_PATH" ]]; then
  for cand in "$HOME/Studio/wpsss-dev" "$HOME/Studio/wpslug-dev"; do
    if [[ -d "$cand/wp-content/plugins/plugin-check" ]]; then
      WP_PATH="$cand"
      break
    fi
  done
fi
if [[ -z "$WP_PATH" ]]; then
  echo "WP path required (--path or WENPAI_PLUGIN_CHECK_WP)" >&2
  exit 2
fi

PLUGINS_DIR="$WP_PATH/wp-content/plugins"
LIVE="$PLUGINS_DIR/$PLUGIN_SLUG"
BACKUP=""
STAGED=0

restore() {
  if [[ "$STAGED" -eq 1 ]]; then
    rm -rf "$LIVE"
    if [[ -n "$BACKUP" && -e "$BACKUP" ]]; then
      mv "$BACKUP" "$LIVE"
    fi
  fi
}
trap restore EXIT

if [[ "${WPSLUG_SKIP_ZIP:-}" != "1" ]]; then
  if [[ -z "$ZIP_PATH" ]]; then
    VER=$(php -r 'preg_match("/Version:\\s*(\\S+)/", file_get_contents("'"$REPO_ROOT"'/wpslug.php"), $m); echo $m[1];')
    ZIP_PATH="$REPO_ROOT/dist/${PLUGIN_SLUG}-${VER}-candidate.zip"
    if [[ ! -f "$ZIP_PATH" ]]; then
      python3 "$REPO_ROOT/scripts/build-candidate.py" --version "$VER"
    fi
  fi
  [[ -f "$ZIP_PATH" ]] || { echo "zip missing: $ZIP_PATH" >&2; exit 2; }
  STAGE="$(mktemp -d /tmp/wpslug-pcp.XXXXXX)"
  unzip -q -o "$ZIP_PATH" -d "$STAGE"
  [[ -d "$STAGE/$PLUGIN_SLUG" ]] || { echo "zip missing $PLUGIN_SLUG/" >&2; exit 2; }
  if [[ -e "$LIVE" || -L "$LIVE" ]]; then
    BACKUP="$PLUGINS_DIR/.wpslug-pcp-backup-$$"
    mv "$LIVE" "$BACKUP"
  fi
  mv "$STAGE/$PLUGIN_SLUG" "$LIVE"
  rm -rf "$STAGE"
  STAGED=1
fi

"${WP[@]}" plugin activate plugin-check --path="$WP_PATH" "${ROOT_ARGS[@]}" >/dev/null 2>&1 || true
"${WP[@]}" plugin activate "$PLUGIN_SLUG" --path="$WP_PATH" "${ROOT_ARGS[@]}" >/dev/null 2>&1 || true

"${WP[@]}" plugin check "$PLUGIN_SLUG" \
  --path="$WP_PATH" \
  --mode=update \
  --ignore-codes="$IGNORE_CODES" \
  --format=strict-table \
  "${ROOT_ARGS[@]}" \
  "${EXTRA[@]}"
