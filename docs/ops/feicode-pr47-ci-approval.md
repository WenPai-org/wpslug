# WPSlug PR #47 FeiCode CI 审批与验收任务

> [CX] 交给 devops/Board owner 发布。本文本本身不授权审批、合并、release、发布或部署。

## Board 字段

- 系统：`feicode-prod`（P0-prod，`write_policy=board_required`）
- family：`change`（manual review）
- capability：`cluster_orchestration`
- owner/created_by：`devops`
- workspace：`ci-cd`
- task_profile：`pipeline-request`
- priority：`high`
- 仓库：`WenPai-org/wpslug`
- PR：`https://feicode.com/WenPai-org/wpslug/pulls/47`
- expected HEAD：`<FINAL_HEAD>`
- runs：gitleaks=`<RUN_GITLEAKS>`，security=`<RUN_SECURITY>`，wp-plugin-ci=`<RUN_WP_CI>`

## task_text

环境=P0-prod；系统=feicode-prod；write_policy=board_required。只批准 WenPai-org/wpslug PR #47、expected HEAD=<FINAL_HEAD> 对应的 CI runs：gitleaks=<RUN_GITLEAKS>、security=<RUN_SECURITY>、wp-plugin-ci=<RUN_WP_CI>。执行前只读复核 PR 仍 open/unmerged、head SHA 精确相等、三个 run 均为 waiting/need_approval；任一不符立即停止。按 gitleaks→security→wp-plugin-ci 顺序仅批准上述 run，不批准其他 run，不改 workflow/分支保护，不重跑生产凭据任务。真实云凭据测试允许明确 SKIP，禁止注入生产 API key。逐项记录 workflow、event、run URL、批准时间/操作者、所有可见 job/context 的 status/conclusion；前一项失败则停止后续审批并回报失败日志。验收后复核 PR 仍 open/unmerged、HEAD 未变、没有 tag/release/deploy。禁止 merge、release、publish、deploy。result 必填 repo、pr_url、head_sha、run_ids、ci 明细及未发布声明，禁止回传 token。

## 影响与回退

影响：仅改变指定 PR HEAD 的三个 Forgejo Actions run 的审批状态并消耗 runner（capacity=1）；不改变代码、main、tag、release 或生产部署。

回退：Actions 审批通常不可撤销；发现 HEAD/run 不匹配则审批前停止。审批后若异常，只取消指定 run 并保留 PR open，不批准后续 run；不 merge/release/deploy。修复通过新 commit 产生新 run，旧 run 作为审计证据保留。

## 验收

1. PR #47 head 等于 `<FINAL_HEAD>`，`open=true`、`merged=false`。
2. 只批准三个指定 run；每项 run URL、workflow、最终 status/conclusion 和可见 job/context 都有证据。
3. 三项均 success 才报 CI PASS；waiting、pending、零 job 不得报 PASS。真实云凭据项保持有理由的 SKIP。
4. PR 仍未合并，未创建 tag/release，未触发部署或发布。
5. result 包含 repo、PR URL、head、run IDs、验收证据且不含 secret。

## 当前远端基线（不得批准）

PR 当前远端 HEAD `8d724d27de4a5e7d29ac295be54ab1a7732a5b00` 的旧 runs 为 gitleaks 181、security 182、wp-plugin-ci 183，均为 waiting/pending、need_approval=true。它们不包含 1.2.2 候选改动，不得代替最终 HEAD 验收。

Board 任务先将主代理回报的 `<FINAL_HEAD>` 推送到既有分支 `codex/wpslug-audit-20260809`，不得 force-push；随后只读查询新 HEAD 产生的三个 run ID，回填 `<RUN_*>` 后再进入逐项审批。推送前若远端分支已变化，立即停止，不覆盖共享 WIP。
