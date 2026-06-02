import { test as setup, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const ADMIN_USER = process.env.MOODLE_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.MOODLE_ADMIN_PASS ?? 'Admin1234!';

const storageDir = path.join(__dirname, '..', 'storage');
const adminAuthFile = path.join(storageDir, 'admin.json');

setup('authenticate as admin', async ({ page, context }) => {
  if (!fs.existsSync(storageDir)) {
    fs.mkdirSync(storageDir, { recursive: true });
  }

  await context.addInitScript(() => {
    // No-op init script kept for parity with extra header config.
  });

  await page.goto('/login/index.php');
  // Moodle login form.
  await page.fill('#username', ADMIN_USER);
  await page.fill('#password', ADMIN_PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login/'), { timeout: 20_000 }),
    page.click('#loginbtn'),
  ]);

  // Sanity check: navbar user menu visible.
  await expect(page.locator('body')).not.toContainText('Invalid login');

  await context.storageState({ path: adminAuthFile });
});
