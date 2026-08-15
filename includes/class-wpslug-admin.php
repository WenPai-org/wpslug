<?php

if (!defined("ABSPATH")) {
    exit();
}

class WPSlug_Admin
{
    private $settings;
    private $converter;
    private $optimizer;

    public function __construct()
    {
        $this->settings = new WPSlug_Settings();
        $this->converter = new WPSlug_Converter();
        $this->optimizer = new WPSlug_Optimizer();

        add_action("admin_menu", [$this, "addAdminMenu"]);
        add_action("admin_init", [$this, "registerSettings"]);
        add_action("admin_enqueue_scripts", [$this, "enqueueScripts"]);
        add_action("admin_notices", [$this, "showAdminNotices"]);
        add_action("wp_ajax_wpslug_preview", [$this, "ajaxPreview"]);
        add_action("wp_ajax_wpslug_test_api", [$this, "ajaxTestApi"]);
        add_action("post_submitbox_start", [$this, "addPostMetaBox"]);
        add_filter("bulk_actions-edit-post", [$this, "addBulkAction"]);
        add_filter("bulk_actions-edit-page", [$this, "addBulkAction"]);
        add_filter(
            "handle_bulk_actions-edit-post",
            [$this, "handleBulkAction"],
            10,
            3
        );
        add_filter(
            "handle_bulk_actions-edit-page",
            [$this, "handleBulkAction"],
            10,
            3
        );
        add_action("admin_notices", [$this, "bulkActionNotice"]);
        add_action("load-options-permalink.php", [$this, "addPermalinkNotice"]);

        add_action("admin_head", [$this, "hideDefaultNotices"]);
    }

