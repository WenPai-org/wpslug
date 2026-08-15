# WPSlug

为 WordPress 文章、页面、分类法和上传文件生成可读 slug，支持中文拼音、跨语言转写、Google/Baidu 翻译和可选 WPMind 集成。

## 运行要求

- WordPress 6.0+
- PHP 7.4+，并启用 mbstring 和 JSON
- 云翻译只在管理员显式选择并配置后使用；服务失败时回退为本地拼音

WPSlug 默认只为尚无 slug 的新内容自动生成。已有或显式填写的 slug 不会在普通更新、发布状态切换时重写；后台批量转换是显式迁移操作，会修改所选内容的 slug。

媒体转换只影响新上传文件名，不回溯重命名媒体库文件。多站点设置按站点保存；网络卸载会逐站清理 WPSlug options，但保留文章、term、媒体记录及其 slug。

## 开发验证

```bash
composer install
composer phpcs
composer phpstan
composer audit
npm ci
npm audit --registry=https://registry.npmjs.org
npm run test:php
npm run wp-env:start
npm test
npm run wp-env:stop
```

Plugin Check 需要已安装并启用 Plugin Check 2.x 的 WordPress/WP-CLI 测试环境。`scripts/plugin-check.sh wpslug` 检查与发布包相同的 first-party 范围；仅忽略 FeiCode 自托管更新器和既有 WPSlug 品牌对应的 WordPress.org 目录政策代码。

<!-- CI test 1777893437 -->
 
