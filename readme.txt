=== WPSlug ===
Contributors: wenpai
Tags: slug, pinyin, transliteration, translation, media
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
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

= 1.2.1 =
* Current stable release.
