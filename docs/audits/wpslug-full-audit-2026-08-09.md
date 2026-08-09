# WPSlug 全面审计（2026-08-09）

> [CX] 本文记录事实、已验证修复和未完成边界；不代表发布批准。

## 范围与代码真源

- 真源：FeiCode `WenPai-org/wpslug`，WenPai VM 路径 `/home/parallels/Projects/wpslug`。
- 隔离工作：`/home/parallels/Projects/wpslug-codex-audit`，分支 `codex/wpslug-audit-20260809`，基线 `origin/main` `0a60b46`。
- 共享 `chore/e2e-harness` 副本中的未跟踪 `.forgejo/workflows/ci.yml` 未覆盖、未提交。
- 未部署、未发布、未合并默认分支，未删除历史数据。

## 功能、入口与数据流

| 功能 | WordPress 入口 | 数据/外部依赖 | 行为边界 |
|---|---|---|---|
| 文章/页面 slug | `wp_insert_post_data` | 标题、文章类型、`wpslug_settings` | 只为空 slug 的新内容自动生成；显式或既有 slug 保留；单篇可禁用 |
| 分类/标签 slug | `pre_insert_term`、`wp_update_term_data` | term 名称、既有 term | 新 term 可生成；更新名称时保留已有 slug |
| 媒体文件名 | `sanitize_file_name` | 上传文件名、扩展名 | 仅新上传；扩展名始终保留；不迁移历史附件 |
| 批量转换 | 后台 bulk action | 选中文章及当前设置 | 显式迁移入口，会改写选中项目；逐项检查 `edit_post` 和启用类型 |
| 拼音 | 本地字典或 WPMind semantic pinyin | 内置映射；可选 WPMind | WPMind 不可用时回退本地拼音 |
| 转写 | `iconv`/`intl` | PHP 扩展 | 不支持字符按本地规则清理 |
| 云翻译 | Google v2、Baidu、WPMind | 固定服务端点/API 凭据 | 同步请求；失败返回原文并继续本地清理，不把密钥写入日志/导出 |
| SEO 清理 | 转换管线 | 停用词、标点、长度、最大词数 | 小写、分隔符、混合内容及截断 |
| 管理设置 | Settings API、admin AJAX | 单站点 options/transients | `manage_options` + nonce；预览和 API 测试同样受能力限制 |
| 更新器 | WordPress update transient | `updates.wenpai.net` | 与 WordPress.org 更新器策略冲突；发布包当前使用自托管更新器 |
| 卸载 | uninstall hook | `wpslug_*` options/transients | 只清当前站点；多站点不遍历全网，不删文章/term/附件 |

主要持久数据为 `wpslug_settings`、统计/错误日志选项及 WPMind 可用性 transient。多站点使用每站点配置，没有 network settings 或 network-wide uninstall。

## 已修复（证据明确）

1. 声明最低 WordPress 6.0/PHP 7.4，但运行时只拦截 WP 5/PHP 7：统一为 WP 6.0/PHP 7.4。
2. 更新器使用 PHP 8 的 `str_starts_with()`：改为 PHP 7.4 可用的 `strpos()`。
3. 全局 `sanitize_title`、`wp_unique_post_slug`、发布状态和重复 term 钩子会在非目标路径触发云转换或二次改写：移除全局/重复钩子，只保留明确写入入口。
4. 自动草稿带显式 `post_name`、term 改名时已有 slug 可能被重写：两条路径均优先保留既有/显式 slug。
5. Google 自动检测错误发送 `source=auto`：自动模式不再传 `source`。
6. WPMind 未列入 translator 支持列表：补充支持、可用性判断，并按当前函数签名测试。
7. 管理 AJAX 只有 nonce：增加 `manage_options`，并规范 `isset`/`wp_unslash`。
8. 调试错误上下文、设置导出可能泄露 Google/Baidu 凭据：递归脱敏；导出排除密钥；密钥输入改为 password。
9. 批量操作缺少逐文章授权：增加 `edit_post` 和启用 post type 检查。
10. uninstall 回调未保证设置类已加载：卸载前显式加载依赖。
11. release workflow 标签发布后可选生产部署：删除部署步骤，仅保留构建/发布；本次未触发。
12. E2E 只有登录页样本：增加真实设置页和 WordPress AJAX 拼音预览；Playwright base URL 可由环境变量覆盖且默认使用一致主机名。

## 安全、性能、兼容和发布结论

- 权限/CSRF：设置页及 AJAX 已验证存在 `manage_options` 和 nonce；批量路径逐项授权。
- SQL：插件未拼接自定义 SQL，使用 WordPress options/posts/terms API。
- SSRF：云服务目标由代码固定，未发现用户可控 URL；但外呼同步超时最高 15 秒，会阻塞保存请求。
- 凭据：已修复日志/导出泄漏；凭据仍明文保存在站点 options，后续应支持常量/过滤器注入和只显示掩码。
- 性能：拼音大字典每个 PHP 请求构建；每次转换写统计 options；同步云调用会增加后台保存延迟及并发竞争。
- 兼容：声明 WP 6.0/PHP 7.4；本轮真实运行只覆盖 WordPress 7.0.3/PHP 8.3.33，最低版本矩阵未覆盖。
- 更新冲突：自托管更新器与 WordPress.org Plugin Check 规则不兼容；`lib/` 中还有未使用的旧 updater vendor，发布工作流虽排除它，但仓库检查仍被污染。
- 发布包：工作流排除 `.git`、`.forgejo`、docs、tests、node、`lib` 等开发内容；尚缺可独立核对的 `readme.txt`/许可证打包验收。

