const { test, expect } = require('@playwright/test');

async function login(page) {
  await page.goto('/wp-admin/');
  if (/wp-login\.php/.test(page.url())) {
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('password');
    await page.locator('#wp-submit').click();
  }
  await expect(page).toHaveURL(/wp-admin/);
}

async function ensurePluginActive(page) {
  await page.goto('/wp-admin/plugins.php');
  const pluginRow = page.locator('#the-list tr').filter({ hasText: 'WPSlug' }).first();
  await expect(pluginRow).toBeVisible();
  const activateLink = pluginRow.getByRole('link', { name: /^Activate$/i });
  if (await activateLink.count()) {
    await activateLink.click();
    await expect(pluginRow.getByRole('link', { name: /^Deactivate$/i })).toBeVisible();
  }
}

test('WPSlug settings loads and pinyin preview runs through WordPress AJAX', async ({ page }) => {
  await login(page);
  await ensurePluginActive(page);
  await page.goto('/wp-admin/options-general.php?page=wpslug');
  await expect(page.getByRole('heading', { name: /WPSlug Settings/i })).toBeVisible();

  await page.locator('#wpslug-preview-input').fill('文派素格');
  await page.locator('#wpslug-preview-button').click();
  await expect(page.locator('#wpslug-preview-result .result-final')).toHaveText('wen-pai-su-ge');
});
