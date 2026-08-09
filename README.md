# WPSlug

为 WordPress 文章、页面、分类法和上传文件生成可读 slug，支持中文拼音、跨语言转写、Google/Baidu 翻译和可选 WPMind 集成。

## 运行要求

- WordPress 6.0+
- PHP 7.4+，并启用 mbstring 和 JSON
- 云翻译只在管理员显式选择并配置后使用；服务失败时回退为本地拼音

WPSlug 默认只为尚无 slug 的新内容自动生成。已有或显式填写的 slug 不会在普通更新、发布状态切换时重写；后台批量转换是显式迁移操作，会修改所选内容的 slug。

媒体转换只影响新上传文件名，不回溯重命名媒体库文件。多站点设置按站点保存，卸载只清理执行卸载的站点数据，不遍历删除整个网络的数据。

## 开发验证

```bash
npm ci
npm run test:php
npm run wp-env:start
npm test
npm run wp-env:stop
```

<!-- CI test 1777893437 -->
 
