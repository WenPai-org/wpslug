=== WPSlug ===
Contributors: wenpai
Tags: slug, pinyin, transliteration, translation, media
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.7
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

= 1.2.7 =
* Label: translation service option text → 心思 AI （推荐） (space before paren)

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
