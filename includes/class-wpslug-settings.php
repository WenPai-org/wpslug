<?php

if (!defined("ABSPATH")) {
    exit();
}

class WPSlug_Settings
{
    private $option_name = "wpslug_options";
    private $defaults;

    public function __construct()
    {
        $this->initDefaults();
    }

    private function initDefaults()
    {
        $this->defaults = [
            "enable_conversion" => true,
            "conversion_mode" => "pinyin",
            "pinyin_separator" => "-",
            "pinyin_format" => "full",
            "max_length" => 50,
            "force_lowercase" => true,
            "transliteration_method" => "basic",
            "translation_service" => "none",
            "google_api_key" => "",
            "baidu_app_id" => "",
            "baidu_secret_key" => "",
            "translation_source_lang" => "auto",
            "translation_target_lang" => "en",
            "enabled_post_types" => ["post", "page"],
            // Empty = inherit global conversion_mode. Example: post=>seo_slug, product=>semantic_pinyin.
            "post_type_modes" => [],
            "enabled_taxonomies" => ["category", "post_tag"],
            "auto_convert" => true,
            // Default on: skip draft/autosave model calls; convert on publish/future.
            "convert_on_publish_only" => true,
            "preserve_english" => true,
            "preserve_numbers" => true,
            "disable_file_convert" => false,
            "media_conversion_mode" => "normal",
            "media_file_prefix" => "",
            "preserve_media_extension" => true,
            "show_slug_column" => false,
            "enable_seo_optimization" => true,
            "remove_stop_words" => true,
            "smart_punctuation" => true,
            "mixed_content_optimization" => true,
            "seo_max_words" => 20,
            "stop_words_list" =>
                "the,a,an,and,or,but,in,on,at,to,for,of,with,by,from,up,about,into,through,during,before,after,above,below,between,among,since,without,within",
        ];
    }

    public function getOptions()
    {
        $options = get_option($this->option_name, []);
        return wp_parse_args($options, $this->defaults);
    }

    public function getOption($key, $default = null)
    {
        $options = $this->getOptions();

        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default !== null
            ? $default
            : (isset($this->defaults[$key])
                ? $this->defaults[$key]
                : null);
    }

    public function updateOption($key, $value)
    {
        $options = $this->getOptions();
        $options[$key] = $value;
        return update_option($this->option_name, $options);
    }

    public function updateOptions($new_options)
    {
        $current_options = $this->getOptions();
        $merged_options = array_merge($current_options, $new_options);
        return update_option($this->option_name, $merged_options);
    }

    public function resetOptions()
    {
        return update_option($this->option_name, $this->defaults);
    }

    public function createDefaultOptions()
    {
        if (false === get_option($this->option_name)) {
            add_option($this->option_name, $this->defaults);
        }
    }

    /**
     * The kit settings instance for wpslug_options, registered once.
     *
     * WPSlug_Admin::registerSettings() owns register_setting() (it merges the
     * posted tab over the saved option first), so the kit's own admin_init
     * register_setting is removed: two sanitize filters on one option would
     * run the secret "clear" twice and the second pass would restore the key.
     *
     * @return Wenpai_Admin_Settings|null
     */
    public function getFramework()
    {
        if (!class_exists("Wenpai_Admin_Settings")) {
            return null;
        }
        $fw = Wenpai_Admin_Settings::get_instance($this->option_name);
        if (null === $fw) {
            $fw = Wenpai_Admin_Settings::register(
                $this->option_name,
                $this->getSchema(),
                [
                    "group" => "wpslug_settings",
                    "strings" => $this->getFrameworkStrings(),
                ]
            );
            if (function_exists("remove_action")) {
                remove_action("admin_init", [$fw, "register_setting"]);
            }
        }
        return $fw;
    }

    /**
     * Kit UI strings, translated in the wpslug text domain. Wording follows the
     * kit defaults (the kit has no text domain of its own). Keys are the ones
     * Wenpai_Admin_Settings::default_strings() has in the vendored copy.
     *
     * @return array<string, string>
     */
    public function getFrameworkStrings()
    {
        return [
            "on" => __("On", "wpslug"),
            "off" => __("Off", "wpslug"),
            "secret_saved" => __("A value is saved and is not shown for security. Leave empty to keep it.", "wpslug"),
            "secret_empty" => __("Not filled in yet.", "wpslug"),
            "secret_clear" => __("Clear the saved value", "wpslug"),
            "saved" => __("Settings saved.", "wpslug"),
            /* translators: %d: number of settings that were not saved. */
            "invalid" => __("Could not save %d settings. Please follow the hint under each field.", "wpslug"),
            "invalid_value" => __("This value is not valid. The previous setting was kept.", "wpslug"),
            "saving" => __("Saving…", "wpslug"),
            "partial_saved" => __("The other settings were saved.", "wpslug"),
            /* translators: %d: total number of fields with errors. */
            "invalid_more" => __("and %d more", "wpslug"),
            "follow_global" => __("Follow the global setting", "wpslug"),
            /* translators: %s: what would be listed, e.g. post types. */
            "empty_choices" => __("No %s to choose from yet.", "wpslug"),
            /* translators: 1: hidden tab label, 2: first tab label. */
            "tab_hidden" => __("The current mode does not use the %1\$s settings, so you are back on %2\$s.", "wpslug"),
            "action_busy" => __("Working", "wpslug"),
            "action_ok" => __("Done.", "wpslug"),
            "action_failed" => __("Could not finish the action. Please try again later.", "wpslug"),
            "tab_expired" => __("Could not save because the page has expired. Please reload it and try again.", "wpslug"),
            "action_denied" => __("Could not finish the action. Please reload the page and try again.", "wpslug"),
            "new_tab" => __("(opens in a new tab)", "wpslug"),
            "secret_multiline" => __("Could not save the key. Please enter a single-line value.", "wpslug"),
        ];
    }

