import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.use({ storageState: 'tests/e2e/.auth/admin.json' });

test('proposals index loads and shows the create button', async ({ page }) => {
  await page.goto('/proposals');

  await expect(page.locator('h2, h1')).toContainText(/proposal/i);
  await expect(page.getByRole('link', { name: /buat proposal/i })).toBeVisible();
});

test('proposals menu appears in sidebar navigation', async ({ page }) => {
  await page.goto('/dashboard');
  await expect(page.getByRole('navigation').getByText('Proposal')).toBeVisible();
});

test('page passes basic accessibility scan on proposals index', async ({ page }) => {
  await page.goto('/proposals');
  const results = await new AxeBuilder({ page }).analyze();
  const serious = results.violations.filter(
    (v) => ['serious', 'critical'].includes((v.impact ?? '').toLowerCase()),
  );
  expect(serious).toEqual([]);
});
