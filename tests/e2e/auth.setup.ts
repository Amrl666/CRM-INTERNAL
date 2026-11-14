import { test as setup } from '@playwright/test';
import { join } from 'node:path';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

const ROOT = process.cwd();
const STORAGE_DIR = join(ROOT, 'tests', 'e2e', '.auth');
const BASE_URL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8001';

const roles: Array<{ key: string; email: string }> = [
  { key: 'admin', email: 'admin@example.com' },
  { key: 'manager', email: 'manager@e2e.test' },
  { key: 'agent', email: 'agent@e2e.test' },
  { key: 'viewer', email: 'viewer@e2e.test' },
];

for (const role of roles) {
  setup(`authenticate ${role.key}`, async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto(`${BASE_URL}/login`);
    await page.getByLabel('Email').fill(role.email);
    await page.getByLabel('Kata sandi').fill('password');
    await page.getByRole('button', { name: /masuk|login|masukk/i }).click();
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 });

    const path = join(STORAGE_DIR, `${role.key}.json`);
    const state = await context.storageState();
    writeFileSync(path, JSON.stringify(state));
    await context.close();
  });
}
