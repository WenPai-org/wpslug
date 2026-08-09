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
