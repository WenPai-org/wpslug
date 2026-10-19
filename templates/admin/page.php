<?php
defined("ABSPATH") || exit();
/** @var array $options */
/** @var string $tab */
/** @var string $mode */
/** @var callable $url */
/** @var string $section */
/** @var array<string, array{label: string, icon: string, sub: string}> $adv_sections */
$wpslug_enabled = !empty($options["enable_conversion"]);
$wpslug_conv = isset($options["conversion_mode"]) ? (string) $options["conversion_mode"] : "pinyin";
$wpslug_types = is_array($options["enabled_post_types"] ?? null) ? $options["enabled_post_types"] : [];
$wpslug_mind = function_exists("wpmind_is_available") && wpmind_is_available();
$wpslug_modes = $this->settings->getConversionModes();
$wpslug_notice = "";
$wpslug_level = "";
// phpcs:disable WordPress.Security.NonceVerification.Recommended
// The settings tab uses the kit notice (receipt / error summary) below.
if ("settings" !== $tab && isset($_GET["settings-updated"]) && "true" === $_GET["settings-updated"]) {
    $wpslug_notice = __("Settings saved.", "wpslug");
    $wpslug_level = "ok";
}
if (isset($_GET["wpslug_notice"]) && "reset-confirm" === $_GET["wpslug_notice"]) {
    $wpslug_notice = __("重置前请先勾选确认。", "wpslug");
    $wpslug_level = "bad";
}
// phpcs:enable
?>
<?php
$wpslug_icon = static function ($n) {
    return class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg($n) : "";
};
Wenpai_Admin_Shell::open([
    "slug" => "wpslug",
    "name" => wpslug_brand_name(),
    "mark_html" => $wpslug_icon("feather"),
    "home_url" => $url("overview"),
    "current" => $tab,
    "tabs" => [
        ["id" => "overview", "label" => __("概览", "wpslug"), "url" => $url("overview"), "icon_html" => $wpslug_icon("home")],
        ["id" => "settings", "label" => __("设置", "wpslug"), "url" => $url("settings"), "icon_html" => $wpslug_icon("equalizer")],
        ["id" => "tools", "label" => __("工具", "wpslug"), "url" => $url("tools"), "icon_html" => $wpslug_icon("server")],
    ],
    "help_url" => $url("help"),
    "help_label" => __("帮助", "wpslug"),
    "help_icon_html" => $wpslug_icon("help"),
    "feedback_url" => "https://wpcy.com/support",
    "feedback_label" => __("反馈", "wpslug"),
    "feedback_icon_html" => $wpslug_icon("feedback"),
    "main_class" => "help" === $tab ? "wpslug-help" : "",
]);
?>
    <?php
    if ("settings" === $tab && ($wpslug_notice_fw = $this->settings->getFramework())) {
        echo $wpslug_notice_fw->notices_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit escapes every part.
    }
    ?>
    <?php if ("" !== $wpslug_notice) : ?>
    <div class="wenpai-notice-wrap">
      <div class="notice <?php echo esc_attr($wpslug_level); ?>"><span><?php echo esc_html($wpslug_notice); ?></span></div>
    </div>
    <?php endif; ?>

