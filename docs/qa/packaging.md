# WPSlug 发版打包

运行时更新器是 `includes/class-wenpai-updater.php`（`WPSlug_Updater` → `https://updates.wenpai.net/api/v1`）。不要把 Yahnis Elsts `plugin-update-checker` 或 `updatepulse-updater` 打进 zip。这两棵目录可以留在 git 里当史料，打包白名单不收。

```bash
python3 scripts/build-candidate.py --version 1.2.6
# 产出 gitignore 的 dist/wpslug-<ver>-candidate.zip
```

白名单：`assets/` `includes/` `languages/` `lib/wenpai-admin-ui/` `templates/`，外加 `readme.txt` `wpslug.php`。

故意不收：

- `lib/plugin-update-checker/`
- `lib/updatepulse-updater/`
- kit 的 `phpcs.xml.dist` 和 `scripts/wenpai-admin-lint.py`

脚本会拒绝 zip 里出现前两棵前缀，并要求 kit 与 `templates/admin/page.php` 在包内。zip 按 **git HEAD** 打，先提交再打包。

Plugin Check：`bash scripts/plugin-check.sh wpslug --path=<WP根>`。需要 Plugin Check 2.x 已启用。`--exclude-directories` 含 `lib`，所以 kit / 旧 vendor 不进那道门；干净与否以 zip 白名单为准。


## 发版（FeiCode + 云桥 + GitHub）

`write_gate=write`（`configs/wenpai-products.yml`）。在 **resident** `~/Projects/wpslug` 的 `main`（含目标 Version）执行：

```bash
# 版本字段一致后
wenpai-plugin-ship verify --version 1.3.0
wenpai-plugin-ship ship 1.3.0
# 结案：ready_to_upgrade true（package 非空且版本匹配）
```

说明：

- `ship` 打 annotated tag `v<ver>`、推 FeiCode、等 CI ZIP；超时则跑本目录 `scripts/build-candidate.py` 并上传 Release 附件。
- 云桥推的是 **最高 semver 且带 zip 的 Release**，不是 `main` tip。
- `main` 受保护：发版提交走 PR 合入，再 ship。
- GitHub 为镜像：`git fetch origin && git push github main --tags`（或确认已同步后）用同一 ZIP 建 GitHub Release。
- 详情身份（Author / Tested up to）以插件头 + `readme.txt` 为准；updater `overlay_local_plugin_info` 盖住陈旧云桥 info。

1.3.0 实录：`docs/release/wpslug-1.3.0.md`。
