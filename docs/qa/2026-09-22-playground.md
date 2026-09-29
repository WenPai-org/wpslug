# 验收 — wpslug Playground

- 席：本机 WordPress Playground（PHP 8.3，WordPress 为 CLI 的 latest）。没有改 `play.wenpai.net`。
- 谁：Grok
- 日期：2026-09-22
- 包：`feat/admin-ui-1.3-php` `543368d`
- kit：`ver=0.2.3`
- 正常：概览默认「转换 / Local pinyin / 开」。设置高级是 `.field`，页面里没有 `form-table`。
- 错：工具页未勾确认就提交重置，停在页顶「重置前请先勾选确认。」没有清掉 option。
- 空：默认转换是开的，「转换还没开」这一支这次没点到。
- 截图：`playground-overview.png`、`playground-settings.png`、`playground-advanced.png`、`playground-error.png`
- 高级字段的说明仍是英文。看见，未改。页脚写 WPSlug 1.2.6。看见，未改。
- 未合 main。

## 补点

- 空：Playground 把 `enable_conversion` 设为关。概览出现「转换还没开」和「去设置」。状态行是「未启用。」和「关」。
- 截图：`playground-empty.png`
- 原先状态行写着「option key 不动」，那是给 agent 看的。已从页面去掉，提交 `82daa1c`。


## 中文

- 之前高级设置看起来是英文，因为那次 Playground 的站点语言是 en_US。
- 插件自带 `languages/wpslug-zh_CN.mo`。站点语言改为 zh_CN 后，高级设置是「中文拼音设置」「拼音格式」「翻译服务」。截图 `playground-advanced-zh.png`。
- 顶栏品牌原先写死 WPSlug，中文菜单却是「文派素格」。顶栏改为走这条已有翻译。