    public function validateOptions($options)
    {
        $fw = $this->getFramework();
        if ($fw) {
            return $fw->sanitize(is_array($options) ? $options : []);
        }
        return $this->validateOptionsLegacy($options);
    }

    public function validateOptionsLegacy($options)
    {
        if (!is_array($options)) {
            return $this->defaults;
        }

        $validated = [];

        foreach ($this->defaults as $key => $default_value) {
            $value = isset($options[$key]) ? $options[$key] : $default_value;

            switch ($key) {
                case "enable_conversion":
                case "force_lowercase":
                case "auto_convert":
                case "convert_on_publish_only":
                case "preserve_english":
                case "preserve_numbers":
                case "disable_file_convert":
                case "preserve_media_extension":
                case "show_slug_column":
                case "enable_seo_optimization":
                case "remove_stop_words":
                case "smart_punctuation":
                case "mixed_content_optimization":
                    $validated[$key] = WPSlug_Validator::validateBoolean(
                        $value
                    );
                    break;

                case "conversion_mode":
                    $validated[$key] = WPSlug_Validator::validateConversionMode(
                        $value
                    );
                    break;

                case "pinyin_separator":
                    $valid_separators = ["-", "_", ""];
                    $validated[$key] = WPSlug_Validator::validateSelect(
                        $value,
                        $valid_separators,
                        "-"
                    );
                    break;

                case "pinyin_format":
                    $valid_formats = ["full", "first"];
                    $validated[$key] = WPSlug_Validator::validateSelect(
                        $value,
                        $valid_formats,
                        "full"
                    );
                    break;

                case "transliteration_method":
                    $validated[
                        $key
                    ] = WPSlug_Validator::validateTransliterationMethod($value);
                    break;

                case "translation_service":
                    $validated[
                        $key
                    ] = WPSlug_Validator::validateTranslationService($value);
                    break;

                case "media_conversion_mode":
                    $valid_modes = ["normal", "md5", "none"];
                    $validated[$key] = WPSlug_Validator::validateSelect(
                        $value,
                        $valid_modes,
                        "normal"
                    );
                    break;

                case "translation_source_lang":
                case "translation_target_lang":
                    $default_lang =
                        $key === "translation_source_lang" ? "auto" : "en";
                    $validated[$key] =
                        WPSlug_Validator::validateLanguageCode($value) ?:
                        $default_lang;
                    break;

                case "max_length":
                    $validated[$key] = WPSlug_Validator::validateInteger(
                        $value,
                        0,
                        500
                    );
                    break;

                case "seo_max_words":
                    $validated[$key] = WPSlug_Validator::validateInteger(
                        $value,
                        1,
                        30
                    );
                    break;

                case "enabled_post_types":
                    $validated[$key] = WPSlug_Validator::validatePostTypes(
                        $value
                    );
                    break;

                case "post_type_modes":
                    $validated[$key] = WPSlug_Validator::validatePostTypeModes(
                        $value
                    );
                    break;

                case "enabled_taxonomies":
                    $validated[$key] = WPSlug_Validator::validateTaxonomies(
                        $value
                    );
                    break;

                case "stop_words_list":
                    $validated[$key] = WPSlug_Validator::validateTextarea(
                        $value
                    );
                    break;

                case "media_file_prefix":
                    $validated[$key] = WPSlug_Validator::validateString(
                        $value,
                        50
                    );
                    break;

                case "google_api_key":
                case "baidu_app_id":
                case "baidu_secret_key":
                    $validated[$key] = WPSlug_Validator::validateApiKey($value);
                    break;

                default:
                    $validated[$key] = WPSlug_Validator::validateString($value);
            }
        }

        return $validated;
    }

    public function getConversionModes()
    {
        // Order matches product copy: local pinyin / semantic pinyin / translation / transliteration.
        $modes = [
            "pinyin" => __("Local pinyin", "wpslug"),
        ];

        if (function_exists("wpmind_is_available") && wpmind_is_available()) {
            $modes["semantic_pinyin"] = __(
                "Semantic pinyin (XinSi AI)",
                "wpslug"
            );
        } elseif (class_exists("\\WPMind\\WPMind")) {
            $modes["semantic_pinyin"] = __(
                "Semantic pinyin (requires WenPai XinSi)",
                "wpslug"
            );
        }

        $modes["translation"] = __("Multi-language translation", "wpslug");
        $modes["transliteration"] = __(
            "Foreign Language Transliteration",
            "wpslug"
        );

        return $modes;
    }

    public function getTransliterationMethods()
    {
        $methods = [
            "basic" => __("Basic Character Mapping", "wpslug"),
        ];

        if (function_exists("iconv")) {
            $methods["iconv"] = __("iconv Transliteration", "wpslug");
        }

        if (class_exists("Transliterator")) {
            $methods["intl"] = __("PHP Intl Extension", "wpslug");
        }

        return $methods;
    }

    public function getTranslationServices()
    {
        $services = [
            "none" => __("None", "wpslug"),
            "google" => __("Google Translate", "wpslug"),
            "baidu" => __("Baidu Translate", "wpslug"),
        ];
        
        // WenPai XinSi (WPMind) first; Google/Baidu stay as BYOK pipelines.
        if (function_exists("wpmind_is_available") && wpmind_is_available()) {
            $services = array_merge(
                [
                    "wpmind" => __(
                        "XinSi AI (Recommended)",
                        "wpslug"
                    ),
                ],
                $services
            );
        } elseif (class_exists("\\WPMind\\WPMind")) {
            $services["wpmind"] = __(
                "WenPai XinSi (WPMind) (Not Configured)",
                "wpslug"
            );
        }

        return $services;
    }