<?php if ("overview" === $tab) : ?>
    <div class="wenpai-page-head">
      <div>
        <h1 class="wenpai-h1"><?php esc_html_e("概览", "wpslug"); ?></h1>
        <p class="wenpai-lede"><?php esc_html_e("将标题改写成拼音或翻译别名，可按文章类型分别选用。", "wpslug"); ?></p>
      </div>
    </div>
    <div class="wenpai-stack">
      <?php if (!$wpslug_enabled) : ?>
      <div class="next">
        <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <div>
          <strong><?php esc_html_e("转换还没开", "wpslug"); ?></strong>
          <div class="meta"><?php esc_html_e("去设置里选拼音、心思或翻译。", "wpslug"); ?></div>
        </div>
        <div class="next-actions">
          <a class="btn btn-primary" href="<?php echo esc_url($url("settings")); ?>"><?php esc_html_e("去设置", "wpslug"); ?></a>
        </div>
      </div>
      <?php endif; ?>
      <section class="card">
        <h2 class="section-title"><?php esc_html_e("运行状态", "wpslug"); ?></h2>
        <div class="simple-row is-static">
          <div class="tile <?php echo $wpslug_enabled ? "ok" : "warn"; ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
          <div>
            <div class="t"><?php esc_html_e("转换模式", "wpslug"); ?></div>
            <div class="d"><?php echo $wpslug_enabled ? esc_html($wpslug_modes[$wpslug_conv] ?? $wpslug_conv) : esc_html__("未启用。", "wpslug"); ?></div>
          </div>
          <div class="r"><span class="pill <?php echo $wpslug_enabled ? "ok" : "warn"; ?>"><?php echo $wpslug_enabled ? esc_html__("已开启", "wpslug") : esc_html__("未开启", "wpslug"); ?></span></div>
        </div>
        <div class="simple-row is-static">
          <div class="tile <?php echo $wpslug_mind ? "ok" : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("sparkle") : ""; ?></div>
          <div>
            <div class="t"><?php esc_html_e("文派心思", "wpslug"); ?></div>
            <div class="d"><?php echo $wpslug_mind ? esc_html__("已接通。额度在心思，这里不做充值。", "wpslug") : esc_html__("实现智能语义化拼音和多语言翻译。", "wpslug"); ?></div>
          </div>
          <div class="r"><span class="pill <?php echo $wpslug_mind ? "ok" : ""; ?>"><?php echo $wpslug_mind ? esc_html__("已安装", "wpslug") : esc_html__("未安装", "wpslug"); ?></span></div>
        </div>
        <div class="simple-row is-static">
          <div class="tile ok"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("info") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
          <div>
            <div class="t"><?php esc_html_e("文章类型范围", "wpslug"); ?></div>
            <div class="d"><?php echo esc_html(implode("、", $wpslug_types)); ?></div>
          </div>
          <div class="r"><span class="scope"><?php echo esc_html(implode(" / ", $wpslug_types)); ?></span></div>
        </div>
      </section>
    </div>

