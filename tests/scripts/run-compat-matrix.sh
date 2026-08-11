#!/usr/bin/env bash
set -Eeuo pipefail

REPO=$(cd "$(dirname "$0")/../.." && pwd)
IMAGE=${WPSLUG_WORDPRESS_IMAGE:-wordpress:php7.4-apache}
WP_VERSION=${WPSLUG_WP_VERSION:-6.0.9}
PHP_PREFIX=${WPSLUG_PHP_PREFIX:-7.4.}
DB_IMAGE=mariadb:lts
CLI_PHAR=${WPSLUG_WP_CLI_PHAR:-$(command -v wp)}
run_id=${WPSLUG_MATRIX_RUN_ID:-$(date +%Y%m%d%H%M%S)-$$-$RANDOM}
LOG=${WPSLUG_MATRIX_LOG:-/tmp/wpslug-compat-matrix-${run_id}.log}
: >"$LOG"
exec > >(tee -a "$LOG") 2>&1

cleanup_project() {
  local project=$1
  docker rm -f "${project}-wp" "${project}-db" >/dev/null 2>&1 || true
  docker network rm "${project}-net" >/dev/null 2>&1 || true
  docker volume rm "${project}-html" "${project}-dbdata" >/dev/null 2>&1 || true
}
setup_project() {
  local project=$1 url=$2 title=$3 mode=$4
  docker network create "${project}-net" >/dev/null
  docker volume create "${project}-html" >/dev/null
  docker volume create "${project}-dbdata" >/dev/null
  docker run -d --name "${project}-db" --network "${project}-net" \
    -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wordpress \
    -e MARIADB_USER=wordpress -e MARIADB_PASSWORD=wordpress \
    -v "${project}-dbdata:/var/lib/mysql" "$DB_IMAGE" >/dev/null
  for _ in $(seq 1 90); do
    if docker exec "${project}-db" mariadb-admin ping -uroot -proot --silent >/dev/null 2>&1; then break; fi
    sleep 1
  done
  docker exec "${project}-db" mariadb-admin ping -uroot -proot --silent >/dev/null

  wp "$project" core download --version="$WP_VERSION" --force
  wp "$project" config create --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost="${project}-db" --skip-check
  if [[ "$mode" == multisite ]]; then
    wp "$project" core multisite-install --url="$url" --title="$title" --admin_user=admin --admin_password=password --admin_email=admin@example.test --subdomains=false --skip-email
  else
    wp "$project" core install --url="$url" --title="$title" --admin_user=admin --admin_password=password --admin_email=admin@example.test --skip-email
  fi
}
wp() {
  local project=$1
  shift
  docker run --rm --network "${project}-net" \
    -v "${project}-html:/var/www/html" \
    -v "$REPO:/var/www/html/wp-content/plugins/wpslug:ro" \
    -v "$CLI_PHAR:/tmp/wp-cli.phar:ro" \
    "$IMAGE" php -d memory_limit=512M /tmp/wp-cli.phar --allow-root "$@"
}
assert_eq() {
  local expected=$1 actual=$2 label=$3
  if [[ "$expected" != "$actual" ]]; then
    printf 'FAIL: %s expected=<%s> actual=<%s>\n' "$label" "$expected" "$actual"
    exit 1
  fi
  printf 'PASS: %s => %s\n' "$label" "$actual"
}
assert_contains() {
  local needle=$1 haystack=$2 label=$3
  if [[ "$haystack" != *"$needle"* ]]; then
    printf 'FAIL: %s missing=<%s> actual=<%s>\n' "$label" "$needle" "$haystack"
    exit 1
  fi
  printf 'PASS: %s contains %s\n' "$label" "$needle"
}

single="wpslug-r3-single-${run_id}"
multi="wpslug-r3-multi-${run_id}"
e2e_port=${WPSLUG_E2E_PORT:-8910}
mode=${WPSLUG_MATRIX_MODE:-all}
trap 'cleanup_project "$single"; cleanup_project "$multi"; rm -f "${LOG}.child-site-id"' EXIT

