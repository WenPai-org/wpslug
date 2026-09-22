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
    $notice = __("已保存。", "wpslug");
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
            echo Wenpai_Admin_Icons::svg("external"); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
        <div class="card-foot start">
          <span class="meta"><?php esc_html_e("转换模式在设置 · 简单。", "wpslug"); ?></span>
          <a class="btn btn-secondary" href="<?php echo esc_url($url("settings")); ?>"><?php esc_html_e("设置", "wpslug"); ?></a>
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
          <a class="<?php echo "advanced" === $mode ? "on" : ""; ?>" href="<?php echo esc_url($url("settings", ["mode" => "advanced", "section" => $section])); ?>"><?php esc_html_e("高级", "wpslug"); ?></a>
        </span>
      </div>
    </div>
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
      <div class="wenpai-stack">
        <?php if ("simple" === $mode) : ?>
        <section class="card">
          <div class="simple-row">
            <div class="tile accent"><?php echo class_exists("Wenpai_Admin_Icons", false) ? Wenpai_Admin_Icons::svg("flash") : ""; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <div>
              <div class="t"><?php esc_html_e("启用转换", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("自动把标题写成别名。", "wpslug"); ?></div>
            </div>
            <div class="r">
              <input type="hidden" name="wpslug_options[enable_conversion]" value="0">
              <label class="toggle<?php echo $enabled ? " on" : ""; ?>">
                <input type="checkbox" name="wpslug_options[enable_conversion]" value="1" <?php checked(1, $options["enable_conversion"]); ?> hidden>
                <i></i> <?php echo $enabled ? esc_html__("已开启", "wpslug") : esc_html__("未开启", "wpslug"); ?>
              </label>
            </div>
          </div>
          <h2 class="section-title"><?php esc_html_e("转换模式", "wpslug"); ?></h2>
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
                    esc_html_e("不经过心思。无网络也能用。", "wpslug");
                } elseif ("semantic_pinyin" === $mode_id) {
                    esc_html_e("未装或额度不够时退回本地拼音。", "wpslug");
                } elseif ("translation" === $mode_id) {
                    esc_html_e("经心思或自备密钥。失败不阻断保存。", "wpslug");
                } else {
                    esc_html_e("多语言音译。", "wpslug");
                }
                ?></div>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
          <?php if (!$mind) : ?>
          <div class="notice info"><span><?php esc_html_e("心思未安装。语义拼音和翻译会走本地拼音，保存不会中断。", "wpslug"); ?></span></div>
          <?php endif; ?>
          <div class="simple-row">
            <div class="tile" aria-hidden="true"></div>
            <div>
              <div class="t"><?php esc_html_e("自动转换", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("保存文章和分类时自动转换", "wpslug"); ?></div>
            </div>
            <div class="r">
              <input type="hidden" name="wpslug_options[auto_convert]" value="0">
              <label class="chk"><input type="checkbox" name="wpslug_options[auto_convert]" value="1" <?php checked(1, $options["auto_convert"]); ?>></label>
            </div>
          </div>
          <div class="simple-row">
            <div class="tile" aria-hidden="true"></div>
            <div>
              <div class="t"><?php esc_html_e("仅发布时转换", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("草稿自动保存不转换", "wpslug"); ?></div>
            </div>
            <div class="r">
              <input type="hidden" name="wpslug_options[convert_on_publish_only]" value="0">
              <label class="chk"><input type="checkbox" name="wpslug_options[convert_on_publish_only]" value="1" <?php checked(1, !empty($options["convert_on_publish_only"])); ?>></label>
            </div>
          </div>
          <div class="simple-row">
            <div class="tile" aria-hidden="true"></div>
            <div>
              <div class="t"><?php esc_html_e("强制小写", "wpslug"); ?></div>
              <div class="d"><?php esc_html_e("别名一律小写", "wpslug"); ?></div>
            </div>
            <div class="r">
              <input type="hidden" name="wpslug_options[force_lowercase]" value="0">
              <label class="chk"><input type="checkbox" name="wpslug_options[force_lowercase]" value="1" <?php checked(1, $options["force_lowercase"]); ?>></label>
            </div>
          </div>
          <div class="card-foot">
            <button type="submit" class="btn btn-primary"><?php esc_html_e("保存设置", "wpslug"); ?></button>
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
            <button type="submit" class="btn btn-primary"><?php esc_html_e("保存设置", "wpslug"); ?></button>
          </div>
          <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </form>
    <?php $this->renderPreviewCard(); ?>

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
            <h2 class="card-title"><?php esc_html_e("重置设置", "wpslug"); ?></h2>
            <p class="card-sub"><?php esc_html_e("不可恢复。", "wpslug"); ?></p>
          </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
          <?php wp_nonce_field("wpslug_reset"); ?>
          <input type="hidden" name="action" value="wpslug_reset">
          <div class="wenpai-gate">
            <label><input type="checkbox" name="wpslug_reset_confirm" value="1"> <?php esc_html_e("我已经核对过，并且有数据库备份。这会清掉本插件 option，不清文章别名。", "wpslug"); ?></label>
          </div>
          <div class="card-foot start">
            <span class="meta"><?php esc_html_e("未勾选不能点。", "wpslug"); ?></span>
            <button type="submit" class="btn btn-danger" data-wenpai-need-apply disabled><?php esc_html_e("重置设置", "wpslug"); ?></button>
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
            <span><?php esc_html_e("在设置里打开「启用转换」。关掉之后，新保存的文章不会改别名。", "wpslug"); ?></span>
            <a class="btn btn-secondary" href="<?php echo esc_url($url("settings")); ?>"><?php esc_html_e("去设置", "wpslug"); ?></a>
          </li>
          <li>
            <strong><?php esc_html_e("选一个模式", "wpslug"); ?></strong>
            <span><?php esc_html_e("本地拼音不经过心思。多语言翻译和语义拼音在心思不可用时退回本地拼音，保存不会中断。外语音译用来处理非拉丁字母。", "wpslug"); ?></span>
          </li>
          <li>
            <strong><?php esc_html_e("先预览再保存", "wpslug"); ?></strong>
            <span><?php esc_html_e("设置页下面有预览。它按你正在改、还没保存的选择来转，不写进文章。", "wpslug"); ?></span>
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
        <div class="field">
          <div class="field-label"><?php esc_html_e("拼音", "wpslug"); ?></div>
          <div class="field-ctl"><?php esc_html_e("全拼或首字母、词间分隔、是否保留英文和数字。", "wpslug"); ?></div>
        </div>
        <div class="field">
          <div class="field-label"><?php esc_html_e("音译", "wpslug"); ?></div>
          <div class="field-ctl"><?php esc_html_e("西里尔、阿拉伯、希腊等字母转成拉丁字母的方式。", "wpslug"); ?></div>
        </div>
        <div class="field">
          <div class="field-label"><?php esc_html_e("翻译", "wpslug"); ?></div>
          <div class="field-ctl"><?php esc_html_e("翻译服务、源语言和目标语言。自备密钥留在这一组。", "wpslug"); ?></div>
        </div>
        <div class="field">
          <div class="field-label"><?php esc_html_e("SEO", "wpslug"); ?></div>
          <div class="field-ctl"><?php esc_html_e("停用词、标点和别名里保留多少个词。", "wpslug"); ?></div>
        </div>
        <div class="field">
          <div class="field-label"><?php esc_html_e("媒体", "wpslug"); ?></div>
          <div class="field-ctl"><?php esc_html_e("上传文件的文件名要不要转换，以及要不要保留扩展名。", "wpslug"); ?></div>
        </div>
        <div class="field">
          <div class="field-label"><?php esc_html_e("类型", "wpslug"); ?></div>
          <div class="field-ctl"><?php esc_html_e("哪些文章类型和分类法参与转换。", "wpslug"); ?></div>
        </div>
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
      <span class="r"><a href="https://wpcy.com/slug"><?php esc_html_e("文档", "wpslug"); ?></a></span>
    </footer>
  </main>
</div>
