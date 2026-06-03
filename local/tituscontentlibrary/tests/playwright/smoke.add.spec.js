// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Smoke — add content flow (SYNC mode).
 *
 * With `addmode = sync` (set in m_config_plugins, see TESTING.md), clicking
 * "Add to Moodle" runs the whole import pipeline inline and the tile goes
 * directly to COMPLETED: a green disabled "Added" label plus a "View course"
 * link (a.titus-view-btn -> /course/view.php?id=<courseid>). The tile also gains
 * the `titus-tile--added` class.
 *
 * Requires CLMSIM reachable + a valid licence key, and the plugin tables to be
 * empty enough that at least one tile still offers [data-action="add"].
 *
 * Labels used below are the real lang strings:
 *   addtomoodle = "Add to Moodle", viewcourse = "View course"
 */

const MARKETPLACE_URL = '/local/tituscontentlibrary/index.php';

const waitForMarketplace = async (page) => {
  await page.waitForSelector('[data-region="titus-marketplace"]', { timeout: 30_000 });
  await page.waitForSelector('[data-region="titus-tile"]', { timeout: 30_000 });
  await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {});
};

test.describe('Titus Content Library — add flow (smoke, sync mode)', () => {
  test('add first available tile -> completed -> course has a SCORM activity', async ({ page }) => {
    await page.goto(MARKETPLACE_URL);
    await waitForMarketplace(page);

    // 1) Find the first tile that still offers the primary "Add to Moodle" button.
    //    NOTE: both the idle Add button and the failed-state Retry button carry
    //    data-action="add"; the idle one is .btn-primary, Retry is .btn-warning.
    //    We target the primary Add button specifically.
    const addButton = page.locator('[data-region="titus-tile"] [data-action="add"].btn-primary').first();
    await expect(addButton, 'a primary "Add to Moodle" button must exist (truncate tables if not)')
      .toBeVisible({ timeout: 30_000 });

    const tile = page.locator('[data-region="titus-tile"]').filter({ has: addButton }).first();
    const contentId = await addButton.getAttribute('data-content-id')
      ?? await tile.getAttribute('data-content-id');
    expect(contentId, 'tile content id').toBeTruthy();

    const actionRegion = page.locator(`[data-content-id="${contentId}"] [data-region="tile-action"]`);

    // 2) Click "Add to Moodle".
    await addButton.click();

    // 3) The tile should reach COMPLETED ("View course" link). In sync mode the
    //    whole import runs inline in the WS request (download via ngrok + SCORM
    //    validation), which can be slow; the in-page transition may lag. We wait
    //    for the live transition first, and if it does not surface in time we
    //    reload — the tile then renders from DB-backed state as "Added" +
    //    "View course" (TESTING.md Part 3.4). Either path proves completion.
    const viewLink = actionRegion.locator('a.titus-view-btn');
    try {
      await expect(viewLink).toBeVisible({ timeout: 80_000 });
    } catch {
      await page.goto(MARKETPLACE_URL);
      await waitForMarketplace(page);
    }

    await expect(viewLink, 'tile should reach completed state with a View course link')
      .toBeVisible({ timeout: 30_000 });

    // The completed tile also carries the added class.
    await expect(page.locator(`[data-content-id="${contentId}"]`)).toHaveClass(/titus-tile--added/);

    // 4) Resolve the course URL and navigate to it (link opens in a new tab; we
    //    just read its href and go there in this page).
    const courseHref = await viewLink.getAttribute('href');
    expect(courseHref, 'View course href').toMatch(/\/course\/view\.php\?id=\d+/);

    await page.goto(courseHref);
    await page.waitForLoadState('domcontentloaded');

    // 5) Assert a SCORM activity exists in the course. mod_scorm activity links
    //    point at /mod/scorm/view.php and carry the modtype_scorm class on the
    //    activity li. Accept either signal.
    const scormByModtype = page.locator('li.modtype_scorm, .activity.scorm, [class*="modtype_scorm"]');
    const scormByLink = page.locator('a[href*="/mod/scorm/view.php"]');

    const scormCount = (await scormByModtype.count()) + (await scormByLink.count());
    expect(scormCount, 'course should contain a SCORM activity').toBeGreaterThan(0);
  });
});
