# Development execution report — local status

Last verified: 2026-11-14

## Summary

Prospectra berjalan lokal di Laravel 13.42.0 / Livewire 4 / PostgreSQL 16 (Docker, port 5433) / Redis 7 (predis). Fondasi (T0) dan modul inti T1–T6 + T8 telah diimplementasi dan diverifikasi dengan **42 test lulus** dan Pint clean. Tidak ada push GitHub, deploy, perubahan DNS, atau pengiriman email/WhatsApp nyata. Production readiness tidak diklaim.

## Environment

- PHP 8.3.28; `pdo_pgsql`, `pgsql`, `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `zip` tersedia; `redis` dan `pcntl` tidak ada.
- Node 22.23.1 / npm 11.8.0.
- PostgreSQL `crm-internal-postgres` :5433, DB `crm_internal` + `crm_internal_test`.
- Redis `crm-internal-redis` :6379, prefix terpisah cache/session/queue.
- Queue diverifikasi end-to-end (`queue:work redis --once` memproses job).
- Bukan Git repository di workspace ini; tidak ada commit/push.

## Implemented

### T0 — Fondasi
- Auth Livewire: login (throttle 5/menit per email+IP), logout, forgot/reset password.
- RBAC spatie (6 role) + `UserPolicy` + `Gate::before` super_admin; user management CRUD.
- Audit log append (`audit_logs`) untuk login, user, lead, deal, campaign.
- Layout sidebar + design tokens (`docs/design/DESIGN.md`).
- CI workflow backend + e2e skeleton (`.github/workflows/ci.yml`).
- Queue: `PingJob` + Redis pipeline; test pipeline Redis.

### T1 — Leads
- Schema: companies, contacts, leads, lead_sources, lead_assignments, tags, lead_tags, activities, notes, attachments, import_batches, import_rows.
- Enums `LeadStatus` dengan transisi sah; `ChangeLeadStatusAction`, `CreateLeadAction`, `AssignLeadAction`, `ArchiveLeadAction`.
- Normalisasi email + telepon E.164 (libphonenumber, default region ID).
- UI: daftar (filter status/prioritas/pemilik/cari), form create/edit, detail (timeline aktivitas, catatan, transisi status, penugasan, arsip).
- Import CSV/XLSX async: preview, job `imports`, validasi baris, dedup email/telepon, idempotensi `file_hash`, laporan sukses/duplikat/gagal.
- Otorisasi: sales_agent hanya melihat lead miliknya (`LeadPolicy` + scope).

### T2 — Consent & eligibility
- `consent_records` append-only dengan trigger PostgreSQL (UPDATE/DELETE ditolak di level DB).
- `suppression_entries` unik per `(channel, normalized_address)`.
- `CommunicationEligibility`: default deny, cek consent GRANTED, suppression, alamat tersedia, lead arsip, WhatsApp wajib mode manual.

### T3/T5 — Email, Gmail, campaign
- `communication_accounts` (token terenkripsi), `GmailProvider` (draft/send via Gmail API), `GmailOAuthService` + route connect/callback dengan state validation dan permission `settings.manage`.
- Template email + campaign + penerima; alur draft → pending approval → approved → job `campaigns` membuat draft per penerima.
- Campaign creator tidak boleh menyetujui campaign sendiri; penerima tanpa consent GRANTED di-skip dengan alasan.
- **Tidak ada pengiriman otomatis**: draft ditinjau dan dikirim manual dari Gmail.

### T4 — WhatsApp/Evolution
- `EvolutionClient`: cek status koneksi (tanpa mengubah instance), simpan event webhook idempoten.
- Endpoint `POST /api/v1/webhooks/evolution`: fail-closed tanpa secret, `hash_equals`, throttle 120/menit.
- Job `webhooks` memetakan pesan masuk ke activity lead bila kontak cocok (E.164).
- `WHATSAPP_OUTBOUND_MODE=manual` tidak diubah; tidak ada jalur kirim WhatsApp otomatis.

### T6/T8 — Deals & analytics
- Pipeline + stage (default seeded), board kanban, `MoveDealStageAction` dengan history + status won/lost + audit.
- Analytics: lead aktif/terkualifikasi, nilai deal terbuka/menang, lead per status, aktivitas 7 hari.

## Verification

- `php artisan test`: **42 passed** (110+ assertions) termasuk consent trigger, webhook idempotensi, campaign approval, transisi deal, import dedup, login.
- `php vendor\bin\pint --test`: clean.
- `php artisan migrate --force` + `php artisan db:seed --force`: sukses.
- Queue Redis: job diproses.
- `composer check-platform-reqs`: lulus.

## Not done / next

- Playwright belum terpasang; smoke test browser belum ada.
- PHPStan/Larastan belum dijalankan (menunggu `composer dump-autoload` yang lambat di Windows).
- `npm run build` belum diverifikasi; lokal memakai `npm run dev`.
- UI template email (CRUD penuh), halaman tasks, tags/attachments UI, export lead, dan laporan lanjutan belum dibangun.
- Integrasi live Gmail/Evolution menunggu kredensial pemilik.
- Tidak ada klaim production readiness; tidak ada push/deploy.

## Local run commands

- `php artisan serve`
- `php artisan queue:work redis`
- `npm run dev`

Jangan jalankan `migrate:fresh`/`db:wipe`; DB lokal berisi data pengembangan.
