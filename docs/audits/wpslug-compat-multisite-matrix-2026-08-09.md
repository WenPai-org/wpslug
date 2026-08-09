# WPSlug 最低版本与多站点矩阵（2026-08-09）

> [CX] 本文记录第三轮真实运行样本；没有使用生产站点或生产云凭据，也不代表发布批准。

## 环境与边界

- 代码真源工作树：`/home/parallels/Projects/wpslug-codex-audit`
- 分支：`codex/wpslug-audit-20260809`
- 容器运行时：`wordpress:php7.4-apache`、WordPress `6.0.9`、`mariadb:lts`
- 测试数据只存在于带时间、PID、随机数 run id 的 `wpslug-r3-*` 临时 Docker 资源；脚本不做启动前强制清理，只在退出时删除本次唯一名称的容器、网络和 volume。
- Google、Baidu、WPMind 真实凭据调用继续记为 SKIP；没有为全绿读取或使用生产密钥。

## 首次运行发现与修复

### 新文章未转换为拼音

首次 WordPress 6.0.9/PHP 7.4 运行中，新建标题“文派素格”的 slug 实际为 URL 编码中文，而不是 `wen-pai-su-ge`。原因是 `wp_insert_post_data` 的标准化 `$postarr` 已带 WordPress 从标题生成的 `post_name`，旧逻辑把它误判为用户显式 slug。

修复后 filter 接收 WordPress 6.0 提供的原始 caller payload 和 update 标记：只把原始 payload 中非空 `post_name` 视为显式 slug；已有文章更新仍保留持久 slug；新建文章允许转换标题派生 slug。

### 多站点卸载只清理当前站

原卸载回调只对当前 blog 的 options 表执行清理，会在其他子站留下 `wpslug_*` 配置。修复后多站点卸载枚举 site IDs，逐站 `switch_to_blog()` 清理，并在 `finally` 中恢复 blog。卸载只删除插件已知 option keys，不使用易误匹配下划线的 SQL LIKE；文章、term、附件、相邻命名空间及非 WPSlug options 不删除。

## 运行矩阵

| 场景 | 结果 |
|---|---|
| PHP 7.4 + WordPress 6.0.9 安装与激活 | PASS |
| 单站新文章拼音转换与唯一化 | PASS，`文派素格` → `wen-pai-su-ge`，同名第二篇 → `wen-pai-su-ge-2` |
| 单站显式/已有自定义 slug | PASS，已发布和 auto-draft 发布路径均不重写；字符串 `0` 单元回归通过 |
| 单站停用 | PASS，插件 inactive，已有内容 slug 保留 |
| multisite 网络激活 | PASS，network active |
| 子站选择与行为 | PASS，真实 `get_current_blog_id()=2`；拼音生成和自定义 slug 保护通过 |
| multisite 网络停用 | PASS，network inactive；主站/子站插件 options 和内容 slug 均保留 |
| multisite uninstall 插件数据 | PASS，主站/子站已知 WPSlug options 清理，blog id 与 switch stack 恢复为 `1:1:0` |
| multisite uninstall 用户数据 | PASS，主站/子站相邻 `wpslugger_*` option、生成 slug、自定义 slug 均保留 |

复现命令：

```bash
cd /home/parallels/Projects/wpslug-codex-audit
tests/scripts/run-compat-matrix.sh
WPSLUG_MATRIX_MODE=e2e tests/scripts/run-compat-matrix.sh
```

`WPSLUG_MATRIX_MODE=single` 和 `multisite` 可单独复核。脚本要求本机有 Docker、WP-CLI phar 和已安装 Playwright 浏览器。

## 自动化检查

| 命令 | 结果 |
|---|---|
| `php tests/php/run.php` | PASS，30/30 assertions |
| `composer phpcs` | PASS，退出码 0 |
| `composer phpstan` | PASS，退出码 0 |
| `bash -n tests/scripts/run-compat-matrix.sh` | PASS，退出码 0 |
| `npm audit --audit-level=moderate --registry=https://registry.npmjs.org` | PASS，0 vulnerabilities，退出码 0 |
| `WPSLUG_MATRIX_MODE=e2e tests/scripts/run-compat-matrix.sh` | PASS，2/2，WordPress 6.0.9/PHP 7.4，退出码 0 |

## FeiCode PR #47 CI

在第三轮修复前 head `6e4cbe890e864909fd98e79e04eba2ec757bceb7` 上，三个 workflow 均未执行，不是 PASS/FAIL：

| Workflow/context | Run | 状态 |
|---|---|---|
| Gitleaks / `gitleaks (pull_request)` | 175 | `waiting` / `pending`；`need_approval=true`；`Blocked by required conditions` |
| Security Scan / `security-scan (pull_request)` | 176 | 同上 |
| WordPress Plugin CI / `ci (pull_request)` | 177 | 同上 |

公开 Actions jobs API 对 run number 和 internal id 均返回 HTTP 404；commit status 的 `/jobs/0` 是当前可取得的最细粒度。第三轮提交推送后需重新记录新 head 对应 runs。
