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
            "enabled_taxonomies" => ["category", "post_tag"],
            "auto_convert" => true,
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

    public function validateOptions($options)
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
        return [
            "pinyin" => __("Chinese Pinyin Conversion", "wpslug"),
            "transliteration" => __(
                "Foreign Language Transliteration",
                "wpslug"
            ),
            "translation" => __("Multi-language Translation", "wpslug"),
        ];
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
        return [
            "none" => __("None", "wpslug"),
            "google" => __("Google Translate", "wpslug"),
            "baidu" => __("Baidu Translate", "wpslug"),
        ];
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

    public function isPostTypeEnabled($post_type)
    {
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
        $validated_options = $this->validateOptions($options);

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

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpslug_%'"
        );
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
        $log_entry = [
            "timestamp" => current_time("mysql"),
            "message" => $message,
            "context" => $context,
            "backtrace" => wp_debug_backtrace_summary(),
        ];

        $error_log = get_option("wpslug_error_log", []);
        $error_log[] = $log_entry;

        if (count($error_log) > 100) {
            $error_log = array_slice($error_log, -100);
        }

        update_option("wpslug_error_log", $error_log);

        if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log(
                "WP Slug Plugin: " .
                    $message .
                    " | Context: " .
                    json_encode($context)
            );
        }
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
            "php_version" => version_compare(PHP_VERSION, "7.0", ">="),
            "wordpress_version" => version_compare(
                get_bloginfo("version"),
                "5.0",
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
                $errors[] = __("PHP 7.0 or higher is required.", "wpslug");
            }
            if (!$requirements["wordpress_version"]) {
                $errors[] = __(
                    "WordPress 5.0 or higher is required.",
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