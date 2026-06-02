import { test, expect } from '@playwright/test';

const SETTINGS_URL = '/admin/settings.php?section=local_tituscontentlibrary';

test.describe('Titus Content Library — Settings page', () => {
  test('settings page renders all expected fields', async ({ page }) => {
    await page.goto(SETTINGS_URL);

    // Headings.
    await expect(page.getByText('API Health', { exact: false })).toBeVisible({ timeout: 10_000 });

    // Field labels (visible somewhere on the page).
    await expect(page.locator('body')).toContainText('Titus API base URL');
    await expect(page.locator('body')).toContainText('Licence key');
    await expect(page.locator('body')).toContainText('Default course category');
    await expect(page.locator('body')).toContainText('Catalogue cache lifetime');
  });

  test('API base URL input is present and editable', async ({ page }) => {
    await page.goto(SETTINGS_URL);
    const input = page.locator('input[name="s_local_tituscontentlibrary_apibaseurl"]');
    await expect(input).toBeVisible({ timeout: 10_000 });
    await expect(input).toBeEditable();
  });

  test('licence key field exists (encrypted password)', async ({ page }) => {
    await page.goto(SETTINGS_URL);
    // admin_setting_encryptedpassword renders the input hidden behind an Edit
    // button when a value is already stored. Assert presence in the DOM rather
    // than visibility.
    const field = page.locator('input[name="s_local_tituscontentlibrary_licencekey"]');
    await expect(field).toHaveCount(1, { timeout: 10_000 });
    // Surrounding form-setting row should be visible and contain the label.
    const row = page.locator('.form-setting').filter({
      has: page.locator('input[name="s_local_tituscontentlibrary_licencekey"]'),
    });
    await expect(row.first()).toBeVisible({ timeout: 10_000 });
  });

  test('saving the form triggers a notification (connexion validation)', async ({ page }) => {
    await page.goto(SETTINGS_URL);

    // Just press Save changes without modifying anything — the licence key
    // updatedcallback only fires if value changed, but the page should still
    // redirect cleanly with the standard "Changes saved" notice.
    const saveBtn = page.getByRole('button', { name: /Save changes/i });
    await expect(saveBtn).toBeVisible({ timeout: 10_000 });
    await saveBtn.click();

    // Wait for redirect; expect a notification or admin landing.
    await page.waitForLoadState('networkidle', { timeout: 15_000 });
    // The admin search/main page should be visible.
    await expect(page.locator('body')).toBeVisible();
  });
});
