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

# WPSlug is distributed from FeiCode and deliberately uses a self-hosted
# Update URI. The brand is established. Ignore only those WordPress.org
# directory-policy codes; all remaining release-package errors are fatal.
"${WP[@]}" plugin check "$PLUGIN_SLUG" \
  --mode=update \
  --ignore-codes=plugin_updater_detected,trademarked_term \
  --ignore-warnings \
  --exclude-directories=lib,tests,docs,scripts,vendor,node_modules \
  --exclude-files=.ci-trigger,.wp-env.json,composer.json,composer.lock,package.json,package-lock.json,phpcs.xml.dist,phpstan.neon.dist \
  --format=strict-table \
  "${ROOT_ARGS[@]}" \
  "$@"
