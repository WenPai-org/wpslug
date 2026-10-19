<?php

declare(strict_types=1);

const ABSPATH = __DIR__ . '/';
const WPSLUG_PLUGIN_DIR = __DIR__ . '/../../';
const WPSLUG_VERSION = '1.2.4';
const WPSLUG_PLUGIN_BASENAME = 'wpslug/wpslug.php';
const DAY_IN_SECONDS = 86400;

$GLOBALS['wpslug_options'] = [];
$GLOBALS['wpslug_hooks'] = [];
$GLOBALS['wpslug_remote_args'] = null;
$GLOBALS['wpslug_posts'] = [];
$GLOBALS['wpslug_terms'] = [];
$GLOBALS['wpslug_remote_failure'] = false;
$GLOBALS['wpslug_wpmind_failure'] = false;
$GLOBALS['wpslug_wpmind_error_code'] = 'wpmind_provider_failure';
$GLOBALS['wpslug_wpmind_last_options'] = null;
$GLOBALS['wpslug_wpmind_pinyin_last_options'] = null;
$GLOBALS['wpslug_transients'] = [];
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
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
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
function wp_remote_post($url, $args) { $GLOBALS['wpslug_remote_args'] = $args; if ($GLOBALS['wpslug_remote_failure']) { return new WP_Error('remote_failure', 'provider unavailable'); } return ['response' => ['code' => 200], 'body' => $GLOBALS['wpslug_remote_body'] ?? '{"data":{"translations":[{"translatedText":"Hello World"}]}}']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_rand($min, $max) { return $min; }
function get_transient($key) { return array_key_exists($key, $GLOBALS['wpslug_transients']) ? $GLOBALS['wpslug_transients'][$key] : false; }
function set_transient($key, $value, $ttl) { $GLOBALS['wpslug_transients'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['wpslug_transients'][$key]); return true; }
function wp_json_encode($value) { return json_encode($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wpmind_is_available() { return true; }
function wpmind_translate($text, $from = 'auto', $to = 'en', $options = []) {
    $GLOBALS['wpslug_wpmind_last_options'] = $options;
    if ($GLOBALS['wpslug_wpmind_failure']) {
        return new WP_Error($GLOBALS['wpslug_wpmind_error_code'], 'provider unavailable');
    }
    return 'semantic-translation';
}
function wpmind_pinyin($text, $options = []) {
    $GLOBALS['wpslug_wpmind_pinyin_last_options'] = $options;
    if ($GLOBALS['wpslug_wpmind_failure']) {
        return new WP_Error($GLOBALS['wpslug_wpmind_error_code'], 'provider unavailable');
    }
    return 'wenpai-suge';
}
function current_user_can($capability, ...$args) { return $GLOBALS['wpslug_can'] ?? true; }
function add_query_arg($key, $value, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . rawurlencode($key) . '=' . rawurlencode((string) $value); }
function wp_update_post($data) { $GLOBALS['wpslug_post_updates'][] = $data; if (isset($GLOBALS['wpslug_posts'][$data['ID']])) { $GLOBALS['wpslug_posts'][$data['ID']]->post_name = $data['post_name']; } return $data['ID']; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_html__($text, $domain = null) { return esc_html(__($text, $domain)); }
function esc_url($url) { return (string) $url; }

class WPSlug_Test_WPDB {
    public $posts = 'wp_posts';
    public function prepare($query, ...$args) { return $query; }
    public function get_var($query) { return null; }
}
$GLOBALS['wpdb'] = new WPSlug_Test_WPDB();

class WP_Query {
    public $posts = [];
    public function __construct($args = []) {
        foreach ($GLOBALS["wpslug_posts"] as $id => $post) {
            if (($post->post_name ?? null) !== ($args["name"] ?? null)) { continue; }
            if (isset($args["post_type"], $post->post_type) && $post->post_type !== $args["post_type"]) { continue; }
            $this->posts[] = (int) $id;
        }
    }
}

class WP_Error {
    private $code;
    private $message;
    public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
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
require WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-cli.php';

$tests = 0;
if (!class_exists("WP_Query")) {
    class WP_Query {
        public $posts = [];
        public function __construct($args = []) {
            foreach (($GLOBALS["wpslug_posts"] ?? []) as $id => $p) {
                if (($p->post_name ?? null) === ($args["name"] ?? null) && (empty($args["post_type"]) || ($p->post_type ?? "post") === $args["post_type"])) {
                    $this->posts[] = (int) $id;
                }
            }
        }
    }
}

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
$base['post_status'] = 'publish';
$out = $core->processPostData($base, ['post_status' => 'publish']);
check($out['post_name'] === 'wen-pai-su-ge', 'generates pinyin for a new published post without a slug');

$draft_skip = $core->processPostData(
    [
        'post_title' => '文派素格',
        'post_name' => '',
        'post_type' => 'post',
        'post_status' => 'draft',
    ],
    ['post_status' => 'draft', 'post_type' => 'post']
);
check($draft_skip['post_name'] === '', 'skips conversion on draft when convert_on_publish_only is enabled');

$GLOBALS['wpslug_posts'][44] = (object) [
    'ID' => 44,
    'post_status' => 'auto-draft',
    'post_name' => 'zi-dong-cao-gao',
    'post_type' => 'post',
];
$draft_clear = $core->processPostData(
    [
        'post_title' => '文派素格',
        'post_name' => 'zi-dong-cao-gao',
        'post_type' => 'post',
        'post_status' => 'draft',
    ],
    [
        'ID' => 44,
        'post_status' => 'draft',
        'post_name' => 'zi-dong-cao-gao',
    ],
    [
        'ID' => 44,
        'post_title' => '文派素格',
        'post_type' => 'post',
        'post_status' => 'draft',
        'post_name' => 'zi-dong-cao-gao',
    ],
    true
);
check($draft_clear['post_name'] === '', 'clears Auto Draft pinyin when leaving auto-draft as draft without converting');

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
check($auto_draft['post_name'] === 'zhun-bei-fa-bu', 'converts leftover auto-draft slug from the real title on publication');

$placeholder = $core->processPostData(
    [
        'post_title' => '自动草稿',
        'post_name' => '',
        'post_type' => 'post',
        'post_status' => 'auto-draft',
    ],
    [
        'post_status' => 'auto-draft',
        'post_type' => 'post',
    ]
);
check($placeholder['post_name'] === '', 'does not convert Auto Draft placeholder title into pinyin');

$GLOBALS['wpslug_options'] = array_merge(
    (array) $GLOBALS['wpslug_options'],
    ['enabled_post_types' => ['post', 'page', 'item']]
);
$GLOBALS['wpslug_posts'][22] = (object) [
    'ID' => 22,
    'post_status' => 'auto-draft',
    'post_name' => 'zi-dong-cao-gao-22',
    'post_type' => 'item',
];
$cpt_publish = $core->processPostData(
    [
        'post_title' => '互感器',
        'post_name' => 'zi-dong-cao-gao-22',
        'post_type' => 'item',
        'post_status' => 'publish',
    ],
    [
        'ID' => 22,
        'post_status' => 'publish',
        'post_type' => 'item',
        'post_name' => 'zi-dong-cao-gao-22',
    ],
    [
        'ID' => 22,
        'post_title' => '互感器',
        'post_type' => 'item',
        'post_status' => 'publish',
        'post_name' => 'zi-dong-cao-gao-22',
    ],
    true
);
check($cpt_publish['post_name'] === 'hu-gan-qi', 'regenerates a CPT slug from the real title instead of Auto Draft pinyin');

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
check(
    is_array($GLOBALS['wpslug_wpmind_last_options']) && ($GLOBALS['wpslug_wpmind_last_options']['context'] ?? '') === 'wpslug_seo_slug',
    'passes stable wpslug_seo_slug context to WPMind translate'
);
$GLOBALS['wpslug_transients'] = [];
$GLOBALS['wpslug_wpmind_failure'] = true;
$wpmind_fallback = $translator->translate('文派素格', ['translation_service' => 'wpmind', 'translation_source_lang' => 'zh', 'translation_target_lang' => 'en']);
$GLOBALS['wpslug_wpmind_failure'] = false;
check($wpmind_fallback === 'wen-pai-su-ge', 'falls back to local pinyin when WPMind returns WP_Error');

$GLOBALS['wpslug_transients'] = [];
$GLOBALS['wpslug_wpmind_failure'] = true;
$GLOBALS['wpslug_wpmind_error_code'] = 'wpmind_budget_exceeded';
$quota_fallback = $translator->translate('文派素格', ['translation_service' => 'wpmind', 'translation_source_lang' => 'zh', 'translation_target_lang' => 'en']);
$GLOBALS['wpslug_wpmind_failure'] = false;
$GLOBALS['wpslug_wpmind_error_code'] = 'wpmind_provider_failure';
check($quota_fallback === 'wen-pai-su-ge', 'falls back to local pinyin when WPMind budget is exceeded');
$quota_notice = (new WPSlug_Settings())->pullWpmindQuotaNotice();
check(
    is_array($quota_notice) && ($quota_notice['code'] ?? '') === 'wpmind_budget_exceeded',
    'records a WPMind quota notice for the admin UI'
);

$semantic = (new WPSlug_Converter())->convert('文派素格', ['conversion_mode' => 'semantic_pinyin']);
check($semantic === 'wenpai-suge', 'uses the current WPMind semantic pinyin function contract');
check(
    is_array($GLOBALS['wpslug_wpmind_pinyin_last_options']) && ($GLOBALS['wpslug_wpmind_pinyin_last_options']['context'] ?? '') === 'wpslug_semantic_pinyin',
    'passes stable wpslug_semantic_pinyin context to WPMind pinyin'
);

$admin_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-admin.php');
check(strpos($admin_source, 'Received input data') === false, 'does not write API credentials to debug logs');
check(substr_count($admin_source, 'current_user_can("manage_options")') >= 3, 'protects settings page and AJAX endpoints with manage_options');
check(strpos($admin_source, 'https://wpcy.com/c/wpslug/') === false, 'does not link support to the missing community URL');
$admin_ui = $admin_source . file_get_contents(WPSLUG_PLUGIN_DIR . 'templates/admin/page.php');
check(substr_count($admin_ui, 'https://wpcy.com/slug') >= 2, 'points documentation and support at wpcy.com/slug');
check(strpos($admin_ui, 'convert_on_publish_only') !== false, 'exposes convert-on-publish-only in the admin UI');
check(strpos($admin_source, 'Bulk Convert is an explicit migration') !== false, 'tips that bulk convert is an explicit migration');
check(strpos($admin_source, 'WPMind quota or budget was exceeded') !== false, 'surfaces WPMind quota notices in admin');
check(strpos($admin_source, 'wpslug_editor_preview') !== false, 'registers editor slug preview AJAX');
check(strpos($admin_source, '用 AI 生成') !== false, 'exposes AI generate preview button copy');
check(strpos($admin_source, '用语义拼音') !== false, 'exposes semantic pinyin preview button copy');
check(strpos($admin_source, 'post_type_modes') !== false, 'exposes per-post-type default strategy UI');
check(file_exists(WPSLUG_PLUGIN_DIR . 'assets/editor.js'), 'ships editor.js for slug preview controls');
check(file_exists(WPSLUG_PLUGIN_DIR . 'assets/editor.css'), 'ships editor.css for slug preview controls');

$settings_defaults = (new WPSlug_Settings())->getDefaults();
check(!empty($settings_defaults['convert_on_publish_only']), 'defaults convert_on_publish_only to enabled');
check(array_key_exists('post_type_modes', $settings_defaults), 'defaults include post_type_modes map');
$modes = (new WPSlug_Settings())->getConversionModes();
check($modes['pinyin'] === 'Local pinyin', 'labels local pinyin without fallback parenthetical');
check(($modes['semantic_pinyin'] ?? '') === 'Semantic pinyin (XinSi AI)' || ($modes['semantic_pinyin'] ?? '') === 'Semantic pinyin (requires WenPai XinSi)', 'labels semantic pinyin as XinSi AI');

$feature_options = (new WPSlug_Settings())->applyFeatureToOptions(
    ['conversion_mode' => 'pinyin', 'translation_service' => 'none'],
    'seo_slug'
);
check(
    $feature_options['conversion_mode'] === 'translation' && $feature_options['translation_service'] === 'wpmind',
    'maps seo_slug feature to WPMind translation options'
);
$GLOBALS['wpslug_options'] = array_merge((new WPSlug_Settings())->getDefaults(), [
    'enable_conversion' => true,
    'auto_convert' => true,
    'convert_on_publish_only' => true,
    'conversion_mode' => 'pinyin',
    'enabled_post_types' => ['post', 'product'],
    'post_type_modes' => ['product' => 'semantic_pinyin'],
]);
$product_options = (new WPSlug_Settings())->resolveOptionsForPostType('product');
check($product_options['conversion_mode'] === 'semantic_pinyin', 'resolves product CPT to semantic pinyin by post-type map');
$post_options = (new WPSlug_Settings())->resolveOptionsForPostType('post');
check($post_options['conversion_mode'] === 'pinyin', 'inherits global mode when post-type map has no override');

$GLOBALS['wpslug_wpmind_pinyin_last_options'] = null;
$product_slug = $core->processPostData(
    [
        'post_title' => '文派素格',
        'post_name' => '',
        'post_type' => 'product',
        'post_status' => 'publish',
    ],
    ['post_status' => 'publish', 'post_type' => 'product']
);
check($product_slug['post_name'] === 'wenpai-suge', 'uses post-type semantic pinyin when publishing a product');
check(
    is_array($GLOBALS['wpslug_wpmind_pinyin_last_options']),
    'product publish path called WPMind semantic pinyin'
);

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
require_once WPSLUG_PLUGIN_DIR . 'includes/class-wenpai-updater.php';
$normalized_assets = WPSlug_Updater::normalize_update_assets(
    [
        '1x' => 'https://updates.wenpai.net/assets/wpslug/icon-128x128.png',
        '2x' => 'https://updates.wenpai.net/assets/wpslug/icon-256x256.png',
        'svg' => 'https://updates.wenpai.net/assets/wpslug/icon-256x256.svg',
    ],
    ''
);
check(!isset($normalized_assets['icons']['svg']), 'drops the 404 svg update icon');
check($normalized_assets['icons']['1x'] === 'https://updates.wenpai.net/assets/wpslug/icon-128x128.png', 'keeps png update icons');
check($normalized_assets['url'] === 'https://wpcy.com/slug', 'uses wpcy.com/slug when the update URL is empty');

$main_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'wpslug.php');
check(strpos($main_source, 'version_compare(PHP_VERSION, "7.4"') !== false, 'runtime PHP requirement matches plugin metadata');
check(strpos($main_source, 'require_once WPSLUG_PLUGIN_DIR . "includes/class-wpslug-settings.php"') !== false, 'loads uninstall dependencies before deleting plugin options');

$release_source = file_get_contents(WPSLUG_PLUGIN_DIR . '.forgejo/workflows/release.yml');
check(strpos($release_source, 'DEPLOY_HOST') === false, 'release workflow does not deploy to a WordPress site');

$pack_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'scripts/build-candidate.py');
check(strpos($pack_source, '"lib/wenpai-admin-ui/"') !== false, 'candidate zip allowlist includes vendored admin kit');
check(strpos($pack_source, 'lib/plugin-update-checker/') !== false, 'candidate zip forbids plugin-update-checker');
check(strpos($pack_source, 'lib/updatepulse-updater/') !== false, 'candidate zip forbids updatepulse-updater');
check(strpos($main_source, 'includes/class-wenpai-updater.php') !== false, 'bootstrap loads WenPai Bridge updater');
check(strpos($main_source, 'plugin-update-checker') === false, 'bootstrap does not load plugin-update-checker');
check(strpos($main_source, 'updatepulse-updater') === false, 'bootstrap does not load updatepulse-updater');

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
if (!defined("WPSLUG_PLUGIN_BASENAME")) {
    define("WPSLUG_PLUGIN_BASENAME", "wpslug/wpslug.php");
}
$admin = new WPSlug_Admin();
$admin->handleBulkAction('/wp-admin/edit.php', 'wpslug-convert', [99]);
$admin->handleBulkAction('/wp-admin/edit.php', 'wpslug-convert', [99]);
check(count($GLOBALS['wpslug_post_updates']) === 1, 'bulk conversion is idempotent after the canonical slug is written');


$cli_source = file_get_contents(WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-cli.php');
check(strpos($cli_source, 'set" : "empty') !== false, 'CLI reports secrets as set/empty');
check(strpos($main_source, 'includes/class-wpslug-cli.php') !== false, 'loads WP-CLI command class from the plugin bootstrap');

$GLOBALS['wpslug_options'] = array_merge((new WPSlug_Settings())->getDefaults(), [
    'enable_conversion' => false,
    'enabled_post_types' => [],
    'conversion_mode' => 'translation',
    'translation_service' => 'none',
]);
$off = WPSlug_CLI::doctor_findings((new WPSlug_Settings())->getOptions(), false);
check(in_array('conversion_off', $off, true), 'doctor reports conversion_off');
check(in_array('no_post_types', $off, true), 'doctor reports no_post_types');
check(in_array('translation_unconfigured', $off, true), 'doctor reports translation_unconfigured');

$GLOBALS['wpslug_options']['enable_conversion'] = true;
$GLOBALS['wpslug_options']['enabled_post_types'] = ['post', 'page'];
$GLOBALS['wpslug_options']['conversion_mode'] = 'pinyin';
$ok = WPSlug_CLI::doctor_findings((new WPSlug_Settings())->getOptions());
check($ok === [], 'doctor is quiet when conversion is on');

$preview = WPSlug_CLI::preview_result('你好，世界', (new WPSlug_Settings())->getOptions());
check($preview['final'] !== '', 'preview returns a slug');
check($preview['mode'] === 'pinyin', 'preview uses saved conversion mode');

$GLOBALS['wpslug_posts'][77] = (object) [
    'ID' => 77,
    'post_title' => '你好，世界',
    'post_name' => 'legacy',
    'post_type' => 'post',
    'post_status' => 'publish',
];
$plan = WPSlug_CLI::plan_post_slug(
    77,
    new WPSlug_Settings(),
    new WPSlug_Converter(),
    new WPSlug_Optimizer()
);
check($plan['skip'] === '' && $plan['old'] === 'legacy' && $plan['new'] !== 'legacy', 'convert plan rewrites a legacy slug');

// ---- Settings framework 0.3.0 (vendored kit): secret, multiselect/matrix, depends_on ----
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function checked($a, $b = true, $echo = true) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function selected($a, $b = true, $echo = true) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function disabled($a, $b = true, $echo = true) { return (string) $a === (string) $b ? ' disabled="disabled"' : ''; }
function wp_list_pluck($list, $field) { $out = []; foreach ($list as $k => $v) { $out[$k] = $v[$field]; } return $out; }
function remove_action($hook, $callback) {
    foreach ($GLOBALS['wpslug_hooks']['action'][$hook] ?? [] as $i => $cb) {
        if ($cb === $callback) { unset($GLOBALS['wpslug_hooks']['action'][$hook][$i]); }
    }
    return true;
}
require_once WPSLUG_PLUGIN_DIR . 'lib/wenpai-admin-ui/php/class-wenpai-admin-settings.php';

$kit_version = trim((string) file_get_contents(WPSLUG_PLUGIN_DIR . 'lib/wenpai-admin-ui/VERSION'));
check(version_compare($kit_version, '0.2.5', '>'), 'vendored kit is newer than 0.2.5 so it wins over 0.2.5 copies in other plugins');

$GLOBALS['wpslug_options'] = array_merge((new WPSlug_Settings())->getDefaults(), [
    'google_api_key' => 'saved-google-key',
    'baidu_app_id' => '2026',
    'baidu_secret_key' => 'saved-baidu-secret',
    'enabled_post_types' => ['post', 'product'],
    'enabled_taxonomies' => ['category'],
    'post_type_modes' => ['product' => 'semantic_pinyin'],
]);
$fw_settings = new WPSlug_Settings();
$fw = $fw_settings->getFramework();
check($fw instanceof Wenpai_Admin_Settings, 'registers wpslug_options with the kit settings framework');
$kit_admin_init = array_filter($GLOBALS['wpslug_hooks']['action']['admin_init'] ?? [], function ($cb) {
    return is_array($cb) && $cb[0] instanceof Wenpai_Admin_Settings;
});
check($kit_admin_init === [], 'only WPSlug registers the option sanitize callback (no second kit pass)');
$fields = $fw->fields();
check($fields['google_api_key']['type'] === 'secret' && $fields['baidu_secret_key']['type'] === 'secret', 'API keys use the secret field type');
check($fields['baidu_app_id']['type'] === 'text' && empty($fields['baidu_app_id']['secret']), 'Baidu App ID stays a plain text field');
check($fields['conversion_mode']['type'] === 'choice', 'conversion mode uses choice cards');
check($fields['enabled_post_types']['type'] === 'multiselect' && $fields['enabled_taxonomies']['type'] === 'multiselect', 'post types and taxonomies use multiselect');
check($fields['post_type_modes']['type'] === 'matrix' && $fields['post_type_modes']['unset_value'] === 'inherit', 'post type modes use a matrix with inherit as unset');
$unlabelled = array_filter(['enable_conversion', 'conversion_mode', 'auto_convert', 'convert_on_publish_only', 'force_lowercase', 'enabled_post_types', 'post_type_modes', 'enabled_taxonomies'], function ($id) use ($fields) {
    return $fields[$id]['label'] === $id;
});
check($unlabelled === [], 'every rendered field has a real label instead of its option key');

$google_html = $fw->field_html('google_api_key');
check(strpos($google_html, 'saved-google-key') === false && strpos($google_html, 'type="password"') !== false && strpos($google_html, 'value=""') !== false, 'secret field never echoes the saved Google key');
check(strpos($fw->field_html('baidu_secret_key'), 'saved-baidu-secret') === false, 'secret field never echoes the saved Baidu secret');
check(strpos($google_html, 'A value is saved and is not shown for security. Leave empty to keep it.') !== false, 'secret state line uses the WPSlug-translated kit wording');
check(strpos($google_html, '__wenpai_clear') !== false, 'saved secret offers the clear checkbox');

$admin030 = new WPSlug_Admin();
$kept = $admin030->validateOptions(['google_api_key' => '', 'baidu_secret_key' => '']);
check($kept['google_api_key'] === 'saved-google-key' && $kept['baidu_secret_key'] === 'saved-baidu-secret', 'empty secret input keeps the saved keys (behaviour change from 1.3.x)');
$other_tab = $admin030->validateOptions(['pinyin_format' => 'full']);
check($other_tab['google_api_key'] === 'saved-google-key', 'saving another tab keeps the saved secrets');
$replaced = $admin030->validateOptions(['google_api_key' => 'new-google-key']);
check($replaced['google_api_key'] === 'new-google-key', 'a typed secret replaces the saved one');
$cleared = $admin030->validateOptions(['google_api_key' => '', '__wenpai_clear' => ['google_api_key']]);
check($cleared['google_api_key'] === '' && $cleared['baidu_secret_key'] === 'saved-baidu-secret', 'clear checkbox empties only that secret in one sanitize pass');
check(!array_key_exists('__wenpai_clear', $cleared), 'the clear marker is not stored in the option');

$pt_html = $fw->field_html('enabled_post_types');
check(preg_match('/value="post" checked/', $pt_html) && preg_match('/value="product" checked/', $pt_html) && !preg_match('/value="page" checked/', $pt_html), 'multiselect reads the saved 1.3.x post type list');
check(strpos($pt_html, 'name="wpslug_options[enabled_post_types][]" value=""') !== false, 'multiselect posts an empty sentinel so unticking all still submits');
$pt_saved = $admin030->validateOptions(['enabled_post_types' => ['', 'page', 'bogus', 'post']]);
check($pt_saved['enabled_post_types'] === ['page', 'post'], 'multiselect saves a plain list of known post types, as before');
$pt_none = $admin030->validateOptions(['enabled_post_types' => ['']]);
check($pt_none['enabled_post_types'] === [], 'unticking every post type stores an empty list, which means every post type (as the hint says)');
check($admin030->validateOptions(['enabled_post_types' => ['bogus']])['enabled_post_types'] === ['post', 'page'] && $admin030->validateOptions(['enabled_taxonomies' => ['']])['enabled_taxonomies'] === [], 'only unknown post types fall back to the defaults; unticked taxonomies also mean all');
$tax_saved = $admin030->validateOptions(['enabled_taxonomies' => ['', 'post_tag']]);
check($tax_saved['enabled_taxonomies'] === ['post_tag'], 'taxonomy multiselect keeps the plain list shape');

$matrix_html = $fw->field_html('post_type_modes');
check(preg_match('/name="wpslug_options\[post_type_modes\]\[product\]".*?value="semantic_pinyin" selected/s', $matrix_html) === 1, 'matrix reads the saved post_type => feature map');
check(preg_match('/name="wpslug_options\[post_type_modes\]\[post\]".*?value="inherit" selected/s', $matrix_html) === 1, 'matrix shows rows without an override as inherit');
$matrix_saved = $admin030->validateOptions(['post_type_modes' => ['post' => 'inherit', 'product' => 'pinyin', 'bogus' => 'pinyin', 'page' => 'nope']]);
check($matrix_saved['post_type_modes'] === ['product' => 'pinyin'], 'matrix saves only real overrides in the 1.3.x map shape');

$auto_html = $fw->field_html('auto_convert');
check(strpos($auto_html, 'data-wenpai-depends-on="wpslug_options[enable_conversion]"') !== false && strpos($auto_html, 'data-wenpai-depends-value="1"') !== false, 'auto convert follows the enable switch through depends_on');
check(strpos($auto_html, 'class="wenpai-switch"') !== false && strpos($auto_html, 'role="switch"') !== false, 'toggles use the kit switch');
check(strpos($google_html, 'data-wenpai-depends-on="wpslug_options[translation_service]"') !== false && strpos($google_html, 'data-wenpai-depends-value="google"') !== false, 'Google key follows translation service through depends_on');
check(strpos($fw->field_html('baidu_app_id'), 'data-wenpai-depends-value="baidu"') !== false, 'Baidu App ID follows translation service through depends_on');
$mode_html = $fw->field_html('conversion_mode');
check(strpos($mode_html, 'choice-cards') !== false && strpos($mode_html, '插件内置组件转换') !== false, 'conversion mode cards keep their one-line descriptions');
check(strpos($mode_html, 'data-wenpai-depends-on="wpslug_options[enable_conversion]"') !== false, 'conversion mode follows the enable switch');

$GLOBALS['wpslug_options']['conversion_mode'] = 'pinyin';
$GLOBALS['wpslug_options']['pinyin_format'] = 'first';
$GLOBALS['wpslug_options']['enable_seo_optimization'] = true;
check((new WPSlug_Settings())->shouldApplySEO() === false, 'server skips SEO in first-letter mode while keeping the saved SEO switch');
$seo_opts = array_merge((new WPSlug_Settings())->getOptions(), ['remove_stop_words' => true]);
check((new WPSlug_Optimizer())->optimize('the-quick-fox', $seo_opts) === 'the-quick-fox', 'optimizer does not apply stop words in first-letter mode');
$seo_opts['pinyin_format'] = 'full';
check((new WPSlug_Optimizer())->optimize('the-quick-fox', $seo_opts) === 'quick-fox', 'optimizer applies SEO again in full pinyin mode');
$no_service = (new WPSlug_Converter())->convert('文派素格', array_merge($seo_opts, ['conversion_mode' => 'translation', 'translation_service' => 'none']));
check($no_service === 'wen-pai-su-ge', 'translation mode without a service falls back to local pinyin (no auto-picked Google)');

$sections = $admin030->visibleSections(['conversion_mode' => 'pinyin', 'post_type_modes' => []]);
check(!in_array('translit', $sections, true) && !in_array('translate', $sections, true) && in_array('pinyin', $sections, true), 'advanced sections follow the saved mode (pinyin)');
check(in_array('translate', $admin030->visibleSections(['conversion_mode' => 'pinyin', 'post_type_modes' => ['post' => 'seo_slug']]), true), 'translation section shows when a post type uses translation');
check(in_array('translit', $admin030->visibleSections(['conversion_mode' => 'transliteration']), true), 'transliteration section shows in transliteration mode');

$js030 = file_get_contents(WPSLUG_PLUGIN_DIR . 'assets/admin.js');
$tpl030 = file_get_contents(WPSLUG_PLUGIN_DIR . 'templates/admin/page.php') . file_get_contents(WPSLUG_PLUGIN_DIR . 'includes/class-wpslug-admin.php');
check(strpos($js030, 'initResetSettings') === false && strpos($js030, 'confirm(') === false, 'admin.js has no confirm() reset with hard-coded defaults');
check(strpos($js030, 'wpslug-debug-checkboxes') === false && strpos($js030, 'wpslug-test-form-data') === false && strpos($js030, 'console.log') === false, 'admin.js has no debug buttons or console logging');
check(strpos($js030, 'wpslug-when-on') === false && strpos($js030, 'initTranslationServiceToggle') === false && strpos($js030, 'initConversionModeToggle') === false, 'hand-written show/hide JS is gone (kit depends_on)');
check(strpos($js030, '"checked", false') === false && strpos($js030, 'val("google")') === false, 'admin.js never changes saved values (SEO, translation service)');
check(strpos($js030, 'seo_first_letter') === false && strpos($js030, 'initSEOOptimizationToggle') === false && strpos($js030, 'initMediaSettings') === false && strpos($js030, 'prop("disabled"') === false, 'hand-written SEO and media disable JS is gone (kit depends_on mode disable)');
check(strpos($js030, 'wpslug-test-api') === false && strpos($js030, 'initApiTesting') === false && strpos($js030, 'wpslug_test_api') === false, 'hand-written Test Connection JS is gone (kit field action)');
$css030 = file_get_contents(WPSLUG_PLUGIN_DIR . 'assets/admin.css');
check(strpos($css030, 'wpslug-key-row') === false && strpos($css030, 'wpslug-test-api') === false && strpos($css030, 'wpslug-api-status') === false && strpos($css030, 'wpslug-seo-dependent') === false && strpos($css030, '.field.disabled') === false, 'dead CSS for the replaced hand-written pieces is gone');
check(strpos($tpl030, 'name="wpslug_options[') === false, 'templates have no hand-written wpslug_options controls');
$main030 = file_get_contents(WPSLUG_PLUGIN_DIR . 'wpslug.php');
$load_at = strpos($main030, 'require_once WPSLUG_PLUGIN_DIR . "lib/wenpai-admin-ui/php/load.php";');
check($load_at !== false && $load_at < strpos($main030, 'class WPSlug'), 'kit load.php is required at plugin load time so its plugins_loaded@1 boot runs before options.php saves');
check(strpos($tpl030, 'renderToggle') === false && strpos($tpl030, 'wpslug-when-on') === false, 'old toggle buttons and when-on wrappers are gone');


// ---- Kit 0.3.0 components: field actions, disable-mode depends_on, status ----
function wp_create_nonce($action) { return 'nonce-' . $action; }
function register_setting($group, $option, $args = []) { $GLOBALS['wpslug_registered'][$option] = $args; }
function check_ajax_referer($action, $arg = false, $die = true) { return ($GLOBALS['wpslug_nonce_ok'] ?? true) && $action === 'wpslug_test_api'; }
class WPSlug_Test_Json extends Exception { public $success; public $data; public $status; public function __construct($success, $data, $status) { parent::__construct('json'); $this->success = $success; $this->data = $data; $this->status = $status; } }
function wp_send_json_success($data = null, $status = null) { throw new WPSlug_Test_Json(true, $data, $status); }
function wp_send_json_error($data = null, $status = null) { throw new WPSlug_Test_Json(false, $data, $status); }
$ajax_call = function ($post) {
    $_POST = $post;
    foreach ($GLOBALS['wpslug_hooks']['action']['wp_ajax_wpslug_test_api'] ?? [] as $cb) {
        try { $cb(); } catch (WPSlug_Test_Json $j) { $_POST = []; return $j; }
    }
    $_POST = [];
    return null;
};

check(!method_exists($admin030, 'keyFieldWithTest') && !method_exists($admin030, 'ajaxTestApi'), 'hand-written key row and wpslug_test_api handler are gone');
check(strpos($tpl030, 'wpslug-test-api') === false && strpos($tpl030, 'wpslug-key-row') === false && strpos($tpl030, 'wpslug-seo-dependent') === false && strpos($tpl030, 'wpslug-wpmind-status') === false, 'templates have no hand-written test button, SEO wrappers or WPMind block');

$google_html = $fw->field_html('google_api_key');
check(preg_match('~<div class="field-ctl-row wenpai-field__row"><input type="password"[^>]*><button type="button" class="btn btn-secondary" hidden data-wenpai-action="wpslug_test_api"~', $google_html) === 1, 'Test Connection is a kit field action on the same row as the Google key input');
check(strpos($google_html, 'data-wenpai-busy="Testing"') !== false && strpos($google_html, 'Testing…') === false && strpos($google_html, 'Testing...') === false, 'busy text is passed without an ellipsis (the kit appends it)');
check(strpos($google_html, 'data-wenpai-nonce="nonce-wpslug_test_api"') !== false, 'action button carries a nonce for wpslug_test_api');
check(strpos($google_html, 'data-wenpai-data="' . esc_attr('{"service":"google"}') . '"') !== false && strpos($google_html, 'data-wenpai-include="' . esc_attr('["wpslug_options[google_api_key]"]') . '"') !== false, 'Google action sends the service and the key field');
check(strpos($google_html, 'data-wenpai-requires-note="Enter the API key first."') !== false && strpos($google_html, 'saved-google-key') === false, 'Google action is disabled until a key is typed or saved, and never echoes the key');
$baidu_html = $fw->field_html('baidu_secret_key');
check(strpos($baidu_html, 'data-wenpai-include="' . esc_attr('["wpslug_options[baidu_app_id]","wpslug_options[baidu_secret_key]"]') . '"') !== false && strpos($baidu_html, esc_attr('{"service":"baidu"}')) !== false, 'Baidu action sends App ID and Secret Key');

$admin030->registerSettings();
check(count($GLOBALS['wpslug_hooks']['action']['wp_ajax_wpslug_test_api'] ?? []) === 1, 'wp_ajax_wpslug_test_api is registered once, through the kit ajax_action()');
$GLOBALS['wpslug_nonce_ok'] = false;
$denied = $ajax_call(['service' => 'google']);
$GLOBALS['wpslug_nonce_ok'] = true;
check($denied && !$denied->success && $denied->status === 403, 'Test Connection rejects a bad nonce with 403');
$GLOBALS['wpslug_can'] = false;
$denied = $ajax_call(['service' => 'google']);
unset($GLOBALS['wpslug_can']);
check($denied && !$denied->success && $denied->status === 403, 'Test Connection requires manage_options');
$GLOBALS['wpslug_remote_args'] = null;
$ok = $ajax_call(['service' => 'google', 'wpslug_options' => ['google_api_key' => '']]);
check($ok && $ok->success && $ok->data['message'] === 'Connected.', 'Google test succeeds with the saved key when the input is empty');
check(($GLOBALS['wpslug_remote_args']['body']['key'] ?? '') === 'saved-google-key', 'an empty key input tests the saved key (server side only)');
$ajax_call(['service' => 'google', 'wpslug_options' => ['google_api_key' => 'typed-google-key']]);
check(($GLOBALS['wpslug_remote_args']['body']['key'] ?? '') === 'typed-google-key', 'a typed, unsaved key is tested without saving it');
check($GLOBALS['wpslug_options']['google_api_key'] === 'saved-google-key', 'Test Connection does not save the typed key');
$GLOBALS['wpslug_remote_failure'] = true;
$bad = $ajax_call(['service' => 'google']);
check($bad && !$bad->success && $bad->data['message'] === 'Could not connect to Google Translate. Please check the key.', 'Google failure names the service (WP_Error message, not action_failed)');
$GLOBALS['wpslug_remote_failure'] = false;
$bad = $ajax_call(['service' => 'baidu']);
check($bad && !$bad->success && $bad->data['message'] === 'Could not connect to Baidu Translate. Please check the key.', 'Baidu failure (fallback pinyin answer) is reported as a failure that names the service');
$GLOBALS['wpslug_remote_body'] = '{"trans_result":[{"src":"你好","dst":"Hello"}]}';
$ok = $ajax_call(['service' => 'baidu']);
unset($GLOBALS['wpslug_remote_body']);
check($ok && $ok->success, 'Baidu test succeeds when the service answers');
$none = $admin030->testApi('google', ['google_api_key' => '']);
check($none instanceof WP_Error && $none->get_error_message() === 'Enter the API key first.', 'missing Google key is reported with the requires note');
check($admin030->testApi('bogus') instanceof WP_Error, 'unknown service is rejected');

// Disable-mode depends_on (SEO, media): reason shown, saved value kept.
$seo_tree = function ($id) use ($fields) { return $fields[$id]['depends_on']; };
check(($seo_tree('smart_punctuation')['mode'] ?? '') === 'disable' && ($seo_tree('seo_max_words')['mode'] ?? '') === 'disable' && ($seo_tree('enable_seo_optimization')['mode'] ?? '') === 'disable', 'SEO fields use kit depends_on mode disable');
check(($seo_tree('media_conversion_mode')['mode'] ?? '') === 'disable' && ($seo_tree('media_file_prefix')['mode'] ?? '') === 'disable' && ($seo_tree('preserve_media_extension')['mode'] ?? '') === 'disable', 'media fields use kit depends_on mode disable');
$sp_html = $fw->field_html('smart_punctuation');
check(strpos($sp_html, 'data-wenpai-depends="') !== false && strpos($sp_html, '&quot;mode&quot;:&quot;disable&quot;') !== false && strpos($sp_html, 'class="wenpai-field__reason"') !== false, 'SEO field prints the kit disable tree and a reason slot');
check(strpos($sp_html, 'SEO optimization is off. Turn it on to use this.') !== false && strpos($sp_html, 'Not available with first-letter pinyin.') !== false, 'SEO reasons: first-letter pinyin, then SEO switch off');
check(strpos($fw->field_html('stop_words_list'), 'Stop word removal is off. Turn it on to use this.') !== false, 'stop word fields also wait for stop word removal');
$mp_html = $fw->field_html('media_file_prefix');
check(strpos($mp_html, 'data-wenpai-depends-on="wpslug_options[disable_file_convert]"') !== false && strpos($mp_html, 'data-wenpai-depends-not') !== false && strpos($mp_html, 'data-wenpai-depends-mode="disable"') !== false && strpos($mp_html, 'data-wenpai-depends-reason="Automatic file name conversion is disabled.') !== false, 'media fields are disabled with a reason while file conversion is off');
$base_vals = (new WPSlug_Settings())->getOptions();
$off = $fw->inactive_fields(array_merge($base_vals, ['conversion_mode' => 'pinyin', 'pinyin_format' => 'first', 'enable_conversion' => true]));
check(($off['enable_seo_optimization'] ?? '') === 'disable' && ($off['smart_punctuation'] ?? '') === 'disable', 'first-letter pinyin disables the SEO group, including its switch');
$off = $fw->inactive_fields(array_merge($base_vals, ['conversion_mode' => 'translation', 'pinyin_format' => 'first', 'enable_conversion' => true, 'enable_seo_optimization' => true]));
check(!isset($off['enable_seo_optimization']) && !isset($off['smart_punctuation']), 'first-letter format only matters in local pinyin mode');
$off = $fw->inactive_fields(array_merge($base_vals, ['pinyin_format' => 'full', 'enable_seo_optimization' => false]));
check(!isset($off['enable_seo_optimization']) && ($off['remove_stop_words'] ?? '') === 'disable' && ($off['stop_words_list'] ?? '') === 'disable', 'SEO switch off disables its dependents but not itself');
$GLOBALS['wpslug_options']['pinyin_format'] = 'full';
$GLOBALS['wpslug_options']['smart_punctuation'] = true;
$GLOBALS['wpslug_options']['media_file_prefix'] = 'img-';
$kept_seo = $admin030->validateOptions(['enable_seo_optimization' => '0', 'smart_punctuation' => '0']);
check($kept_seo['enable_seo_optimization'] === false && $kept_seo['smart_punctuation'] === true, 'a disabled SEO field keeps its saved value (the switch hidden 0 does not turn it off)');
$kept_media = $admin030->validateOptions(['disable_file_convert' => '1', 'media_file_prefix' => '']);
check($kept_media['disable_file_convert'] === true && $kept_media['media_file_prefix'] === 'img-', 'a disabled media field keeps its saved value');
$GLOBALS['wpslug_options']['enable_seo_optimization'] = true;
$mirror = new ReflectionMethod($admin030, 'conversionModeMirror');
$mirror->setAccessible(true);
ob_start(); $mirror->invoke($admin030, ['enable_conversion' => true, 'conversion_mode' => 'pinyin']); $mirror_html = ob_get_clean();
check($mirror_html === '<input type="hidden" name="wpslug_options[conversion_mode]" value="pinyin" disabled data-wpslug-mirror>', 'advanced page mirrors the saved mode in a disabled (never submitted) hidden input for kit JS');
ob_start(); $mirror->invoke($admin030, ['enable_conversion' => false, 'conversion_mode' => 'pinyin']); $mirror_off = ob_get_clean();
check(strpos($mirror_off, 'value=""') !== false, 'mirror is empty while the plugin is off, like the server (inactive conversion_mode never matches)');

// Read-only WPMind status field.
check($fields['wpmind_status']['type'] === 'status', 'WPMind block is the kit status field');
$ws_html = $fw->field_html('wpmind_status');
check(strpos($ws_html, 'class="pill ok"') !== false && strpos($ws_html, '>Active<') !== false && strpos($ws_html, 'options-general.php?page=wpmind') !== false && strpos($ws_html, 'aria-labelledby="wenpai-f-wpslug_options-wpmind_status-label wenpai-f-wpslug_options-wpmind_status-pill"') !== false, 'WPMind status: pill, link to its settings, label + pill read together');
check(strpos($ws_html, 'data-wenpai-depends-value="wpmind"') !== false && strpos($ws_html, 'name=') === false, 'WPMind status follows translation_service and submits nothing');
$st_off = (new WPSlug_Settings())->getWpmindStatus();
check($st_off['level'] === 'ok', 'status reflects wpmind_is_available()');

// Saved-option compatibility with main 0391cf6: same keys, no status key stored.
$saved030 = $admin030->validateOptions([]);
check(array_keys($saved030) === array_keys((new WPSlug_Settings())->getDefaults()) && !array_key_exists('wpmind_status', $saved030), 'stored option keys are exactly the 1.3.x defaults (status field not stored)');

$page_src = file_get_contents(__DIR__ . '/../../templates/admin/page.php');
check(strpos($page_src, '"Save Changes"') === false && substr_count($page_src, '"Save Settings"') === 2, 'every settings tab saves with Save Settings');
check(strpos($page_src, '把本插件的设置恢复为默认值，不可撤销。') !== false, 'reset card subtitle says what is reset');
check(strpos($fields['enabled_post_types']['desc'], 'If none are selected') !== false && strpos($fields['enabled_taxonomies']['desc'], 'If none are selected') !== false, 'post type and taxonomy hints say what none selected means');

// ---- Per-tab save (kit tab marker + nonce): saving one form never touches another ----
function wp_verify_nonce($nonce, $action) { return $nonce === 'nonce-' . $action ? 1 : false; }
function add_settings_error($setting, $code, $message, $type = 'error') { $GLOBALS['wpslug_settings_errors'][] = ['setting' => $setting, 'code' => $code, 'message' => $message, 'type' => $type]; }
function esc_html_e($text, $domain = null) { echo esc_html__($text, $domain); }
$tab_map = WPSlug_Settings::fieldTabs();
$tab_ids = array_values(array_unique($tab_map));
check($tab_ids === ['simple', 'pinyin', 'translit', 'translate', 'seo', 'media', 'types'], 'every settings form has a kit tab: simple page plus the six advanced sections');
$untabbed = array_diff(array_keys((new WPSlug_Settings())->getDefaults()), array_keys($tab_map));
check(array_values($untabbed) === ['max_length'], 'every stored field is in a tab except max_length, which no page shows');
foreach ($tab_map as $id => $t) {
    if ($fields[$id]['tab'] !== $t) { check(false, "schema tab of {$id}"); }
}
check($fields['wpmind_status']['tab'] === 'translate', 'schema carries each field\'s tab (WPMind status on translate)');

// Each form renders exactly the fields of its tab (else an unrendered checkbox would read as unticked).
$GLOBALS['wpslug_options'] = array_merge((new WPSlug_Settings())->getDefaults(), ['conversion_mode' => 'translation', 'translation_service' => 'google']);
$renderers = ['pinyin' => 'renderPinyinSettings', 'translit' => 'renderTransliterationSettings', 'translate' => 'renderTranslationSettings', 'seo' => 'renderSEOSettings', 'media' => 'renderMediaSettings', 'types' => 'renderAdvancedSettings'];
foreach ($renderers as $t => $method) {
    $rm = new ReflectionMethod($admin030, $method);
    $rm->setAccessible(true);
    ob_start(); $rm->invoke($admin030, $GLOBALS['wpslug_options']); $html = ob_get_clean();
    preg_match_all('/name="wpslug_options\[([a-z_]+)\]/', $html, $mm);
    $rendered = array_values(array_unique(array_diff($mm[1], ['__wenpai_clear', 'conversion_mode'])));
    $expected = array_keys(array_filter($tab_map, function ($x) use ($t) { return $x === $t; }));
    sort($rendered); sort($expected);
    check($rendered === $expected, "advanced section {$t} renders exactly the fields of tab {$t} (" . implode(',', array_diff($expected, $rendered)) . ')');
}
$page_src = file_get_contents(__DIR__ . '/../../templates/admin/page.php');
$simple_src = substr($page_src, strpos($page_src, '<?php if ("simple" === $mode) : ?>'), strpos($page_src, '<?php else : ?>', strpos($page_src, '<?php if ("simple" === $mode) : ?>')) - strpos($page_src, '<?php if ("simple" === $mode) : ?>'));
$simple_missing = array_filter(array_keys(array_filter($tab_map, function ($x) { return $x === 'simple'; })), function ($id) use ($simple_src) { return strpos($simple_src, '"' . $id . '"') === false; });
check($simple_missing === [], 'simple page renders every field of tab simple (' . implode(',', $simple_missing) . ')');
check(strpos($page_src, 'wpslug_current_tab') === false && substr_count($page_src, 'tab_input_html(') === 1 && strpos($page_src, 'tab_input_html("advanced" === $mode ? $section : "simple")') !== false, 'the settings form prints the kit tab marker once: simple, or the open advanced section');
$marker = $fw->tab_input_html('seo');
check(strpos($marker, 'name="wpslug_options[__wenpai_tab]" value="seo"') !== false && strpos($marker, 'name="wpslug_options[__wenpai_tab_nonce]" value="nonce-wenpai_tab_wpslug_options_seo"') !== false, 'tab marker carries a per-tab nonce');

// Non-default values on every tab, including secrets, multiselects and the matrix.
$tab_base = array_merge((new WPSlug_Settings())->getDefaults(), [
    'enable_conversion' => true, 'conversion_mode' => 'translation', 'auto_convert' => false, 'convert_on_publish_only' => false, 'force_lowercase' => false,
    'pinyin_format' => 'full', 'pinyin_separator' => '_', 'preserve_english' => false, 'preserve_numbers' => false,
    'transliteration_method' => 'basic',
    'translation_service' => 'google', 'translation_source_lang' => 'zh', 'translation_target_lang' => 'en', 'google_api_key' => 'G-KEY\\123', 'baidu_app_id' => '2026', 'baidu_secret_key' => 'BAIDU-SECRET',
    'enable_seo_optimization' => true, 'smart_punctuation' => false, 'mixed_content_optimization' => true, 'remove_stop_words' => true, 'seo_max_words' => 7, 'stop_words_list' => 'the,a',
    'disable_file_convert' => false, 'media_conversion_mode' => 'md5', 'media_file_prefix' => 'img-', 'preserve_media_extension' => false,
    'enabled_post_types' => ['post', 'product'], 'post_type_modes' => ['post' => 'seo_slug', 'product' => 'pinyin'], 'enabled_taxonomies' => ['category'], 'show_slug_column' => true,
    'max_length' => 80,
]);
// What the browser posts for a field: toggles as hidden 0 + checked 1, multiselects with the empty sentinel, secrets empty.
$as_posted = function ($id, $value) use ($fields) {
    $type = $fields[$id]['type'];
    if ($type === 'toggle') { return $value ? '1' : '0'; }
    if ($type === 'multiselect') { return array_merge([''], $value); }
    if ($type === 'secret') { return ''; }
    if ($type === 'matrix') { $rows = []; foreach (['post', 'page', 'product'] as $r) { $rows[$r] = $value[$r] ?? 'inherit'; } return $rows; }
    return is_int($value) ? (string) $value : $value;
};
// A different value per field, to see whether a field outside the saved tab moved.
$other_value = function ($id, $value) use ($fields) {
    $type = $fields[$id]['type'];
    if ($type === 'toggle') { return !$value; }
    if ($type === 'multiselect') { return []; }
    if ($type === 'matrix') { return []; }
    if ($type === 'secret') { return 'OTHER-SECRET'; }
    if ($type === 'number') { return 3; }
    $choices = $fields[$id]['choices'] instanceof Closure ? call_user_func($fields[$id]['choices'], $fields[$id]) : $fields[$id]['choices'];
    if (is_array($choices) && $choices !== []) { foreach (array_keys($choices) as $k) { if ((string) $k !== (string) $value) { return (string) $k; } } }
    return 'other-' . $value;
};
$changed_keys = function ($before, $after) { $out = []; foreach ($before as $k => $v) { if (!array_key_exists($k, $after) || $after[$k] !== $v) { $out[] = $k; } } return $out; };
foreach ($tab_ids as $t) {
    $own = array_keys(array_filter($tab_map, function ($x) use ($t) { return $x === $t; }));
    $marker_in = ['__wenpai_tab' => $t, '__wenpai_tab_nonce' => 'nonce-wenpai_tab_wpslug_options_' . $t];
    // 1) Simple-page style form: only this tab's fields are posted, unchanged.
    $GLOBALS['wpslug_options'] = $tab_base;
    $in = $marker_in;
    foreach ($own as $id) { $in[$id] = $as_posted($id, $tab_base[$id]); }
    $out = $admin030->validateOptions($in);
    check($changed_keys($tab_base, $out) === [], "saving tab {$t} unchanged changes nothing anywhere (" . implode(',', $changed_keys($tab_base, $out)) . ')');
    check(!array_key_exists('__wenpai_tab', $out) && !array_key_exists('__wenpai_tab_nonce', $out), "tab {$t}: the marker is not stored");
    check(($GLOBALS['wpslug_transients']['wpslug_admin_tab'] ?? '') === $t, "tab {$t}: the saved form is remembered in the wpslug_admin_tab transient");
    // 2) Advanced-page style form: every section is in the form; other sections post different values.
    $in = $marker_in;
    foreach ($tab_base as $id => $v) {
        if ($id === 'max_length') { continue; }
        $in[$id] = in_array($id, $own, true) ? $as_posted($id, $v) : $as_posted($id, $other_value($id, $v));
        if (!in_array($id, $own, true) && $fields[$id]['type'] === 'secret') { $in[$id] = 'OTHER-SECRET'; }
    }
    $GLOBALS['wpslug_options'] = $tab_base;
    $out = $admin030->validateOptions($in);
    check($changed_keys($tab_base, $out) === [], "saving tab {$t} ignores values posted for every other tab (" . implode(',', $changed_keys($tab_base, $out)) . ')');
    // 3) Edits on the saved tab land; nothing outside it moves.
    $in = $marker_in;
    $edit = [];
    foreach ($own as $id) {
        $type = $fields[$id]['type'];
        if (in_array($type, ['toggle', 'multiselect', 'matrix'], true)) { $edit[$id] = $other_value($id, $tab_base[$id]); }
        $in[$id] = $as_posted($id, array_key_exists($id, $edit) ? $edit[$id] : $tab_base[$id]);
    }
    $GLOBALS['wpslug_options'] = $tab_base;
    $out = $admin030->validateOptions($in);
    $outside = array_values(array_diff($changed_keys($tab_base, $out), $own));
    check($outside === [], "editing tab {$t} leaves every other tab's toggles, multiselects, matrix and secrets as saved (" . implode(',', $outside) . ')');
    foreach ($edit as $id => $want) {
        if ($fields[$id]['depends_on'] !== [] && isset($fw->inactive_fields($out)[$id])) { continue; }
        if ($out[$id] !== $want) { check(false, "tab {$t}: edit of {$id} saved"); }
    }
}
// Explicit cases named in the QA report (N1).
$GLOBALS['wpslug_options'] = $tab_base;
$simple_save = $admin030->validateOptions(['__wenpai_tab' => 'simple', '__wenpai_tab_nonce' => 'nonce-wenpai_tab_wpslug_options_simple', 'enable_conversion' => '1', 'conversion_mode' => 'translation', 'auto_convert' => '0', 'convert_on_publish_only' => '0', 'force_lowercase' => '1']);
check($simple_save['force_lowercase'] === true && $simple_save['show_slug_column'] === true && $simple_save['preserve_media_extension'] === false && $simple_save['remove_stop_words'] === true
    && $simple_save['enabled_post_types'] === ['post', 'product'] && $simple_save['enabled_taxonomies'] === ['category'] && $simple_save['post_type_modes'] === ['post' => 'seo_slug', 'product' => 'pinyin']
    && $simple_save['google_api_key'] === 'G-KEY\\123' && $simple_save['baidu_secret_key'] === 'BAIDU-SECRET' && $simple_save['max_length'] === 80, 'simple page save: advanced toggles, multiselects, matrix and secrets are untouched');
$GLOBALS['wpslug_options'] = $tab_base;
$seo_save = $admin030->validateOptions(['__wenpai_tab' => 'seo', '__wenpai_tab_nonce' => 'nonce-wenpai_tab_wpslug_options_seo', 'enable_seo_optimization' => '1', 'smart_punctuation' => '1', 'mixed_content_optimization' => '0', 'remove_stop_words' => '1', 'seo_max_words' => '9', 'stop_words_list' => 'the']);
check($seo_save['smart_punctuation'] === true && $seo_save['mixed_content_optimization'] === false && $seo_save['seo_max_words'] === 9
    && $changed_keys($tab_base, $seo_save) === ['smart_punctuation', 'mixed_content_optimization', 'seo_max_words', 'stop_words_list'], 'SEO tab save writes only the SEO fields');
$GLOBALS['wpslug_options'] = $tab_base;
$types_none = $admin030->validateOptions(['__wenpai_tab' => 'types', '__wenpai_tab_nonce' => 'nonce-wenpai_tab_wpslug_options_types', 'enabled_post_types' => [''], 'enabled_taxonomies' => [''], 'post_type_modes' => ['post' => 'seo_slug', 'page' => 'inherit', 'product' => 'pinyin'], 'show_slug_column' => '1']);
check($types_none['enabled_post_types'] === [] && $types_none['enabled_taxonomies'] === [] && $changed_keys($tab_base, $types_none) === ['enabled_post_types', 'enabled_taxonomies'], 'unticking every post type and taxonomy on the types tab stores [] (all) and changes nothing else');
$GLOBALS['wpslug_options'] = $types_none;
$tpl_settings = new WPSlug_Settings();
check($tpl_settings->isPostTypeEnabled('product') && $tpl_settings->isPostTypeEnabled('page') && $tpl_settings->isTaxonomyEnabled('product_cat'), 'an empty list really converts every post type and taxonomy');

// Forged, expired or swapped tab nonces write nothing and report tab_expired.
foreach ([
    'forged' => ['__wenpai_tab' => 'simple', '__wenpai_tab_nonce' => 'forged'],
    'expired' => ['__wenpai_tab' => 'seo', '__wenpai_tab_nonce' => ''],
    'swapped' => ['__wenpai_tab' => 'types', '__wenpai_tab_nonce' => 'nonce-wenpai_tab_wpslug_options_seo'],
] as $case => $bad) {
    $GLOBALS['wpslug_options'] = $tab_base;
    $GLOBALS['wpslug_settings_errors'] = [];
    $in = $bad + ['enable_conversion' => '0', 'force_lowercase' => '1', 'smart_punctuation' => '1', 'enabled_post_types' => [''], 'post_type_modes' => [], 'google_api_key' => 'NEW', '__wenpai_clear' => ['baidu_secret_key'], 'show_slug_column' => '0'];
    $out = $admin030->validateOptions($in);
    $errs = array_values(array_filter($GLOBALS['wpslug_settings_errors'], function ($e) { return $e['code'] === 'wenpai_tab'; }));
    check($changed_keys($tab_base, $out) === [] && array_keys($out) === array_keys($tab_base), "{$case} tab nonce writes nothing (" . implode(',', $changed_keys($tab_base, $out)) . ')');
    check(count($errs) === 1 && $errs[0]['message'] === $settings->getFrameworkStrings()['tab_expired'] && $errs[0]['type'] === 'error', "{$case} tab nonce reports the WPSlug tab_expired message");
}
// Without a marker (update_option() from code, import, CLI) absent keys keep their saved value.
$GLOBALS['wpslug_options'] = $tab_base;
$partial = $admin030->validateOptions(['pinyin_separator' => '-']);
check($changed_keys($tab_base, $partial) === ['pinyin_separator'], 'a partial array without a tab marker changes only the keys it carries');
$GLOBALS['wpslug_options'] = $tab_base;
$kit_keys = array_keys(Wenpai_Admin_Settings::default_strings());
$our_keys = array_keys($settings->getFrameworkStrings());
check(array_diff($kit_keys, $our_keys) === [], 'WPSlug translates every kit string (missing: ' . implode(', ', array_diff($kit_keys, $our_keys)) . ')');
check(substr($settings->getFrameworkStrings()['action_busy'], -3) !== '…', 'action_busy has no ellipsis (kit JS appends one)');
echo "PASS: {$tests} assertions\n";
