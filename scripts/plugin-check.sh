#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="${1:-wpslug}"
shift || true

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

# WenPai plugins are self-hosted (updates.wenpai.net) and are NOT submitted to
# WordPress.org. Ignore ONLY these directory-policy codes; document in packaging SOP.
# Everything else (including warnings) fails the release gate.
#
# - plugin_updater_detected: intentional Update URI + includes/class-wenpai-updater.php
# - trademarked_term: "wp" in WPSlug / wpslug — product brand; not a WP.org listing
IGNORE_CODES="plugin_updater_detected,trademarked_term"

"${WP[@]}" plugin check "$PLUGIN_SLUG" \
  --mode=update \
  --ignore-codes="$IGNORE_CODES" \
  --exclude-directories=lib,tests,docs,scripts,vendor,node_modules,.git,.forgejo \
  --exclude-files=.ci-trigger,.wp-env.json,composer.json,composer.lock,package.json,package-lock.json,phpcs.xml.dist,phpstan.neon.dist,.gitignore,.git \
  --format=strict-table \
  "${ROOT_ARGS[@]}" \
  "$@"
