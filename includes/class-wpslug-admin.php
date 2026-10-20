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

        // Test Connection (kit field action on the key fields). admin_init runs
        // on admin-ajax.php before wp_ajax_*; the kit checks the nonce
        // (_wpnonce, action wpslug_test_api) and manage_options in handle_ajax().
        $fw = $this->fw();
        if ($fw) {
            $fw->ajax_action("wpslug_test_api", [$this, "testApiAction"], ["capability" => "manage_options"]);
        }
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
     * Translation. Key fields follow translation_service through depends_on;
     * Test Connection is a kit field action on google_api_key and
     * baidu_secret_key; WPMind is the kit read-only status field.
     */
    private function renderTranslationSettings($options)
    {
        $this->field("translation_service");
        $this->field("translation_source_lang");
        $this->field("translation_target_lang");
        ?>
        <?php $this->field("google_api_key"); ?>
        <div class="wpslug-api-actions"<?php echo $this->dependsAttrs(["field" => "translation_service", "value" => "google"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <a href="https://cloud.google.com/translate/docs/setup" target="_blank" rel="noopener noreferrer"><?php esc_html_e("Get API Key", "wpslug"); ?><span class="screen-reader-text"><?php esc_html_e("(opens in a new tab)", "wpslug"); ?></span></a>
        </div>
        <?php $this->field("baidu_app_id"); ?>
        <?php $this->field("baidu_secret_key"); ?>
        <div class="wpslug-api-actions"<?php echo $this->dependsAttrs(["field" => "translation_service", "value" => "baidu"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <a href="https://fanyi-api.baidu.com/doc/21" target="_blank" rel="noopener noreferrer"><?php esc_html_e("Get API Key", "wpslug"); ?><span class="screen-reader-text"><?php esc_html_e("(opens in a new tab)", "wpslug"); ?></span></a>
        </div>
        <?php $this->field("wpmind_status"); ?>
        <?php
    }

    /**
     * SEO. Disabled-with-reason linkage is kit depends_on (mode "disable")
     * in the schema. conversion_mode is on the simple page, not here: the kit
     * prints the condition's result against saved values (initial disabled
     * state and data-wenpai-depends-saved), so no mirror control is needed.
     */
    private function renderSEOSettings($options)
    {
        $this->field("enable_seo_optimization");
        $this->field("smart_punctuation");
        $this->field("mixed_content_optimization");
        $this->field("remove_stop_words");
        $this->field("seo_max_words");
        $this->field("stop_words_list");
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
        $this->field("show_editor_panel");
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

    /**
     * Sanitize callback of wpslug_options.
     *
     * Settings forms post the kit tab marker (tab_input_html()): the kit then
     * writes only that form's fields, keeps every other field as saved, and
     * writes nothing when the marker's nonce is forged or expired (tab_expired).
     * The input goes to the kit untouched; merging the saved option in first
     * would hide an unticked checkbox on the saved form.
     *
     * Without a marker (update_option() from code, CLI, import, older forms)
     * keys absent from the input keep their saved value, so a partial array
     * never turns other toggles off or empties multiselects.
     *
     * @param mixed $input Raw value.
     * @return array
     */
    public function validateOptions($input)
    {
        $posted = is_array($input) ? $input : [];
        $fw = $this->fw();
        $tab_key = $fw ? Wenpai_Admin_Settings::TAB_KEY : "__wenpai_tab";
        if (isset($posted[$tab_key]) && is_scalar($posted[$tab_key])) {
            // Remembered for one minute so the page reopens on the saved form.
            set_transient("wpslug_admin_tab", sanitize_key((string) $posted[$tab_key]), 60);
            return $this->settings->validateOptions($posted);
        }
        return $this->settings->validateOptions(array_merge($this->settings->getOptions(), $posted));
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
        // Setting "show_editor_panel" (default off): when off, register and
        // enqueue nothing, so the panel's script and style never load.
        if (!$this->settings->getOption("show_editor_panel")) {
            return;
        }
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

    /**
     * Kit action handler for Test Connection (wp_ajax_wpslug_test_api).
     * Wenpai_Admin_Settings::handle_ajax() has already checked the nonce and
     * manage_options before this runs.
     *
     * @param array $values Posted values of wpslug_options (an empty secret means the saved one).
     * @return string|WP_Error Success receipt, or an error naming the service.
     */
    public function testApiAction($values = [])
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in Wenpai_Admin_Settings::handle_ajax().
        $service = isset($_POST["service"]) ? sanitize_key(wp_unslash($_POST["service"])) : "";
        return $this->testApi($service, is_array($values) ? $values : []);
    }

    /**
     * Test one translation service with the given (possibly unsaved) values.
     *
     * @param string $service google|baidu.
     * @param array  $values  Field values over the saved options.
     * @return string|WP_Error
     */
    public function testApi($service, array $values = [])
    {
        $options = array_merge($this->settings->getOptions(), $values);

        if ($service === "google") {
            $failed = __("Could not connect to Google Translate. Please check the key.", "wpslug");
            if (trim((string) $options["google_api_key"]) === "") {
                return new WP_Error("wpslug_missing_key", __("Enter the API key first.", "wpslug"));
            }
        } elseif ($service === "baidu") {
            $failed = __("Could not connect to Baidu Translate. Please check the key.", "wpslug");
            if (trim((string) $options["baidu_app_id"]) === "" || trim((string) $options["baidu_secret_key"]) === "") {
                return new WP_Error("wpslug_missing_key", __("Enter the App ID and Secret Key first.", "wpslug"));
            }
        } else {
            return new WP_Error("wpslug_invalid_service", __("Could not run the test. Please choose a translation service first.", "wpslug"));
        }

        // The translator falls back to local pinyin when the service fails, so
        // a result equal to that fallback means the service did not answer.
        $probe = "你好";
        $test_options = array_merge($options, [
            "translation_service" => $service,
            "translation_source_lang" => "zh",
            "translation_target_lang" => "en",
        ]);
        $fallback = $this->converter->convert($probe, array_merge($test_options, ["conversion_mode" => "pinyin"]));

        try {
            $result = (new WPSlug_Translator())->translate($probe, $test_options);
        } catch (Exception $e) {
            return new WP_Error("wpslug_test_exception", $failed);
        }

        if (is_string($result) && $result !== "" && $result !== $fallback) {
            return __("Connected.", "wpslug");
        }
        return new WP_Error("wpslug_test_failed", $failed);
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
