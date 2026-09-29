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
