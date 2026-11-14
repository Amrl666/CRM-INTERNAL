import { execSync } from 'node:child_process';
import { readFileSync, existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const requiredEnvVars = [
  'APP_ENV',
  'DB_DATABASE',
  'REDIS_PREFIX',
  'EMAIL_PROVIDER',
  'WHATSAPP_PROVIDER',
  'WHATSAPP_OUTBOUND_MODE',
];

export function validateE2eEnvironment(): void {
  const missing = requiredEnvVars.filter((key) => process.env[key] === undefined || process.env[key] === '');

  if (missing.length > 0) {
    throw new Error(
      `[Playwright E2E] Environment variables yang wajib tersedia tidak ditemukan pada saat menjalankan Playwright:\n  - ${missing.join('\n  - ')}`
    );
  }

  if (String(process.env.APP_ENV) !== 'e2e') {
    throw new Error('[Playwright E2E] APP_ENV harus bernilai "e2e". Jangan jalankan E2E di lingkungan development lokal.');
  }

  const database = String(process.env.DB_DATABASE);
  if (!database.endsWith('_e2e')) {
    throw new Error(
      `[Playwright E2E] DB_DATABASE harus berakhiran "_e2e" agar database development tidak ikut terpengaruh. Nilai saat ini: "${database}".`
    );
  }

  const prefix = String(process.env.REDIS_PREFIX);
  if (!prefix.includes('e2e')) {
    throw new Error(
      `[Playwright E2E] REDIS_PREFIX harus mengandung suffix "e2e" untuk mengisolasi cache/session. Nilai saat ini: "${prefix}".`
    );
  }

  if (String(process.env.EMAIL_PROVIDER) !== 'fake') {
    throw new Error('[Playwright E2E] EMAIL_PROVIDER harus "fake" selama pengujian browser otomatis.');
  }

  if (String(process.env.WHATSAPP_PROVIDER) !== 'fake') {
    throw new Error('[Playwright E2E] WHATSAPP_PROVIDER harus "fake" selama pengujian browser otomatis.');
  }

  if (String(process.env.WHATSAPP_OUTBOUND_MODE) !== 'manual') {
    throw new Error(
      '[Playwright E2E] WHATSAPP_OUTBOUND_MODE harus tetap manual. Pengiriman WhatsApp otomatis dilarang oleh spesifikasi keamanan CRM.'
    );
  }
}

interface SetupStateOptions {
  roles: string[];
  password?: string;
  outputDir: string;
}

interface AuthFixture {
  email: string;
  password: string;
}

export async function prepareE2eAuthStates({ roles, password = 'password', outputDir }: SetupStateOptions): Promise<void> {
  const fixtures: Record<string, AuthFixture> = {
    admin: { email: 'admin@example.com', password },
    manager: { email: 'manager@e2e.test', password },
    agent: { email: 'agent@e2e.test', password },
    viewer: { email: 'viewer@e2e.test', password },
  };

  const baseStatePath = join(outputDir, '.auth');
  const finalState = [];

  for (const role of roles) {
    const fixture = fixtures[role];
    if (!fixture) {
      continue;
    }

    const path = join(baseStatePath, `${role}.json`);

    let state = { cookies: [], origins: [] };
    if (existsSync(path)) {
      try {
        state = JSON.parse(readFileSync(path, 'utf8'));
      } catch {
        // Biarkan state baru ditulis ulang
      }
    }

    finalState.push({ role, path, fixture, state });
  }

  return {
    persist: (updates: Array<{ role: string; payload: { cookies: unknown[]; origins: unknown[] } }>) => {
      for (const update of updates) {
        const record = finalState.find((item) => item.role === update.role);
        if (record) {
          writeFileSync(record.path, JSON.stringify(update.payload));
        }
      }
    },
    defaults: () =>
      finalState.map(({ role, path, state }) => ({
        role,
        path,
        use: { storageState: path },
      })),
  };
}
