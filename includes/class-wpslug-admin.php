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
        add_filter(
            "plugin_action_links_" . WPSLUG_PLUGIN_BASENAME,
            [$this, "actionLinks"]
        );
        add_filter("all_plugins", [$this, "injectPluginSlug"]);
    }

    public function handleReset()
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("You do not have sufficient permissions to access this page.", "wpslug"));
        }
        check_admin_referer("wpslug_reset");
        if (empty($_POST["wpslug_reset_confirm"])) {
            wp_safe_redirect(admin_url("options-general.php?page=wpslug&tab=tools&wpslug_notice=reset-confirm"));
            exit;
        }
        delete_option("wpslug_options");
        wp_safe_redirect(
            admin_url(
                "options-general.php?page=wpslug&tab=tools&settings-updated=true"
            )
        );
        exit;
    }


    /**
     * Plugins list: Settings before Deactivate.
     *
     * @param string[] $links Action links.
     * @return string[]
     */
    public function actionLinks($links)
    {
        $url = admin_url("options-general.php?page=wpslug");
        array_unshift(
            $links,
            '<a href="' . esc_url($url) . '">' . esc_html__("设置", "wpslug") . "</a>"
        );
        return $links;
    }

    /**
     * Give the list table a slug so "View details" appears (Update URI plugins
     * only get a slug after an update check lands in the transient).
     *
     * @param array<string, array<string, mixed>> $plugins Plugins.
     * @return array<string, array<string, mixed>>
     */
    public function injectPluginSlug($plugins)
    {
        $file = WPSLUG_PLUGIN_BASENAME;
        if (isset($plugins[$file]) && empty($plugins[$file]["slug"])) {
            $plugins[$file]["slug"] = "wpslug";
        }
        return $plugins;
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
        if (class_exists("Wenpai_Admin_Menu", false)) {
            Wenpai_Admin_Menu::register([
                "slug" => "wpslug",
                "label" => __("Slug", "wpslug"),
                "title" => __("WPSlug Settings", "wpslug"),
                "render" => [$this, "displayAdminPage"],
            ]);
            return;
        }
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


    /**
     * The kit settings instance. The kit is vendored in lib/ and always loaded
     * (the page shell needs it too), so there is no hand-written fallback.
     *
     * @return Wenpai_Admin_Settings|null
     */
    private function fw()
    {
        return $this->settings->getFramework();
    }

    /**
     * Echo one framework field.
     *
     * @param string $id Field id.
     */
    private function field($id)
    {
        $fw = $this->fw();
        if ($fw) {
            $fw->render_field($id);
        }
    }

    /**
     * Data attributes that make a hand-written block follow a field (kit depends_on).
     *
     * @param array $depends_on Same shape as the schema key.
     * @return string Leading space included, or ''.
     */
    private function dependsAttrs(array $depends_on)
    {
        $fw = $this->fw();
        return $fw ? $fw->depends_on_attrs($depends_on) : "";
    }

    /**
     * Simple-page row: title and description on the left, the kit switch on the right.
     *
     * @param string $id Toggle field id.
     */
    private function renderSwitchRow($id)
    {
        $fw = $this->fw();
        if (!$fw) {
            return;
        }
        $f = $fw->fields()[$id];
        $rid = "wpslug-row-" . $id;
        ?>
          <div class="simple-row"<?php echo $this->dependsAttrs((array) $f["depends_on"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>>
            <div>
              <div class="t" id="<?php echo esc_attr($rid); ?>-t"><?php echo esc_html($f["label"]); ?></div>
              <div class="d" id="<?php echo esc_attr($rid); ?>-d"><?php echo esc_html($f["desc"]); ?></div>
            </div>
            <div class="r">
              <?php $fw->render_control($id, ["labelledby" => $rid . "-t", "describedby" => $rid . "-d"]); ?>
            </div>
          </div>
        <?php
    }

    private function renderPreviewCard()
    {
        ?>
        <section class="card wpslug-preview">
          <div class="card-head">
            <span class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("eye") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div>
              <h2 class="card-title"><?php esc_html_e("Preview", "wpslug"); ?></h2>
              <p class="card-sub"><?php esc_html_e("Test your conversion settings with live preview.", "wpslug"); ?></p>
            </div>
          </div>
          <div class="wpslug-preview-fields">
            <div class="field">
              <label class="field-label" for="wpslug-preview-input"><?php esc_html_e("原文", "wpslug"); ?></label>
              <div class="field-ctl"><input type="search" id="wpslug-preview-input" placeholder="<?php esc_attr_e("Enter text to preview conversion...", "wpslug"); ?>" autocomplete="off"></div>
            </div>
            <div class="field" id="wpslug-preview-result-row" hidden>
              <div class="field-label" id="wpslug-preview-result-label"><?php esc_html_e("Slug", "wpslug"); ?></div>
              <div class="field-ctl"><div class="preview-out" id="wpslug-preview-result" role="status" aria-live="polite" aria-labelledby="wpslug-preview-result-label"></div></div>
            </div>
          </div>
          <p class="meta"><?php esc_html_e("不写进文章。输入后直接显示。", "wpslug"); ?></p>
        </section>
        <?php
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
        if (!in_array($tab, ["overview", "settings", "tools", "help"], true)) {
            $tab = "overview";
        }
        if ($mode !== "advanced") {
            $mode = "simple";
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.
        $section = isset($_GET["section"]) ? sanitize_key(wp_unslash($_GET["section"])) : "pinyin";
        $adv_sections = [
            "pinyin" => [
                "label" => __("Pinyin", "wpslug"),
                "icon" => "flash",
                "sub" => __("Configure Chinese characters to Pinyin romanization.", "wpslug"),
            ],
            "translit" => [
                "label" => __("Transliteration", "wpslug"),
                "icon" => "map",
                "sub" => __("Convert foreign scripts (Cyrillic, Arabic, Greek) to Latin alphabet.", "wpslug"),
            ],
            "translate" => [
                "label" => __("Translation", "wpslug"),
                "icon" => "sparkle",
                "sub" => __("Use online translation services to convert text to English slugs.", "wpslug"),
            ],
            "seo" => [
                "label" => __("SEO Optimization", "wpslug"),
                "icon" => "equalizer",
                "sub" => __("Optimize slugs for better search engine performance and user experience.", "wpslug"),
            ],
            "media" => [
                "label" => __("Media Files", "wpslug"),
                "icon" => "image",
                "sub" => __("Configure how media file names are handled during upload.", "wpslug"),
            ],
            "types" => [
                "label" => __("Content Types", "wpslug"),
                "icon" => "server",
                "sub" => __("哪些文章类型和分类法参与转换。", "wpslug"),
            ],
        ];
        $adv_sections = array_intersect_key(
            $adv_sections,
            array_flip($this->visibleSections($options))
        );
        if (!isset($adv_sections[$section])) {
            $section = "pinyin";
        }
        if (function_exists("wenpai_admin_ui_boot")) {
            wenpai_admin_ui_boot();
        }
        $url = static function ($t, $extra = []) {
            $args = array_merge(["page" => "wpslug", "tab" => $t], $extra);
            return admin_url("options-general.php?" . http_build_query($args));
        };
        include WPSLUG_PLUGIN_DIR . "templates/admin/page.php";
    }

    /**
     * Advanced sections shown for the saved settings. Decided on the server
     * from saved values (no live switching): transliteration only when it is
     * the global mode; translation when it is the global mode or a post type
     * uses multi-language translation. Pinyin is the fallback of every mode,
     * so it always shows. Fields of a section that is not shown are not in the
     * form, and validateOptions() keeps their saved values.
     *
     * @param array $options Saved options.
     * @return string[] Section ids.
     */
    public function visibleSections($options)
    {
        $mode = isset($options["conversion_mode"]) ? (string) $options["conversion_mode"] : "pinyin";
        $map = isset($options["post_type_modes"]) && is_array($options["post_type_modes"]) ? $options["post_type_modes"] : [];
        $ids = ["pinyin"];
        if ("transliteration" === $mode) {
            $ids[] = "translit";
        }
        if ("translation" === $mode || in_array("seo_slug", $map, true)) {
            $ids[] = "translate";
        }
        return array_merge($ids, ["seo", "media", "types"]);
    }

    private function renderPinyinSettings($options)
    {
        $this->field("pinyin_format");
        $this->field("pinyin_separator");
        $this->field("preserve_english");
        $this->field("preserve_numbers");
    }

    private function renderTransliterationSettings($options)
    {
        $this->field("transliteration_method");
    }

    /**
     * Translation. Key fields follow translation_service through depends_on.
     * The "Test API" buttons and the WPMind status block stay hand-written until
     * the kit has field actions and a read-only status field.
     */
    private function renderTranslationSettings($options)
    {
        $this->field("translation_service");
        $this->field("translation_source_lang");
        $this->field("translation_target_lang");
        ?>
        <div class="wpslug-api-sections">
            <?php $this->field("google_api_key"); ?>
            <div class="wpslug-api-actions"<?php echo $this->dependsAttrs(["field" => "translation_service", "value" => "google"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <button type="button" class="btn btn-secondary wpslug-test-api" data-service="google">
                    <?php esc_html_e("Test API", "wpslug"); ?>
                </button>
                <a href="https://cloud.google.com/translate/docs/setup" target="_blank" rel="noopener"><?php esc_html_e("Get API Key", "wpslug"); ?></a>
            </div>
            <?php $this->field("baidu_app_id"); ?>
            <?php $this->field("baidu_secret_key"); ?>
            <div class="wpslug-api-actions"<?php echo $this->dependsAttrs(["field" => "translation_service", "value" => "baidu"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <button type="button" class="btn btn-secondary wpslug-test-api" data-service="baidu">
                    <?php esc_html_e("Test API", "wpslug"); ?>
                </button>
                <a href="https://fanyi-api.baidu.com/doc/21" target="_blank" rel="noopener"><?php esc_html_e("Get API Key", "wpslug"); ?></a>
            </div>
            <div class="field wpslug-api-section"<?php echo $this->dependsAttrs(["field" => "translation_service", "value" => "wpmind"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <div class="field-label"><?php esc_html_e("WenPai XinSi (WPMind)", "wpslug"); ?></div>
                <div class="field-ctl">
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
                            <a href="https://wpcy.com/mind/" target="_blank" rel="noopener"><?php esc_html_e("Learn more", "wpslug"); ?></a>
                        </p>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * SEO. The group is disabled (not hidden) by several conditions, which the
     * kit's depends_on cannot express yet, so admin.js keeps that part.
     */
    private function renderSEOSettings($options)
    {
        $this->field("enable_seo_optimization");
        ?>
        <div class="wpslug-seo-dependent"><?php $this->field("smart_punctuation"); ?></div>
        <div class="wpslug-seo-dependent"><?php $this->field("mixed_content_optimization"); ?></div>
        <div class="wpslug-seo-dependent"><?php $this->field("remove_stop_words"); ?></div>
        <div class="wpslug-seo-dependent wpslug-stopwords-dependent"><?php $this->field("seo_max_words"); ?></div>
        <div class="wpslug-seo-dependent wpslug-stopwords-dependent"><?php $this->field("stop_words_list"); ?></div>
        <?php
    }

    private function renderMediaSettings($options)
    {
        $this->field("disable_file_convert");
        $this->field("media_conversion_mode");
        $this->field("media_file_prefix");
        $this->field("preserve_media_extension");
    }

    private function renderAdvancedSettings($options)
    {
        $this->field("enabled_post_types");
        $this->field("post_type_modes");
        $this->field("enabled_taxonomies");
        $this->field("show_slug_column");
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
                    "options-general.php?page=wpslug&tab=settings"
                )); ?>" class="button button-small" style="margin-left: 10px;">
                    <?php esc_html_e("Configure WP Slug", "wpslug"); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function validateOptions($input)
    {
        $current_options = $this->settings->getOptions();
        $posted = is_array($input) ? $input : [];
        // Keys absent from this form keep their saved value.
        $validated = $this->settings->validateOptions(array_merge($current_options, $posted));

        if (!empty($validated)) {
            $merged_options = $validated;

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

        $saved = $this->settings->getOptions();
        wp_localize_script("wpslug-admin", "wpslug_ajax", [
            "ajax_url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("wpslug_nonce"),
            "current_tab" => $saved_tab ?: "general",
            // Saved values, for pages that do not carry these controls.
            "conversion_mode" => (string) $saved["conversion_mode"],
            "pinyin_format" => (string) $saved["pinyin_format"],
            "strings" => [
                "preview" => __("Preview", "wpslug"),
                "converting" => __("Converting...", "wpslug"),
                "testing" => __("Testing...", "wpslug"),
                "test_api" => __("Test API", "wpslug"),
                "seo_first_letter" => __(
                    "Not available with first-letter pinyin. Switch to full pinyin to turn it on.",
                    "wpslug"
                ),
                "seo_off" => __(
                    "Turn on SEO optimization to use this.",
                    "wpslug"
                ),
                "stop_words_off" => __(
                    "Turn on stop word removal to use this.",
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
                "conversion_error" => __("Conversion failed. Please check your settings.", "wpslug"),
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
        // Preview may include the unsaved settings form. Validate, do not save.
        // Nonce checked above with check_ajax_referer().
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST["wpslug_options"]) && is_array($_POST["wpslug_options"])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $posted = wp_unslash($_POST["wpslug_options"]);
            $draft = $this->settings->validateOptions(array_merge($options, $posted));
            if (is_array($draft) && $draft !== []) {
                $options = $draft;
            }
        }

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
