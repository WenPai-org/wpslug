# WPSlug 产品方向与 WPMind 计量（后续版本）

> 日期：2026-09-17  
> 范围：WPSlug + WPMind 分工；积分 / BYOK；功能方向  
> 状态：产品拍板备忘，供 1.2.3 之后与下一主版本使用  
> 签名：[Grok代wenpai]  
> 插件仓副本：`WenPai-org/wpslug` → `docs/dev/direction-and-wpmind-billing.md`（随开发分支维护）

---

## 1. 产品定位

WPSlug 的主能力是：**在站内用大模型为标题生成 SEO 友好 slug，以及语义化拼音**。

| 能力 | 角色 |
|------|------|
| WPMind 翻译 → SEO slug | **主路径**：理解标题，收成适合检索、可读的路径 |
| WPMind 语义拼音 | **主路径（中文站）**：按词成音，优于逐字拼音 |
| 本地拼音 / 基础清理 | **兜底**：未装 WPMind、未配置、超时、`WP_Error`、空结果时仍能写出 slug，保存不中断 |

本地拼音不是产品卖点，是断电灯。有 WPMind 且可用时，设置与文案应倾向模型能力（「Recommended」说得通）；不可用时静默回退本地拼音。

Google / 百度是传统机翻管道（自备 key），与 WPMind 统一 LLM 能力面不同；不要把三者写成同一等级的「推荐」。

---

## 2. 计量与积分：放在 WPMind

**账本只在 WPMind。** WPSlug 不实现积分余额、不扣次、不存充值记录。

依据：WPMind 已是站内 AI 核心（provider、路由、Usage、cost-control 预算、api-gateway quota/audit）。WPSlug、以后 Elementor 桥、其它插件共用同一本账，避免每插件一套「本月转换了几次」。

### 2.1 两条通道

| 通道 | 行为 |
|------|------|
| **BYOK**（站点自带 API key） | 走用户 provider → **不扣文派积分**；仍可记用量审计，标 `billable=false` / `payer=byok` |
| **平台接口**（文派提供的 AI 上游） | 走统一网关 → **按次或按 token 折算积分（或预算）扣减**；扣前校验，不足返回 `WP_Error`（如 `wpmind_budget_exceeded` / `wpmind_api_quota`） |

对外可用「积分」包装；对内继续用 Pricing + UsageTracker 做 token/cost。

### 2.2 WPSlug 作为调用方

- 调用 `wpmind_translate` / `wpmind_pinyin` 时传稳定 `context` / `feature`（例如 `wpslug_seo_slug`、`wpslug_semantic_pinyin`），便于按功能差异定价。
- 不存积分、不减余额。
- 收到额度类错误：提示去 WPMind 充值或改填自己的 key，同时 **本地拼音兜底**，保存不中断。

### 2.3 拍板三句

1. 账本只在 WPMind（全站 AI 共用）。  
2. BYOK 免积分，平台接口才扣。  
3. WPSlug 是计费感知的调用方，不是计费系统。

---

## 3. 写路径纪律（与 1.2.3 同源）

主路径是模型时，更要管住自动保存：

- auto-draft 占位标题（「自动草稿」/ Auto Draft）**不要**送进模型或本地拼音锁死 slug（见 1.2.3 / PR #60）。
- 真标题首次落成或发布时再转换。
- 已发布且用户手写的 slug 默认不覆盖；批量转换是显式迁移。
- 模型失败必须快速回退本地拼音，不能拖垮保存。

---

## 4. 功能方向（现行 → 后续）

### 4.1 近期（1.2.x）

- 合入并发布 **1.2.3**：CPT auto-draft 占位 slug 冻结；更新 UI 链接 `wpcy.com/slug`；客户端丢掉坏 svg 键的逻辑可与资产侧 200 并存。
- 文案对齐定位：有 WPMind 时突出「SEO slug / 语义拼音」；本地拼音写明「离线或失败时使用」。（本 PR 已改设置标签与 WPMind 区块）
- 额度类 `WP_Error` 的用户可见提示（指向 WPMind 设置 / 充值）。（本 PR：`recordWpmindQuotaNotice` + admin notice）
- 调用 context：`wpslug_seo_slug` / `wpslug_semantic_pinyin`。（本 PR）
- 默认 `convert_on_publish_only`：草稿不打模型，离开 auto-draft 仍清占位 slug；批量转换提示为显式迁移。（本 PR）
- CI：mock 成功、`WP_Error`、额度错误、未安装 WPMind；真实密钥继续 SKIP。

### 4.2 下一主版本（方向）

- **WPMind**：平台通道 vs BYOK 在路由里标清；`translate` / `pinyin` 接同一套扣减与预算；站长可见余额 / 本月用量。
- **WPSlug**：按 feature 传 context；处理额度错误文案；可选「仅发布时转换」减轻自动保存打模型。
- Google / 百度 key 管道可降为高级折叠或可选模块；**WPMind 软依赖留在主包可见能力**，不要藏进「高级才看见」。
- 媒体 MD5 等旁支仍按既有拍板可拆可选模块；与 AI 主路径分开。

### 4.3 明确不做（除非另拍板）

- 在 WPSlug 内自建积分表 / 充值 / 套餐。  
- 无 WPMind 时假装有 LLM slug。  
- 为测绿使用生产 WPMind / 云密钥。  
- 把 Update URI 改成产品页域名（更新检查主机名必须仍是 `updates.wenpai.net`）。

---

## 5. 相关

- PR：`https://feicode.com/WenPai-org/wpslug/pulls/60`（1.2.3 候选）  
- 集群记忆：#8339（CPT auto-draft 拼音冻结）  
- WPMind：Usage、`modules/cost-control`、`modules/api-gateway`；公共 API `wpmind_translate` / `wpmind_pinyin` / `wpmind_is_available`  
- 既有拍板史料：`docs/ai-context/wenpai-plugin-release-closeout-2026-08-11.md`（本地拼音核心 vs 云能力拆分——**已被本节产品口径修正**：本地拼音改为兜底，WPMind 为主路径）
