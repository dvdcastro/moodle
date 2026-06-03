// @ts-check
const { test: setup, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Smoke setup — authenticate as admin once and persist the session.
 *
 * Saves Playwright storageState to `auth.json` in this directory; the chromium
 * project (see playwright.config.js) reuses it so the other smoke specs start
 * already logged in.
 */

const ADMIN_USER = process.env.MOODLE_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.MOODLE_ADMIN_PASS ?? 'Admin1234!';

const authFile = path.join(__dirname, 'auth.json');

setup('authenticate as admin and save storage state', async ({ page, context }) => {
  await page.goto('/login/index.php');

  await page.fill('#username', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);

  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login/'), { timeout: 30_000 }),
    page.click('#loginbtn'),
  ]);

  // We must have left the login page (no "Invalid login" error).
  await expect(page.locator('body')).not.toContainText('Invalid login');

  await context.storageState({ path: authFile });
  expect(fs.existsSync(authFile)).toBe(true);
});