    public function hideDefaultNotices()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin route.
        if (isset($_GET["page"]) && "wpslug" === sanitize_key(wp_unslash($_GET["page"]))) {
            echo "<style>.settings-error.notice-success { display: none !important; }</style>";
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    public function addAdminMenu()
    {
        add_options_page(
            __("WPSlug Settings", "wpslug"),
            __("Slug", "wpslug"),
            "manage_options",
            "wpslug",
            [$this, "displayAdminPage"]
        );
    }

    public function registerSettings()
    {
        register_setting("wpslug_settings", "wpslug_options", [
            "type" => "array",
            "sanitize_callback" => [$this, "validateOptions"],
            "default" => [],
            "show_in_rest" => false,
        ]);

        add_filter("wp_redirect", [$this, "preventDefaultNotice"], 10, 2);
    }

    public function preventDefaultNotice($location, $status)
    {
        if (strpos($location, "options-general.php?page=wpslug") !== false) {
            if (strpos($location, "settings-updated=true") !== false) {
                return $location;
            }
        }
        return $location;
    }

    public function showAdminNotices()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin status parameters.
        if (isset($_GET["page"]) && "wpslug" === sanitize_key(wp_unslash($_GET["page"]))) {
            if (
                isset($_GET["settings-updated"]) &&
                $_GET["settings-updated"] == "true"
            ) {
                $message = __("Settings saved successfully!", "wpslug");
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html($message) .
                    "</p></div>";
            }

            if (isset($_GET["wpslug-error"])) {
                $error_message = sanitize_text_field(wp_unslash($_GET["wpslug-error"]));
                echo '<div class="notice notice-error is-dismissible"><p>' .
                    esc_html($error_message) .
                    "</p></div>";
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    public function displayAdminPage()
    {
        if (!current_user_can("manage_options")) {
            wp_die(
                esc_html__(
                    "You do not have sufficient permissions to access this page.",
                    "wpslug"
                )
            );
        }

        $options = $this->settings->getOptions();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
        $current_tab = isset($_GET["tab"])
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
            ? sanitize_key(wp_unslash($_GET["tab"]))
            : "general";
        ?>
        <div class="wrap">

        <h1><?php echo esc_html( get_admin_page_title() ); ?>
        <span style="font-size: 13px; padding-left: 10px;">
            <?php
            /* translators: %s: plugin version. */
            printf( esc_html__( 'Version: %s', 'wpslug' ), esc_html( WPSLUG_VERSION ) );
            ?>
        </span>
        <a href="https://wpcy.com/slug/" target="_blank" class="button button-secondary" style="margin-left: 10px;">
            <?php esc_html_e( 'Documentation', 'wpslug' ); ?>
        </a>
        <a href="https://wpcy.com/c/wpslug/" target="_blank" class="button button-secondary">
            <?php esc_html_e( 'Support', 'wpslug' ); ?>
        </a>
    </h1>


            <form method="post" action="options.php" id="wpslug-settings-form">
                <?php settings_fields("wpslug_settings"); ?>
                <input type="hidden" name="wpslug_current_tab" id="wpslug_current_tab" value="<?php echo esc_attr(
                    $current_tab
                ); ?>">

                <div class="wpslug-card">
                    <h2><?php esc_html_e("Slug Settings", "wpslug"); ?></h2>
                    <div class="wpslug-tabs">
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "general"
                            ? "active"
                            : ""; ?>" data-tab="general">
                            <?php esc_html_e("General", "wpslug"); ?>
                        </button>
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "pinyin"
                            ? "active"
                            : ""; ?>" data-tab="pinyin" style="<?php echo $options[
    "conversion_mode"
] !== "pinyin"
    ? "display:none;"
    : ""; ?>">
                            <?php esc_html_e("Pinyin", "wpslug"); ?>
                        </button>
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "transliteration"
                            ? "active"
                            : ""; ?>" data-tab="transliteration" style="<?php echo $options[
    "conversion_mode"
] !== "transliteration"
    ? "display:none;"
    : ""; ?>">
                            <?php esc_html_e("Transliteration", "wpslug"); ?>
                        </button>
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "translation"
                            ? "active"
                            : ""; ?>" data-tab="translation" style="<?php echo $options[
    "conversion_mode"
] !== "translation"
    ? "display:none;"
    : ""; ?>">
                            <?php esc_html_e("Translation", "wpslug"); ?>
                        </button>
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "seo"
                            ? "active"
                            : ""; ?>" data-tab="seo">
                            <?php esc_html_e("SEO Optimization", "wpslug"); ?>
                        </button>
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "media"
                            ? "active"
                            : ""; ?>" data-tab="media">
                            <?php esc_html_e("Media Files", "wpslug"); ?>
                        </button>
                        <button type="button" class="wpslug-tab <?php echo $current_tab ===
                        "advanced"
                            ? "active"
                            : ""; ?>" data-tab="advanced">
                            <?php esc_html_e("Advanced", "wpslug"); ?>
                        </button>
                    </div>

                    <div class="wpslug-tab-content">
                        <div class="wpslug-section <?php echo $current_tab ===
                        "general"
                            ? "active"
                            : ""; ?>" data-section="general">
                            <?php $this->renderGeneralSettings($options); ?>
                        </div>

                        <div class="wpslug-section <?php echo $current_tab ===
                        "pinyin"
                            ? "active"
                            : ""; ?>" data-section="pinyin">
                            <?php $this->renderPinyinSettings($options); ?>
                        </div>

                        <div class="wpslug-section <?php echo $current_tab ===
                        "transliteration"
                            ? "active"
                            : ""; ?>" data-section="transliteration">
                            <?php $this->renderTransliterationSettings(
                                $options
                            ); ?>
                        </div>

                        <div class="wpslug-section <?php echo $current_tab ===
                        "translation"
                            ? "active"
                            : ""; ?>" data-section="translation">
                            <?php $this->renderTranslationSettings($options); ?>
                        </div>

                        <div class="wpslug-section <?php echo $current_tab ===
                        "seo"
                            ? "active"
                            : ""; ?>" data-section="seo">
                            <?php $this->renderSEOSettings($options); ?>
                        </div>

                        <div class="wpslug-section <?php echo $current_tab ===
                        "media"
                            ? "active"
                            : ""; ?>" data-section="media">
                            <?php $this->renderMediaSettings($options); ?>
                        </div>

                        <div class="wpslug-section <?php echo $current_tab ===
                        "advanced"
                            ? "active"
                            : ""; ?>" data-section="advanced">
                            <?php $this->renderAdvancedSettings($options); ?>
                        </div>
                    </div>

                    <div class="wpslug-submit-section">
                        <?php submit_button(
                            __("Save Changes", "wpslug"),
                            "primary",
                            "submit",
                            false
                        ); ?>
                        <button type="button" id="wpslug-reset-settings" class="button button-secondary">
                            <?php esc_html_e("Reset to Defaults", "wpslug"); ?>
                        </button>
                        <?php if (defined("WP_DEBUG") && WP_DEBUG): ?>
                        <button type="button" id="wpslug-debug-checkboxes" class="button button-secondary">
                            <?php esc_html_e("Debug Checkboxes", "wpslug"); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="wpslug-card">
                <h2><?php esc_html_e("Preview", "wpslug"); ?></h2>
                <div id="wpslug-status" class="wpslug-notice" style="display:none;"></div>
                <p><?php esc_html_e(
                    "Test your conversion settings with live preview.",
                    "wpslug"
                ); ?></p>
                <div class="wpslug-preview-section">
                    <div class="wpslug-preview-input">
                        <input type="text" id="wpslug-preview-input" placeholder="<?php esc_html_e(
                            "Enter text to preview conversion...",
                            "wpslug"
                        ); ?>" />
                        <button type="button" id="wpslug-preview-button" class="button button-primary">
                            <?php esc_html_e("Preview", "wpslug"); ?>
                        </button>
                    </div>
                    <div id="wpslug-preview-result"></div>
                </div>
            </div>

        </div>
        <?php
    }

    private function renderGeneralSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("General Settings", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Configure basic plugin behavior and choose your conversion method.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e("Enable Plugin", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[enable_conversion]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[enable_conversion]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["enable_conversion"]
                               ); ?>
                               id="enable_conversion">
                        <?php esc_html_e(
                            "Enable automatic slug conversion for your content",
                            "wpslug"
                        ); ?>
                    </label>
                </td>
            </tr>
            <tr class="wpslug-dependent" data-depends="enable_conversion">
                <th scope="row"><?php esc_html_e("Conversion Mode", "wpslug"); ?></th>
                <td>
                    <select name="wpslug_options[conversion_mode]" id="conversion_mode">
                        <?php foreach (
                            $this->settings->getConversionModes()
                            as $mode => $label
                        ): ?>
                            <option value="<?php echo esc_attr(
                                $mode
                            ); ?>" <?php selected(
    $options["conversion_mode"],
    $mode
); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Select the conversion method. Pinyin for Chinese, Transliteration for Cyrillic/Arabic scripts, Translation for other languages.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wpslug-dependent" data-depends="enable_conversion">
                <th scope="row"><?php esc_html_e("Auto Convert", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[auto_convert]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[auto_convert]"
                               value="1"
                               <?php checked(1, $options["auto_convert"]); ?>>
                        <?php esc_html_e(
                            "Automatically convert slugs when saving posts and terms",
                            "wpslug"
                        ); ?>
                    </label>
                </td>
            </tr>
            <tr class="wpslug-dependent" data-depends="enable_conversion">
                <th scope="row"><?php esc_html_e("Force Lowercase", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[force_lowercase]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[force_lowercase]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["force_lowercase"]
                               ); ?>>
                        <?php esc_html_e(
                            "Convert all slugs to lowercase for consistency",
                            "wpslug"
                        ); ?>
                    </label>
                </td>
            </tr>
            <tr class="wpslug-dependent" data-depends="enable_conversion">
                <th scope="row"><?php esc_html_e("Maximum Length", "wpslug"); ?></th>
                <td>
                    <input type="number"
                           name="wpslug_options[max_length]"
                           value="<?php echo esc_attr(
                               $options["max_length"]
                           ); ?>"
                           min="0"
                           max="500"
                           class="small-text">
                    <p class="description">
                        <?php esc_html_e(
                            "Maximum length of generated slugs (0 = no limit).",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderPinyinSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("Chinese Pinyin Settings", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Configure Chinese characters to Pinyin romanization.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e("Pinyin Format", "wpslug"); ?></th>
                <td>
                    <select name="wpslug_options[pinyin_format]" id="pinyin_format">
                        <option value="full" <?php selected(
                            $options["pinyin_format"],
                            "full"
                        ); ?>>
                            <?php esc_html_e("Full Pinyin (ni-hao)", "wpslug"); ?>
                        </option>
                        <option value="first" <?php selected(
                            $options["pinyin_format"],
                            "first"
                        ); ?>>
                            <?php esc_html_e("First Letter Only (n-h)", "wpslug"); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Choose between full pinyin or first letters only. First letter mode creates very concise URLs and automatically disables SEO optimization.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e("Word Separator", "wpslug"); ?></th>
                <td>
                    <select name="wpslug_options[pinyin_separator]">
                        <option value="-" <?php selected(
                            $options["pinyin_separator"],
                            "-"
                        ); ?>>
                            <?php esc_html_e("Dash (-)", "wpslug"); ?>
                        </option>
                        <option value="_" <?php selected(
                            $options["pinyin_separator"],
                            "_"
                        ); ?>>
                            <?php esc_html_e("Underscore (_)", "wpslug"); ?>
                        </option>
                        <option value="" <?php selected(
                            $options["pinyin_separator"],
                            ""
                        ); ?>>
                            <?php esc_html_e("No Separator", "wpslug"); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Choose the separator between pinyin words.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e("Preserve Settings", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[preserve_english]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[preserve_english]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["preserve_english"]
                               ); ?>>
                        <?php esc_html_e(
                            "Preserve English letters in mixed content",
                            "wpslug"
                        ); ?>
                    </label><br>
                    <input type="hidden" name="wpslug_options[preserve_numbers]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[preserve_numbers]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["preserve_numbers"]
                               ); ?>>
                        <?php esc_html_e("Preserve numbers in slugs", "wpslug"); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            "Keep English letters and numbers when converting mixed language content.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderTransliterationSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("Transliteration Settings", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Convert foreign scripts (Cyrillic, Arabic, Greek) to Latin alphabet.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e(
                    "Transliteration Method",
                    "wpslug"
                ); ?></th>
                <td>
                    <select name="wpslug_options[transliteration_method]">
                        <?php foreach (
                            $this->settings->getTransliterationMethods()
                            as $method => $label
                        ): ?>
                            <option value="<?php echo esc_attr(
                                $method
                            ); ?>" <?php selected(
    $options["transliteration_method"],
    $method
); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Choose the transliteration method. iconv and Intl provide better accuracy if available.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderTranslationSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("Translation Settings", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Use online translation services to convert text to English slugs.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e(
                    "Translation Service",
                    "wpslug"
                ); ?></th>
                <td>
                    <select name="wpslug_options[translation_service]" id="translation_service">
                        <?php foreach (
                            $this->settings->getTranslationServices()
                            as $service => $label
                        ): ?>
                            <option value="<?php echo esc_attr(
                                $service
                            ); ?>" <?php selected(
    $options["translation_service"],
    $service
); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Choose translation service. Useful for non-English content to generate English slugs.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e("Source Language", "wpslug"); ?></th>
                <td>
                    <select name="wpslug_options[translation_source_lang]">
                        <?php foreach (
                            $this->settings->getLanguages()
                            as $lang => $label
                        ): ?>
                            <option value="<?php echo esc_attr(
                                $lang
                            ); ?>" <?php selected(
    $options["translation_source_lang"],
    $lang
); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Source language for translation. Auto-detect is recommended.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e("Target Language", "wpslug"); ?></th>
                <td>
                    <select name="wpslug_options[translation_target_lang]">
                        <?php foreach (
                            $this->settings->getLanguages()
                            as $lang => $label
                        ): ?>
                            <option value="<?php echo esc_attr(
                                $lang
                            ); ?>" <?php selected(
    $options["translation_target_lang"],
    $lang
); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Target language for translation. English is recommended for SEO.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <div class="wpslug-api-sections">
            <div class="wpslug-api-section" data-service="google">
                <h4><?php esc_html_e("Google Translate API", "wpslug"); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e("API Key", "wpslug"); ?></th>
                        <td>
                            <input type="password"
                                   name="wpslug_options[google_api_key]"
                                   value="<?php echo esc_attr(
                                       $options["google_api_key"]
                                   ); ?>"
                                   class="regular-text" autocomplete="new-password">
                            <button type="button" class="button wpslug-test-api" data-service="google">
                                <?php esc_html_e("Test API", "wpslug"); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e(
                                    "Enter your Google Translate API key.",
                                    "wpslug"
                                ); ?>
                                <a href="https://cloud.google.com/translate/docs/setup" target="_blank">
                                    <?php esc_html_e("Get API Key", "wpslug"); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpslug-api-section" data-service="baidu">
                <h4><?php esc_html_e("Baidu Translate API", "wpslug"); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e("App ID", "wpslug"); ?></th>
                        <td>
                            <input type="text"
                                   name="wpslug_options[baidu_app_id]"
                                   value="<?php echo esc_attr(
                                       $options["baidu_app_id"]
                                   ); ?>"
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e(
                                    "Enter your Baidu Translate App ID.",
                                    "wpslug"
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e(
                            "Secret Key",
                            "wpslug"
                        ); ?></th>
                        <td>
                            <input type="password"
                                   name="wpslug_options[baidu_secret_key]"
                                   value="<?php echo esc_attr(
                                       $options["baidu_secret_key"]
                                   ); ?>"
                                   class="regular-text" autocomplete="new-password">
                            <button type="button" class="button wpslug-test-api" data-service="baidu">
                                <?php esc_html_e("Test API", "wpslug"); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e(
                                    "Enter your Baidu Translate Secret Key.",
                                    "wpslug"
                                ); ?>
                                <a href="https://fanyi-api.baidu.com/doc/21" target="_blank">
                                    <?php esc_html_e("Get API Key", "wpslug"); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wpslug-api-section" data-service="wpmind">
                <h4><?php esc_html_e("WPMind AI Translation", "wpslug"); ?></h4>
                <div class="wpslug-wpmind-status">
                    <?php if (function_exists('wpmind_is_available') && wpmind_is_available()): ?>
                        <p class="description" style="color: #2e7d32;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e("WPMind is active and configured. No additional settings required.", "wpslug"); ?>
                        </p>
                        <p class="description">
                            <?php esc_html_e("WPMind uses AI to translate titles to SEO-friendly slugs. It provides more accurate and context-aware translations compared to traditional translation services.", "wpslug"); ?>
                        </p>
                        <p class="description">
                            <a href="<?php echo esc_url(admin_url('options-general.php?page=wpmind')); ?>">
                                <?php esc_html_e("Configure WPMind Settings", "wpslug"); ?> →
                            </a>
                        </p>
                    <?php else: ?>
                        <p class="description" style="color: #d32f2f;">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e("WPMind plugin is not active or not configured.", "wpslug"); ?>
                        </p>
                        <p class="description">
                            <?php esc_html_e("Please install and activate the WPMind plugin to use AI-powered translation.", "wpslug"); ?>
                            <a href="https://wpcy.com/mind/" target="_blank"><?php esc_html_e("Learn more", "wpslug"); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderSEOSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("SEO Optimization", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Optimize slugs for better search engine performance and user experience.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e(
                    "Enable SEO Optimization",
                    "wpslug"
                ); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[enable_seo_optimization]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[enable_seo_optimization]"
                               value="1"
                               id="enable_seo_optimization"
                               <?php checked(
                                   1,
                                   $options["enable_seo_optimization"]
                               ); ?>>
                        <?php esc_html_e(
                            "Enable SEO-friendly slug optimization",
                            "wpslug"
                        ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            "Apply SEO best practices to generated slugs.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wpslug-seo-dependent">
                <th scope="row"><?php esc_html_e("Smart Punctuation", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[smart_punctuation]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[smart_punctuation]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["smart_punctuation"]
                               ); ?>>
                        <?php esc_html_e(
                            "Intelligently handle punctuation marks",
                            "wpslug"
                        ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            "Convert colons, semicolons, and other punctuation to hyphens or remove them.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wpslug-seo-dependent">
                <th scope="row"><?php esc_html_e(
                    "Mixed Content Optimization",
                    "wpslug"
                ); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[mixed_content_optimization]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[mixed_content_optimization]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["mixed_content_optimization"]
                               ); ?>>
                        <?php esc_html_e(
                            "Optimize mixed language and number content",
                            "wpslug"
                        ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            "Better handling of content mixing languages with numbers and English text.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wpslug-seo-dependent">
                <th scope="row"><?php esc_html_e("Remove Stop Words", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[remove_stop_words]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[remove_stop_words]"
                               value="1"
                               id="remove_stop_words"
                               <?php checked(
                                   1,
                                   $options["remove_stop_words"]
                               ); ?>>
                        <?php esc_html_e(
                            "Remove common stop words from slugs",
                            "wpslug"
                        ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            'Remove words like "the", "a", "an", "and", etc. to create cleaner slugs.',
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wpslug-seo-dependent wpslug-stopwords-dependent">
                <th scope="row"><?php esc_html_e("Maximum Words", "wpslug"); ?></th>
                <td>
                    <input type="number"
                           name="wpslug_options[seo_max_words]"
                           value="<?php echo esc_attr(
                               $options["seo_max_words"]
                           ); ?>"
                           min="1"
                           max="30"
                           class="small-text">
                    <p class="description">
                        <?php esc_html_e(
                            "Maximum number of words to keep in slug for SEO optimization.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr class="wpslug-seo-dependent wpslug-stopwords-dependent">
                <th scope="row"><?php esc_html_e("Stop Words List", "wpslug"); ?></th>
                <td>
                    <textarea name="wpslug_options[stop_words_list]"
                              rows="3"
                              cols="50"
                              id="stop_words_list"
                              class="large-text"><?php echo esc_textarea(
                                  $options["stop_words_list"]
                              ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e(
                            "Comma-separated list of stop words to remove from slugs.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderMediaSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("Media Files", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Configure how media file names are handled during upload.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e(
                    "Media File Conversion",
                    "wpslug"
                ); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[disable_file_convert]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[disable_file_convert]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["disable_file_convert"]
                               ); ?>>
                        <?php esc_html_e(
                            "Disable automatic file name conversion for uploaded media",
                            "wpslug"
                        ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            "When checked, media files will not be converted automatically.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e(
                    "Media Conversion Mode",
                    "wpslug"
                ); ?></th>
                <td>
                    <select name="wpslug_options[media_conversion_mode]">
                        <option value="normal" <?php selected(
                            $options["media_conversion_mode"],
                            "normal"
                        ); ?>>
                            <?php esc_html_e(
                                "Normal Conversion (same as content)",
                                "wpslug"
                            ); ?>
                        </option>
                        <option value="md5" <?php selected(
                            $options["media_conversion_mode"],
                            "md5"
                        ); ?>>
                            <?php esc_html_e(
                                "MD5 Hash (generates unique hash)",
                                "wpslug"
                            ); ?>
                        </option>
                        <option value="none" <?php selected(
                            $options["media_conversion_mode"],
                            "none"
                        ); ?>>
                            <?php esc_html_e(
                                "No Conversion (keep original)",
                                "wpslug"
                            ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e(
                            "Choose how media file names should be processed. MD5 creates unique hashes for file names.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e("Media File Prefix", "wpslug"); ?></th>
                <td>
                    <input type="text"
                           name="wpslug_options[media_file_prefix]"
                           value="<?php echo esc_attr(
                               $options["media_file_prefix"]
                           ); ?>"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e(
                            'Optional prefix to add to all media file names (e.g., "img-", "file-").',
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e(
                    "Preserve Original Extension",
                    "wpslug"
                ); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[preserve_media_extension]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[preserve_media_extension]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["preserve_media_extension"]
                               ); ?>>
                        <?php esc_html_e(
                            "Always preserve the original file extension",
                            "wpslug"
                        ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e(
                            "Ensures file extensions are kept even when using MD5 conversion.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function renderAdvancedSettings($options)
    {
        ?>
        <div class="wpslug-section-header">
            <h3><?php esc_html_e("Advanced Settings", "wpslug"); ?></h3>
            <p><?php esc_html_e(
                "Advanced options and content type configuration for power users.",
                "wpslug"
            ); ?></p>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e("Content Types", "wpslug"); ?></th>
                <td>
                    <h4><?php esc_html_e("Post Types", "wpslug"); ?></h4>
                    <div class="wpslug-checkbox-grid">
                        <?php
                        $post_types = get_post_types(
                            ["public" => true],
                            "objects"
                        );
                        foreach ($post_types as $post_type) {
                            $checked =
                                is_array($options["enabled_post_types"]) &&
                                in_array(
                                    $post_type->name,
                                    $options["enabled_post_types"]
                                )
                                    ? "checked"
                                    : ""; ?>
                            <label class="wpslug-checkbox-item">
                                <input type="checkbox"
                                       name="wpslug_options[enabled_post_types][]"
                                       value="<?php echo esc_attr(
                                           $post_type->name
                                       ); ?>"
                                       <?php echo esc_attr($checked); ?>>
                                <span><?php echo esc_html(
                                    $post_type->label
                                ); ?></span>
                            </label>
                            <?php
                        }
                        ?>
                    </div>
                    <p class="description">
                        <?php esc_html_e(
                            "Select post types to apply slug conversion.",
                            "wpslug"
                        ); ?>
                    </p>

                    <h4><?php esc_html_e("Taxonomies", "wpslug"); ?></h4>
                    <div class="wpslug-checkbox-grid">
                        <?php
                        $taxonomies = get_taxonomies(
                            ["public" => true],
                            "objects"
                        );
                        foreach ($taxonomies as $taxonomy) {
                            $checked =
                                is_array($options["enabled_taxonomies"]) &&
                                in_array(
                                    $taxonomy->name,
                                    $options["enabled_taxonomies"]
                                )
                                    ? "checked"
                                    : ""; ?>
                            <label class="wpslug-checkbox-item">
                                <input type="checkbox"
                                       name="wpslug_options[enabled_taxonomies][]"
                                       value="<?php echo esc_attr(
                                           $taxonomy->name
                                       ); ?>"
                                       <?php echo esc_attr($checked); ?>>
                                <span><?php echo esc_html(
                                    $taxonomy->label
                                ); ?></span>
                            </label>
                            <?php
                        }
                        ?>
                    </div>
                    <p class="description">
                        <?php esc_html_e(
                            "Select taxonomies to apply slug conversion.",
                            "wpslug"
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e("Display Options", "wpslug"); ?></th>
                <td>
                    <input type="hidden" name="wpslug_options[show_slug_column]" value="0">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[show_slug_column]"
                               value="1"
                               <?php checked(
                                   1,
                                   $options["show_slug_column"]
                               ); ?>>
                        <?php esc_html_e(
                            "Show slug column in post and page lists for easy reference",
                            "wpslug"
                        ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function addPermalinkNotice()
    {
        add_action("admin_notices", [$this, "displayPermalinkNotice"]);
    }

    public function displayPermalinkNotice()
    {
        $screen = get_current_screen();
        if ($screen->id !== "options-permalink") {
            return;
        }

        $options = $this->settings->getOptions();
        if (!$options["enable_conversion"]) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e(
                    "WP Slug Plugin Active",
                    "wpslug"
                ); ?></strong> -
                <?php esc_html_e(
                    "Your slugs are automatically converted based on your WP Slug settings.",
                    "wpslug"
                ); ?>
                <a href="<?php echo esc_url(admin_url(
                    "options-general.php?page=wpslug"
                )); ?>" class="button button-small" style="margin-left: 10px;">
                    <?php esc_html_e("Configure WP Slug", "wpslug"); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function validateOptions($input)
    {
        $validated = $this->settings->validateOptions($input);

        if (!empty($validated)) {
            $current_options = $this->settings->getOptions();
            $merged_options = array_merge($current_options, $validated);

            if (
                // Settings API verifies the options nonce before this sanitize callback.
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                isset($_POST["wpslug_current_tab"]) &&
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                !empty($_POST["wpslug_current_tab"])
            ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $tab = sanitize_text_field(wp_unslash($_POST["wpslug_current_tab"]));
                set_transient("wpslug_admin_tab", $tab, 60);
            }

            return $merged_options;
        }

        return $input;
    }

    public function enqueueScripts($hook)
    {
        if ("settings_page_wpslug" !== $hook) {
            return;
        }

        wp_enqueue_script(
            "wpslug-admin",
            WPSLUG_PLUGIN_URL . "assets/admin.js",
            ["jquery"],
            WPSLUG_VERSION,
            true
        );
        wp_enqueue_style(
            "wpslug-admin",
            WPSLUG_PLUGIN_URL . "assets/admin.css",
            [],
            WPSLUG_VERSION
        );

        $saved_tab = get_transient("wpslug_admin_tab");
        if ($saved_tab) {
            delete_transient("wpslug_admin_tab");
        }

        wp_localize_script("wpslug-admin", "wpslug_ajax", [
            "ajax_url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("wpslug_nonce"),
            "current_tab" => $saved_tab ?: "general",
            "strings" => [
                "preview" => __("Preview", "wpslug"),
                "converting" => __("Converting...", "wpslug"),
                "testing" => __("Testing...", "wpslug"),
                "test_api" => __("Test API", "wpslug"),
                "reset_confirm" => __(
                    "Are you sure you want to reset all settings to default values?",
                    "wpslug"
                ),
                "api_test_success" => __(
                    "API connection successful!",
                    "wpslug"
                ),
                "api_test_failed" => __(
                    "API connection failed. Please check your credentials.",
                    "wpslug"
                ),
                "no_text" => __("Please enter some text to preview.", "wpslug"),
                "conversion_error" => __(
                    "Conversion failed. Please check your settings.",
                    "wpslug"
                ),
            ],
        ]);
    }

    public function ajaxPreview()
    {
        check_ajax_referer("wpslug_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("You are not allowed to manage WPSlug settings.", "wpslug")], 403);
        }

        $text = isset($_POST["text"]) ? sanitize_text_field(wp_unslash($_POST["text"])) : "";
        $options = $this->settings->getOptions();

        if (empty($text)) {
            wp_send_json_error([
                "message" => __("Please enter some text to preview.", "wpslug"),
            ]);
        }

        try {
            $converted = $this->converter->convert($text, $options);
            $optimized = $this->optimizer->optimize($converted, $options);

            wp_send_json_success([
                "original" => $text,
                "converted" => $converted,
                "optimized" => $optimized,
                "final" => $optimized,
                "mode" => $options["conversion_mode"],
                "detected_language" => $this->converter->detectLanguage($text),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                "message" => __(
                    "Conversion failed. Please check your settings.",
                    "wpslug"
                ),
            ]);
        }
    }

    public function ajaxTestApi()
    {
        check_ajax_referer("wpslug_nonce", "nonce");

        if (!current_user_can("manage_options")) {
            wp_send_json_error(["message" => __("You are not allowed to manage WPSlug settings.", "wpslug")], 403);
        }

        $service = isset($_POST["service"]) ? sanitize_text_field(wp_unslash($_POST["service"])) : "";
        $options = $this->settings->getOptions();

        if ($service === "google") {
            $api_key = trim($options["google_api_key"]);
            if (empty($api_key)) {
                wp_send_json_error([
                    "message" => __(
                        "Google API key is required for testing.",
                        "wpslug"
                    ),
                ]);
            }
        } elseif ($service === "baidu") {
            $app_id = trim($options["baidu_app_id"]);
            $secret_key = trim($options["baidu_secret_key"]);
            if (empty($app_id) || empty($secret_key)) {
                wp_send_json_error([
                    "message" => __(
                        "Both Baidu App ID and Secret Key are required for testing.",
                        "wpslug"
                    ),
                ]);
            }
        } else {
            wp_send_json_error([
                "message" => __("Invalid service selected.", "wpslug"),
            ]);
        }

        $test_text = "Hello World";
        $translator = new WPSlug_Translator();

        try {
            $result = $translator->translate(
                $test_text,
                array_merge($options, ["translation_service" => $service])
            );

            if (!empty($result) && $result !== $test_text) {
                wp_send_json_success([
                    "message" => __("API connection successful!", "wpslug"),
                ]);
            } else {
                wp_send_json_error([
                    "message" => __(
                        "API connection failed. Please check your credentials.",
                        "wpslug"
                    ),
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                "message" =>
                    __("API connection failed: ", "wpslug") . $e->getMessage(),
            ]);
        }
    }

    public function addPostMetaBox()
    {
        global $post;

        if (!$post || !current_user_can("edit_post", $post->ID)) {
            return;
        }

        $options = $this->settings->getOptions();

        if (
            !$options["enable_conversion"] ||
            !$this->settings->isPostTypeEnabled($post->post_type)
        ) {
            return;
        }

        echo '<div class="misc-pub-section wpslug-disable-section">';
        echo "<label>";
        echo '<input type="checkbox" name="wpslug_disable_conversion" value="1" style="margin-right: 5px;">';
        echo esc_html__(
            "Disable automatic slug conversion for this post",
            "wpslug"
        );
        echo "</label>";
        echo "</div>";
    }

    public function addBulkAction($bulk_actions)
    {
        $options = $this->settings->getOptions();
        if (!$options["enable_conversion"]) {
            return $bulk_actions;
        }

        $modes = $this->settings->getConversionModes();
        $action_text = sprintf(
            /* translators: %s: active conversion mode label. */
            __("Convert Slugs (%s)", "wpslug"),
            $modes[$options["conversion_mode"]]
        );
        $bulk_actions["wpslug-convert"] = $action_text;
        return $bulk_actions;
    }

    public function handleBulkAction($redirect_url, $action, $post_ids)
    {
        if ($action !== "wpslug-convert") {
            return $redirect_url;
        }

        $options = $this->settings->getOptions();
        $converted_count = 0;

        foreach ($post_ids as $post_id) {
            if (!current_user_can("edit_post", $post_id)) {
                continue;
            }

            $post = get_post($post_id);
            if (!$post || !$this->settings->isPostTypeEnabled($post->post_type)) {
                continue;
            }

            $new_slug = $this->converter->convert($post->post_title, $options);
            $new_slug = $this->optimizer->optimize($new_slug, $options);

            if (!empty($new_slug) && $new_slug !== $post->post_name) {
                $unique_slug = $this->optimizer->generateUniqueSlug(
                    $new_slug,
                    $post->ID,
                    $post->post_type
                );
                wp_update_post([
                    "ID" => $post->ID,
                    "post_name" => $unique_slug,
                ]);
                $converted_count++;
            }
        }

        $redirect_url = add_query_arg(
            "wpslug-converted",
            $converted_count,
            $redirect_url
        );
        return $redirect_url;
    }

    public function bulkActionNotice()
    {
        // The value is added by handleBulkAction after WordPress verifies the bulk-action nonce.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET["wpslug-converted"])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $count = absint($_GET["wpslug-converted"]);
            $message = sprintf(
                /* translators: %d: number of converted slugs. */
                __("Successfully converted %d slug(s).", "wpslug"),
                $count
            );
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html($message)
            );
        }
    }
}
