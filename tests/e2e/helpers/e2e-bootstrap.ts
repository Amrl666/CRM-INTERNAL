import { execSync } from 'node:child_process';
import { writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const ENV_FILE = join(ROOT, '.env.e2e');
const STORAGE_DIR = join(ROOT, 'tests', 'e2e', '.auth');

interface RoleFixture {
  email: string;
  password: string;
  storagePath: string;
}

function validateE2eEnvironment(): void {
  if (process.env.APP_ENV !== 'e2e') {
    throw new Error(
      '[Playwright E2E] GAGAL VALIDASI: APP_ENV harus bernilai "e2e" agar test tidak berjalan di lingkungan development lokal.'
    );
  }

  const db = String(process.env.DB_DATABASE ?? '');
  if (!db.endsWith('_e2e')) {
    throw new Error(
      `[Playwright E2E] GAGAL VALIDASI: DB_DATABASE seharusnya berakhiran "_e2e". Ditemukan "${db}". Jangan biarkan test menyentuh database development.`
    );
  }

  const redisPrefix = String(process.env.REDIS_PREFIX ?? '');
  if (!redisPrefix.includes('e2e')) {
    throw new Error(
      `[Playwright E2E] GAGAL VALIDASI: REDIS_PREFIX tidak mengandung suffix e2e ("${redisPrefix}"). Cache/session harus terisolasi.`
    );
  }

  if (String(process.env.EMAIL_PROVIDER) !== 'fake') {
    throw new Error('[Playwright E2E] EMAIL_PROVIDER wajib "fake" selama pengujian browser otomatis.');
  }

  if (String(process.env.WHATSAPP_PROVIDER) !== 'fake') {
    throw new Error('[Playwright E2E] WHATSAPP_PROVIDER wajib "fake" selama pengujian browser otomatis.');
  }

  if (String(process.env.WHATSAPP_OUTBOUND_MODE) !== 'manual') {
    throw new Error('[Playwright E2E] WHATSAPP_OUTBOUND_MODE wajib tetap manual sesuai ketentuan keamanan CRM.');
  }
}

function ensureStorageDirectory(): void {
  if (!existsSync(STORAGE_DIR)) {
    mkdirSync(STORAGE_DIR, { recursive: true });
  }
}

function bootstrapEnvironment(): void {
  if (!existsSync(ENV_FILE)) {
    throw new Error(`[Playwright E2E] File environment .env.e2e tidak ditemukan di ${ENV_FILE}. Lengkapi dulu environment isolasi ini.`);
  }

  console.log('[Playwright E2E] Memverifikasi environment dan migrasi database...');

  try {
    execSync('php artisan migrate --env=e2e --force', { stdio: 'inherit', cwd: ROOT });
    console.log('[Playwright E2E] Migrasi database E2E selesai.');
  } catch (err) {
    console.error('[Playwright E2E] Gagal menjalankan migrasi E2E.');
    throw err;
  }

  try {
    execSync('php artisan db:seed --env=e2e --class=\\Database\\Seeders\\E2eUsersSeeder --force', { stdio: 'inherit', cwd: ROOT });
    console.log('[Playwright E2E] Seeder pengguna E2E selesai.');
  } catch (err) {
    console.error('[Playwright E2E] Gagal menjalankan seeder E2eUsersSeeder.');
    throw err;
  }
}

async function init(): Promise<void> {
  validateE2eEnvironment();
  ensureStorageDirectory();
  bootstrapEnvironment();
}

void init();

export type { RoleFixture };
export { validateE2eEnvironment, ensureStorageDirectory, bootstrapEnvironment };