<?php elseif ("settings" === $tab) : ?>
    <div class="wenpai-page-head">
      <div>
        <h1 class="wenpai-h1"><?php esc_html_e("设置", "wpslug"); ?></h1>
        <p class="wenpai-lede"><?php echo "advanced" === $mode ? esc_html__("一次只看一组。预览在这页下面。", "wpslug") : esc_html__("选好模式后，在下面直接看别名。", "wpslug"); ?></p>
      </div>
      <div class="mode">
        <span class="seg">
          <a class="<?php echo "simple" === $mode ? "on" : ""; ?>" href="<?php echo esc_url($url("settings", ["mode" => "simple"])); ?>"><?php esc_html_e("简单", "wpslug"); ?></a>
          <a class="<?php echo "advanced" === $mode ? "on" : ""; ?>" href="<?php echo esc_url($url("settings", ["mode" => "advanced", "section" => $section])); ?>"><?php esc_html_e("Advanced", "wpslug"); ?></a>
        </span>
      </div>
    </div>
    <div class="wenpai-stack">
    <?php if ("advanced" === $mode) : ?>
    <div class="mode wpslug-sections">
      <span class="seg">
        <?php foreach ($adv_sections as $wpslug_section_id => $wpslug_meta) : ?>
        <a class="<?php echo $section === $wpslug_section_id ? "on" : ""; ?>" href="<?php echo esc_url($url("settings", ["mode" => "advanced", "section" => $wpslug_section_id])); ?>"><?php echo esc_html($wpslug_meta["label"]); ?></a>
        <?php endforeach; ?>
      </span>
    </div>
    <?php endif; ?>
    <form method="post" action="options.php" id="wpslug-settings-form">
      <?php settings_fields("wpslug_settings"); ?>
      <input type="hidden" name="wpslug_current_tab" value="<?php echo esc_attr($tab); ?>">
        <?php if ("simple" === $mode) : ?>
        <section class="card wpslug-simple">
          <?php
          $wpslug_fw = $this->settings->getFramework();
          $wpslug_on_attrs = $wpslug_fw ? $wpslug_fw->depends_on_attrs(["field" => "enable_conversion", "value" => true]) : "";
          ?>
          <div class="simple-row">
            <div class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <div>
              <div class="t" id="wpslug-row-enable_conversion-t"><?php esc_html_e("Enable Plugin", "wpslug"); ?></div>
              <div class="d" id="wpslug-row-enable_conversion-d"><?php esc_html_e("Enable automatic slug conversion for your content", "wpslug"); ?></div>
            </div>
            <div class="r">
              <?php if ($wpslug_fw) { $wpslug_fw->render_control("enable_conversion", ["labelledby" => "wpslug-row-enable_conversion-t", "describedby" => "wpslug-row-enable_conversion-d"]); } ?>
            </div>
          </div>
          <div class="wpslug-on-group"<?php echo $wpslug_on_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped data attributes. ?>>
          <div class="wpslug-mode-field">
            <?php if ($wpslug_fw) { $wpslug_fw->render_field("conversion_mode"); } ?>
            <?php if (!$wpslug_mind) : ?>
            <div class="notice info"><span><?php esc_html_e("Install WenPai XinSi (WPMind) for semantic pinyin (XinSi AI) and multi-language translation. Until then, local pinyin remains available.", "wpslug"); ?></span></div>
            <?php endif; ?>
          </div>
          <?php $this->renderSwitchRow("auto_convert"); ?>
          <?php $this->renderSwitchRow("convert_on_publish_only"); ?>
          <?php $this->renderSwitchRow("force_lowercase"); ?>
          </div>
          <div class="card-foot">
            <button type="submit" class="btn btn-primary"><?php esc_html_e("Save Settings", "wpslug"); ?></button>
          </div>
        </section>
        <?php else : ?>
        <?php
        $wpslug_renderers = [
            "pinyin" => "renderPinyinSettings",
            "translit" => "renderTransliterationSettings",
            "translate" => "renderTranslationSettings",
            "seo" => "renderSEOSettings",
            "media" => "renderMediaSettings",
            "types" => "renderAdvancedSettings",
        ];
        foreach ($wpslug_renderers as $wpslug_section_id => $wpslug_method) :
            if (!isset($adv_sections[$wpslug_section_id])) {
                continue;
            }
            $wpslug_meta = $adv_sections[$wpslug_section_id];
            ?>
        <section class="card"<?php echo $section === $wpslug_section_id ? "" : " hidden"; ?>>
          <div class="card-head">
            <span class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg($wpslug_meta["icon"]) : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div>
              <h2 class="card-title"><?php echo esc_html($wpslug_meta["label"]); ?></h2>
              <p class="card-sub"><?php echo esc_html($wpslug_meta["sub"]); ?></p>
            </div>
          </div>
          <?php $this->{$wpslug_method}($options); ?>
          <?php if ($section === $wpslug_section_id) : ?>
          <div class="card-foot">
            <button type="submit" class="btn btn-primary"><?php esc_html_e("Save Settings", "wpslug"); ?></button>
          </div>
          <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </form>
    <?php
    // Simple page: the preview follows the switch (kit depends_on). Advanced page
    // has no switch on it, so it uses the saved state.
    $wpslug_preview_attrs = "simple" === $mode
        ? $this->settings->getFramework()->depends_on_attrs(["field" => "enable_conversion", "value" => true])
        : ($wpslug_enabled ? "" : " hidden");
    ?>
    <div class="wpslug-preview-wrap"<?php echo $wpslug_preview_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped data attributes. ?>>
    <?php $this->renderPreviewCard(); ?>
    </div>
    </div>