    /**
     * Remember a WPMind quota/budget miss for the next admin page load.
     * Billing lives in WPMind; WPSlug only surfaces the notice and falls back.
     *
     * @param WP_Error $error Error from wpmind_translate / wpmind_pinyin.
     * @return void
     */
    public function recordWpmindQuotaNotice($error)
    {
        if (!is_wp_error($error)) {
            return;
        }

        $code = method_exists($error, "get_error_code")
            ? $error->get_error_code()
            : "";
        if (
            !in_array(
                $code,
                ["wpmind_budget_exceeded", "wpmind_api_quota"],
                true
            )
        ) {
            return;
        }

        set_transient(
            "wpslug_wpmind_quota_notice",
            [
                "code" => $code,
                "message" => $error->get_error_message(),
                "time" => time(),
            ],
            DAY_IN_SECONDS
        );
    }

    /**
     * Pull and clear a pending WPMind quota notice.
     *
     * @return array|null
     */
    public function pullWpmindQuotaNotice()
    {
        $notice = get_transient("wpslug_wpmind_quota_notice");
        if ($notice === false || $notice === null) {
            return null;
        }

        delete_transient("wpslug_wpmind_quota_notice");
        return is_array($notice) ? $notice : null;
    }

    public function getLanguages()
    {
        return [
            "auto" => __("Auto Detect", "wpslug"),
            "zh" => __("Chinese (Simplified)", "wpslug"),
            "zh-TW" => __("Chinese (Traditional)", "wpslug"),
            "en" => __("English", "wpslug"),
            "es" => __("Spanish", "wpslug"),
            "fr" => __("French", "wpslug"),
            "de" => __("German", "wpslug"),
            "ja" => __("Japanese", "wpslug"),
            "ko" => __("Korean", "wpslug"),
            "ru" => __("Russian", "wpslug"),
            "ar" => __("Arabic", "wpslug"),
            "it" => __("Italian", "wpslug"),
            "pt" => __("Portuguese", "wpslug"),
            "nl" => __("Dutch", "wpslug"),
            "pl" => __("Polish", "wpslug"),
            "tr" => __("Turkish", "wpslug"),
            "sv" => __("Swedish", "wpslug"),
            "da" => __("Danish", "wpslug"),
            "no" => __("Norwegian", "wpslug"),
            "fi" => __("Finnish", "wpslug"),
            "cs" => __("Czech", "wpslug"),
            "hu" => __("Hungarian", "wpslug"),
            "ro" => __("Romanian", "wpslug"),
            "bg" => __("Bulgarian", "wpslug"),
            "hr" => __("Croatian", "wpslug"),
            "sk" => __("Slovak", "wpslug"),
            "sl" => __("Slovenian", "wpslug"),
            "et" => __("Estonian", "wpslug"),
            "lv" => __("Latvian", "wpslug"),
            "lt" => __("Lithuanian", "wpslug"),
            "mt" => __("Maltese", "wpslug"),
            "el" => __("Greek", "wpslug"),
            "cy" => __("Welsh", "wpslug"),
        ];
    }

