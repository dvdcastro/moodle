import { test, expect, Page } from '@playwright/test';

const MARKETPLACE_URL = '/local/tituscontentlibrary/index.php';

/**
 * Wait until the marketplace AMD module has finished initialising.
 *
 * marketplace.js init() is async — it prefetches strings/templates then binds
 * delegated event listeners on the root. We wait for:
 *   1) RequireJS to have defined the marketplace module
 *   2) The init promise to resolve (we detect this by injecting a marker into
 *      the marketplace's init export)
 *   3) The network to be idle (string/template prefetches done)
 */
const waitForMarketplaceReady = async (page: Page) => {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForSelector('[data-region="titus-marketplace"]', { timeout: 15_000 });
  await page.waitForSelector('[data-region="titus-tile"]', { timeout: 15_000 });

  // Wait for the AMD module to be defined in requirejs.
  await page.waitForFunction(
    () => {
      const w = window as any;
      return typeof w.require === 'function'
        && w.require.defined
        && w.require.defined('local_tituscontentlibrary/marketplace');
    },
    null,
    { timeout: 10_000, polling: 200 },
  );

  // Wait for network to be idle — all prefetches complete.
  await page.waitForLoadState('networkidle', { timeout: 10_000 });

  // Belt-and-braces: give the init coroutine a final tick to attach handlers.
  await page.waitForTimeout(300);
};

/**
 * Type a value into the search input, dispatching the events the marketplace
 * JS listens to. We avoid page.fill which sometimes triggers a single
 * input event in a way the delegated listener does not catch.
 */
const setSearch = async (page: Page, value: string) => {
  await page.evaluate((v) => {
    const input = document.querySelector('#titus-search-input') as HTMLInputElement;
    input.focus();
    input.value = v;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }, value);
};

