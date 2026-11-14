# B2B Outreach CRM

Platform internal untuk mengelola prospek B2B: import & validasi data, segmentasi, consent/suppression, komunikasi email (Gmail API) dan WhatsApp (Evolution API + Baileys), sales pipeline, follow-up otomatis, dan analytics.

Sistem ini dirancang sebagai **modular monolith** — satu aplikasi Laravel yang tetap memungkinkan menjadi SaaS di masa depan.

## Ringkasan Fitur

| Modul | Status | Keterangan |
|-------|--------|------------|
| Authentication & RBAC | ✅ Selesai | Login, logout, reset password, role-based access |
| User Management | ✅ Selesai | CRUD user, assign role |
| Companies & Contacts | ✅ Selesai | CRUD perusahaan dan kontak dengan normalisasi telepon E.164 |
| Lead Management | ✅ Selesai | CRUD lead, transisi status, assignment, timeline aktivitas |
| Import CSV/XLSX | ✅ Selesai | Upload, validasi, deduplikasi, preview, hasil per baris |
| Consent & Suppression | ✅ Selesai | Append-only consent records, suppression list global |
| Communication Eligibility | ✅ Selesai | Gate default-deny sebelum setiap komunikasi |
| Email (Gmail API) | ✅ Selesai inti | OAuth connect, draft email, approval workflow |
| WhatsApp (Evolution API) | ✅ Selesai inti | Webhook inbound, draft pesan, mode manual outbound |
| Campaigns | ✅ Selesai inti | Buat campaign, approval, generate draft |
| Sales Pipeline & Deals | ✅ Selesai inti | Pipeline stages, board deals, stage history |
| Tasks | ✅ Selesai inti | CRUD task, due date, overdue detection |
| Analytics | ✅ Selesai inti | Dashboard ringkasan lead/deal/aktivitas |
| Automation Engine | ⏳ T7 | Rule-based automation (belum dimulai) |
| Analytics Full | ⏳ T8 | KPI lengkap, export CSV/PDF (versi inti selesai) |

## Stack Teknologi

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | PHP + Laravel | 8.3.x / ^13.0 |
| Frontend | Livewire + Blade + Alpine.js | 4.4.8 |
| CSS | Tailwind CSS | ^4.0.0 |
| Database | PostgreSQL | 16.x |
| Cache/Queue | Redis | 7.x |
| Testing | Pest + PHPUnit | 4.7 / ^12.5 |
| E2E | Playwright | ^1.65.1 |
| Static Analysis | PHPStan + Larastan | latest |
| Formatter | Laravel Pint | ^1.27 |

### Dependencies Utama

- `spatie/laravel-permission` — RBAC
- `google/apiclient` — Gmail API
- `openspout/openspout` — Import XLSX/CSV hemat memori
- `giggsey/libphonenumber-for-php` — Normalisasi nomor telepon ke E.164
- `predis/predis` — Redis client (PHP lokal)
- `pestphp/pest` — Test framework

## Arsitektur

```
app/
├── Domain/
│   ├── Identity/          → Auth, User, Role, Permission
│   ├── Companies/         → Company model
│   ├── Contacts/          → Contact model
│   ├── Leads/             → Lead, Activity, ImportBatch, ImportRow
│   ├── Consent/           → ConsentRecord, SuppressionEntry, Eligibility gate
│   ├── Campaigns/         → Campaign, EmailTemplate, CampaignRecipient
│   ├── Communications/    → GmailProvider, EvolutionClient, Message service
│   ├── Deals/             → Deal, Pipeline, PipelineStage
│   ├── Tasks/             → Task model
│   └── Audit/             → AuditLog
├── Http/
│   ├── Controllers/       → Gmail OAuth, Evolution webhook
│   ├── Middleware/        → AuthenticateWithRoles
│   └── Livewire/          → Semua halaman UI
└── Jobs/                  → Async jobs (import, campaign, webhook)
```

## Prinsip Keamanan

1. **Consent default-deny** — Komunikasi tidak bisa dikirim tanpa izin yang valid
2. **Draft-first email** — Semua email lewat draft + review manusia
3. **WhatsApp manual** — Outbound mode tetap `manual`, tidak ada blast otomatis
4. **Append-only consent** — Riwayat consent tidak bisa dihapus/diubah (database trigger)
5. **Global suppression** — Opt-out berlaku universal, bukan per campaign
6. **Webhook idempoten** — Duplicate webhook diproses sekali via `provider_events`

## Setup Lokal

