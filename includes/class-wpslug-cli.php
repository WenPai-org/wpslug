<?php

if (!defined("ABSPATH")) {
    exit();
}

/**
 * WP-CLI: wp slug …
 *
 * Do not pass translation keys on the command line.
 */
final class WPSlug_CLI
{
    /**
     * Show conversion status. Does not print secrets.
     *
     * ## EXAMPLES
     *
     *     wp slug status
     *
     * @when after_wp_load
     */
    public function status(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        $options = (new WPSlug_Settings())->getOptions();
        $wpmind = function_exists("wpmind_is_available") && wpmind_is_available();

        WP_CLI::log("enable_conversion=" . ($options["enable_conversion"] ? "yes" : "no"));
        WP_CLI::log("conversion_mode=" . $options["conversion_mode"]);
        WP_CLI::log("auto_convert=" . ($options["auto_convert"] ? "yes" : "no"));
        WP_CLI::log("convert_on_publish_only=" . ($options["convert_on_publish_only"] ? "yes" : "no"));
        WP_CLI::log("force_lowercase=" . ($options["force_lowercase"] ? "yes" : "no"));
        WP_CLI::log("enabled_post_types=" . implode(",", (array) $options["enabled_post_types"]));
        WP_CLI::log("enabled_taxonomies=" . implode(",", (array) $options["enabled_taxonomies"]));
        WP_CLI::log("translation_service=" . $options["translation_service"]);
        WP_CLI::log("wpmind=" . ($wpmind ? "yes" : "no"));
        WP_CLI::log("google_api_key=" . (trim((string) $options["google_api_key"]) !== "" ? "set" : "empty"));
        WP_CLI::log("baidu_app_id=" . (trim((string) $options["baidu_app_id"]) !== "" ? "set" : "empty"));
        WP_CLI::log("baidu_secret_key=" . (trim((string) $options["baidu_secret_key"]) !== "" ? "set" : "empty"));

        if ($options["enable_conversion"]) {
            WP_CLI::success(__("Conversion is on.", "wpslug"));
        } else {
            WP_CLI::warning(__("Conversion is off. New saves keep their current slugs.", "wpslug"));
        }
    }

    /**
     * Convert a title without writing a post.
     *
     * ## OPTIONS
     *
     * <title>
     * : Source title.
     *
     * [--mode=<mode>]
     * : pinyin, semantic_pinyin, transliteration, or translation.
     *
     * ## EXAMPLES
     *
     *     wp slug preview "你好，世界"
     *
     * @when after_wp_load
     */
    public function preview(array $args, array $assoc_args): void
    {
        $title = isset($args[0]) ? (string) $args[0] : "";
        if ($title === "") {
            WP_CLI::error(__("Pass a title to preview.", "wpslug"));
        }

        $options = (new WPSlug_Settings())->getOptions();
        if (isset($assoc_args["mode"]) && $assoc_args["mode"] !== "") {
            $mode = sanitize_key((string) $assoc_args["mode"]);
            $converter = new WPSlug_Converter();
            if (!$converter->isModeSupported($mode)) {
                WP_CLI::error(__("Unknown conversion mode.", "wpslug"));
            }
            $options["conversion_mode"] = $mode;
        }

        $result = self::preview_result($title, $options);
        WP_CLI::log("original=" . $result["original"]);
        WP_CLI::log("converted=" . $result["converted"]);
        WP_CLI::log("final=" . $result["final"]);
        WP_CLI::log("mode=" . $result["mode"]);
        WP_CLI::log("language=" . $result["language"]);
        WP_CLI::success($result["final"]);
    }

