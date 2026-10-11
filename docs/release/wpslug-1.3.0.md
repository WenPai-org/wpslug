# WPSlug 1.3.0 发版记录

日期：2026-10-11  
签名：`[Cursor]`  
HEAD：`c0afaf0`（PR #75 merge）  
Tag：`v1.3.0`（annotated → `c0afaf0`）

## 通道

| 面 | 状态 |
|----|------|
| FeiCode Release | https://feicode.com/WenPai-org/wpslug/releases/tag/v1.3.0 |
| ZIP | `wpslug-1.3.0.zip` + `.sha256`（CI 超时后 `build-candidate` fallback 上传） |
| 云桥 `update-check` | version `1.3.0`，package 非空 → `ready_to_upgrade: true` |
| GitHub mirror | `main`/`v1.3.0` 已对齐；Release https://github.com/WenPai-org/wpslug/releases/tag/v1.3.0 |

核验：`wenpai-plugin-ship verify --version 1.3.0` → `ready_to_upgrade: true`。

## 本版要点

- wenpai-admin-ui 壳：概览 / 设置（简单\|高级）/ 工具；帮助在页头
- `wp slug` CLI；品牌 Lucide feather；插件列表「设置」+ View details
- View details 身份用本地 overlay（Version / Author WPCY.COM / Tested up to 7.2），不信陈旧云桥 info 字段
- Gate3：pill 文案、藏 update-nag、窄屏 select、语义拼音仅心思可用、i18n

## 发版步骤（已执行）

1. Gate 3 人过；Version/Stable/`package.json` → **1.3.0**；changelog 写入 `readme.txt`
2. 提交 → PR #75 → 合 `main`（`main` 受保护，不可直推）
3. `wenpai-plugin-ship ship 1.3.0`（tag + Release；CI zip 超时则本地 `scripts/build-candidate.py` 上传附件）
4. 轮询云桥至 `package` 含 `wpslug-1.3.0.zip`
5. GitHub：`main`/tag 已同步；补建 GitHub Release 并挂同一 ZIP

## 未做（发版后会话）

见 `docs/qa/1.3-after-gate3.md`「发版之后」：图说截图、wpcy.com/slug 教程、E2E 重写。不要走薇晓朵 docs-pipeline。

## 备注

- 云桥 `/plugins/wpslug/info` 仍可能残留旧 author/tested；安装包内 updater overlay 已盖住后台弹窗。
- 1.2.6 曾下架；通道现以 1.3.0 为最高带 zip 的 Release。