### Requirements

- PHP 8.3+
- Composer 2.x
- Node.js 22.x
- PostgreSQL 16+
- Redis 7+

### Instalasi

```bash
# Clone repository
git clone https://github.com/Amrl666/CRM-INTERNAL.git
cd crm-internal

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env
php artisan key:generate

# Jalankan database lokal (PostgreSQL + Redis via Docker)
docker compose up -d

# Run migrations & seeders
php artisan migrate --seed

# Build frontend
npm run build

# Jalankan development server
npm run dev
```

Akses aplikasi di `http://localhost:8000`.

### Default Users (seeding)

| Email | Password | Role |
|-------|----------|------|
| admin@example.com | password | admin |
| manager@e2e.test | password | sales_manager |
| agent@e2e.test | password | sales_agent |
| viewer@e2e.test | password | viewer |

## Testing

```bash
# Jalankan semua test
php artisan test

# Format code
php vendor/bin/pint

# Static analysis
php vendor/bin/phpstan analyse

# E2E test
npx playwright test
```

## Environment Configuration

Lihat `.env.example` untuk daftar lengkap variabel. Yang perlu dikonfigurasi:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=crm_internal
DB_USERNAME=crm_local
DB_PASSWORD=crm_pass

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PREFIX=crm_internal_local_

# Email (optional - default fake)
EMAIL_PROVIDER=fake          # gmail | fake
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/integrations/google/callback

# WhatsApp (optional - default fake)
WHATSAPP_PROVIDER=fake       # evolution | fake
WHATSAPP_OUTBOUND_MODE=manual
EVOLUTION_API_URL=
EVOLUTION_API_KEY=
```

Untuk testing: gunakan `EMAIL_PROVIDER=fake` dan `WHATSAPP_PROVIDER=fake`.

## Struktur Database

Tabel utama:
- `users` — Akun pengguna
- `companies` — Perusahaan/prospek
- `contacts` — Kontak person
- `leads` — Prospek aktif dengan status lifecycle
- `consent_records` — Riwayat consent (append-only)
- `suppression_entries` — List suppression global
- `campaigns` — Kampanye komunikasi
- `campaign_recipients` — Target per kampanye
- `communication_messages` — Riwayat pesan (email & WhatsApp)
- `deals` — Peluang penjualan
- `pipelines`, `pipeline_stages` — Konfigurasi pipeline
- `tasks` — Tugas sales
- `activities` — Timeline aktivitas
- `audit_logs` — Riwayat perubahan sistem

Migration lengkap tersedia di `database/migrations/`.

## API Endpoints

API prefix: `/api/v1`

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/auth/login` | Login |
| GET | `/leads` | Daftar leads |
| POST | `/leads/import` | Import CSV/XLSX |
| POST | `/integrations/google/callback` | OAuth callback Gmail |
| POST | `/api/v1/webhooks/evolution` | Webhook masuk WhatsApp |
| GET | `/dashboard` | Overview metrics |

Full endpoint list lihat `Agent.md` §8.

## Roadmap Implementasi

| Tahap | Nama | Status |
|-------|------|--------|
| T0 | Fondasi proyek | ✅ Selesai |
| T1 | Companies, Contacts, Leads, Import | ✅ Selesai |
| T2 | Consent, Suppression, Eligibility | ✅ Selesai |
| T3 | Email (Gmail, draft-first) | ✅ Selesai inti |
| T4 | WhatsApp (Evolution, inbound + manual) | ✅ Selesai inti |
| T5 | Campaign (email approval flow) | ✅ Selesai inti |
| T6 | Sales Pipeline, Deals, Tasks | ✅ Selesai inti |
| T7 | Automation Engine | ⏳ Ditunda |
| T8 | Analytics & Reporting | ✅ Selesai inti |
| T9 | Hardening & Deploy Readiness | 🔄 Berjalan |
| T10 | Proposal Outreach Campaign | ⏳ Ditunda |

## Deployment

Target server: **LAB-01** (Ubuntu 24.04, PostgreSQL 16, Redis 7, Nginx + PHP-FPM).

Deploy dilakukan secara manual bertahap dari tag Git. See `Agent.md` §20 untuk runbook lengkap.

## Dokumentasi

- `Agent.md` — Dokumentasi teknis lengkap (target arsitektur, spesifikasi modul, keamanan)
- `docs/design/DESIGN.md` — Design system tokens & komponen
- `docs/decisions/STATUS.md` — Status implementasi terkini

## License

MIT
