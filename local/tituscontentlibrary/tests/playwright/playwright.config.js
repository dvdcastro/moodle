// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright config for the Titus Content Library SMOKE tests.
 *
 * These are the fast, critical-path tests (login, plugin availability, add flow).
 * They live alongside this config as `smoke.*.spec.js`.
 *
 * The richer functional suite (marketplace / settings / manage) lives in
 * `./tests/*.spec.ts` and uses its own `playwright.config.ts`. Run that one with
 * `npx playwright test --config=playwright.config.ts`.
 *
 * Run the smoke suite explicitly with:
 *   npx playwright test --config=playwright.config.js --reporter=list
 */

const BASE_URL = process.env.MOODLE_BASE_URL ?? 'http://localhost:8011';

module.exports = defineConfig({
  // Same directory as this config — smoke specs sit next to it.
  testDir: './',
  // Only pick up the smoke specs; ignore the TS functional suite under ./tests.
  testMatch: /smoke\..*\.spec\.js$/,

  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,

  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
  ],

  use: {
    baseURL: BASE_URL,
    headless: true,
    ignoreHTTPSErrors: true,
    // Screenshots on failure (Task 3 requirement).
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    // Reasonable timeouts (Task 3 requirement).
    actionTimeout: 30_000,
    navigationTimeout: 60_000,
    // Skip the ngrok interstitial if BASE_URL is ever pointed at an ngrok host.
    extraHTTPHeaders: {
      'ngrok-skip-browser-warning': 'true',
    },
  },

  projects: [
    {
      name: 'setup',
      testMatch: /smoke\.setup\.spec\.js$/,
    },
    {
      name: 'chromium',
      // The setup spec belongs to the `setup` project only — don't re-run it here.
      testIgnore: /smoke\.setup\.spec\.js$/,
      use: {
        ...devices['Desktop Chrome'],
        // Reuse the admin session captured by smoke.setup.spec.js.
        storageState: 'auth.json',
        extraHTTPHeaders: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      dependencies: ['setup'],
    },
  ],
});
