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
        add_action("wp_ajax_wpslug_editor_preview", [$this, "ajaxEditorPreview"]);
        add_action("post_submitbox_start", [$this, "addPostMetaBox"]);
        add_action("enqueue_block_editor_assets", [$this, "enqueueBlockEditorAssets"]);
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
        add_filter("admin_body_class", [$this, "bodyClass"]);
        add_action("admin_post_wpslug_reset", [$this, "handleReset"]);
    }

    public function handleReset()
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("You do not have sufficient permissions to access this page.", "wpslug"));
        }
        check_admin_referer("wpslug_reset");
        if (empty($_POST["wpslug_reset_confirm"])) {
            wp_safe_redirect(admin_url("admin.php?page=wpslug&tab=tools&wpslug_notice=reset-confirm"));
            exit;
        }
        delete_option("wpslug_options");
        wp_safe_redirect(
            admin_url(
                "admin.php?page=wpslug&tab=tools&settings-updated=true"
            )
        );
        exit;
    }

    public function bodyClass($classes)
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_GET["page"]) && "wpslug" === sanitize_key(wp_unslash($_GET["page"]))) {
            $classes .= " wpslug-admin";
        }
        // phpcs:enable
        return $classes;
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
        add_menu_page(
            "文派素格",
            "素格",
            "manage_options",
            "wpslug",
            [$this, "displayAdminPage"],
            "dashicons-admin-links",
            82
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
        if (strpos($location, "page=wpslug") !== false) {
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

        $quota = $this->settings->pullWpmindQuotaNotice();
        if (is_array($quota)) {
            $wpmind_url = admin_url("options-general.php?page=wpmind");
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo esc_html__(
                "WPMind quota or budget was exceeded while generating a slug. WPSlug fell back to local pinyin so the save was not blocked.",
                "wpslug"
            );
            echo " ";
            printf(
                /* translators: %s: WPMind settings URL. */
                esc_html__(
                    "Top up credits or switch to your own API key in %s.",
                    "wpslug"
                ),
                '<a href="' . esc_url($wpmind_url) . '">' .
                    esc_html__("WPMind settings", "wpslug") .
                    "</a>"
            );
            echo "</p></div>";
        }
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
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET["tab"]) ? sanitize_key(wp_unslash($_GET["tab"])) : "overview";
        $mode = isset($_GET["mode"]) ? sanitize_key(wp_unslash($_GET["mode"])) : "simple";
        // phpcs:enable
        if (!in_array($tab, ["overview", "settings", "tools"], true)) {
            $tab = "overview";
        }
        if ($mode !== "advanced") {
            $mode = "simple";
        }
        if (function_exists("wenpai_admin_ui_boot")) {
            wenpai_admin_ui_boot();
        }
        $url = static function ($t, $extra = []) {
            $args = array_merge(["page" => "wpslug", "tab" => $t], $extra);
            return admin_url("admin.php?" . http_build_query($args));
        };
        include WPSLUG_PLUGIN_DIR . "templates/admin/page.php";
    }

    private function renderGeneralSettings($options)
    {
        ?>
        <h2 class="section-title"><?php esc_html_e("General Settings", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Configure basic plugin behavior and choose your conversion method.",
            "wpslug"
        ); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Enable Plugin", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[enable_conversion]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[enable_conversion]"
                               value="1"
                               id="enable_conversion"
                               <?php checked(1, $options["enable_conversion"]); ?>>
                        <?php esc_html_e(
                            "Enable automatic slug conversion for your content",
                            "wpslug"
                        ); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="field wpslug-dependent" data-depends="enable_conversion">
            <div class="field-label"><?php esc_html_e("Conversion Mode", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[conversion_mode]" id="conversion_mode">
                    <?php foreach ($this->settings->getConversionModes() as $mode => $label) : ?>
                        <option value="<?php echo esc_attr($mode); ?>" <?php selected($options["conversion_mode"], $mode); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Choose local pinyin, semantic pinyin (XinSi AI), multi-language translation, or transliteration.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-dependent" data-depends="enable_conversion">
            <div class="field-label"><?php esc_html_e("Auto Convert", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[auto_convert]" value="0">
                <div class="chk">
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
                </div>
            </div>
        </div>
        <div class="field wpslug-dependent" data-depends="enable_conversion">
            <div class="field-label"><?php esc_html_e("Convert on Publish Only", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[convert_on_publish_only]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[convert_on_publish_only]"
                               value="1"
                               <?php checked(1, !empty($options["convert_on_publish_only"])); ?>>
                        <?php esc_html_e(
                            "Only convert post slugs when status is publish or future (skips draft autosaves)",
                            "wpslug"
                        ); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "Reduces WPMind calls during autosave. Placeholder Auto Draft slugs are still cleared so they cannot freeze. Bulk Convert remains an explicit migration and may use WPMind quota.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-dependent" data-depends="enable_conversion">
            <div class="field-label"><?php esc_html_e("Force Lowercase", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[force_lowercase]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[force_lowercase]"
                               value="1"
                               <?php checked(1, $options["force_lowercase"]); ?>>
                        <?php esc_html_e(
                            "Convert all slugs to lowercase for consistency",
                            "wpslug"
                        ); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="field wpslug-dependent" data-depends="enable_conversion">
            <div class="field-label"><?php esc_html_e("Maximum Length", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="number"
                       name="wpslug_options[max_length]"
                       value="<?php echo esc_attr($options["max_length"]); ?>"
                       min="0"
                       max="500">
                <p class="hint"><?php esc_html_e(
                    "Maximum length of generated slugs (0 = no limit).",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <?php
    }

    private function renderPinyinSettings($options)
    {
        ?>
        <h2 class="section-title"><?php esc_html_e("Chinese Pinyin Settings", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Configure Chinese characters to Pinyin romanization.",
            "wpslug"
        ); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Pinyin Format", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[pinyin_format]" id="pinyin_format">
                    <option value="full" <?php selected($options["pinyin_format"], "full"); ?>>
                        <?php esc_html_e("Full Pinyin (ni-hao)", "wpslug"); ?>
                    </option>
                    <option value="first" <?php selected($options["pinyin_format"], "first"); ?>>
                        <?php esc_html_e("First Letter Only (n-h)", "wpslug"); ?>
                    </option>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Choose between full pinyin or first letters only. First letter mode creates very concise URLs and automatically disables SEO optimization.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Word Separator", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[pinyin_separator]">
                    <option value="-" <?php selected($options["pinyin_separator"], "-"); ?>>
                        <?php esc_html_e("Dash (-)", "wpslug"); ?>
                    </option>
                    <option value="_" <?php selected($options["pinyin_separator"], "_"); ?>>
                        <?php esc_html_e("Underscore (_)", "wpslug"); ?>
                    </option>
                    <option value="" <?php selected($options["pinyin_separator"], ""); ?>>
                        <?php esc_html_e("No Separator", "wpslug"); ?>
                    </option>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Choose the separator between pinyin words.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Preserve Settings", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[preserve_english]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[preserve_english]"
                               value="1"
                               <?php checked(1, $options["preserve_english"]); ?>>
                        <?php esc_html_e("Preserve English letters in mixed content", "wpslug"); ?>
                    </label>
                </div>
                <input type="hidden" name="wpslug_options[preserve_numbers]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[preserve_numbers]"
                               value="1"
                               <?php checked(1, $options["preserve_numbers"]); ?>>
                        <?php esc_html_e("Preserve numbers in slugs", "wpslug"); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "Keep English letters and numbers when converting mixed language content.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <?php
    }

    private function renderTransliterationSettings($options)
    {
        ?>
        <h2 class="section-title"><?php esc_html_e("Transliteration Settings", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Convert foreign scripts (Cyrillic, Arabic, Greek) to Latin alphabet.",
            "wpslug"
        ); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Transliteration Method", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[transliteration_method]">
                    <?php foreach ($this->settings->getTransliterationMethods() as $method => $label) : ?>
                        <option value="<?php echo esc_attr($method); ?>" <?php selected($options["transliteration_method"], $method); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Choose the transliteration method. iconv and Intl provide better accuracy if available.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <?php
    }

    private function renderTranslationSettings($options)
    {
        ?>
        <h2 class="section-title"><?php esc_html_e("Translation Settings", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Use online translation services to convert text to English slugs.",
            "wpslug"
        ); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Translation Service", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[translation_service]" id="translation_service">
                    <?php foreach ($this->settings->getTranslationServices() as $service => $label) : ?>
                        <option value="<?php echo esc_attr($service); ?>" <?php selected($options["translation_service"], $service); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Choose translation service. Useful for non-English content to generate English slugs.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Source Language", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[translation_source_lang]">
                    <?php foreach ($this->settings->getLanguages() as $lang => $label) : ?>
                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($options["translation_source_lang"], $lang); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Source language for translation. Auto-detect is recommended.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Target Language", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[translation_target_lang]">
                    <?php foreach ($this->settings->getLanguages() as $lang => $label) : ?>
                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($options["translation_target_lang"], $lang); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Target language for translation. English is recommended for SEO.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="wpslug-api-sections">
            <div class="wpslug-api-section" data-service="google">
                <h3 class="section-title"><?php esc_html_e("Google Translate API", "wpslug"); ?></h3>
                <div class="field">
                    <div class="field-label"><?php esc_html_e("API Key", "wpslug"); ?></div>
                    <div class="field-ctl">
                        <div class="field-ctl-row">
                            <input type="password"
                                   name="wpslug_options[google_api_key]"
                                   value="<?php echo esc_attr($options["google_api_key"]); ?>"
                                   autocomplete="new-password">
                            <button type="button" class="btn btn-secondary wpslug-test-api" data-service="google">
                                <?php esc_html_e("Test API", "wpslug"); ?>
                            </button>
                        </div>
                        <p class="hint">
                            <?php esc_html_e("Enter your Google Translate API key.", "wpslug"); ?>
                            <a href="https://cloud.google.com/translate/docs/setup" target="_blank">
                                <?php esc_html_e("Get API Key", "wpslug"); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="wpslug-api-section" data-service="baidu">
                <h3 class="section-title"><?php esc_html_e("Baidu Translate API", "wpslug"); ?></h3>
                <div class="field">
                    <div class="field-label"><?php esc_html_e("App ID", "wpslug"); ?></div>
                    <div class="field-ctl">
                        <input type="text"
                               name="wpslug_options[baidu_app_id]"
                               value="<?php echo esc_attr($options["baidu_app_id"]); ?>">
                        <p class="hint"><?php esc_html_e("Enter your Baidu Translate App ID.", "wpslug"); ?></p>
                    </div>
                </div>
                <div class="field">
                    <div class="field-label"><?php esc_html_e("Secret Key", "wpslug"); ?></div>
                    <div class="field-ctl">
                        <div class="field-ctl-row">
                            <input type="password"
                                   name="wpslug_options[baidu_secret_key]"
                                   value="<?php echo esc_attr($options["baidu_secret_key"]); ?>"
                                   autocomplete="new-password">
                            <button type="button" class="btn btn-secondary wpslug-test-api" data-service="baidu">
                                <?php esc_html_e("Test API", "wpslug"); ?>
                            </button>
                        </div>
                        <p class="hint">
                            <?php esc_html_e("Enter your Baidu Translate Secret Key.", "wpslug"); ?>
                            <a href="https://fanyi-api.baidu.com/doc/21" target="_blank">
                                <?php esc_html_e("Get API Key", "wpslug"); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="wpslug-api-section" data-service="wpmind">
                <h3 class="section-title"><?php esc_html_e("WenPai XinSi (WPMind)", "wpslug"); ?></h3>
                <div class="wpslug-wpmind-status">
                    <?php if (function_exists("wpmind_is_available") && wpmind_is_available()) : ?>
                        <p class="hint is-ok"><?php esc_html_e(
                            "WenPai XinSi (WPMind) is active. Credits and BYOK keys are managed there.",
                            "wpslug"
                        ); ?></p>
                        <p class="hint"><?php esc_html_e(
                            "Use it for semantic pinyin (XinSi AI) and multi-language translation. If quota is exceeded or the provider fails, WPSlug falls back to local pinyin so saving is not blocked.",
                            "wpslug"
                        ); ?></p>
                        <p class="hint">
                            <a href="<?php echo esc_url(admin_url("options-general.php?page=wpmind")); ?>">
                                <?php esc_html_e("Open WenPai XinSi (WPMind) settings", "wpslug"); ?> →
                            </a>
                        </p>
                    <?php else : ?>
                        <p class="hint is-bad"><?php esc_html_e(
                            "WenPai XinSi (WPMind) is not active or not configured.",
                            "wpslug"
                        ); ?></p>
                        <p class="hint">
                            <?php esc_html_e(
                                "Install WenPai XinSi (WPMind) for semantic pinyin (XinSi AI) and multi-language translation. Until then, local pinyin remains available.",
                                "wpslug"
                            ); ?>
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
        <h2 class="section-title"><?php esc_html_e("SEO Optimization", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Optimize slugs for better search engine performance and user experience.",
            "wpslug"
        ); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Enable SEO Optimization", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[enable_seo_optimization]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[enable_seo_optimization]"
                               value="1"
                               id="enable_seo_optimization"
                               <?php checked(1, $options["enable_seo_optimization"]); ?>>
                        <?php esc_html_e("Enable SEO-friendly slug optimization", "wpslug"); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "Apply SEO best practices to generated slugs.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-seo-dependent">
            <div class="field-label"><?php esc_html_e("Smart Punctuation", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[smart_punctuation]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[smart_punctuation]"
                               value="1"
                               <?php checked(1, $options["smart_punctuation"]); ?>>
                        <?php esc_html_e("Intelligently handle punctuation marks", "wpslug"); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "Convert colons, semicolons, and other punctuation to hyphens or remove them.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-seo-dependent">
            <div class="field-label"><?php esc_html_e("Mixed Content Optimization", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[mixed_content_optimization]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[mixed_content_optimization]"
                               value="1"
                               <?php checked(1, $options["mixed_content_optimization"]); ?>>
                        <?php esc_html_e("Optimize mixed language and number content", "wpslug"); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "Better handling of content mixing languages with numbers and English text.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-seo-dependent">
            <div class="field-label"><?php esc_html_e("Remove Stop Words", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[remove_stop_words]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[remove_stop_words]"
                               value="1"
                               id="remove_stop_words"
                               <?php checked(1, $options["remove_stop_words"]); ?>>
                        <?php esc_html_e("Remove common stop words from slugs", "wpslug"); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    'Remove words like "the", "a", "an", "and", etc. to create cleaner slugs.',
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-seo-dependent wpslug-stopwords-dependent">
            <div class="field-label"><?php esc_html_e("Maximum Words", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="number"
                       name="wpslug_options[seo_max_words]"
                       value="<?php echo esc_attr($options["seo_max_words"]); ?>"
                       min="1"
                       max="30">
                <p class="hint"><?php esc_html_e(
                    "Maximum number of words to keep in slug for SEO optimization.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field wpslug-seo-dependent wpslug-stopwords-dependent">
            <div class="field-label"><?php esc_html_e("Stop Words List", "wpslug"); ?></div>
            <div class="field-ctl">
                <textarea name="wpslug_options[stop_words_list]"
                          rows="3"
                          id="stop_words_list"><?php echo esc_textarea($options["stop_words_list"]); ?></textarea>
                <p class="hint"><?php esc_html_e(
                    "Comma-separated list of stop words to remove from slugs.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <?php
    }

    private function renderMediaSettings($options)
    {
        ?>
        <h2 class="section-title"><?php esc_html_e("Media Files", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Configure how media file names are handled during upload.",
            "wpslug"
        ); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Media File Conversion", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[disable_file_convert]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[disable_file_convert]"
                               value="1"
                               <?php checked(1, $options["disable_file_convert"]); ?>>
                        <?php esc_html_e(
                            "Disable automatic file name conversion for uploaded media",
                            "wpslug"
                        ); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "When checked, media files will not be converted automatically.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Media Conversion Mode", "wpslug"); ?></div>
            <div class="field-ctl">
                <select name="wpslug_options[media_conversion_mode]">
                    <option value="normal" <?php selected($options["media_conversion_mode"], "normal"); ?>>
                        <?php esc_html_e("Normal Conversion (same as content)", "wpslug"); ?>
                    </option>
                    <option value="md5" <?php selected($options["media_conversion_mode"], "md5"); ?>>
                        <?php esc_html_e("MD5 Hash (generates unique hash)", "wpslug"); ?>
                    </option>
                    <option value="none" <?php selected($options["media_conversion_mode"], "none"); ?>>
                        <?php esc_html_e("No Conversion (keep original)", "wpslug"); ?>
                    </option>
                </select>
                <p class="hint"><?php esc_html_e(
                    "Choose how media file names should be processed. MD5 creates unique hashes for file names.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Media File Prefix", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="text"
                       name="wpslug_options[media_file_prefix]"
                       value="<?php echo esc_attr($options["media_file_prefix"]); ?>">
                <p class="hint"><?php esc_html_e(
                    'Optional prefix to add to all media file names (e.g., "img-", "file-").',
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Preserve Original Extension", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[preserve_media_extension]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[preserve_media_extension]"
                               value="1"
                               <?php checked(1, $options["preserve_media_extension"]); ?>>
                        <?php esc_html_e("Always preserve the original file extension", "wpslug"); ?>
                    </label>
                </div>
                <p class="hint"><?php esc_html_e(
                    "Ensures file extensions are kept even when using MD5 conversion.",
                    "wpslug"
                ); ?></p>
            </div>
        </div>
        <?php
    }

    private function renderAdvancedSettings($options)
    {
        $post_types = get_post_types(["public" => true], "objects");
        $features = $this->settings->getPostTypeFeatures();
        $mode_map = isset($options["post_type_modes"]) && is_array($options["post_type_modes"])
            ? $options["post_type_modes"]
            : [];
        ?>
        <h2 class="section-title"><?php esc_html_e("Advanced Settings", "wpslug"); ?></h2>
        <p class="section-desc"><?php esc_html_e(
            "Advanced options and content type configuration for power users.",
            "wpslug"
        ); ?></p>
        <h3 class="section-title"><?php esc_html_e("Content Types", "wpslug"); ?></h3>
        <h3 class="section-title"><?php esc_html_e("Post Types", "wpslug"); ?></h3>
        <div class="chk-grid">
            <?php foreach ($post_types as $post_type) : ?>
                <label>
                    <input type="checkbox"
                           name="wpslug_options[enabled_post_types][]"
                           value="<?php echo esc_attr($post_type->name); ?>"
                           <?php checked(
                               is_array($options["enabled_post_types"]) &&
                               in_array($post_type->name, $options["enabled_post_types"])
                           ); ?>>
                    <?php echo esc_html($post_type->label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="hint"><?php esc_html_e("Select post types to apply slug conversion.", "wpslug"); ?></p>
        <h3 class="section-title"><?php esc_html_e("Default strategy per post type", "wpslug"); ?></h3>
        <p class="section-desc"><?php esc_html_e(
            "Optional overrides per post type. Example: posts → multi-language translation; products → semantic pinyin (XinSi AI). Choose “use global” to follow the conversion mode above.",
            "wpslug"
        ); ?></p>
        <table class="tbl">
            <thead>
                <tr>
                    <th><?php esc_html_e("Post type", "wpslug"); ?></th>
                    <th><?php esc_html_e("Default feature", "wpslug"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($post_types as $post_type) :
                    $current = isset($mode_map[$post_type->name]) ? $mode_map[$post_type->name] : "inherit";
                    ?>
                    <tr>
                        <td>
                            <div class="t"><?php echo esc_html($post_type->label); ?></div>
                            <div class="d"><?php echo esc_html($post_type->name); ?></div>
                        </td>
                        <td>
                            <select name="wpslug_options[post_type_modes][<?php echo esc_attr($post_type->name); ?>]">
                                <?php foreach ($features as $feature_key => $feature_label) : ?>
                                    <option value="<?php echo esc_attr($feature_key); ?>" <?php selected($current, $feature_key); ?>>
                                        <?php echo esc_html($feature_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h3 class="section-title"><?php esc_html_e("Taxonomies", "wpslug"); ?></h3>
        <div class="chk-grid">
            <?php foreach (get_taxonomies(["public" => true], "objects") as $taxonomy) : ?>
                <label>
                    <input type="checkbox"
                           name="wpslug_options[enabled_taxonomies][]"
                           value="<?php echo esc_attr($taxonomy->name); ?>"
                           <?php checked(
                               is_array($options["enabled_taxonomies"]) &&
                               in_array($taxonomy->name, $options["enabled_taxonomies"])
                           ); ?>>
                    <?php echo esc_html($taxonomy->label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="hint"><?php esc_html_e("Select taxonomies to apply slug conversion.", "wpslug"); ?></p>
        <div class="field">
            <div class="field-label"><?php esc_html_e("Display Options", "wpslug"); ?></div>
            <div class="field-ctl">
                <input type="hidden" name="wpslug_options[show_slug_column]" value="0">
                <div class="chk">
                    <label>
                        <input type="checkbox"
                               name="wpslug_options[show_slug_column]"
                               value="1"
                               <?php checked(1, $options["show_slug_column"]); ?>>
                        <?php esc_html_e(
                            "Show slug column in post and page lists for easy reference",
                            "wpslug"
                        ); ?>
                    </label>
                </div>
            </div>
        </div>
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
                    "admin.php?page=wpslug&tab=settings"
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
        if ("toplevel_page_wpslug" === $hook || "settings_page_wpslug" === $hook) {
            $this->enqueueSettingsAssets();
            return;
        }

        if (in_array($hook, ["post.php", "post-new.php"], true)) {
            $screen = function_exists("get_current_screen")
                ? get_current_screen()
                : null;
            $post_type = $screen && !empty($screen->post_type)
                ? $screen->post_type
                : "post";
            // Block editor loads via enqueue_block_editor_assets instead.
            if (
                function_exists("use_block_editor_for_post_type") &&
                use_block_editor_for_post_type($post_type)
            ) {
                return;
            }
            $this->enqueueEditorAssets(false);
        }
    }

    /**
     * Block editor assets (Gutenberg sidebar panel).
     */
    public function enqueueBlockEditorAssets()
    {
        $this->enqueueEditorAssets(true);
    }

    private function enqueueSettingsAssets()
    {
        if (function_exists("wenpai_admin_ui_boot")) {
            wenpai_admin_ui_boot();
        }
        if (class_exists("Wenpai_Admin_Loader", false)) {
            Wenpai_Admin_Loader::enqueue(["full" => true]);
        }
        wp_enqueue_script(
            "wpslug-admin",
            WPSLUG_PLUGIN_URL . "assets/admin.js",
            ["jquery", "wenpai-admin-ui"],
            WPSLUG_VERSION,
            true
        );
        wp_enqueue_style(
            "wpslug-admin",
            WPSLUG_PLUGIN_URL . "assets/admin.css",
            ["wenpai-admin-ui"],
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

    /**
     * @param bool $block Whether loading inside the block editor.
     */
    private function enqueueEditorAssets($block)
    {
        $screen = function_exists("get_current_screen")
            ? get_current_screen()
            : null;
        $post_type = $screen && !empty($screen->post_type)
            ? $screen->post_type
            : "post";

        if (!$this->settings->isPostTypeEnabled($post_type)) {
            return;
        }

        $options = $this->settings->getOptions();
        if (empty($options["enable_conversion"])) {
            return;
        }

        $deps = ["jquery"];
        if ($block) {
            $deps = [
                "wp-plugins",
                "wp-edit-post",
                "wp-element",
                "wp-components",
                "wp-data",
                "wp-i18n",
            ];
        }

        wp_enqueue_style(
            "wpslug-editor",
            WPSLUG_PLUGIN_URL . "assets/editor.css",
            [],
            WPSLUG_VERSION
        );
        wp_enqueue_script(
            "wpslug-editor",
            WPSLUG_PLUGIN_URL . "assets/editor.js",
            $deps,
            WPSLUG_VERSION,
            true
        );

        $wpmind_ready =
            function_exists("wpmind_is_available") && wpmind_is_available();

        wp_localize_script("wpslug-editor", "wpslugEditor", [
            "ajaxUrl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("wpslug_editor_nonce"),
            "postType" => $post_type,
            "wpmindReady" => $wpmind_ready,
            "isBlock" => (bool) $block,
            "strings" => [
                "panelTitle" => __("WPSlug", "wpslug"),
                "seoButton" => __("用 AI 生成", "wpslug"),
                "pinyinButton" => __("用语义拼音", "wpslug"),
                "applyButton" => __("应用到固定链接", "wpslug"),
                "generating" => __("生成中…", "wpslug"),
                "emptyTitle" => __("请先填写标题。", "wpslug"),
                "previewLabel" => __("预览", "wpslug"),
                "applied" => __("已写入固定链接，保存后生效。", "wpslug"),
                "error" => __("生成失败，已可改用本地拼音或稍后重试。", "wpslug"),
                "wpmindMissing" => __(
                    "未启用 WPMind 时将回退本地拼音。",
                    "wpslug"
                ),
                "hint" => __(
                    "先预览，确认后再写入。不会在自动保存时静默覆盖。",
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

        $wpmind_ready =
            function_exists("wpmind_is_available") && wpmind_is_available();
        ?>
        <div id="wpslug-editor-tools" class="misc-pub-section wpslug-editor-tools" data-post-id="<?php echo esc_attr(
            (string) $post->ID
        ); ?>">
            <strong><?php esc_html_e("WPSlug 预览", "wpslug"); ?></strong>
            <p class="description" style="margin: 6px 0 8px;">
                <?php esc_html_e(
                    "先预览，确认后再写入。不会在自动保存时静默覆盖。",
                    "wpslug"
                ); ?>
                <?php if (!$wpmind_ready): ?>
                    <?php esc_html_e(
                        "未启用 WPMind 时将回退本地拼音。",
                        "wpslug"
                    ); ?>
                <?php endif; ?>
            </p>
            <p class="wpslug-editor-actions" style="margin: 0 0 8px;">
                <button type="button" class="button button-secondary wpslug-editor-seo">
                    <?php esc_html_e("用 AI 生成", "wpslug"); ?>
                </button>
                <button type="button" class="button button-secondary wpslug-editor-pinyin">
                    <?php esc_html_e("用语义拼音", "wpslug"); ?>
                </button>
            </p>
            <p class="wpslug-editor-preview" hidden>
                <span class="wpslug-editor-preview-label"><?php esc_html_e(
                    "预览",
                    "wpslug"
                ); ?>:</span>
                <code class="wpslug-editor-preview-value"></code>
                <button type="button" class="button button-primary button-small wpslug-editor-apply">
                    <?php esc_html_e("应用到固定链接", "wpslug"); ?>
                </button>
            </p>
            <p class="wpslug-editor-status description" aria-live="polite"></p>
            <label class="wpslug-disable-section" style="display:block;margin-top:8px;">
                <input type="checkbox" name="wpslug_disable_conversion" value="1" style="margin-right: 5px;">
                <?php esc_html_e(
                    "Disable automatic slug conversion for this post",
                    "wpslug"
                ); ?>
            </label>
        </div>
        <?php
    }

    /**
     * Editor-side preview: generate a candidate slug without writing the post.
     */
    public function ajaxEditorPreview()
    {
        check_ajax_referer("wpslug_editor_nonce", "nonce");

        $post_id = isset($_POST["post_id"]) ? absint($_POST["post_id"]) : 0;
        $text = isset($_POST["text"])
            ? sanitize_text_field(wp_unslash($_POST["text"]))
            : "";
        $feature = isset($_POST["feature"])
            ? sanitize_key(wp_unslash($_POST["feature"]))
            : "seo_slug";
        $post_type = isset($_POST["post_type"])
            ? sanitize_key(wp_unslash($_POST["post_type"]))
            : "post";

        if ($post_id > 0) {
            if (!current_user_can("edit_post", $post_id)) {
                wp_send_json_error(
                    ["message" => __("无权编辑此文章。", "wpslug")],
                    403
                );
            }
            $post = get_post($post_id);
            if ($post) {
                $post_type = $post->post_type;
            }
        } elseif (!current_user_can("edit_posts")) {
            wp_send_json_error(
                ["message" => __("无权生成 slug 预览。", "wpslug")],
                403
            );
        }

        if ($text === "") {
            wp_send_json_error([
                "message" => __("请先填写标题。", "wpslug"),
            ]);
        }

        if (!in_array($feature, ["seo_slug", "semantic_pinyin", "pinyin"], true)) {
            $feature = "seo_slug";
        }

        $options = $this->settings->resolveOptionsForPostType($post_type);
        $options = $this->settings->applyFeatureToOptions($options, $feature);

        try {
            $converted = $this->converter->convert($text, $options);
            $optimized = $this->optimizer->optimize($converted, $options);
            if ($optimized === "") {
                wp_send_json_error([
                    "message" => __(
                        "生成失败，已可改用本地拼音或稍后重试。",
                        "wpslug"
                    ),
                ]);
            }

            wp_send_json_success([
                "slug" => $optimized,
                "feature" => $feature,
                "mode" => isset($options["conversion_mode"])
                    ? $options["conversion_mode"]
                    : "",
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                "message" => __(
                    "生成失败，已可改用本地拼音或稍后重试。",
                    "wpslug"
                ),
            ]);
        }
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

            $type_options = $this->settings->resolveOptionsForPostType(
                $post->post_type
            );
            $new_slug = $this->converter->convert(
                $post->post_title,
                $type_options
            );
            $new_slug = $this->optimizer->optimize($new_slug, $type_options);

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
            $tip = __(
                "Bulk Convert is an explicit migration: it rewrites selected posts now and may consume WPMind quota when AI modes are active.",
                "wpslug"
            );
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p><p>%s</p></div>',
                esc_html($message),
                esc_html($tip)
            );
        }
    }
}