<?php elseif ("tools" === $tab) : ?>
    <div class="wenpai-page-head">
      <div>
        <h1 class="wenpai-h1"><?php esc_html_e("工具", "wpslug"); ?></h1>
        <p class="wenpai-lede"><?php esc_html_e("本页可直接预览别名，不写进文章。重置是危险动作，要先勾选确认。", "wpslug"); ?></p>
      </div>
    </div>
    <div class="wenpai-stack">
      <?php $this->renderPreviewCard(); ?>
      <section class="card">
        <div class="card-head">
          <span class="tile bad"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("info") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <div>
            <h2 class="card-title"><?php esc_html_e("Reset to Defaults", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("把本插件的设置恢复为默认值，不可撤销。", "wpslug"); ?></p>
          </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
          <?php wp_nonce_field("wpslug_reset"); ?>
          <input type="hidden" name="action" value="wpslug_reset">
          <div class="wenpai-gate">
            <label><input type="checkbox" name="wpslug_reset_confirm" value="1"> <?php esc_html_e("I confirm I want to restore this plugin's settings to their defaults", "wpslug"); ?></label>
            <p class="meta"><?php esc_html_e("这会清掉本插件的设置，不清文章别名。", "wpslug"); ?></p>
          </div>
          <div class="card-foot start">
            <span class="meta"><?php esc_html_e("未勾选不能点。", "wpslug"); ?></span>
            <button type="submit" class="btn btn-danger" data-wenpai-need-apply disabled><?php esc_html_e("Reset to Defaults", "wpslug"); ?></button>
          </div>
        </form>
      </section>
    </div>
<?php elseif ("help" === $tab) : ?>
    <div class="wenpai-page-head">
      <div>
        <h1 class="wenpai-h1"><?php esc_html_e("帮助", "wpslug"); ?></h1>
        <p class="wenpai-lede"><?php esc_html_e("新保存的标题按所选模式写成拉丁字母别名。", "wpslug"); ?></p>
      </div>
    </div>
    <section class="card wpslug-help-card">
      <div class="card-head">
        <span class="tile ok"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <div>
          <h2 class="card-title"><?php esc_html_e("快速开始", "wpslug"); ?></h2>
          <p class="card-sub"><?php esc_html_e("首次配置按以下顺序完成。", "wpslug"); ?></p>
        </div>
      </div>
      <ol class="wpslug-help-steps">
        <li>
          <strong><?php
            printf(
                /* translators: %s: Enable Plugin label */
                esc_html__("打开「%s」", "wpslug"),
                esc_html__("Enable Plugin", "wpslug")
            );
            ?></strong>
          <span><?php esc_html_e("关掉后新保存的不再改，已经写进文章的别名不动。", "wpslug"); ?></span>
          <a class="btn btn-secondary" href="<?php echo esc_url($url("settings")); ?>"><?php esc_html_e("去设置", "wpslug"); ?></a>
        </li>
        <li>
          <strong><?php esc_html_e("选一种模式", "wpslug"); ?></strong>
          <span><?php echo esc_html(
              sprintf(
                  /* translators: 1: Local pinyin, 2: Multi-language translation, 3: Foreign Language Transliteration */
                  __("%1\$s、%2\$s或%3\$s。没装心思时没有语义拼音。", "wpslug"),
                  __("Local pinyin", "wpslug"),
                  __("Multi-language translation", "wpslug"),
                  __("Foreign Language Transliteration", "wpslug")
              )
          ); ?></span>
        </li>
        <li>
          <strong><?php esc_html_e("预览别名", "wpslug"); ?></strong>
          <span><?php esc_html_e("设置页下面输入标题，立刻看到结果，不写进文章。", "wpslug"); ?></span>
        </li>
      </ol>
    </section>
    <div class="wpslug-help-stack">
      <details class="card wpslug-help-details" open>
        <summary>
          <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("home") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <span class="wpslug-help-summary-text">
            <strong><?php esc_html_e("后台页面", "wpslug"); ?></strong>
            <span><?php esc_html_e("概览、设置、工具各负责什么。", "wpslug"); ?></span>
          </span>
        </summary>
        <div class="wpslug-help-body">
          <dl class="wpslug-help-dl">
            <dt><?php esc_html_e("概览", "wpslug"); ?></dt>
            <dd><?php esc_html_e("转换是否开启、心思是否装了、哪些文章类型会改别名。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("设置 · 简单", "wpslug"); ?></dt>
            <dd><?php esc_html_e("启用、转换模式、自动转换 / 仅发布时 / 强制小写。预览在这张表单下面。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("设置 · 高级", "wpslug"); ?></dt>
            <dd><?php esc_html_e("拼音、转写、翻译、SEO、媒体、内容类型。一次打开一组，没打开的保存时不会被改掉。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("工具", "wpslug"); ?></dt>
            <dd><?php esc_html_e("再留一份预览，以及重置。重置要先勾选确认。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("文章列表", "wpslug"); ?></dt>
            <dd><?php esc_html_e("批量改已有内容在文章列表，不在工具页。", "wpslug"); ?></dd>
          </dl>
        </div>
      </details>
      <details class="card wpslug-help-details">
        <summary>
          <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("equalizer") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <span class="wpslug-help-summary-text">
            <strong><?php esc_html_e("转换模式", "wpslug"); ?></strong>
            <span><?php esc_html_e("按标题语言选。失败会退回本地拼音，保存不会中断。", "wpslug"); ?></span>
          </span>
        </summary>
        <div class="wpslug-help-body">
          <dl class="wpslug-help-dl">
            <dt><?php esc_html_e("Local pinyin", "wpslug"); ?></dt>
            <dd><?php esc_html_e("中文标题、不经过心思。没装心思时也用这个。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("Multi-language translation", "wpslug"); ?></dt>
            <dd><?php esc_html_e("标题要先译成另一种语言再写成别名。需要心思或自备翻译服务。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("Foreign Language Transliteration", "wpslug"); ?></dt>
            <dd><?php esc_html_e("西里尔、阿拉伯、希腊等字母转成拉丁字母。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("Semantic pinyin (XinSi AI)", "wpslug"); ?></dt>
            <dd><?php esc_html_e("装了心思才会出现这一项。", "wpslug"); ?></dd>
          </dl>
        </div>
      </details>
      <details class="card wpslug-help-details">
        <summary>
          <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("help") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <span class="wpslug-help-summary-text">
            <strong><?php esc_html_e("常见问题", "wpslug"); ?></strong>
            <span><?php esc_html_e("关掉启用、预览、重置、批量。", "wpslug"); ?></span>
          </span>
        </summary>
        <div class="wpslug-help-body">
          <dl class="wpslug-help-dl">
            <dt><?php
              printf(
                  /* translators: %s: Enable Plugin label */
                  esc_html__("关掉「%s」后，已经写进文章的别名会怎样？", "wpslug"),
                  esc_html__("Enable Plugin", "wpslug")
              );
              ?></dt>
            <dd><?php esc_html_e("不动。新保存的不再改。模式和三项开关会立刻藏起来，保存过的值还在。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("预览会写进文章吗？", "wpslug"); ?></dt>
            <dd><?php esc_html_e("不会。设置页下面输入标题，立刻看到结果。启用关掉时，设置页这份跟着藏；工具页那份还在。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("没装心思能用吗？", "wpslug"); ?></dt>
            <dd><?php esc_html_e("能。用本地拼音。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("翻译或转写失败能保存吗？", "wpslug"); ?></dt>
            <dd><?php esc_html_e("能。退回本地拼音，保存不会中断。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("重置会清掉文章别名吗？", "wpslug"); ?></dt>
            <dd><?php esc_html_e("不会。只清本插件选项。要先勾选确认。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("批量改已有内容在哪？", "wpslug"); ?></dt>
            <dd><?php esc_html_e("文章列表。", "wpslug"); ?></dd>
            <dt><?php esc_html_e("翻译密钥空着是什么意思？", "wpslug"); ?></dt>
            <dd><?php esc_html_e("不改已保存的值。页面上不会再显示密钥。", "wpslug"); ?></dd>
          </dl>
        </div>
      </details>
      <details class="card wpslug-help-details">
        <summary>
          <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("server") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <span class="wpslug-help-summary-text">
            <strong><?php esc_html_e("WP-CLI", "wpslug"); ?></strong>
            <span><?php esc_html_e("在已激活插件的站点上可用。", "wpslug"); ?></span>
          </span>
        </summary>
        <div class="wpslug-help-body">
          <pre class="wenpai-code"><code>wp slug status
wp slug preview "你好，世界"
wp slug convert 123 --dry-run
wp slug doctor</code></pre>
          <p class="wpslug-help-note"><?php esc_html_e("完整说明在文档站。不要在命令行里粘贴翻译密钥。", "wpslug"); ?></p>
        </div>
      </details>
      <section class="card wpslug-help-card">
        <div class="card-head">
          <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("feedback") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <div>
            <h2 class="card-title"><?php esc_html_e("需要更多帮助", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("产品文档与工单支持请访问 wpcy.com。", "wpslug"); ?></p>
          </div>
        </div>
        <div class="card-foot start">
          <a class="btn btn-secondary" href="https://wpcy.com/slug" target="_blank" rel="noopener"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("external") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e("打开文档", "wpslug"); ?></a>
          <a class="btn btn-ghost" href="https://wpcy.com/support" target="_blank" rel="noopener"><?php esc_html_e("提交反馈", "wpslug"); ?></a>
        </div>
      </section>
    </div>
<?php endif; ?>

<?php
Wenpai_Admin_Shell::close([
    "version" => wpslug_plugin_name() . " " . WPSLUG_VERSION,
    "links" => [["url" => "https://wpcy.com/slug", "label" => __("文档", "wpslug")], ["url" => "https://wpcy.com/support", "label" => __("支持", "wpslug")]],
]);
