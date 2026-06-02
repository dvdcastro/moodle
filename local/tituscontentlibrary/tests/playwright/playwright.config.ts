import { defineConfig, devices } from '@playwright/test';

export const BASE_URL = process.env.MOODLE_BASE_URL
  ?? 'https://terrorless-keli-unhampered.ngrok-free.dev';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  use: {
    baseURL: BASE_URL,
    headless: true,
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    extraHTTPHeaders: {
      'ngrok-skip-browser-warning': 'true',
    },
  },
  projects: [
    {
      name: 'setup',
      testMatch: /global\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'storage/admin.json',
        extraHTTPHeaders: {
          'ngrok-skip-browser-warning': 'true',
        },
      },
      dependencies: ['setup'],
    },
  ],
});