    public function getPostTypes()
    {
        $post_types = get_post_types(["public" => true], "objects");
        $enabled_post_types = $this->getOption("enabled_post_types", []);

        if (empty($enabled_post_types)) {
            return $post_types;
        }

        $filtered_post_types = [];
        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, $enabled_post_types)) {
                $filtered_post_types[$post_type->name] = $post_type;
            }
        }

        return $filtered_post_types;
    }

    public function getTaxonomies()
    {
        $taxonomies = get_taxonomies(["public" => true], "objects");
        $enabled_taxonomies = $this->getOption("enabled_taxonomies", []);

        if (empty($enabled_taxonomies)) {
            return $taxonomies;
        }

        $filtered_taxonomies = [];
        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, $enabled_taxonomies)) {
                $filtered_taxonomies[$taxonomy->name] = $taxonomy;
            }
        }

        return $filtered_taxonomies;
    }

    /**
     * Feature labels for per-post-type defaults.
     *
     * @return array<string,string>
     */
    public function getPostTypeFeatures()
    {
        $features = [
            "inherit" => __("Use global conversion mode", "wpslug"),
            "seo_slug" => __("Multi-language translation", "wpslug"),
            "pinyin" => __("Local pinyin", "wpslug"),
        ];

        // Content Types: semantic pinyin only when WPMind is installed/ready.
        if (function_exists("wpmind_is_available") && wpmind_is_available()) {
            $features = [
                "inherit" => $features["inherit"],
                "semantic_pinyin" => __(
                    "Semantic pinyin (XinSi AI)",
                    "wpslug"
                ),
                "seo_slug" => $features["seo_slug"],
                "pinyin" => $features["pinyin"],
            ];
        }

        return $features;
    }

    /**
     * Apply a feature alias onto a copy of options.
     *
     * @param array  $options Base options.
     * @param string $feature Feature key.
     * @return array
     */
    public function applyFeatureToOptions($options, $feature)
    {
        $options = is_array($options) ? $options : [];
        $feature = sanitize_key((string) $feature);

        switch ($feature) {
            case "seo_slug":
                $options["conversion_mode"] = "translation";
                $options["translation_service"] = "wpmind";
                break;
            case "semantic_pinyin":
                $options["conversion_mode"] = "semantic_pinyin";
                break;
            case "pinyin":
                $options["conversion_mode"] = "pinyin";
                break;
        }

        return $options;
    }

    /**
     * Resolve effective options for a post type (global + optional override).
     *
     * @param string $post_type Post type.
     * @return array
     */
    public function resolveOptionsForPostType($post_type)
    {
        $options = $this->getOptions();
        $map = isset($options["post_type_modes"]) &&
            is_array($options["post_type_modes"])
            ? $options["post_type_modes"]
            : [];
        $feature = isset($map[$post_type]) ? $map[$post_type] : "inherit";

        if ($feature === "" || $feature === "inherit") {
            return $options;
        }

        return $this->applyFeatureToOptions($options, $feature);
    }

    public function isPostTypeEnabled($post_type)
    {
        $excluded = array(
            'acf-field-group',
            'acf-field',
            'acf-post-type',
            'acf-taxonomy',
            'acf-ui-options-page',
        );
        if (in_array($post_type, $excluded, true)) {
            return false;
        }
        $enabled_post_types = $this->getOption("enabled_post_types", []);
        return empty($enabled_post_types) ||
            in_array($post_type, $enabled_post_types);
    }

    public function isTaxonomyEnabled($taxonomy)
    {
        $enabled_taxonomies = $this->getOption("enabled_taxonomies", []);
        return empty($enabled_taxonomies) ||
            in_array($taxonomy, $enabled_taxonomies);
    }

    public function exportOptions()
    {
        $options = $this->getOptions();
        foreach (["google_api_key", "baidu_secret_key"] as $secret_key) {
            unset($options[$secret_key]);
        }
        $export_data = [
            "version" => WPSLUG_VERSION,
            "timestamp" => time(),
            "site_url" => home_url(),
            "options" => $options,
        ];

        return json_encode($export_data, JSON_PRETTY_PRINT);
    }

    public function importOptions($json_data)
    {
        $data = json_decode($json_data, true);

        if (!is_array($data) || !isset($data["options"])) {
            return new WP_Error(
                "invalid_data",
                __("Invalid import data format.", "wpslug")
            );
        }

        $options = $data["options"];
        $current_options = $this->getOptions();
        foreach (["google_api_key", "baidu_secret_key"] as $secret_key) {
            unset($options[$secret_key]);
        }
        $validated_options = $this->validateOptions($options);

        // Import files are intentionally credential-free. Preserve secrets
        // already stored on this site and ignore any injected secret fields.
        foreach (["google_api_key", "baidu_secret_key"] as $secret_key) {
            $validated_options[$secret_key] = isset($current_options[$secret_key])
                ? $current_options[$secret_key]
                : "";
        }

        if (update_option($this->option_name, $validated_options)) {
            return true;
        }

        return new WP_Error(
            "update_failed",
            __("Failed to update options.", "wpslug")
        );
    }

    public function uninstall()
    {
        delete_option($this->option_name);
        delete_option("wpslug_conversion_stats");
        delete_option("wpslug_error_log");
        delete_option("wpslug_cache");
    }

    /**
     * Declarative schema for Wenpai_Admin_Settings (kit 0.3.0).
     * Defaults come from $this->defaults; sanitize reuses WPSlug_Validator so
     * stored shapes stay exactly as before (lists of names, post_type => feature).
     */
    public function getSchema()
    {
        $V = "WPSlug_Validator";
        $rules = [
            "conversion_mode" => function ($v) use ($V) { return $V::validateConversionMode($v); },
            "pinyin_separator" => function ($v) use ($V) { return $V::validateSelect($v, ["-", "_", ""], "-"); },
            "pinyin_format" => function ($v) use ($V) { return $V::validateSelect($v, ["full", "first"], "full"); },
            "transliteration_method" => function ($v) use ($V) { return $V::validateTransliterationMethod($v); },
            "translation_service" => function ($v) use ($V) { return $V::validateTranslationService($v); },
            "media_conversion_mode" => function ($v) use ($V) { return $V::validateSelect($v, ["normal", "md5", "none"], "normal"); },
            "translation_source_lang" => function ($v) use ($V) { return $V::validateLanguageCode($v) ?: "auto"; },
            "translation_target_lang" => function ($v) use ($V) { return $V::validateLanguageCode($v) ?: "en"; },
            "max_length" => function ($v) use ($V) { return $V::validateInteger($v, 0, 500); },
            "seo_max_words" => function ($v) use ($V) { return $V::validateInteger($v, 1, 30); },
            "enabled_post_types" => function ($v) use ($V) { return $V::validatePostTypes($v); },
            "post_type_modes" => function ($v) use ($V) { return $V::validatePostTypeModes($v); },
            "enabled_taxonomies" => function ($v) use ($V) { return $V::validateTaxonomies($v); },
            "stop_words_list" => function ($v) use ($V) { return $V::validateTextarea($v); },
            "media_file_prefix" => function ($v) use ($V) { return $V::validateString($v, 50); },
            "google_api_key" => function ($v) use ($V) { return $V::validateApiKey($v); },
            "baidu_app_id" => function ($v) use ($V) { return $V::validateApiKey($v); },
            "baidu_secret_key" => function ($v) use ($V) { return $V::validateApiKey($v); },
        ];
        $when_on = ["field" => "enable_conversion", "value" => true];
        // SEO and media groups are disabled (not hidden) with a reason (kit
        // depends_on mode "disable"). A disabled field keeps its saved value.
        $not_first_letter = [
            "relation" => "OR",
            "reason" => __("Not available with first-letter pinyin. Switch to full pinyin to turn it on.", "wpslug"),
            "rules" => [
                ["field" => "conversion_mode", "value" => "pinyin", "not" => true],
                ["field" => "pinyin_format", "value" => "first", "not" => true],
            ],
        ];
        $seo_on = ["field" => "enable_seo_optimization", "value" => true, "reason" => __("SEO optimization is off. Turn it on to use this.", "wpslug")];
        $stop_words_on = ["field" => "remove_stop_words", "value" => true, "reason" => __("Stop word removal is off. Turn it on to use this.", "wpslug")];
        $seo_group = ["mode" => "disable", "rules" => [$not_first_letter, $seo_on]];
        $stop_words_group = ["mode" => "disable", "rules" => [$not_first_letter, $seo_on, $stop_words_on]];
        $media_on = [
            "mode" => "disable",
            "rules" => [
                ["field" => "disable_file_convert", "value" => true, "not" => true, "reason" => __("Automatic file name conversion is disabled. Turn off the switch above to use this.", "wpslug")],
            ],
        ];
        // Test Connection: kit field action, handled by WPSlug_Admin::testApiAction().
        $test_action = function ($service, array $include, $note) {
            return [
                "id" => "test",
                "label" => __("Test Connection", "wpslug"),
                "ajax" => "wpslug_test_api",
                // No ellipsis: the kit JS appends it.
                "busy" => __("Testing", "wpslug"),
                "include" => $include,
                "data" => ["service" => $service],
                "requires" => $include,
                "requires_note" => $note,
            ];
        };
        $settings = $this;
        $post_types = function () {
            return self::objectChoices(get_post_types(["public" => true], "objects"));
        };
        $post_type_rows = function () {
            return self::objectChoices(get_post_types(["public" => true], "objects"), true);
        };
        $taxonomies = function () {
            return self::objectChoices(get_taxonomies(["public" => true], "objects"));
        };
        $meta = [
            "enable_conversion" => [
                "label" => __("Enable Plugin", "wpslug"),
                "desc" => __("Enable automatic slug conversion for your content", "wpslug"),
            ],
            "conversion_mode" => [
                "type" => "choice",
                "label" => __("Conversion Mode", "wpslug"),
                "desc" => __("Choose local pinyin, semantic pinyin (XinSi AI), multi-language translation, or transliteration.", "wpslug"),
                "choices" => function () use ($settings) { return $settings->getConversionModeChoices(); },
                "depends_on" => $when_on,
            ],
            "auto_convert" => [
                "label" => __("Auto Convert", "wpslug"),
                "desc" => __("Automatically convert slugs when saving posts and terms", "wpslug"),
                "depends_on" => $when_on,
            ],
            "convert_on_publish_only" => [
                "label" => __("Convert on Publish Only", "wpslug"),
                "desc" => __("Only convert post slugs when status is publish or future (skips draft autosaves)", "wpslug"),
                "depends_on" => $when_on,
            ],
            "force_lowercase" => [
                "label" => __("Force Lowercase", "wpslug"),
                "desc" => __("Convert all slugs to lowercase for consistency", "wpslug"),
                "depends_on" => $when_on,
            ],
            "preserve_english" => ["label" => __("Preserve English letters in mixed content", "wpslug")],
            "preserve_numbers" => ["label" => __("Preserve numbers in slugs", "wpslug")],
            "pinyin_format" => ["type" => "select", "label" => __("Pinyin Format", "wpslug"), "desc" => __("Choose between full pinyin or first letters only. First letter mode creates very concise URLs and automatically disables SEO optimization.", "wpslug"), "choices" => ["full" => __("Full Pinyin (ni-hao)", "wpslug"), "first" => __("First Letter Only (n-h)", "wpslug")]],
            "pinyin_separator" => ["type" => "select", "label" => __("Word Separator", "wpslug"), "desc" => __("Choose the separator between pinyin words.", "wpslug"), "choices" => ["-" => __("Hyphen (-)", "wpslug"), "_" => __("Underscore (_)", "wpslug"), "" => __("No Separator", "wpslug")]],
            "transliteration_method" => ["type" => "select", "label" => __("Transliteration Method", "wpslug"), "desc" => __("Choose the transliteration method. iconv and Intl provide better accuracy if available.", "wpslug"), "choices" => function () use ($settings) { return $settings->getTransliterationMethods(); }],
            "translation_service" => ["type" => "select", "label" => __("Translation Service", "wpslug"), "desc" => __("Choose translation service. Useful for non-English content to generate English slugs.", "wpslug"), "choices" => function () use ($settings) { return $settings->getTranslationServices(); }],
            "translation_source_lang" => ["type" => "select", "label" => __("Source Language", "wpslug"), "desc" => __("Source language for translation. Auto-detect is recommended.", "wpslug"), "choices" => $this->getLanguages()],
            "translation_target_lang" => ["type" => "select", "label" => __("Target Language", "wpslug"), "desc" => __("Target language for translation. English is recommended for SEO.", "wpslug"), "choices" => $this->getLanguages()],
            "google_api_key" => [
                "type" => "secret",
                "label" => __("API Key", "wpslug"),
                "desc" => __("Enter your Google Translate API key.", "wpslug"),
                "depends_on" => ["field" => "translation_service", "value" => "google"],
                "actions" => [$test_action("google", ["google_api_key"], __("Enter the API key first.", "wpslug"))],
            ],
            "baidu_app_id" => [
                "label" => __("App ID", "wpslug"),
                "desc" => __("Enter your Baidu Translate App ID.", "wpslug"),
                "depends_on" => ["field" => "translation_service", "value" => "baidu"],
            ],
            "baidu_secret_key" => [
                "type" => "secret",
                "label" => __("Secret Key", "wpslug"),
                "desc" => __("Enter your Baidu Translate Secret Key.", "wpslug"),
                "depends_on" => ["field" => "translation_service", "value" => "baidu"],
                "actions" => [$test_action("baidu", ["baidu_app_id", "baidu_secret_key"], __("Enter the App ID and Secret Key first.", "wpslug"))],
            ],
            "enabled_post_types" => [
                "type" => "multiselect",
                "label" => __("Post Types", "wpslug"),
                "desc" => __("Select post types to apply slug conversion. If none are selected, every post type is converted.", "wpslug"),
                "choices" => $post_types,
            ],
            "post_type_modes" => [
                "type" => "matrix",
                "label" => __("Default strategy per post type", "wpslug"),
                "desc" => __("Optional overrides per post type. Example: posts → multi-language translation; products → semantic pinyin (XinSi AI). Choose “use global” to follow the conversion mode above.", "wpslug"),
                "rows" => $post_type_rows,
                "row_label" => __("Post type", "wpslug"),
                "col_label" => __("Default feature", "wpslug"),
                "choices" => function () use ($settings) { return $settings->getPostTypeFeatures(); },
                "unset_value" => "inherit",
            ],
            "enabled_taxonomies" => [
                "type" => "multiselect",
                "label" => __("Taxonomies", "wpslug"),
                "desc" => __("Select taxonomies to apply slug conversion. If none are selected, every taxonomy is converted.", "wpslug"),
                "choices" => $taxonomies,
            ],
            "disable_file_convert" => ["label" => __("Disable automatic file name conversion for uploaded media", "wpslug"), "desc" => __("When checked, media files will not be converted automatically.", "wpslug")],
            "media_conversion_mode" => ["type" => "select", "label" => __("Media Conversion Mode", "wpslug"), "desc" => __("Choose how media file names should be processed. MD5 creates unique hashes for file names.", "wpslug"), "choices" => function () use ($settings) { return $settings->getMediaConversionModes(); }, "depends_on" => $media_on],
            "media_file_prefix" => ["label" => __("Media File Prefix", "wpslug"), "desc" => __("Optional prefix to add to all media file names (e.g., \"img-\", \"file-\").", "wpslug"), "depends_on" => $media_on],
            "preserve_media_extension" => ["label" => __("Always preserve the original file extension", "wpslug"), "desc" => __("Ensures file extensions are kept even when using MD5 conversion.", "wpslug"), "depends_on" => $media_on],
            "show_slug_column" => ["label" => __("Show slug column in post and page lists for easy reference", "wpslug")],
            "enable_seo_optimization" => ["label" => __("Enable SEO-friendly slug optimization", "wpslug"), "desc" => __("Apply SEO best practices to generated slugs.", "wpslug"), "depends_on" => ["mode" => "disable", "rules" => [$not_first_letter]]],
            "smart_punctuation" => ["label" => __("Intelligently handle punctuation marks", "wpslug"), "desc" => __("Convert colons, semicolons, and other punctuation to hyphens or remove them.", "wpslug"), "depends_on" => $seo_group],
            "mixed_content_optimization" => ["label" => __("Optimize mixed language and number content", "wpslug"), "desc" => __("Better handling of content mixing languages with numbers and English text.", "wpslug"), "depends_on" => $seo_group],
            "remove_stop_words" => ["label" => __("Remove common stop words from slugs", "wpslug"), "desc" => __('Remove words like "the", "a", "an", "and", etc. to create cleaner slugs.', "wpslug"), "depends_on" => $seo_group],
            "seo_max_words" => ["label" => __("Maximum Words", "wpslug"), "desc" => __("Maximum number of words to keep in slug for SEO optimization.", "wpslug"), "min" => 1, "max" => 30, "depends_on" => $stop_words_group],
            "stop_words_list" => ["type" => "textarea", "label" => __("Stop Words List", "wpslug"), "desc" => __("Comma-separated list of stop words to remove from slugs.", "wpslug"), "depends_on" => $stop_words_group],
        ];
        // Per-tab save (kit 'tab'): each settings form posts the kit tab marker
        // (tab_input_html()), so only that form's fields are written and every
        // other field keeps its saved value. Ids match the simple page and the
        // advanced sections in WPSlug_Admin. max_length is on no page, so it is
        // in no tab and is never written from a form.
        $tabs = self::fieldTabs();
        $schema = [];
        foreach ($this->defaults as $key => $default) {
            $m = isset($meta[$key]) ? $meta[$key] : [];
            if (is_bool($default)) {
                $type = "toggle";
                $fn = function ($v) use ($V) { return $V::validateBoolean($v); };
            } else {
                $type = is_int($default) ? "number" : (is_array($default) ? "multicheck" : "text");
                $fn = isset($rules[$key]) ? $rules[$key] : function ($v) use ($V) { return $V::validateString($v); };
            }
            $field = [
                "type" => isset($m["type"]) ? $m["type"] : $type,
                "default" => $default,
                "label" => isset($m["label"]) ? $m["label"] : $key,
                "desc" => isset($m["desc"]) ? $m["desc"] : "",
                "choices" => isset($m["choices"]) ? $m["choices"] : [],
                "min" => isset($m["min"]) ? $m["min"] : null,
                "max" => isset($m["max"]) ? $m["max"] : null,
                "depends_on" => isset($m["depends_on"]) ? $m["depends_on"] : [],
                "tab" => isset($tabs[$key]) ? $tabs[$key] : "",
                // WPSlug validators keep the stored shapes of 1.3.x (see tests).
                "sanitize" => function ($v) use ($fn, $default) { return $fn(null === $v ? $default : $v); },
            ];
            foreach (["rows", "row_label", "col_label", "unset_value", "actions"] as $extra) {
                if (array_key_exists($extra, $m)) {
                    $field[$extra] = $m[$extra];
                }
            }
            $schema[$key] = $field;
        }
        // Read-only (kit "status" type): not submitted and not stored, so the
        // saved option keeps exactly the keys of $this->defaults.
        $schema["wpmind_status"] = [
            "type" => "status",
            "label" => __("WenPai XinSi (WPMind)", "wpslug"),
            "depends_on" => ["field" => "translation_service", "value" => "wpmind"],
            "status" => function () use ($settings) { return $settings->getWpmindStatus(); },
            "tab" => "translate",
        ];
        return $schema;
    }

    /**
     * Settings form (kit tab) of every stored field: "simple" for the simple
     * page, otherwise the advanced section id. Each form renders exactly these
     * fields (tests/php/run.php checks it), so a ticked-off checkbox on the
     * saved form really means "empty" and nothing outside it is touched.
     *
     * @return array<string, string> Field id => tab id.
     */
    public static function fieldTabs()
    {
        $map = [
            "simple" => ["enable_conversion", "conversion_mode", "auto_convert", "convert_on_publish_only", "force_lowercase"],
            "pinyin" => ["pinyin_format", "pinyin_separator", "preserve_english", "preserve_numbers"],
            "translit" => ["transliteration_method"],
            "translate" => ["translation_service", "translation_source_lang", "translation_target_lang", "google_api_key", "baidu_app_id", "baidu_secret_key"],
            "seo" => ["enable_seo_optimization", "smart_punctuation", "mixed_content_optimization", "remove_stop_words", "seo_max_words", "stop_words_list"],
            "media" => ["disable_file_convert", "media_conversion_mode", "media_file_prefix", "preserve_media_extension"],
            "types" => ["enabled_post_types", "post_type_modes", "enabled_taxonomies", "show_slug_column"],
        ];
        $out = [];
        foreach ($map as $tab => $ids) {
            foreach ($ids as $id) {
                $out[$id] = $tab;
            }
        }
        return $out;
    }

    /**
     * WPMind status for the read-only status field on the translation section.
     *
     * The on-site WPMind settings link opens in the current tab; the off-site
     * "Learn more" link opens in a new tab.
     *
     * @return array{level: string, text: string, desc: string[], link: array{url: string, label: string, target?: string}}
     */
    public function getWpmindStatus()
    {
        if (function_exists("wpmind_is_available") && wpmind_is_available()) {
            return [
                "level" => "ok",
                "text" => __("Active", "wpslug"),
                "desc" => [
                    __("WenPai XinSi (WPMind) is active. Credits and BYOK keys are managed there.", "wpslug"),
                    __("Use it for semantic pinyin (XinSi AI) and multi-language translation. If quota is exceeded or the provider fails, WPSlug falls back to local pinyin so saving is not blocked.", "wpslug"),
                ],
                "link" => [
                    "url" => function_exists("admin_url") ? admin_url("options-general.php?page=wpmind") : "options-general.php?page=wpmind",
                    "label" => __("Open WenPai XinSi (WPMind) settings", "wpslug"),
                ],
            ];
        }
        return [
            "level" => "warn",
            "text" => __("Not active", "wpslug"),
            "desc" => [
                __("WenPai XinSi (WPMind) is not active or not configured.", "wpslug"),
                __("Install WenPai XinSi (WPMind) for semantic pinyin (XinSi AI) and multi-language translation. Until then, local pinyin remains available.", "wpslug"),
            ],
            "link" => [
                "url" => "https://wpcy.com/mind/",
                "label" => __("Learn more", "wpslug"),
                // Off-site: new tab; the kit adds rel="noopener noreferrer" and a screen-reader note.
                "target" => "_blank",
            ],
        ];
    }

    /**
     * Choice cards for conversion_mode: label plus the one-line description.
     *
     * @return array<string, array{label: string, desc: string}>
     */
    public function getConversionModeChoices()
    {
        $desc = [
            "pinyin" => __("插件内置组件转换", "wpslug"),
            "semantic_pinyin" => __("不够时用本地拼音", "wpslug"),
            "translation" => __("翻译为目标语言", "wpslug"),
            "transliteration" => __("外文转拉丁字母", "wpslug"),
        ];
        $out = [];
        foreach ($this->getConversionModes() as $mode => $label) {
            $out[$mode] = [
                "label" => $label,
                "desc" => isset($desc[$mode]) ? $desc[$mode] : "",
            ];
        }
        return $out;
    }

    /**
     * name => label from get_post_types()/get_taxonomies() objects (or plain names).
     *
     * @param array $items    Objects with ->name and ->label, or names.
     * @param bool  $with_key Use array('label', 'desc' => name) for matrix rows.
     * @return array
     */
    private static function objectChoices($items, $with_key = false)
    {
        $out = [];
        foreach ((array) $items as $k => $item) {
            if (is_object($item) && isset($item->name)) {
                $name = (string) $item->name;
                $label = isset($item->label) ? (string) $item->label : $name;
            } else {
                $name = is_string($k) ? $k : (string) $item;
                $label = (string) $item;
            }
            $out[$name] = $with_key ? ["label" => $label, "desc" => $name] : $label;
        }
        return $out;
    }

    public function getDefaults()
    {
        return $this->defaults;
    }

    public function getOptionName()
    {
        return $this->option_name;
    }

    public function updateConversionStats(
        $mode,
        $success = true,
        $execution_time = 0
    ) {
        $stats = get_option("wpslug_conversion_stats", [
            "total_conversions" => 0,
            "successful_conversions" => 0,
            "failed_conversions" => 0,
            "last_conversion" => null,
            "most_used_mode" => "pinyin",
            "performance_data" => [],
        ]);

        $stats["total_conversions"]++;
        if ($success) {
            $stats["successful_conversions"]++;
        } else {
            $stats["failed_conversions"]++;
        }

        $stats["last_conversion"] = current_time("mysql");
        $stats["most_used_mode"] = $mode;

        if ($execution_time > 0) {
            $stats["performance_data"][] = [
                "mode" => $mode,
                "time" => $execution_time,
                "timestamp" => time(),
            ];

            $stats["performance_data"] = array_slice(
                $stats["performance_data"],
                -100
            );
        }

        update_option("wpslug_conversion_stats", $stats);
    }

    public function logError($message, $context = [])
    {
        $context = $this->redactSensitiveContext($context);
        $log_entry = [
            "timestamp" => current_time("mysql"),
            "message" => $message,
            "context" => $context,
        ];

        $error_log = get_option("wpslug_error_log", []);
        $error_log[] = $log_entry;

        if (count($error_log) > 100) {
            $error_log = array_slice($error_log, -100);
        }

        update_option("wpslug_error_log", $error_log, false);
    }

    /**
     * Prevent credentials from being persisted in the option-backed error log.
     */
    private function redactSensitiveContext($context)
    {
        if (!is_array($context)) {
            return $context;
        }

        foreach ($context as $key => $value) {
            if (preg_match('/(?:api.?key|secret|token|password)/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redactSensitiveContext($value);
            }
        }

        return $context;
    }

    public function isFeatureEnabled($feature)
    {
        $features = [
            "auto_convert" => $this->getOption("auto_convert"),
            "file_convert" => !$this->getOption("disable_file_convert"),
            "slug_column" => $this->getOption("show_slug_column"),
            "seo_optimization" => $this->getOption("enable_seo_optimization"),
            "stop_words" => $this->getOption("remove_stop_words"),
            "smart_punctuation" => $this->getOption("smart_punctuation"),
            "mixed_content" => $this->getOption("mixed_content_optimization"),
        ];

        return isset($features[$feature]) ? $features[$feature] : false;
    }

    public function getStopWords()
    {
        $stop_words_string = $this->getOption("stop_words_list", "");
        if (empty($stop_words_string)) {
            return [];
        }

        $stop_words = explode(",", $stop_words_string);
        return array_map("trim", $stop_words);
    }

    public function isModeEnabled($mode)
    {
        $conversion_mode = $this->getOption("conversion_mode");
        return $conversion_mode === $mode;
    }

    public function isFirstLetterMode()
    {
        $conversion_mode = $this->getOption("conversion_mode");
        $pinyin_format = $this->getOption("pinyin_format");
        return $conversion_mode === "pinyin" && $pinyin_format === "first";
    }

    public function shouldApplySEO()
    {
        $seo_enabled = $this->getOption("enable_seo_optimization");
        $is_first_letter = $this->isFirstLetterMode();
        return $seo_enabled && !$is_first_letter;
    }

    public function getMediaConversionModes()
    {
        return [
            "normal" => __("Normal Conversion (same as content)", "wpslug"),
            "md5" => __("MD5 Hash (generates unique hash)", "wpslug"),
            "none" => __("No Conversion (keep original)", "wpslug"),
        ];
    }

    public function isMediaConversionEnabled()
    {
        return !$this->getOption("disable_file_convert");
    }

    public function getMediaConversionMode()
    {
        return $this->getOption("media_conversion_mode", "normal");
    }

    public function getMediaFilePrefix()
    {
        return $this->getOption("media_file_prefix", "");
    }

    public function shouldPreserveMediaExtension()
    {
        return $this->getOption("preserve_media_extension", true);
    }

    public function getConversionStats()
    {
        return get_option("wpslug_conversion_stats", [
            "total_conversions" => 0,
            "successful_conversions" => 0,
            "failed_conversions" => 0,
            "last_conversion" => null,
            "most_used_mode" => "pinyin",
            "performance_data" => [],
        ]);
    }

    public function getErrorLog()
    {
        return get_option("wpslug_error_log", []);
    }

    public function clearErrorLog()
    {
        return delete_option("wpslug_error_log");
    }

    public function clearConversionStats()
    {
        return delete_option("wpslug_conversion_stats");
    }

    public function getSystemInfo()
    {
        $info = [
            "plugin_version" => WPSLUG_VERSION,
            "wordpress_version" => get_bloginfo("version"),
            "php_version" => PHP_VERSION,
            "iconv_available" => function_exists("iconv"),
            "intl_available" => class_exists("Transliterator"),
            "curl_available" => function_exists("curl_init"),
            "options_count" => count($this->getOptions()),
            "active_mode" => $this->getOption("conversion_mode"),
            "seo_enabled" => $this->getOption("enable_seo_optimization"),
            "media_conversion" => $this->getOption("media_conversion_mode"),
            "enabled_post_types" => $this->getOption("enabled_post_types"),
            "enabled_taxonomies" => $this->getOption("enabled_taxonomies"),
        ];

        return $info;
    }

    public function validateSystemRequirements()
    {
        $requirements = [
            "php_version" => version_compare(PHP_VERSION, "7.4", ">="),
            "wordpress_version" => version_compare(
                get_bloginfo("version"),
                "6.0",
                ">="
            ),
            "mbstring_extension" => extension_loaded("mbstring"),
            "json_extension" => extension_loaded("json"),
        ];

        return $requirements;
    }

    public function isValidConfiguration()
    {
        $errors = $this->getConfigurationErrors();
        return empty($errors);
    }

    public function getConfigurationErrors()
    {
        $options = $this->getOptions();

        if (!$options["enable_conversion"]) {
            return [];
        }

        $errors = [];

        if (class_exists("WPSlug_Validator")) {
            $api_validation = WPSlug_Validator::hasRequiredApiCredentials(
                $options
            );
            if ($api_validation !== true) {
                $errors = array_merge($errors, $api_validation);
            }

            $system_validation = WPSlug_Validator::validateSystemRequirements();
            if ($system_validation !== true) {
                $errors = array_merge($errors, $system_validation);
            }
        } else {
            if ($options["conversion_mode"] === "translation") {
                $service = $options["translation_service"];
                if (
                    $service === "google" &&
                    empty($options["google_api_key"])
                ) {
                    $errors[] = __(
                        "Google API key is required for Google Translate service.",
                        "wpslug"
                    );
                }
                if (
                    $service === "baidu" &&
                    (empty($options["baidu_app_id"]) ||
                        empty($options["baidu_secret_key"]))
                ) {
                    $errors[] = __(
                        "Baidu App ID and Secret Key are required for Baidu Translate service.",
                        "wpslug"
                    );
                }
            }

            $requirements = $this->validateSystemRequirements();
            if (!$requirements["php_version"]) {
                $errors[] = __("PHP 7.4 or higher is required.", "wpslug");
            }
            if (!$requirements["wordpress_version"]) {
                $errors[] = __(
                    "WordPress 6.0 or higher is required.",
                    "wpslug"
                );
            }
            if (!$requirements["mbstring_extension"]) {
                $errors[] = __("PHP mbstring extension is required.", "wpslug");
            }
            if (!$requirements["json_extension"]) {
                $errors[] = __("PHP JSON extension is required.", "wpslug");
            }
        }

        return $errors;
    }
}
