<?php
defined("ABSPATH") || exit();
/** @var array $options */
/** @var string $tab */
/** @var string $mode */
/** @var callable $url */
/** @var string $section */
/** @var array<string, array{label: string, icon: string, sub: string}> $adv_sections */
$enabled = !empty($options["enable_conversion"]);
$conv = isset($options["conversion_mode"]) ? (string) $options["conversion_mode"] : "pinyin";
$types = is_array($options["enabled_post_types"] ?? null) ? $options["enabled_post_types"] : [];
$mind = function_exists("wpmind_is_available") && wpmind_is_available();
$modes = $this->settings->getConversionModes();
$notice = "";
$level = "";
// phpcs:disable WordPress.Security.NonceVerification.Recommended
if (isset($_GET["settings-updated"]) && "true" === $_GET["settings-updated"]) {
    $notice = __("Settings saved successfully!", "wpslug");
    $level = "ok";
}
if (isset($_GET["wpslug_notice"]) && "reset-confirm" === $_GET["wpslug_notice"]) {
    $notice = __("重置前请先勾选确认。", "wpslug");
    $level = "bad";
}
// phpcs:enable
?>
<div class="wenpai-app">
  <div class="wenpai-top"><div class="wenpai-wrap">
    <div class="wenpai-head">
      <a class="wenpai-brand" href="<?php echo esc_url($url("overview")); ?>">
        <span class="wenpai-mark"><?php
        if (class_exists("Wenpai_Admin_Icons", false)) {
            echo Wenpai_Admin_Icons::svg("home"); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?></span>
        <span class="wenpai-name"><?php echo esc_html(wpslug_brand_name()); ?></span>
      </a>
      <nav class="wenpai-tabs" aria-label="文派素格">
        <a class="wenpai-tab<?php echo "overview" === $tab ? " is-active" : ""; ?>" href="<?php echo esc_url($url("overview")); ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("home") : ""; ?><?php esc_html_e("概览", "wpslug"); ?></a>
        <a class="wenpai-tab<?php echo "settings" === $tab ? " is-active" : ""; ?>" href="<?php echo esc_url($url("settings")); ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("equalizer") : ""; ?><?php esc_html_e("设置", "wpslug"); ?></a>
        <a class="wenpai-tab<?php echo "tools" === $tab ? " is-active" : ""; ?>" href="<?php echo esc_url($url("tools")); ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("server") : ""; ?><?php esc_html_e("工具", "wpslug"); ?></a>
      </nav>
      <div class="wenpai-head-right">
        <a class="btn btn-ghost<?php echo "help" === $tab ? " is-active" : ""; ?>" href="<?php echo esc_url($url("help")); ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("help") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e("帮助", "wpslug"); ?></a>
        <a class="btn btn-ghost" href="https://wpcy.com/support" target="_blank" rel="noopener"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("feedback") : ""; ?><?php esc_html_e("反馈", "wpslug"); ?></a>
      </div>
    </div>
  </div></div>
  <main class="wenpai-main wenpai-wrap">
    <?php if ("" !== $notice) : ?>
    <div class="wenpai-notice-wrap">
      <div class="notice <?php echo esc_attr($level); ?>"><span><?php echo esc_html($notice); ?></span></div>
    </div>
    <?php endif; ?>

<?php if ("overview" === $tab) : ?>
    <div class="wenpai-page-head">
      <div>
        <h1 class="wenpai-h1"><?php esc_html_e("概览", "wpslug"); ?></h1>
        <p class="wenpai-lede"><?php esc_html_e("把标题写成别名。文章类型范围在这里。", "wpslug"); ?></p>
      </div>
    </div>
    <div class="wenpai-stack">
      <?php if (!$enabled) : ?>
      <div class="next">
        <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; ?></span>
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
          <div class="tile <?php echo $enabled ? "ok" : "warn"; ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; ?></div>
          <div>
            <div class="t"><?php esc_html_e("转换", "wpslug"); ?></div>
            <div class="d"><?php echo $enabled ? esc_html($modes[$conv] ?? $conv) : esc_html__("未启用。", "wpslug"); ?></div>
          </div>
          <div class="r"><span class="pill <?php echo $enabled ? "ok" : "warn"; ?>"><?php echo $enabled ? esc_html__("开", "wpslug") : esc_html__("关", "wpslug"); ?></span></div>
        </div>
        <div class="simple-row is-static">
          <div class="tile <?php echo $mind ? "ok" : ""; ?>"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("sparkle") : ""; ?></div>
          <div>
            <div class="t"><?php esc_html_e("文派心思", "wpslug"); ?></div>
            <div class="d"><?php echo $mind ? esc_html__("已接通。额度在心思，这里不做充值。", "wpslug") : esc_html__("未安装时本地拼音兜底，保存不中断。", "wpslug"); ?></div>
          </div>
          <div class="r"><span class="pill <?php echo $mind ? "ok" : ""; ?>"><?php echo $mind ? esc_html__("已装", "wpslug") : esc_html__("未装", "wpslug"); ?></span></div>
        </div>
        <div class="simple-row is-static">
          <div class="tile ok"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("info") : ""; ?></div>
          <div>
            <div class="t"><?php esc_html_e("文章类型范围", "wpslug"); ?></div>
            <div class="d"><?php echo esc_html(implode("、", $types)); ?></div>
          </div>
          <div class="r"><span class="scope"><?php echo esc_html(implode(" / ", $types)); ?></span></div>
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
        <?php foreach ($adv_sections as $section_id => $meta) : ?>
        <a class="<?php echo $section === $section_id ? "on" : ""; ?>" href="<?php echo esc_url($url("settings", ["mode" => "advanced", "section" => $section_id])); ?>"><?php echo esc_html($meta["label"]); ?></a>
        <?php endforeach; ?>
      </span>
    </div>
    <?php endif; ?>
    <form method="post" action="options.php" id="wpslug-settings-form">
      <?php settings_fields("wpslug_settings"); ?>
      <input type="hidden" name="wpslug_current_tab" value="<?php echo esc_attr($tab); ?>">
        <?php if ("simple" === $mode) : ?>
        <section class="card wpslug-simple">
          <div class="simple-row">
            <div class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <div>
              <div class="t"><?php esc_html_e("Enable Plugin", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("Enable automatic slug conversion for your content", "wpslug"); ?></div>
            </div>
            <div class="r">
              <?php $this->renderToggle("enable_conversion", "wpslug_options[enable_conversion]", $enabled); ?>
            </div>
          </div>
          <h2 class="section-title"><?php esc_html_e("Conversion Mode", "wpslug"); ?></h2>
          <p class="card-sub"><?php esc_html_e("Choose local pinyin, semantic pinyin (XinSi AI), multi-language translation, or transliteration.", "wpslug"); ?></p>
          <div class="choice-cards wpslug-modes">
            <?php foreach ($modes as $mode_id => $label) :
                $on = $conv === $mode_id;
                ?>
            <label class="choice<?php echo $on ? " is-on" : ""; ?>">
              <input type="radio" name="wpslug_options[conversion_mode]" value="<?php echo esc_attr($mode_id); ?>" <?php checked($conv, $mode_id); ?>>
              <span>
                <div class="t"><?php echo esc_html($label); ?></div>
                <div class="d"><?php
                if ("pinyin" === $mode_id) {
                    esc_html_e("不经过心思", "wpslug");
                } elseif ("semantic_pinyin" === $mode_id) {
                    esc_html_e("不够时用本地拼音", "wpslug");
                } elseif ("translation" === $mode_id) {
                    esc_html_e("失败仍可保存", "wpslug");
                } else {
                    esc_html_e("外文转拉丁字母", "wpslug");
                }
                ?></div>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php if (!$mind) : ?>
          <div class="notice info"><span><?php esc_html_e("Install WenPai XinSi (WPMind) for semantic pinyin (XinSi AI) and multi-language translation. Until then, local pinyin remains available.", "wpslug"); ?></span></div>
          <?php endif; ?>
          <div class="simple-row">
            <div>
              <div class="t"><?php esc_html_e("Auto Convert", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("Automatically convert slugs when saving posts and terms", "wpslug"); ?></div>
            </div>
            <div class="r">
              <?php $this->renderToggle("wpslug-auto-convert", "wpslug_options[auto_convert]", !empty($options["auto_convert"])); ?>
            </div>
          </div>
          <div class="simple-row">
            <div>
              <div class="t"><?php esc_html_e("Convert on Publish Only", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("Only convert post slugs when status is publish or future (skips draft autosaves)", "wpslug"); ?></div>
            </div>
            <div class="r">
              <?php $this->renderToggle("wpslug-publish-only", "wpslug_options[convert_on_publish_only]", !empty($options["convert_on_publish_only"])); ?>
            </div>
          </div>
          <div class="simple-row">
            <div>
              <div class="t"><?php esc_html_e("Force Lowercase", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("Convert all slugs to lowercase for consistency", "wpslug"); ?></div>
            </div>
            <div class="r">
              <?php $this->renderToggle("wpslug-force-lower", "wpslug_options[force_lowercase]", !empty($options["force_lowercase"])); ?>
            </div>
          </div>
          <div class="card-foot">
            <button type="submit" class="btn btn-primary"><?php esc_html_e("Save Changes", "wpslug"); ?></button>
          </div>
        </section>
        <?php else : ?>
        <?php
        $renderers = [
            "pinyin" => "renderPinyinSettings",
            "translit" => "renderTransliterationSettings",
            "translate" => "renderTranslationSettings",
            "seo" => "renderSEOSettings",
            "media" => "renderMediaSettings",
            "types" => "renderAdvancedSettings",
        ];
        foreach ($renderers as $section_id => $method) :
            $meta = $adv_sections[$section_id];
            ?>
        <section class="card"<?php echo $section === $section_id ? "" : " hidden"; ?>>
          <div class="card-head">
            <span class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg($meta["icon"]) : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div>
              <h2 class="card-title"><?php echo esc_html($meta["label"]); ?></h2>
              <p class="card-sub"><?php echo esc_html($meta["sub"]); ?></p>
            </div>
          </div>
          <?php $this->{$method}($options); ?>
          <?php if ($section === $section_id) : ?>
          <div class="card-foot">
            <button type="submit" class="btn btn-primary"><?php esc_html_e("Save Changes", "wpslug"); ?></button>
          </div>
          <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </form>
    <?php $this->renderPreviewCard(); ?>
    </div>

<?php elseif ("tools" === $tab) : ?>
    <div class="wenpai-page-head">
      <div>
        <h1 class="wenpai-h1"><?php esc_html_e("工具", "wpslug"); ?></h1>
        <p class="wenpai-lede"><?php esc_html_e("重置是危险动作。预览在设置页下面。", "wpslug"); ?></p>
      </div>
    </div>
    <div class="wenpai-stack">
      <?php $this->renderPreviewCard(); ?>
      <section class="card">
        <div class="card-head">
          <span class="tile bad"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("info") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <div>
            <h2 class="card-title"><?php esc_html_e("Reset to Defaults", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("不可恢复。", "wpslug"); ?></p>
          </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
          <?php wp_nonce_field("wpslug_reset"); ?>
          <input type="hidden" name="action" value="wpslug_reset">
          <div class="wenpai-gate">
            <label><input type="checkbox" name="wpslug_reset_confirm" value="1"> <?php esc_html_e("Are you sure you want to reset all settings to default values?", "wpslug"); ?> <?php esc_html_e("这会清掉本插件的设置，不清文章别名。", "wpslug"); ?></label>
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
        <p class="wenpai-lede"><?php esc_html_e("把标题写成别名。这一页只讲怎么用，不代替文档站。", "wpslug"); ?></p>
      </div>
    </div>
    <div class="wenpai-stack">
      <section class="card">
        <div class="card-head">
          <span class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <div>
            <h2 class="card-title"><?php esc_html_e("快速开始", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("三步就够。", "wpslug"); ?></p>
          </div>
        </div>
        <ol class="wpslug-help-steps">
          <li>
            <strong><?php esc_html_e("打开转换", "wpslug"); ?></strong>
            <span><?php printf(
                /* translators: %s: Enable Plugin label */
                esc_html__("在设置里打开「%s」。关掉之后，新保存的文章不会改别名。", "wpslug"),
                esc_html__("Enable Plugin", "wpslug")
            ); ?></span>
            <a class="btn btn-secondary" href="<?php echo esc_url($url("settings")); ?>"><?php esc_html_e("去设置", "wpslug"); ?></a>
          </li>
          <li>
            <strong><?php esc_html_e("选一个模式", "wpslug"); ?></strong>
            <span><?php echo esc_html(
                sprintf(
                    /* translators: 1: Local pinyin, 2: Multi-language translation, 3: Semantic pinyin, 4: Foreign Language Transliteration */
                    __("%1\$s不经过心思。%2\$s和%3\$s在心思不可用时退回%1\$s，保存不会中断。%4\$s用来处理非拉丁字母。", "wpslug"),
                    __("Local pinyin", "wpslug"),
                    __("Multi-language translation", "wpslug"),
                    __("Semantic pinyin (XinSi AI)", "wpslug"),
                    __("Foreign Language Transliteration", "wpslug")
                )
            ); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("先预览再保存", "wpslug"); ?></strong>
            <span><?php esc_html_e("设置页下面有预览。输入标题就显示别名，按还没保存的选择来转，不写进文章。", "wpslug"); ?></span>
          </li>
        </ol>
      </section>
      <section class="card">
        <div class="card-head">
          <span class="tile"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("equalizer") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <div>
            <h2 class="card-title"><?php esc_html_e("高级里有什么", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("一次只打开一组。", "wpslug"); ?></p>
          </div>
        </div>
        <ul class="wpslug-help-steps">
          <li>
            <strong><?php esc_html_e("Pinyin", "wpslug"); ?></strong>
            <span><?php esc_html_e("全拼或首字母、词间分隔、是否保留英文和数字。", "wpslug"); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("Transliteration", "wpslug"); ?></strong>
            <span><?php esc_html_e("西里尔、阿拉伯、希腊等字母转成拉丁字母的方式。", "wpslug"); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("Translation", "wpslug"); ?></strong>
            <span><?php esc_html_e("翻译服务、源语言和目标语言。自备密钥留在这一组。", "wpslug"); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("SEO Optimization", "wpslug"); ?></strong>
            <span><?php esc_html_e("停用词、标点和别名里保留多少个词。", "wpslug"); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("Media Files", "wpslug"); ?></strong>
            <span><?php esc_html_e("上传文件的文件名要不要转换，以及要不要保留扩展名。", "wpslug"); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("Advanced", "wpslug"); ?></strong>
            <span><?php esc_html_e("哪些文章类型和分类法参与转换。", "wpslug"); ?></span>
          </li>
        </ul>
        <div class="card-foot start">
          <span class="meta"><?php esc_html_e("简单页只管开关和模式。", "wpslug"); ?></span>
          <a class="btn btn-secondary" href="<?php echo esc_url($url("settings", ["mode" => "advanced"])); ?>"><?php esc_html_e("打开高级", "wpslug"); ?></a>
        </div>
      </section>
      <section class="card">
        <div class="card-head">
          <span class="tile bad"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("info") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <div>
            <h2 class="card-title"><?php esc_html_e("重置", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("只清本插件的设置，不清已经写进文章的别名。", "wpslug"); ?></p>
          </div>
        </div>
        <div class="card-foot start">
          <span class="meta"><?php esc_html_e("要先勾选确认。", "wpslug"); ?></span>
          <a class="btn btn-ghost" href="<?php echo esc_url($url("tools")); ?>"><?php esc_html_e("去工具", "wpslug"); ?></a>
        </div>
      </section>
    </div>
<?php endif; ?>

    <footer class="foot">
      <span><?php echo esc_html(wpslug_plugin_name()); ?> <?php echo esc_html(WPSLUG_VERSION); ?></span>
      <span class="r"><a href="https://wpcy.com/slug"><?php esc_html_e("Documentation", "wpslug"); ?></a></span>
    </footer>
  </main>
</div>