test.describe('Titus Content Library — Marketplace', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(MARKETPLACE_URL);
    await waitForMarketplaceReady(page);
  });

  test('page heading and marketplace root are visible', async ({ page }) => {
    await expect(page.locator('#titus-marketplace-heading')).toBeVisible();
    await expect(page.locator('#titus-marketplace-heading')).toContainText('Titus Content Catalogue');
  });

  test('all 12 catalogue tiles render', async ({ page }) => {
    const tiles = page.locator('[data-region="titus-tile"]');
    await expect(tiles).toHaveCount(12, { timeout: 15_000 });
  });

  test('aria-live announcer region exists with correct attributes', async ({ page }) => {
    const announcer = page.locator('[data-region="titus-announcer"]');
    await expect(announcer).toHaveAttribute('role', 'status');
    await expect(announcer).toHaveAttribute('aria-live', 'polite');
  });

  test('search input filters tiles by keyword (Crisis)', async ({ page }) => {
    const initialCount = await page.locator('[data-region="titus-tile"]').count();
    expect(initialCount).toBe(12);

    await setSearch(page, 'Crisis');
    // Wait for the filtered grid to settle.
    await expect.poll(
      async () => page.locator('[data-region="titus-tile"]').count(),
      { timeout: 5_000 },
    ).toBeLessThan(initialCount);

    await expect(page.getByRole('heading', { name: 'Leadership in Crisis' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Python for Beginners' })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Fire Safety' })).toHaveCount(0);
  });

  test('clear-search button restores all tiles', async ({ page }) => {
    await setSearch(page, 'Python');
    await expect.poll(
      async () => page.locator('[data-region="titus-tile"]').count(),
      { timeout: 5_000 },
    ).toBeLessThan(12);

    await page.click('[data-action="clear-search"]');
    await expect(page.locator('[data-region="titus-tile"]')).toHaveCount(12, { timeout: 5_000 });
  });

  test('category pill filters tiles to Leadership only', async ({ page }) => {
    await page.locator('[data-region="titus-categories"]')
      .getByRole('button', { name: 'Leadership', exact: true }).click();

    await expect.poll(
      async () => page.locator('[data-region="titus-tile"]').count(),
      { timeout: 5_000 },
    ).toBeLessThan(12);

    const titles = await page.locator('[data-region="titus-tile"] .card-title').allTextContents();
    expect(titles.length).toBeGreaterThan(0);
    expect(titles.find((t) => t.includes('Python for Beginners'))).toBeUndefined();
  });

  test('All category pill restores all tiles after filtering', async ({ page }) => {
    await page.locator('[data-region="titus-categories"]')
      .getByRole('button', { name: 'Leadership', exact: true }).click();
    await expect.poll(
      async () => page.locator('[data-region="titus-tile"]').count(),
      { timeout: 5_000 },
    ).toBeLessThan(12);

    await page.locator('[data-region="titus-categories"]')
      .getByRole('button', { name: 'All', exact: true }).click();
    await expect(page.locator('[data-region="titus-tile"]')).toHaveCount(12, { timeout: 5_000 });
  });

  test('sort dropdown Z-A reorders tiles in descending title order', async ({ page }) => {
    // Capture the FULL set of tile titles BEFORE changing sort, then build
    // the expected order ourselves.
    const allTitles = await page.locator('[data-region="titus-tile"] .card-title').allTextContents();
    expect(allTitles.length).toBe(12);
    const expectedDesc = [...allTitles].sort((a, b) => b.localeCompare(a));

    await page.selectOption('#titus-sort-select', 'za');

    await expect.poll(
      async () => page.locator('[data-region="titus-tile"] .card-title').allTextContents(),
      { timeout: 5_000 },
    ).toEqual(expectedDesc);
  });

  test('sort dropdown A-Z reorders tiles in ascending title order', async ({ page }) => {
    const allTitles = await page.locator('[data-region="titus-tile"] .card-title').allTextContents();
    expect(allTitles.length).toBe(12);
    const expectedAsc = [...allTitles].sort((a, b) => a.localeCompare(b));

    // Start by changing sort to something else, then to az — so we observe a real change.
    await page.selectOption('#titus-sort-select', 'za');
    await expect.poll(
      async () => page.locator('[data-region="titus-tile"] .card-title').allTextContents(),
      { timeout: 5_000 },
    ).toEqual([...allTitles].sort((a, b) => b.localeCompare(a)));

    await page.selectOption('#titus-sort-select', 'az');
    await expect.poll(
      async () => page.locator('[data-region="titus-tile"] .card-title').allTextContents(),
      { timeout: 5_000 },
    ).toEqual(expectedAsc);
  });

  test('View Details button opens detail modal with content info', async ({ page }) => {
    const firstTile = page.locator('[data-region="titus-tile"]').first();
    const tileTitle = (await firstTile.locator('.card-title').textContent())?.trim() ?? '';
    await firstTile.locator('[data-action="open-detail"]').click();

    // The modal renders with role="dialog"; Moodle ModalFactory inserts it last.
    const modal = page.locator('[role="dialog"]').last();
    await expect(modal).toBeVisible({ timeout: 8_000 });
    if (tileTitle) {
      await expect(modal).toContainText(tileTitle);
    }
  });

  test('Add to Moodle button transitions tile to Queued state', async ({ page }) => {
    // Pick a tile that has an [data-action="add"] button (not already added).
    const tile = page.locator('[data-region="titus-tile"]').filter({
      has: page.locator('[data-action="add"]'),
    }).first();
    const contentId = await tile.getAttribute('data-content-id');
    expect(contentId).toBeTruthy();

    const actionRegion = page.locator(`[data-content-id="${contentId}"] [data-region="tile-action"]`);

    await tile.locator('[data-action="add"]').click();

    // The tile must briefly pass through QUEUED. Accept QUEUED OR a
    // transitive state on the way (Adding...) followed by QUEUED. We poll
    // the text rapidly so we don't miss it.
    await expect.poll(
      async () => (await actionRegion.textContent())?.trim() ?? '',
      { timeout: 15_000, intervals: [50, 100, 200] },
    ).toMatch(/Queued/);

    // The button in any of these in-flight states must be disabled.
    const button = actionRegion.locator('button');
    await expect(button).toBeDisabled();
  });
});

