=== WPSlug ===
Contributors: wenpai
Tags: slug, pinyin, transliteration, translation, media
Requires at least: 6.0
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate readable slugs with local Chinese pinyin, transliteration, optional translation providers, and safe media filenames.

== Description ==

WPSlug converts empty slugs for enabled post types and taxonomies. Existing or explicitly supplied slugs are preserved. Optional Google, Baidu, and WPMind providers fall back to local pinyin when unavailable.

Media conversion applies only to newly uploaded filenames. Bulk conversion is an explicit migration action and should be tested on a backup before use.

== Installation ==

1. Upload the `wpslug` directory to `/wp-content/plugins/`.
2. Activate WPSlug from Plugins.
3. Configure it under Settings > Slug.

== Changelog ==

= 1.3.3 =
* Changed: bundled wenpai-admin-ui 0.2.5 → 0.3.1; settings fields use the kit's two-column row (280px label column, top-aligned, WPSlug 1.3.1 layout) and stack at ≤960px with controls filling the row.
* Changed: settings save per tab on the simple page and every advanced section (kit 0.3.0 field actions, disable-mode depends_on and status field).
* Added: show_editor_panel setting for the block editor panel (default off).
* Fixed: block editor sidebar preview buttons use core Button + Flex with equal height.
* Fixed: 「获取 API 密钥」 links open with noopener noreferrer and a new-tab note.
* Changed: plain Chinese copy for WPMind status and mode cards.

= 1.3.2 =
* Changed: bundled wenpai-admin-ui 0.2.3.3 → 0.2.5; admin menu and page chrome come from the kit (Wenpai_Admin_Menu / Wenpai_Admin_Shell) instead of hand-written markup.
* Changed: settings use a declarative schema (Wenpai_Admin_Settings); option validation delegates to the kit, with the legacy validator kept as fallback.
* Changed: settings page renders 19 fields through the kit's render_field (selects, toggles, seo_max_words, stop_words_list, media_file_prefix), with the hand-written markup kept as fallback.
* Changed: admin.js selects fields by name instead of id, so kit-generated ids do not break show/hide or Test API.
* Added: schema labels for toggles and API credential fields.
* Changed: zh_CN copy review — terms are 分类项, the separator is 连字符（-） (source string now "Hyphen (-)"), transliteration is consistently 转写 (外文转写), spaces between Chinese and Latin/digits, and the Tools page intro now says the preview is on that page.
* Fixed: PHP test harness defines WPSLUG_PLUGIN_BASENAME and stubs WP_Query so the suite runs to completion.
* Note: post type / taxonomy multichecks, per-post-type modes and API credential inputs stay hand-written (runtime choices; Test API button layout).

= 1.3.1 =
* Removed: leftover Yahnis Elsts plugin-update-checker and updatepulse-updater trees from the public repo; runtime stays WenPai updater (updates.wenpai.net).
* Added: root LICENSE (GPLv2 or later) in repo and release zip.
* Fixed: Plugin Check findings — remove production error_log/debug_backtrace, prefix admin template locals, unique-slug via get_posts, load_textdomain without load_plugin_textdomain.
* Note: plugin_updater_detected and trademarked_term (wp in WPSlug) intentionally ignored — self-hosted via updates.wenpai.net; not submitted to WordPress.org; wp brand retained.

= 1.3.0 =
* Added: wenpai-admin-ui 0.2.3 settings shell — overview / settings (simple|advanced) / tools; help is a header link, not a fourth tab.
* Added: `wp slug` CLI — status, preview, convert [--dry-run] [--yes], doctor. Secrets print as set or empty.
* Changed: when conversion is off, hide mode / toggles / settings preview immediately (`.wpslug-when-on`), do not disable inputs.
* Changed: preview keeps the source title visible; alias appears only with a result; success/fail color the whole frame.
* Changed: simple conversion mode is a one-column `.field`; without XinSi the three cards are local pinyin / multi-language translation / transliteration.
* Changed: admin font-weight 400; hierarchy from spacing.
* Changed: narrow header help/feedback icon-only; 782px checkbox clip and full-width tabs.
* Changed: help page follows WPSSS structure — quick start + details accordion.
* Changed: overview pills use 已开启/未开启 and 已安装/未安装; brand mark is Lucide feather.
* Changed: hide core update-nag on the shell; relocate third-party top notices into main content.
* Changed: at ≤782px, select overflow is unlocked and touch uses the system menulist.
* Changed: content-type semantic pinyin option only when XinSi is available.
* Changed: local pinyin / translation subcopy tightened; overview lede no longer says 可选自选.
* Fixed: zh_CN.po/mo/l10n.php for new shell and CLI strings.

= 1.2.6 =
* Changed: translation service label to XinSi AI (Recommended) / 心思 AI（推荐）.

= 1.2.5 =
* Changed: trim conversion-mode and XinSi status copy.

= 1.2.4 =
* Changed: conversion mode labels — Local pinyin / Semantic pinyin (XinSi AI) / Multi-language translation / Transliteration.
* Changed: show WPMind as WenPai XinSi (WPMind) / 文派心思（WPMind）.
* Fixed: per-post-type strategy table layout in Advanced settings.

= 1.2.3 =
* Fixed: stop freezing custom post type slugs as the pinyin of "Auto Draft" / 自动草稿; regenerate from the real title when leaving auto-draft.
* Added: convert-on-publish-only (default on) to skip draft autosave model calls while still clearing placeholder Auto Draft slugs.
* Added: editor slug preview controls (「用 AI 生成」/「用语义拼音」) that write the permalink only after confirm.
* Added: per-post-type default strategy (`post_type_modes`).
* Changed: WPMind contexts (`wpslug_seo_slug`, `wpslug_semantic_pinyin`); quota/budget errors show an admin notice and fall back to local pinyin.
* Changed: plugin links to https://wpcy.com/slug; drop the missing SVG update icon; author WPCY.COM.
* Added: zh_CN translations for the 1.2.x strings.

= 1.2.2 =
* Support WordPress 6.0 with PHP 7.4 through WordPress 7.0.
* Preserve existing and explicitly supplied slugs, including auto-drafts, and keep converted slugs unique.
* Keep cloud providers and WPMind compatible while safely falling back to local pinyin.
* Harden settings import/export, AJAX permissions, bulk conversion idempotency, and multisite uninstall cleanup.
* Keep media normal and legacy MD5 filename modes compatible; no historical media is renamed.

= 1.2.1 =
* Previous stable release.