## 验证结果

| 命令 | 退出码/结果 |
|---|---|
| `php tests/php/run.php` | 0，20/20 assertions |
| `npx playwright test --reporter=line` | 0，2/2（WP 7.0.3/PHP 8.3.33） |
| 全部项目 PHP 文件 `php -l` | 0 |
| `git diff --check` | 0 |
| WordPress Plugin Check 2.0.0 | 非零，457 errors / 167 warnings；大量来自仓库 `lib/plugin-update-checker`，也包含自托管更新器策略警告 |
| PHPCS `WordPress-Extra` | 2，10500 errors / 800 warnings，属于需分阶段清理的既有基线 |
| PHPStan | 1；当前没有 WordPress stubs/config，结果以未定义 WP 符号为主，不能算 PASS |
| `npm audit`（official registry） | 1，26 个开发工具链漏洞（18 moderate / 8 high），主要来自 `@wordpress/env`/Playground 传递依赖 |

`wp-env start` 的 CLI 镜像构建受 Alpine `apk add` 网络延迟影响；为取得真实运行样本，本轮只启动该隔离项目已生成的 WordPress/MySQL compose 服务，并完成浏览器验收。没有使用生产站点。

## 未完成项/非 PASS

- Plugin Check、PHPCS、PHPStan、npm audit 仍红；不能发布前忽略。
- PHP 7.4 + WP 6.0 最低版本矩阵、多站点 runtime、卸载 runtime 尚未执行。
- Google/Baidu/WPMind 真实凭据调用未执行；没有把无凭据/零样本写成成功。
- `preserve_media_extension` 设置目前不生效（代码始终安全保留扩展名）；不应为了匹配该开关而生成不可上传文件，建议废弃该 UI。
- 云翻译同步、统计逐次写库、拼音字典重复构建尚未优化。
- WPMind 失败回退虽有行为测试，但后台缺少明确的 provider/fallback 可观测信息。
- `lib/` 旧 updater vendor 尚未删除；因禁止争议性删除，本轮只记录。

## 功能取舍和版本方向

### 1.2.x：保留并收紧

保留本地拼音、新内容自动生成、既有 slug 保护、安全媒体重命名、显式批量迁移和 WPMind 回退。合入前应先处理本轮确定性修复，并把 E2E/行为测试设为必需检查。

### 1.3：精简和迁移安全

- 废弃无效的“移除媒体扩展名”设置；不改变安全默认行为。
- 批量迁移增加 dry-run、变更清单、分批执行和可回滚日志。
- 云翻译改为显式触发或后台队列，展示 provider、错误和 fallback。
- 凭据支持常量/过滤器注入，后台只显示掩码。
- 缓存拼音字典，统计改为聚合/抽样写入。
- 在确认无运行引用后移除旧 updater vendor，明确只保留一种更新机制。

### 后续 1–2 个版本

若云翻译/WPMind 维护成本和实际使用量明显高于本地转换，可把“本地 slug 核心”与“云/WPMind provider”拆成独立组件；在没有使用数据前不擅自删除云翻译或批量迁移能力。

## 交付状态

- [CX] 本地审计分支已有提交。
- [CX] `git push -u origin codex/wpslug-audit-20260809` 被 FeiCode pre-receive hook 拒绝：用户 `wenpai` 无权直接推送该分支；远端提示可使用 `refs/for/...` 创建 PR。本轮未越权创建 PR。


## 第二轮复核（2026-08-09）

- [CX] first-party PHPCS 聚焦安全、nonce、输出、数据库、i18n 和前缀，退出码 0；历史 10500/800 是包含格式和 vendor 的旧宽口径，已由可执行聚焦门槛替代，不声称全仓 WordPress-Extra 零告警。
- [CX] PHPStan 配置 WordPress stubs，指定主文件、core、translator、admin AJAX 和 updater，退出码 0。
- [CX] 发布包 Plugin Check 清除 hidden file、readme、替代函数等错误。FeiCode 自托管 Update URI 和既有 WPSlug 品牌是明确目录政策例外；忽略这两个代码后 0 errors，原始剩余 33 warnings。
- [CX] npm audit 从 2 个 high（第一轮旧快照曾为 26）降至 0；使用 `adm-zip` 0.6.0 override，`@wordpress/env` 11.12.0、Playwright 1.62.1。
- [CX] 行为测试 25/25，E2E 2/2。真实云凭据、最低版本和多站点运行矩阵仍未覆盖。
