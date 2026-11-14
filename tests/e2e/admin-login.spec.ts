import { test, expect } from '@playwright/test';

test.use({ storageState: 'tests/e2e/.auth/admin.json' });

test('admin dapat mengakses dasbor setelah login dengan state e2e', async ({ page }) => {
  await page.goto('/dashboard');
  await expect(page.locator('h1, h2, h3')).toContainText(/dasbor|dashboard|welcome|selamat/i);
});
