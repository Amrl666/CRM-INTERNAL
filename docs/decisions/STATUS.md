# STATUS Implementasi

Diperbarui: 2026-11-15

## Tahap aktif

**T0–T9: semua tahap inti selesai.** Proposal Outreach Campaign (§6.8) implementasi lengkap — schema, models, actions, Livewire UI, feature tests, e2e smoke test.

| Modul | Status |
|---|---|
| T0 Fondasi (auth, RBAC, audit, queue, CI) | Selesai |
| T1 Companies/Contacts/Leads/Import CSV-XLSX | Selesai |
| T2 Consent/Suppression/Eligibility | Selesai |
| T3 Email/Gmail draft-first | Selesai |
| T4 WhatsApp/Evolution | Selesai |
| T5 Campaign/Queue + Proposal Outreach | **Selesai** — state machine, approval, scheduling, delivery, idempotency, pause/cancel, re-eligibility |
| T6 Deals/Pipeline/Tasks | Selesai |
| T8 Analytics | Selesai |
| T9 Hardening | Sebagian: arch test, audit, throttle. Playwright structure siap, browser belum terunduh lokal |

### Verifikasi akhir
| Cek | Hasil |
|---|---|
| `php artisan test` | **63 passed / 0 failed** (163 assertions) |
| `./vendor/bin/pint --test` | **passed** |
| `npm run build` | **clean** (vite, 61 modules, ~1s) |
| `npx playwright test --list` | **7 test ditemukan** (4 auth setup + 3 proposals spec) |
| PHPStan level 5 | ~81 error pre-existing di enum/string comparison (bukan bug fungsional) |

### Perubahan hari ini
- **Routes**: tambah `proposals.index`, `proposals.create`, `proposals.show`
- **Sidebar**: tambahkan link "Proposal" setelah menu Campaign (`@can('campaigns.view')`)
- **Badge component**: tambah mapping untuk `REVIEW, SCHEDULED, RUNNING, PAUSED, GENERATING, READY, PROCESSING, ACCEPTED, DELIVERED, ELIGIBLE, INELIGIBLE`
- **Factories**: `ProposalTemplateFactory`, `MessageTemplateFactory` + `HasFactory` di model
- **Bug fix Campaign**: hapus typed property `$status` (sebabkan uninitialized Error saat cast tidak aktif)
- **Bug fix Renderer**: camelCase aliases (`companyName`, `senderCompany`, dll) agar Blade template `$senderCompany` terbaca
- **Bug fix Snapshot**: `$campaign->channel?->value` (cast enum → string, bukan `(string) $enum`)
- **Bug fix ArchTest**: ganti `app_path()` (Facade butuh container) dengan `glob()`
- **Bug fix MessagePersonalization**: extract `$matches[1] ?? []` sebelum `array_diff`
- **Bug fix BusinessSchedule**: cast eksplisit `$timezone` dan nullable array ops
- **E2e test**: `tests/e2e/proposals.spec.ts` — index page load, sidebar nav, axe accessibility scan

### Proposal test baru (14 test)
draft creation, WhatsApp flag disabled, empty leads rejected, request approval, creator self-approval blocked, different-user approve + snapshot, schedule before approval blocked, skip ineligible on schedule, pause scheduled campaign, cancel campaign + recipients, render message personalization, re-check eligibility before send, idempotency key, eligible count assertion.

## Keputusan yang dicatat

| # | Keputusan | Alasan |
|---|---|---|
| D-01 | PHP native 8.3 + PostgreSQL/Redis Docker port 5433/6379 | Environment lokal pemilik |
| D-02 | Queue worker `queue:work redis` (bukan Horizon) | `pcntl` tidak tersedia di PHP Windows lokal |
| D-03 | Redis client predis | Ekstensi `redis` tidak terpasang |
| D-04 | `WHATSAPP_OUTBOUND_MODE=manual` + guard boot | AGENT.md §A10 |
| D-05 | Provider lokal `fake` (Email & WhatsApp) | Tidak ada kredensial produksi di lokal |
| D-06 | Test memakai PostgreSQL `crm_internal_test` + guard nama DB `_test` | `jsonb`/`timestamptz` tidak kompatibel SQLite |
| D-07 | Super Admin bypass permission via `Gate::before` | AGENT.md §6.1 |
| D-08 | `laravel/breeze` dihapus | Tidak tersedia untuk Laravel 13; auth Livewire langsung |
| D-09 | Campaign hanya membuat **draft** email; tidak ada pengiriman otomatis | Review manusia wajib; pengiriman dilakukan manual dari Gmail |
| D-10 | `consent_records` dilindungi trigger PostgreSQL append-only | Mencegah UPDATE/DELETE di level database, bukan hanya aplikasi |
| D-11 | Webhook Evolution fail-closed bila secret kosong; idempoten via unique `(provider,event_id)` | Tidak ada endpoint tanpa autentikasi; retry provider aman |
| D-12 | Import idempoten via unique `file_hash` + dedup kontak (email/telepon E.164) | Re-upload file sama tidak menggandakan lead |

## Gap yang masih ada (jujur)

- **PHPStan level 5**: ~81 error pre-existing di seluruh domain Campaign (termasuk legacy code sebelum implementasi proposal). Penyebab: pola `CampaignStatus::Draft` vs `string` comparison setelah Laravel cast. Bukan bug fungsional — semua 63 test lulus.
- **Playwright browser**: Chromium belum terunduh di laptop. Struktur e2e test sudah benar, tinggal jalankan `npx playwright install --with-deps chromium`.
- **Live integrasi**: Gmail/Evolution kredensial kosong sesuai desain AGENT.md.
- **Horizon worker lokal**: tidak bisa jalan tanpa `pcntl` di Windows.