    /**
     * Rewrite selected post slugs. Same path as the posts-list bulk action.
     *
     * ## OPTIONS
     *
     * <id>...
     * : Post IDs.
     *
     * [--dry-run]
     * : Print old and new slugs without writing.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp slug convert 123 --dry-run
     *     wp slug convert 123 456 --yes
     *
     * @when after_wp_load
     */
    public function convert(array $args, array $assoc_args): void
    {
        $ids = array_values(array_filter(array_map("absint", $args)));
        if ($ids === []) {
            WP_CLI::error(__("Pass one or more post IDs.", "wpslug"));
        }

        $settings = new WPSlug_Settings();
        $options = $settings->getOptions();
        if (empty($options["enable_conversion"])) {
            WP_CLI::error(__("Conversion is off. Turn it on in settings first.", "wpslug"));
        }

        $dry_run = !empty($assoc_args["dry-run"]);
        if (!$dry_run) {
            WP_CLI::confirm(
                __("Rewrite slugs for the given posts? This is an explicit migration.", "wpslug"),
                $assoc_args
            );
        }

        $converter = new WPSlug_Converter();
        $optimizer = new WPSlug_Optimizer();
        $written = 0;
        $skipped = 0;

        foreach ($ids as $post_id) {
            $plan = self::plan_post_slug($post_id, $settings, $converter, $optimizer);
            if ($plan["skip"] !== "") {
                WP_CLI::log(sprintf("id=%d skip=%s", $post_id, $plan["skip"]));
                $skipped++;
                continue;
            }
            WP_CLI::log(
                sprintf("id=%d old=%s new=%s", $post_id, $plan["old"], $plan["new"])
            );
            if ($dry_run) {
                continue;
            }
            wp_update_post([
                "ID" => $post_id,
                "post_name" => $plan["new"],
            ]);
            $written++;
        }

        if ($dry_run) {
            WP_CLI::success(
                sprintf(
                    /* translators: 1: planned count, 2: skipped count. */
                    __('Dry run. %1$d would change, %2$d skipped.', 'wpslug'),
                    count($ids) - $skipped,
                    $skipped
                )
            );
            return;
        }

        WP_CLI::success(
            sprintf(
                /* translators: 1: written count, 2: skipped count. */
                __('Converted %1$d slug(s). %2$d skipped.', 'wpslug'),
                $written,
                $skipped
            )
        );
    }

    /**
     * Check conversion settings. Does not print secrets.
     *
     * ## EXAMPLES
     *
     *     wp slug doctor
     *
     * @when after_wp_load
     */
    public function doctor(array $args, array $assoc_args): void
    {
        unset($args, $assoc_args);
        $options = (new WPSlug_Settings())->getOptions();
        $findings = self::doctor_findings($options);
        foreach ($findings as $line) {
            WP_CLI::warning($line);
        }
        if ($findings === []) {
            WP_CLI::success(__("No conversion problems found.", "wpslug"));
            return;
        }
        WP_CLI::error(
            sprintf(
                /* translators: %d: finding count. */
                __("%d conversion problem(s).", "wpslug"),
                count($findings)
            ),
            false
        );
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,string>
     */
    public static function doctor_findings(array $options, $wpmind = null): array
    {
        $findings = [];
        if ($wpmind === null) {
            $wpmind = function_exists("wpmind_is_available") && wpmind_is_available();
        }
        $mode = isset($options["conversion_mode"]) ? (string) $options["conversion_mode"] : "pinyin";
        $service = isset($options["translation_service"]) ? (string) $options["translation_service"] : "none";

        if (empty($options["enable_conversion"])) {
            $findings[] = "conversion_off";
        }
        if (empty($options["enabled_post_types"])) {
            $findings[] = "no_post_types";
        }
        if ($mode === "semantic_pinyin" && !$wpmind) {
            $findings[] = "semantic_pinyin_needs_wpmind";
        }
        if ($mode === "translation" && !$wpmind && $service === "none") {
            $findings[] = "translation_unconfigured";
        }

        return $findings;
    }

    /**
     * @param array<string,mixed> $options
     * @return array{original:string,converted:string,final:string,mode:string,language:string}
     */
    public static function preview_result(string $title, array $options): array
    {
        $converter = new WPSlug_Converter();
        $optimizer = new WPSlug_Optimizer();
        $converted = $converter->convert($title, $options);
        $final = $optimizer->optimize($converted, $options);

        return [
            "original" => $title,
            "converted" => (string) $converted,
            "final" => (string) $final,
            "mode" => isset($options["conversion_mode"]) ? (string) $options["conversion_mode"] : "pinyin",
            "language" => $converter->detectLanguage($title),
        ];
    }

    /**
     * @return array{skip:string,old:string,new:string}
     */
    public static function plan_post_slug(
        int $post_id,
        WPSlug_Settings $settings,
        WPSlug_Converter $converter,
        WPSlug_Optimizer $optimizer
    ): array {
        $post = get_post($post_id);
        if (!$post) {
            return ["skip" => "missing", "old" => "", "new" => ""];
        }
        if (!$settings->isPostTypeEnabled($post->post_type)) {
            return ["skip" => "type_disabled", "old" => $post->post_name, "new" => ""];
        }

        $type_options = $settings->resolveOptionsForPostType($post->post_type);
        $new_slug = $converter->convert($post->post_title, $type_options);
        $new_slug = $optimizer->optimize($new_slug, $type_options);
        if ($new_slug === "") {
            return ["skip" => "empty", "old" => $post->post_name, "new" => ""];
        }
        $unique = $optimizer->generateUniqueSlug($new_slug, $post->ID, $post->post_type);
        if ($unique === $post->post_name) {
            return ["skip" => "unchanged", "old" => $post->post_name, "new" => $unique];
        }

        return ["skip" => "", "old" => $post->post_name, "new" => $unique];
    }
}
