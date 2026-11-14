# STATUS Implementasi

Diperbarui: 2026-11-14

## Tahap aktif

**T0–T4 inti selesai, T6 + T8 inti selesai.** Roadmap penuh dieksekusi atas instruksi pemilik produk (AGENT.md §18).

| Modul | Status |
|---|---|
| T0 Fondasi (auth, RBAC, audit, queue, CI) | Selesai |
| T1 Companies/Contacts/Leads/Import CSV-XLSX | Selesai (CRUD, transisi status, import async + dedup + idempotensi) |
| T2 Consent/Suppression/Eligibility | Selesai (append-only trigger, default-deny, suppression per channel) |
| T3 Email/Gmail | Selesai inti (OAuth connect/callback, draft via Gmail API, fake lokal, campaign approval + generate draft). Belum: UI kelola template penuh, pengiriman draft manual dari aplikasi |
| T4 WhatsApp/Evolution | Selesai inti (client status, webhook secret + idempotensi `provider_events`, job inbound → activity, mode manual) |
| T5 Campaign/Queue | Selesai inti (create, approval 2 orang, job `campaigns`, eligibility per penerima) |
| T6 Deals/Pipeline/Tasks | Selesai inti (pipeline + stage, board, history, status won/lost, tabel tasks). Belum: UI tasks |
| T7 CRM tambahan | Belum (notes/attachments/tags sudah schema; UI terbatas di detail lead) |
| T8 Analytics | Selesai inti (ringkasan lead/deal/aktivitas) |
| T9 Hardening/Playwright | Sebagian: arch test, audit, throttle, CSRF/API secret. Playwright belum terpasang |

## Keputusan yang dicatat

| # | Keputusan | Alasan |
|---|---|---|
| D-01 | PHP native 8.3 + PostgreSQL/Redis Docker port 5433/6379 | Environment lokal pemilik |
| D-02 | Queue worker `queue:work redis` (bukan Horizon) | `pcntl` tidak tersedia di PHP Windows lokal |
| D-03 | Redis client predis | Ekstensi `redis` tidak terpasang |
| D-04 | `WHATSAPP_OUTBOUND_MODE=manual` + guard boot | AGENT.md §10 |
| D-05 | Provider lokal `fake` (Email & WhatsApp) | Tidak ada kredensial produksi di lokal |
| D-06 | Test memakai PostgreSQL `crm_internal_test` + guard nama DB `_test` | `jsonb`/`timestamptz` tidak kompatibel SQLite |
| D-07 | Super Admin bypass permission via `Gate::before` | AGENT.md §6.1 |
| D-08 | `laravel/breeze` dihapus | Tidak tersedia untuk Laravel 13; auth Livewire langsung |
| D-09 | Campaign hanya membuat **draft** email; tidak ada pengiriman otomatis | Review manusia wajib; pengiriman dilakukan manual dari Gmail |
| D-10 | `consent_records` dilindungi trigger PostgreSQL append-only | Mencegah UPDATE/DELETE di level database, bukan hanya aplikasi |
| D-11 | Webhook Evolution fail-closed bila secret kosong; idempoten via unique `(provider,event_id)` | Tidak ada endpoint tanpa autentikasi; retry provider aman |
| D-12 | Import idempoten via unique `file_hash` + dedup kontak (email/telepon E.164) | Re-upload file sama tidak menggandakan lead |

## Blocker / belum tersedia

- Playwright belum terpasang (unduhan browser tidak dijalankan).
- Larastan terpasang; `composer dump-autoload` Windows sangat lambat sehingga analisis PHPStan belum dijalankan.
- `npm run build` belum terverifikasi (timeout pada percobaan); pengembangan lokal memakai `npm run dev`.
- Kredensial Gmail/Evolution kosong → integrasi live hanya dapat diuji setelah diisi pemilik.
- Horizon tidak dapat dijalankan lokal (tanpa `pcntl`).

## Perintah verifikasi

```powershell
php artisan test
php vendor\bin\pint --test
php artisan migrate --force
php artisan db:seed --force
```

Hasil terakhir: **42 test lulus** (termasuk Architecture), Pint clean.