if [[ "$mode" != multisite ]]; then
  echo "=== WordPress ${WP_VERSION} / PHP ${PHP_PREFIX} ==="
  setup_project "$single" "http://localhost:${e2e_port}" WPSlug-Minimum single
assert_contains "$PHP_PREFIX" "$(wp "$single" eval 'echo PHP_VERSION;')" 'PHP runtime'
assert_eq "$WP_VERSION" "$(wp "$single" core version)" 'WordPress runtime'
wp "$single" plugin activate wpslug
wp "$single" plugin is-active wpslug
printf 'PASS: single-site activation\n'
single_id=$(wp "$single" post create --post_title='文派素格' --post_status=publish --porcelain)
assert_eq 'wen-pai-su-ge' "$(wp "$single" post get "$single_id" --field=post_name)" 'single-site pinyin conversion'
duplicate_id=$(wp "$single" post create --post_title='文派素格' --post_status=publish --porcelain)
assert_eq 'wen-pai-su-ge-2' "$(wp "$single" post get "$duplicate_id" --field=post_name)" 'single-site converted slug uniqueness'
custom_id=$(wp "$single" post create --post_title='原始标题' --post_name='kept-custom-slug' --post_status=publish --porcelain)
wp "$single" post update "$custom_id" --post_title='修改后的标题' >/dev/null
assert_eq 'kept-custom-slug' "$(wp "$single" post get "$custom_id" --field=post_name)" 'single-site custom slug preservation'
auto_draft_id=$(wp "$single" post create --post_title='自动草稿' --post_name='auto-draft-custom' --post_status=auto-draft --porcelain)
wp "$single" post update "$auto_draft_id" --post_title='自动草稿发布' --post_status=publish >/dev/null
assert_eq 'auto-draft-custom' "$(wp "$single" post get "$auto_draft_id" --field=post_name)" 'single-site auto-draft custom slug preservation'
wp "$single" plugin deactivate wpslug
if wp "$single" plugin is-active wpslug >/dev/null 2>&1; then echo 'FAIL: single-site deactivation'; exit 1; fi
printf 'PASS: single-site deactivation\n'
assert_eq 'wen-pai-su-ge' "$(wp "$single" post get "$single_id" --field=post_name)" 'deactivation retains content slug'
if [[ "$mode" == e2e ]]; then
  wp "$single" plugin activate wpslug
  docker run -d --name "${single}-wp" --network "${single}-net" -p "${e2e_port}:80" \
    -e WORDPRESS_DB_HOST="${single}-db" -e WORDPRESS_DB_USER=wordpress \
    -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
    -v "${single}-html:/var/www/html" \
    -v "$REPO:/var/www/html/wp-content/plugins/wpslug:ro" \
    "$IMAGE" >/dev/null
  for _ in $(seq 1 90); do
    if curl -fsS http://127.0.0.1:${e2e_port}/wp-login.php >/dev/null 2>&1; then break; fi
    sleep 1
  done
  curl -fsS http://127.0.0.1:${e2e_port}/wp-login.php >/dev/null
  (
    cd "$REPO"
    WP_BASE_URL="http://localhost:${e2e_port}" npx playwright test
  )
  echo 'COMPAT_E2E_PASS'
  exit 0
fi
cleanup_project "$single"
fi
if [[ "$mode" == single ]]; then
  echo 'COMPAT_MATRIX_PASS'
  exit 0
fi

