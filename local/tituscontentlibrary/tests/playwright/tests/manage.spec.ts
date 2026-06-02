import { test, expect } from '@playwright/test';

const MANAGE_URL = '/local/tituscontentlibrary/manage.php';

test.describe('Titus Content Library — Manage page', () => {
  test('manage page loads and shows the heading', async ({ page }) => {
    await page.goto(MANAGE_URL);
    await expect(page.locator('h2, h1').filter({ hasText: /Manage|Added/i }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('manage page renders the added-content table', async ({ page }) => {
    await page.goto(MANAGE_URL);
    // The Moodle table_sql renders an HTML table.
    const table = page.locator('table.generaltable, table#titus-manage-table, .generaltable');
    await expect(table.first()).toBeVisible({ timeout: 10_000 });
  });

  test('manage page shows at least one added row with a remove action', async ({ page }) => {
    await page.goto(MANAGE_URL);

    // Wait for the table.
    const table = page.locator('table').first();
    await expect(table).toBeVisible({ timeout: 10_000 });

    // Body rows (excluding header).
    const rows = page.locator('table tbody tr');
    const count = await rows.count();

    if (count === 0) {
      test.skip(true, 'No added content yet — run smoke_test_03_add_pipeline.php first');
    }

    // First row should have at least one action button or link.
    const firstRowActions = rows.first().locator('a, button');
    const actionCount = await firstRowActions.count();
    expect(actionCount).toBeGreaterThan(0);

    // Should include a remove action.
    const removeLink = rows.first().locator('a').filter({ hasText: /remove/i }).first();
    await expect(removeLink).toBeVisible();
  });

  test('completed rows expose a resync button', async ({ page }) => {
    await page.goto(MANAGE_URL);
    const resyncButtons = page.locator('button[data-action="resync"]');
    const count = await resyncButtons.count();

    if (count === 0) {
      test.skip(true, 'No completed rows present — pipeline may not have finished');
    }

    // Verify the first resync button has a data-content-id.
    const first = resyncButtons.first();
    const cid = await first.getAttribute('data-content-id');
    expect(cid).toBeTruthy();
  });

  test('Back to catalogue link is present', async ({ page }) => {
    await page.goto(MANAGE_URL);
    const catalogueLink = page.getByRole('link', { name: /Titus Content Catalogue/i }).first();
    await expect(catalogueLink).toBeVisible({ timeout: 10_000 });
  });
});
