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
    // MLFR-321: capture uncaught JS errors so we can assert the sync success
    // handler does not throw "Cannot read properties of undefined".
    const pageErrors = [];
    page.on('pageerror', (err) => pageErrors.push(err.message || String(err)));

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

    // 3) MLFR-321: in sync mode the whole import runs inline in the WS request
    //    (download via ngrok + SCORM validation), which can be slow. On success the
    //    LIVE handler must move the tile to the Added state (View course link)
    //    without throwing a JS error or surfacing a false error banner. We race the
    //    success signal against the error signal so a false-error regression fails
    //    the test instead of being masked by a page reload.
    const viewLink    = actionRegion.locator('a.titus-view-btn');
    const errorBanner = page.locator(`[data-content-id="${contentId}"] [data-region="tile-error"]`);

    const outcome = await Promise.race([
      viewLink.waitFor({ state: 'visible', timeout: 90_000 }).then(() => 'completed'),
      errorBanner.waitFor({ state: 'visible', timeout: 90_000 }).then(() => 'failed'),
    ]).catch(() => 'timeout');

    // MLFR-321 acceptance: a successful sync add must NOT show a false error banner.
    expect(outcome, 'sync add success handler must not show a false error banner (MLFR-321)')
      .toBe('completed');
    await expect(errorBanner, 'no error banner on a successful sync add (MLFR-321)').toBeHidden();

    // MLFR-321 acceptance: no uncaught JS error (e.g. the reported TypeError) on the success path.
    const typeErrors = pageErrors.filter((e) => /undefined|TypeError/i.test(String(e)));
    expect(typeErrors, `no uncaught JS error on sync add success path (MLFR-321): ${typeErrors.join('; ')}`)
      .toHaveLength(0);

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