echo "=== multisite network lifecycle: WordPress ${WP_VERSION} / PHP ${PHP_PREFIX} ==="
setup_project "$multi" http://localhost:8911 WPSlug-Multisite multisite
assert_contains "$PHP_PREFIX" "$(wp "$multi" eval 'echo PHP_VERSION;')" 'multisite PHP runtime'
assert_eq "$WP_VERSION" "$(wp "$multi" core version)" 'multisite WordPress runtime'
wp "$multi" plugin activate wpslug --network
wp "$multi" plugin is-active wpslug --network
printf 'PASS: network activation\n'
wp "$multi" site create --slug=child --title=Child --email=child@example.test --porcelain | tr -d '\r' | tee "${LOG}.child-site-id"
child_site_id=$(tail -n1 "${LOG}.child-site-id")
assert_eq 2 "$child_site_id" 'subsite creation'
child_url=$(wp "$multi" eval 'switch_to_blog(2); echo home_url("/"); restore_current_blog();')
printf 'INFO: child URL => %s\n' "$child_url"
assert_eq 2 "$(wp "$multi" eval 'echo get_current_blog_id();' --url="$child_url")" 'subsite URL selects blog 2'
wp "$multi" option update wpslug_cache root --url=http://localhost:8911 >/dev/null
wp "$multi" option update wpslug_cache child --url="$child_url" >/dev/null
wp "$multi" option update wpslugger_round3_marker keep-root --url=http://localhost:8911 >/dev/null
wp "$multi" option update wpslugger_round3_marker keep-child --url="$child_url" >/dev/null
child_id=$(wp "$multi" post create --url="$child_url" --post_title='文派素格' --post_status=publish --porcelain)
assert_eq 'wen-pai-su-ge' "$(wp "$multi" post get "$child_id" --url="$child_url" --field=post_name)" 'subsite pinyin conversion'
child_custom_id=$(wp "$multi" post create --url="$child_url" --post_title='子站原始标题' --post_name='child-custom-slug' --post_status=publish --porcelain)
wp "$multi" post update "$child_custom_id" --url="$child_url" --post_title='子站修改标题' >/dev/null
assert_eq 'child-custom-slug' "$(wp "$multi" post get "$child_custom_id" --url="$child_url" --field=post_name)" 'subsite custom slug preservation'
wp "$multi" plugin deactivate wpslug --network
if wp "$multi" plugin is-active wpslug --network >/dev/null 2>&1; then
  echo 'FAIL: network deactivation'; exit 1
fi
printf 'PASS: network deactivation\n'
assert_eq root "$(wp "$multi" option get wpslug_cache --url=http://localhost:8911)" 'network deactivation retains root plugin data'
assert_eq child "$(wp "$multi" option get wpslug_cache --url="$child_url")" 'network deactivation retains child plugin data'
assert_eq 'wen-pai-su-ge' "$(wp "$multi" post get "$child_id" --url="$child_url" --field=post_name)" 'network deactivation retains child content slug'

uninstall_state=$(wp "$multi" eval '$before = get_current_blog_id(); require WP_PLUGIN_DIR . "/wpslug/wpslug.php"; WPSlug::uninstall(); echo $before . ":" . get_current_blog_id() . ":" . (ms_is_switched() ? "1" : "0");' --url=http://localhost:8911)
assert_eq '1:1:0' "$uninstall_state" 'uninstall restores the original blog and switch stack'
root_plugin_cache=$(wp "$multi" eval 'echo get_option("wpslug_cache", "missing");' --url=http://localhost:8911)
child_plugin_cache=$(wp "$multi" eval 'echo get_option("wpslug_cache", "missing");' --url="$child_url")
assert_eq missing "$root_plugin_cache" 'uninstall cleans root plugin options'
assert_eq missing "$child_plugin_cache" 'uninstall cleans child plugin options'
assert_eq keep-root "$(wp "$multi" option get wpslugger_round3_marker --url=http://localhost:8911)" 'uninstall retains adjacent root namespace'
assert_eq keep-child "$(wp "$multi" option get wpslugger_round3_marker --url="$child_url")" 'uninstall retains adjacent child namespace'
assert_eq 'wen-pai-su-ge' "$(wp "$multi" post get "$child_id" --url="$child_url" --field=post_name)" 'uninstall retains generated content slug'
assert_eq 'child-custom-slug' "$(wp "$multi" post get "$child_custom_id" --url="$child_url" --field=post_name)" 'uninstall retains custom content slug'

echo 'COMPAT_MATRIX_PASS'
