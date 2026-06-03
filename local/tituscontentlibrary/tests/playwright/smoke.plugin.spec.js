// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Smoke — plugin availability.
 *
 * Verifies the marketplace and settings pages load, the Titus API connection
 * works (tiles render rather than an availability error), and at least one tile
 * is present.
 *
 * Requires the CLMSIM simulator to be reachable at the configured API base URL
 * (https://titusclmsim.ngrok.app/api) with a valid licence key — see TESTING.md.
 */

const MARKETPLACE_URL = '/local/tituscontentlibrary/index.php';
const SETTINGS_URL = '/admin/settings.php?section=local_tituscontentlibrary';

// Real lang strings from lang/en/local_tituscontentlibrary.php.
const CATALOGUE_HEADING = 'Titus Content Catalogue';
const SETTINGS_HEADING = 'Titus API configuration';
const CATALOGUE_UNAVAILABLE = 'The Titus content catalogue is currently unavailable';

test.describe('Titus Content Library — plugin availability (smoke)', () => {
  test('admin can reach the marketplace page', async ({ page }) => {
    const response = await page.goto(MARKETPLACE_URL);
    expect(response?.status(), 'marketplace HTTP status').toBeLessThan(400);

    // The marketplace root region and its heading must be present.
    await expect(page.locator('[data-region="titus-marketplace"]')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('#titus-marketplace-heading')).toContainText(CATALOGUE_HEADING);
  });

  test('plugin settings page loads', async ({ page }) => {
    const response = await page.goto(SETTINGS_URL);
    expect(response?.status(), 'settings HTTP status').toBeLessThan(400);

    // The settings page renders the Titus API configuration heading.
    await expect(page.locator('body')).toContainText(SETTINGS_HEADING);
    // The API base URL field is present.
    await expect(page.locator('#id_s_local_tituscontentlibrary_apibaseurl')).toBeVisible();
  });

  test('API connection works — no availability error on the marketplace', async ({ page }) => {
    await page.goto(MARKETPLACE_URL);
    await page.waitForSelector('[data-region="titus-marketplace"]', { timeout: 30_000 });
    await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {});

    // The red "catalogue unavailable" alert must NOT be present.
    await expect(page.locator('body')).not.toContainText(CATALOGUE_UNAVAILABLE);
  });

  test('at least one tile is rendered', async ({ page }) => {
    await page.goto(MARKETPLACE_URL);
    await page.waitForSelector('[data-region="titus-marketplace"]', { timeout: 30_000 });

    // The tile element is <article class="titus-tile" data-region="titus-tile">.
    const tiles = page.locator('.titus-tile');
    await expect(tiles.first()).toBeVisible({ timeout: 30_000 });
    expect(await tiles.count(), 'rendered tile count').toBeGreaterThan(0);
  });
});
