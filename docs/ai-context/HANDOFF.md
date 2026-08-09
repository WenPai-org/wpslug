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
