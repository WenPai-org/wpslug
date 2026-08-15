# WPSlug 1.2.2 发布收口证据（2026-08-11）

> [CX] 本记录对应本地隔离分支 `codex/wpslug-audit-20260809`。未推送、未批准 CI、未合并、未创建 release、未发布、未部署。

## 元数据与包

- 候选版本统一为 1.2.2；插件头、运行常量、Stable tag、npm 元数据、测试常量和语言资产一致。
- WordPress 最低版本 6.0，PHP 最低版本 7.4，`.wp-env.json` 固定 WordPress 7.0.3。
- `Update URI` 保持 `https://updates.wenpai.net`；更新器 API 保持同源。
- `scripts/build-candidate.py` 仅读取提交后的 Git tree，归一化路径顺序、mtime 和权限；两次独立构建的 ZIP SHA-256 相同。
- 候选包、SHA256 和 manifest 位于 `dist/`，不进入 Git。

## 最终运行证据

| 检查 | 命令摘要 | 结果 |
|---|---|---|
| PHP 行为 | `php tests/php/run.php` | PASS，30/30 |
| PHPCS | `composer phpcs` | PASS，exit 0 |
| PHPStan | `composer phpstan` | PASS，exit 0 |
| npm audit | 官方 npm registry | PASS，0 vulnerabilities |
| Composer audit | `composer audit` | PASS，无 advisory |
| WP 6 / PHP 7.4 | matrix `mode=all` | PASS；单站、网络激活、子站、网络停用、uninstall |
| WP 7.0.3 / PHP 8.3 | 参数化 matrix `mode=all` | PASS；单站、网络激活、子站、网络停用、uninstall |
| WP 6 E2E | matrix `mode=e2e`，端口 18920 | PASS，2/2 |
| WP 7 E2E | matrix `mode=e2e`，端口 18921 | PASS，2/2 |
| 候选 ZIP Plugin Check | WP 7.0.3，Plugin Check 2.0.0，`mode=update` | PASS，0 rows；仅忽略已批准的自托管更新器/品牌目录政策代码 |
| 云服务真实凭据 | Google/Baidu/WPMind | SKIP；未提供独立测试凭据，未使用生产密钥 |

完整日志保存在 WenPai VM：`/tmp/wpslug-release-logs/`。矩阵验证包括已有/显式 slug 保护、同名唯一化、auto-draft、云失败本地回退、密钥不导出、批量幂等、多站点逐站清理和相邻 option 保留。

## 远端门

2026-08-11 只读刷新时，PR #47 仍 open、merged=false、mergeable=true，远端 HEAD 仍为 `8d724d27de4a5e7d29ac295be54ab1a7732a5b00`。旧 runs 181/182/183 都是 waiting/pending、need_approval=true，尚未执行，不能记 PASS。

本地候选提交未自行推送，因为 FeiCode 是 P0 且 `write_policy=board_required`。devops 必须按 `docs/ops/feicode-pr47-ci-approval.md` 发布 Board change：先无 force-push 地更新既有 PR 分支，再查询最终 HEAD 的新 run IDs，只批准对应的 gitleaks、security-scan、wp-plugin-ci。三项完成前候选状态是“本地验证通过，远端 CI 待人工审批”，不是发布候选。
