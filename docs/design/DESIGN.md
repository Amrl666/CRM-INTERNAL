# DESIGN.md — Sistem Desain B2B Outreach CRM (v1)

Sumber kebenaran visual. Semua halaman wajib mengikuti token dan pola di sini.
Perubahan token harus konsisten di seluruh UI dan diperbarui lewat PR.

## 1. Token warna

```css
:root {
  /* netral */
  --color-bg:            #f8fafc; /* slate-50  */
  --color-surface:       #ffffff;
  --color-border:        #e2e8f0; /* slate-200 */
  --color-text:          #0f172a; /* slate-900 */
  --color-text-muted:    #64748b; /* slate-500 */

  /* aksen tunggal */
  --color-accent:        #1d4ed8; /* blue-700 */
  --color-accent-hover:  #1e40af; /* blue-800 */

  /* semantik */
  --color-success:       #15803d; /* green-700 */
  --color-warning:       #b45309; /* amber-700 */
  --color-danger:        #b91c1c; /* red-700   */
  --color-info:          #0369a1; /* sky-700   */

  /* status consent (T2) */
  --color-consent-granted: #15803d;
  --color-consent-unknown: #64748b;
  --color-consent-revoked: #b91c1c;

  /* bentuk */
  --radius-sm: 4px;
  --radius-md: 6px;
  --radius-lg: 8px;
  --space-unit: 4px;
}
```

Kontras teks minimal WCAG AA (4.5:1). Pasangan yang dipakai saat ini: text
`#0f172a` di atas `#ffffff`/`#f8fafc`, text-muted `#64748b` di atas `#ffffff`
(≥ 4.5:1), aksen `#1d4ed8` untuk teks/ikon di permukaan terang.

## 2. Tipografi

- Family: system UI stack (self-host, tanpa CDN): `ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`.
- Skala: 12 (xs), 14 (sm, body default), 16 (base), 18 (lg), 20 (xl), 24 (2xl), 30 (3xl).
- Bobot: 400 regular, 500 medium (label/tombol), 600 semibold (judul).
- Line-height: 1.5 body, 1.25 judul.

## 3. Spacing & grid

- Skala 4px: 4, 8, 12, 16, 24, 32, 48.
- Sidebar tetap 256px (`w-64`), topbar 64px (`h-16`).
- Konten maksimum 1152px (`max-w-6xl`), padding halaman 24px.

## 4. Radius, border, shadow, z-index

- Radius: sm 4px, md 6px (default input/tombol), lg 8px (card).
- Border 1px `--color-border`; hindari shadow berat, cukup border.
- z-index terbatas: dropdown 40, modal 50, toast 60.

## 5. Ikon

- Satu library (Heroicons via Blade SVG inline) ukuran 16/20px.
- Ikon dekoratif `aria-hidden="true"`.

## 6. Komponen

Daftar minimum (anatomi + state): button (primary/secondary/danger/ghost,
disabled/loading), input, select, textarea, checkbox/radio, badge, table
(header sticky, sortable, pagination server-side), pagination, filter bar,
tabs, modal/dialog, drawer, dropdown, toast, alert/inline banner, empty state,
skeleton, timeline, stat tile, stepper.

Yang sudah diimplementasikan (T0):
- `components/stat-card.blade.php` — stat tile.
- `layouts/sidebar.blade.php` — sidebar + topbar + slot konten.
- `layouts/guest.blade.php` — halaman auth.

## 7. Pola halaman

- List: filter bar di atas, tabel server-side, empty state informatif.
- Detail: header ringkas + timeline aktivitas.
- Form: label terlihat, error inline, ringkasan error untuk form panjang,
  tombol submit menampilkan state loading dan mencegah double submit.
- Import wizard: upload → validasi → preview → konfirmasi → hasil (T1).
- Pipeline board: kolom stage dengan drag-and-drop (T6).

## 8. Copywriting

- Bahasa Indonesia, ringkas, imperatif ("Simpan", "Import CSV/XLSX").
- Sebutkan alasan empty state dan aksi berikutnya.
- Pesan error menjelaskan cara memperbaiki, bukan hanya "gagal".

## 9. Aksesibilitas

- Semua input punya `<label>` terlihat.
- Fokus keyboard jelas (`focus:ring`).
- Tombol ikon wajib `aria-label`.
- Jangan mengandalkan warna saja untuk status (sertakan teks badge).
