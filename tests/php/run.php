<?php

declare(strict_types=1);

const ABSPATH = __DIR__ . '/';
const WPSLUG_PLUGIN_DIR = __DIR__ . '/../../';
const WPSLUG_VERSION = '1.2.2';
const DAY_IN_SECONDS = 86400;

$GLOBALS['wpslug_options'] = [];
$GLOBALS['wpslug_hooks'] = [];
$GLOBALS['wpslug_remote_args'] = null;
$GLOBALS['wpslug_posts'] = [];
$GLOBALS['wpslug_terms'] = [];
$GLOBALS['wpslug_remote_failure'] = false;
$GLOBALS['wpslug_wpmind_failure'] = false;
$GLOBALS['wpslug_post_updates'] = [];
$GLOBALS['wpslug_unique_slug_collision'] = false;

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['wpslug_hooks']['filter'][$hook][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    ];
}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['wpslug_hooks']['action'][$hook][] = $callback; }
function is_admin() { return false; }
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, is_array($args) ? $args : []); }
function get_option($name, $default = false) { return $name === 'wpslug_options' ? ($GLOBALS['wpslug_options'] ?: $default) : $default; }
function update_option($name, $value) { if ($name === 'wpslug_options') { $GLOBALS['wpslug_options'] = $value; } return true; }
function add_option($name, $value) { return update_option($name, $value); }
function delete_option($name) { return true; }
function home_url() { return 'https://example.test'; }
function current_time($type) { return '2026-08-09 00:00:00'; }
function wp_debug_backtrace_summary() { return 'test'; }
function __($text, $domain = null) { return $text; }
function sanitize_text_field($value) { return is_scalar($value) ? trim((string) $value) : ''; }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function get_post_types($args = []) { return ['post', 'page', 'product']; }
function get_taxonomies($args = []) { return ['category', 'post_tag', 'product_cat']; }
function get_post($id) { return $GLOBALS['wpslug_posts'][$id] ?? null; }
function get_term($id, $taxonomy = '') { return $GLOBALS['wpslug_terms'][$id] ?? null; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_unique_term_slug($slug, $term) { return $slug; }
function wp_unique_post_slug($slug, $post_id, $status, $type, $parent) {
    return $GLOBALS['wpslug_unique_slug_collision'] ? $slug . '-2' : $slug;
}
function doing_action($hook) { return false; }
function get_current_screen() { return null; }
function wp_remote_post($url, $args) { $GLOBALS['wpslug_remote_args'] = $args; if ($GLOBALS['wpslug_remote_failure']) { return new WP_Error('remote_failure', 'provider unavailable'); } return ['response' => ['code' => 200], 'body' => '{"data":{"translations":[{"translatedText":"Hello World"}]}}']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_rand($min, $max) { return $min; }
function get_transient($key) { return false; }
function set_transient($key, $value, $ttl) { return true; }
function wp_json_encode($value) { return json_encode($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wpmind_is_available() { return true; }
function wpmind_translate($text, $from = 'auto', $to = 'en', $options = []) { return $GLOBALS['wpslug_wpmind_failure'] ? new WP_Error('wpmind_provider_failure', 'provider unavailable') : 'semantic-translation'; }
function wpmind_pinyin($text, $options = []) { return 'wenpai-suge'; }
function current_user_can($capability, ...$args) { return true; }
function add_query_arg($key, $value, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . rawurlencode($key) . '=' . rawurlencode((string) $value); }
function wp_update_post($data) { $GLOBALS['wpslug_post_updates'][] = $data; if (isset($GLOBALS['wpslug_posts'][$data['ID']])) { $GLOBALS['wpslug_posts'][$data['ID']]->post_name = $data['post_name']; } return $data['ID']; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }

class WPSlug_Test_WPDB {
    public $posts = 'wp_posts';
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query) { return null; }
}
$GLOBALS['wpdb'] = new WPSlug_Test_WPDB();

class WP_Error {
    private $message;
    public function __construct($code = '', $message = '') { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}

require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-validator.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-settings.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-pinyin.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-optimizer.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-transliterator.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-translator.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-converter.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-core.php';
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-admin.php';

$tests = 0;
function check($condition, string $message): void {
    global $tests;
    $tests++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "ok {$tests} - {$message}\n";
}

$core = new WPSlug_Core();
check(empty($GLOBALS['wpslug_hooks']['filter']['sanitize_title']), 'does not globally intercept sanitize_title');
check(empty($GLOBALS['wpslug_hooks']['filter']['wp_unique_post_slug']), 'does not rewrite during uniqueness resolution');
check(empty($GLOBALS['wpslug_hooks']['action']['transition_post_status']), 'does not rewrite on publish transition');
check(
    $GLOBALS['wpslug_hooks']['filter']['wp_insert_post_data'][0]['accepted_args'] === 4,
    'registers all WordPress 6.0 post data arguments'
);

$base = ['post_title' => '文派素格', 'post_name' => 'manual-slug', 'post_type' => 'post'];
$out = $core->processPostData($base, ['post_name' => 'manual-slug', 'post_status' => 'auto-draft']);
check($out['post_name'] === 'manual-slug', 'preserves explicitly supplied post slug');

$GLOBALS['wpslug_posts'][42] = (object) ['ID' => 42, 'post_status' => 'publish', 'post_name' => 'customer-kept-slug'];
$persisted = $core->processPostData(
    ['post_title' => '改名后的标题', 'post_name' => 'customer-kept-slug', 'post_type' => 'post'],
    ['ID' => 42, 'post_status' => 'publish']
);
check($persisted['post_name'] === 'customer-kept-slug', 'never rewrites a persisted custom slug when the title changes');

$base['post_name'] = '';
$out = $core->processPostData($base, ['post_status' => 'auto-draft']);
check($out['post_name'] === 'wen-pai-su-ge', 'generates pinyin for a new post without a slug');

$wp60_insert = $core->processPostData(
    [
        'post_title' => '文派素格',
        'post_name' => '%e6%96%87%e6%b4%be%e7%b4%a0%e6%a0%bc',
        'post_type' => 'post',
    ],
    [
        'post_title' => '文派素格',
        'post_name' => '%e6%96%87%e6%b4%be%e7%b4%a0%e6%a0%bc',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    [
        'post_title' => '文派素格',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    false
);
check($wp60_insert['post_name'] === 'wen-pai-su-ge', 'converts a WordPress 6.0 title-derived slug on insert');

$GLOBALS['wpslug_unique_slug_collision'] = true;
$wp60_duplicate = $core->processPostData(
    [
        'post_title' => '文派素格',
        'post_name' => '%e6%96%87%e6%b4%be%e7%b4%a0%e6%a0%bc',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    [
        'post_title' => '文派素格',
        'post_name' => '%e6%96%87%e6%b4%be%e7%b4%a0%e6%a0%bc',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    [
        'post_title' => '文派素格',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    false
);
$GLOBALS['wpslug_unique_slug_collision'] = false;
check($wp60_duplicate['post_name'] === 'wen-pai-su-ge-2', 'uniquifies a converted slug after the post data filter');

$GLOBALS['wpslug_posts'][43] = (object) [
    'ID' => 43,
    'post_status' => 'auto-draft',
    'post_name' => 'auto-draft-custom',
];
$auto_draft = $core->processPostData(
    [
        'post_title' => '准备发布',
        'post_name' => 'auto-draft-custom',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    [
        'ID' => 43,
        'post_status' => 'publish',
    ],
    [
        'ID' => 43,
        'post_title' => '准备发布',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    true
);
check($auto_draft['post_name'] === 'auto-draft-custom', 'preserves a custom auto-draft slug on publication');

$zero_slug = $core->processPostData(
    [
        'post_title' => '数字零',
        'post_name' => '0',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    [
        'post_title' => '数字零',
        'post_name' => '0',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    [
        'post_title' => '数字零',
        'post_name' => '0',
        'post_type' => 'post',
        'post_status' => 'publish',
    ],
    false
);
check($zero_slug['post_name'] === '0', 'preserves the explicit slug string zero');

$GLOBALS['wpslug_terms'][7] = (object) ['slug' => 'kept-term'];
$term = $core->processTermDataUpdate(['name' => '新分类', 'slug' => 'kept-term'], 7, 'category', []);
check($term['slug'] === 'kept-term', 'preserves an existing term slug on name update');

$translator = new WPSlug_Translator();
$result = $translator->translate('你好', [
    'translation_service' => 'google',
    'google_api_key' => 'test-key',
    'translation_source_lang' => 'auto',
    'translation_target_lang' => 'en',
]);
check($result === 'Hello-World', 'normalizes a successful Google translation');
check(!array_key_exists('source', $GLOBALS['wpslug_remote_args']['body']), 'omits Google source when automatic detection is selected');
$GLOBALS['wpslug_remote_failure'] = true;
$fallback = $translator->translate('文派素格', [
    'translation_service' => 'google',
    'google_api_key' => 'test-key',
    'translation_source_lang' => 'auto',
    'translation_target_lang' => 'en',
]);
$GLOBALS['wpslug_remote_failure'] = false;
check($fallback === 'wen-pai-su-ge', 'falls back to local pinyin when a cloud API fails');
check(in_array('wpmind', $translator->getSupportedServices(), true), 'reports WPMind as a supported translation service');
check($translator->isServiceConfigured('wpmind', []), 'detects an available WPMind integration');
$wpmind = $translator->translate('文派素格', ['translation_service' => 'wpmind', 'translation_source_lang' => 'zh', 'translation_target_lang' => 'en']);
check($wpmind === 'semantic-translation', 'uses the current WPMind translation function contract');
$GLOBALS['wpslug_wpmind_failure'] = true;
$wpmind_fallback = $translator->translate('文派素格', ['translation_service' => 'wpmind', 'translation_source_lang' => 'zh', 'translation_target_lang' => 'en']);
$GLOBALS['wpslug_wpmind_failure'] = false;
check($wpmind_fallback === 'wen-pai-su-ge', 'falls back to local pinyin when WPMind returns WP_Error');
$semantic = (new WPSlug_Converter())->convert('文派素格', ['conversion_mode' => 'semantic_pinyin']);
check($semantic === 'wenpai-suge', 'uses the current WPMind semantic pinyin function contract');

$admin_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-admin.php');
check(strpos($admin_source, 'Received input data') === false, 'does not write API credentials to debug logs');
check(substr_count($admin_source, 'current_user_can("manage_options")') >= 3, 'protects settings page and AJAX endpoints with manage_options');

$settings = new WPSlug_Settings();
$GLOBALS['wpslug_options']['google_api_key'] = 'google-secret';
$GLOBALS['wpslug_options']['baidu_secret_key'] = 'baidu-secret';
$export = json_decode($settings->exportOptions(), true);
check(!isset($export['options']['google_api_key'], $export['options']['baidu_secret_key']), 'excludes translation secrets from settings exports');
$imported = $settings->importOptions(json_encode([
    'options' => [
        'enable_conversion' => true,
        'google_api_key' => 'injected-google',
        'baidu_secret_key' => 'injected-baidu',
    ],
]));
check($imported === true, 'imports a credential-free settings document');
check(
    $GLOBALS['wpslug_options']['google_api_key'] === 'google-secret' &&
    $GLOBALS['wpslug_options']['baidu_secret_key'] === 'baidu-secret',
    'ignores imported credentials and preserves site-local API secrets'
);
$redactor = new ReflectionMethod($settings, 'redactSensitiveContext');
$redactor->setAccessible(true);
$redacted = $redactor->invoke($settings, ['options' => ['google_api_key' => 'secret', 'mode' => 'translation']]);
check($redacted['options']['google_api_key'] === '[redacted]' && $redacted['options']['mode'] === 'translation', 'redacts nested credentials before persisting error context');

$updater_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'includes/class-wenpai-updater.php');
check(strpos($updater_source, 'str_starts_with') === false, 'keeps updater compatible with declared PHP 7.4 minimum');

$main_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'wpslug.php');
check(strpos($main_source, 'version_compare(PHP_VERSION, "7.4"') !== false, 'runtime PHP requirement matches plugin metadata');
check(strpos($main_source, 'require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-settings.php"') !== false, 'loads uninstall dependencies before deleting plugin options');

$release_source = file_get_contents(WPSLUG_PLUGIN_DIR . '.forgejo/workflows/release.yml');
check(strpos($release_source, 'DEPLOY_HOST') === false, 'release workflow does not deploy to a WordPress site');

$GLOBALS['wpslug_options'] = array_merge((new WPSlug_Settings())->getDefaults(), [
    'enable_conversion' => true,
    'auto_convert' => true,
    'enabled_post_types' => ['post'],
]);
$GLOBALS['wpslug_posts'][99] = (object) [
    'ID' => 99,
    'post_title' => '文派素格',
    'post_name' => 'legacy-slug',
    'post_type' => 'post',
    'post_status' => 'publish',
];
$GLOBALS['wpslug_post_updates'] = [];
$admin = new WPSlug_Admin();
$admin->handleBulkAction('/wp-admin/edit.php', 'wpslug-convert', [99]);
$admin->handleBulkAction('/wp-admin/edit.php', 'wpslug-convert', [99]);
check(count($GLOBALS['wpslug_post_updates']) === 1, 'bulk conversion is idempotent after the canonical slug is written');

echo "PASS: {$tests} assertions\n";
