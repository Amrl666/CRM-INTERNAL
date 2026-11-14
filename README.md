# Prospectra

Platform internal untuk mengelola prospek B2B: import & validasi data, segmentasi, consent/suppression, komunikasi email (Gmail API) dan WhatsApp (Evolution API + Baileys), sales pipeline, follow-up, dan analytics.

Sistem ini dirancang sebagai **modular monolith** — satu aplikasi Laravel yang tetap memungkinkan menjadi SaaS di masa depan.

## Ringkasan Fitur

| Modul | Status | Keterangan |
|-------|--------|------------|
| Authentication & RBAC | Selesai | Login, logout, reset password, role-based access |
| User Management | Selesai | CRUD user, assign role |
| Companies & Contacts | Selesai | CRUD perusahaan dan kontak dengan normalisasi telepon E.164 |
| Lead Management | Selesai | CRUD lead, transisi status, assignment, timeline aktivitas |
| Import CSV/XLSX | Selesai | Upload, validasi, deduplikasi, preview, hasil per baris |
| Consent & Suppression | Selesai | Append-only consent records, suppression list global |
| Communication Eligibility | Selesai | Gate default-deny sebelum setiap komunikasi |
| Email (Gmail API) | Selesai inti | OAuth connect, draft email, approval workflow |
| WhatsApp (Evolution API) | Selesai inti | Webhook inbound, draft pesan, mode manual outbound |
| Campaigns (Email) | Selesai inti | Buat campaign, approval, generate draft |
| Proposal Outreach Campaign | Selesai | PDF proposal per penerima, scheduling, delivery, idempotency |
| Sales Pipeline & Deals | Selesai inti | Pipeline stages, board deals, stage history |
| Tasks | Selesai inti | CRUD task, due date, overdue detection |
| Analytics | Selesai inti | Dashboard KPI, distribusi lead/deal |
| Automation Engine | Belum | Rule-based automation (T7) |

## Stack Teknologi

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | PHP + Laravel | 8.3.x / 13.x |
| Frontend | Livewire + Blade + Alpine.js | 4.4.8 |
| CSS | Tailwind CSS | 4.x |
| Database | PostgreSQL | 16.x |
| Cache/Queue | Redis | 7.x |
| Testing | Pest + PHPUnit | 4.x / 12.x |
| E2E | Playwright | 1.65.x |
| Static Analysis | PHPStan + Larastan | level 5 |
| Formatter | Laravel Pint | 1.x |

## Arsitektur

```
app/
├── Domain/
│   ├── Identity/          → Auth, User, Role, Permission
│   ├── Companies/         → Company model
│   ├── Contacts/          → Contact model
│   ├── Leads/             → Lead, Activity, ImportBatch, ImportRow
│   ├── Consent/           → ConsentRecord, SuppressionEntry, Eligibility gate
│   ├── Campaigns/         → Campaign, ProposalTemplate, MessageTemplate, CampaignRecipient
│   ├── Communications/    → EmailProvider, WhatsAppProvider, CommunicationManager
│   ├── Sales/             → Deal, Pipeline, PipelineStage
│   ├── Tasks/             → Task model
│   ├── Audit/             → AuditLog
│   └── Analytics/         → KPI aggregation
├── Http/Controllers/      → API controllers
├── Livewire/              → Livewire components (UI)
├── Jobs/                  → Queue jobs
├── Policies/              → Authorization policies
└── Providers/             → Service providers
```

## Setup Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
```

### Environment Test

```bash
cp .env.testing.example .env.testing
# Edit .env.testing dengan DB test
php artisan test
```

## Perintah Verifikasi

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test
npm run build
npx playwright test
```

## Struktur UI (Pines UI)

Komponen reusable berbasis Blade + Alpine.js di `resources/views/components/ui/`:
- `modal.blade.php` — dialog modal
- `dropdown.blade.php` — dropdown menu
- `tabs.blade.php` — tab switching
- `toast.blade.php` — notifikasi auto-dismiss
- `tooltip.blade.php` — tooltip hover/focus
- `accordion.blade.php` — expand/collapse
- `date-picker.blade.php` — date picker interaktif

## Keamanan

- Consent default-deny (tidak ada komunikasi tanpa izin)
- Suppression list global (UNIQUE channel + address)
- WhatsApp outbound mode: `manual` (tidak boleh diubah)
- OAuth tokens terenkripsi (`encrypted:array`)
- RBAC + Policy untuk setiap aksi
- Audit trail untuk operasi penting

## Lisensi

Proprietary — internal use only.
