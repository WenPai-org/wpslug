# WPSlug Handoff

## 2026-08-09 — 全面整治

- [CX] 代码真源确认：WenPai VM `/home/parallels/Projects/wpslug`，FeiCode `WenPai-org/wpslug`。
- [CX] 隔离 worktree：`/home/parallels/Projects/wpslug-codex-audit`；分支 `codex/wpslug-audit-20260809`；基线 `origin/main` `0a60b46`。
- [CX] 共享 `chore/e2e-harness` 未跟踪 `.forgejo/workflows/ci.yml` 保持原样，隔离副本未纳入提交。
- [CX] 已修复：运行要求不一致、PHP 7.4 更新器兼容、全局/重复 slug 钩子、显式/既有 slug 保护、Google auto source、WPMind 服务识别、AJAX/批量权限、凭据日志与导出、卸载依赖、release 生产部署步骤。
- [CX] 新增 PHP 行为/契约测试和真实 WordPress 设置页/AJAX E2E；扩展 CI 流程与 README 边界说明。
- [CX] 通过：`php tests/php/run.php`（20/20）、`npx playwright test --reporter=line`（2/2，WP 7.0.3/PHP 8.3.33）、PHP lint、`git diff --check`。
- [CX] 未通过：Plugin Check 457 errors/167 warnings；PHPCS 10500 errors/800 warnings；PHPStan 缺 WP stubs/config；npm audit 26 个 dev-tool 漏洞。最低版本、多站点、卸载、真实云凭据未形成运行样本。
- [CX] 完整证据、功能清单、未完成项与路线见 `docs/audits/wpslug-full-audit-2026-08-09.md`。
- [CX] 未部署、未发布、未合并默认分支、未删除历史数据。
- [CX] 本地提交已建立；FeiCode 拒绝直接推送该分支（用户 `wenpai` 无分支 push 权限，提示使用 `refs/for/...`）。未擅自创建 PR。


## 2026-08-09 — 第二轮质量基线

- [CX] 新增可复现 Composer 工具链、`phpcs.xml.dist` 聚焦 first-party 安全/nonce/输出/SQL/i18n/全局前缀检查，`composer phpcs` 退出码 0。未对全仓做机械格式化。
- [CX] 新增 WordPress stubs 的 `phpstan.neon.dist`，覆盖主文件、core、translator、admin AJAX、updater，`composer phpstan` 退出码 0。
- [CX] 发布包 Plugin Check 2.0.0：忽略自托管更新器和既有品牌两个目录政策代码后 0 errors；原始剩余 33 warnings 已记录，主要是 debug-only 日志、直接查询和 WordPress 核心 hook。`scripts/plugin-check.sh` 为 error gate。
- [CX] npm override 将 `adm-zip` 升至 0.6.0；`npm audit` 为 0 vulnerabilities；Playwright 2/2 通过。
- [CX] PHP 行为测试扩至 25 assertions：新增持久自定义 slug 保护、云失败回退、导入导出密钥隔离、批量幂等。
- [CX] 发布包增加 `readme.txt`，排除 `.ci-trigger`、`.wp-env.json`、scripts；三处 `strip_tags` 改用 `wp_strip_all_tags`。
- [CX] 组件拆分与迁移边界见 `docs/audits/wpslug-component-migration-plan-2026-08-09.md`。
- [CX] 第二轮提交 `a7aa776` 已通过 AGit 正常评审引用推送到 FeiCode PR #47：https://feicode.com/WenPai-org/wpslug/pulls/47；未合并、未发布。

## 2026-08-09 — 第三轮最低版本与多站点

- [CX] WordPress 6.0.9/PHP 7.4 首次真实运行发现新文章仍产生 URL 编码中文 slug；原因是标准化 `$postarr` 的标题派生 `post_name` 被误判为用户显式 slug。现改用 WordPress 6.0 原始 caller payload 与 update 标记区分新建、显式 slug 和已有内容更新，并在转换后调用核心唯一化；已发布和 auto-draft 自定义 slug 均保留。
- [CX] 多站点 uninstall 首次审计确认旧回调只清当前 blog，且原 SQL LIKE 会把下划线当通配符；现逐站清除已知 WPSlug options，恢复 blog/switch stack，并保留文章、term、附件、自定义/生成 slug、相邻命名空间及无关 options。
- [CX] 最低版本单站、网络激活、子站 blog 2 行为、网络停用、主站/子站卸载矩阵均 PASS；真实子站拼音与已有自定义 slug 保护均通过。完整命令和矩阵见 `docs/audits/wpslug-compat-multisite-matrix-2026-08-09.md`。
- [CX] 回归：PHP 行为 30/30、WordPress 6.0.9/PHP 7.4 Playwright 2/2、PHPCS 0、PHPStan 0、npm audit official registry 0 vulnerabilities。
- [CX] Google/Baidu/WPMind 真实云凭据继续 SKIP；未读取或使用生产密钥。
- [CX] PR #47 修复前 head 的 Gitleaks run 175、Security run 176、WordPress Plugin CI run 177 均 `waiting/pending`、`need_approval=true`，不是 PASS/FAIL；公开 Jobs API 返回 404。
- [CX] 未部署、未发布、未合并默认分支、未批准远端 CI、未覆盖共享 WIP。
