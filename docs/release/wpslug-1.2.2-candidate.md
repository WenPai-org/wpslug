# WPSlug 1.2.2 兼容修复候选版

> [CX] 这是 PR #47 的评审候选说明，不是发布授权。不得据此合并、创建 tag/release、部署或发布。

## 候选边界

- 版本：1.2.2
- 最低运行要求：WordPress 6.0、PHP 7.4
- 已验证上限目标：WordPress 7.0
- 更新通道：`Update URI: https://updates.wenpai.net`
- 核心保留：本地拼音、唯一 slug 生成、已有或显式 slug 保护。
- 兼容保留：Google/Baidu 云翻译、WPMind、媒体 normal/MD5 模式。
- 本版不执行组件拆分，不迁移或删除设置，不自动重命名历史媒体，不使用生产云凭据。

## 候选包

候选包必须从已提交的最终 HEAD 构建，构建器只读取 Git tree，因此不会带入未跟踪或忽略文件：

```bash
python3 scripts/build-candidate.py --version 1.2.2
sha256sum -c dist/wpslug-1.2.2-candidate.zip.sha256
```

输出：

- `dist/wpslug-1.2.2-candidate.zip`
- `dist/wpslug-1.2.2-candidate.zip.sha256`
- `dist/wpslug-1.2.2-candidate.manifest.txt`

用相同 HEAD 与 `SOURCE_DATE_EPOCH` 重建时，ZIP 字节及 SHA-256 必须一致。正式 tag 流程使用同一构建器，但正式发布仍须单独的 FeiCode Board 审批。

## 升级说明

1. 记录当前插件版本、启用范围（单站或网络）以及 WPSlug 设置；备份数据库和当前 1.2.1 插件目录。
2. 校验候选 ZIP SHA-256 和 manifest，确认包根目录为 `wpslug/`。
3. 不执行 uninstall。以 WordPress 插件升级方式替换 1.2.1 文件，保持原启用范围。
4. 检查插件版本为 1.2.2；确认原设置仍在、已有自定义 slug 未变化。
5. 新建中文标题内容，确认生成本地拼音 slug；创建同名内容，确认 WordPress 唯一化后缀。
6. 仅用独立测试凭据检查云 provider；没有凭据时记录 SKIP，并验证失败回退到本地拼音。
7. 多站点逐站抽查设置与已有 slug；网络停用不清数据，卸载才逐站清理 WPSlug options。

## 回滚说明

1. 先停用 1.2.2，保留数据库，不运行 uninstall。
2. 用已备份或已校验的 1.2.1 包替换插件目录，再按原单站/网络范围启用。
3. 复核设置、已有 slug 和新建内容转换；保留候选测试日志与失败证据。
4. 如已执行 uninstall，插件设置已被逐站清理，只能从升级前数据库备份恢复；文章、term、媒体记录及其 slug 不由 uninstall 删除。

## 发布门

- 最低版本、WordPress 7、多站点/卸载、PHP tests、PHPCS、PHPStan、Plugin Check、E2E 都有非零样本且通过。
- 候选包连续两次重建 SHA-256 相同，包清单只含运行文件。
- FeiCode PR #47 最终 HEAD 固定，三个 Actions workflow 经 devops 人工批准并完成。
- PR 保持未合并，未创建 tag/release，未触发发布或部署。
