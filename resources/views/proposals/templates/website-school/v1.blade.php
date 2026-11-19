<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Proposal Website Sekolah - {{ $senderCompany }}</title>
<style>
{!! $fontCss !!}
    * { box-sizing: border-box; }
    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #f0f0f0;
        margin: 0;
        padding: 0;
        color: #1a1a1a;
    }

    .a4-page {
        width: 595px;
        min-height: 842px;
        background: #fff;
        margin: 40px auto;
        padding: 40px 40px 15px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        position: relative;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }

    .a4-page .browser-footer {
        margin-top: auto;
    }

    @page {
        size: A4;
        margin: 0;
        @bottom-center {
            content: "© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.\n{{ $footerText }}";
            font-size: 8pt;
            color: #888;
            text-align: center;
            white-space: pre-line;
        }
    }

    @media print {
        body { background: transparent; }
        .a4-page {
            margin: 0;
            width: 595px;
            min-height: 842px;
            padding: 40px 40px 15px;
            box-shadow: none;
            box-sizing: border-box;
            zoom: 1.3333333333;
            break-after: page;
            page-break-after: always;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .a4-page:last-child {
            break-after: auto;
            page-break-after: auto;
        }
        .browser-footer {
            display: block !important;
            margin-top: auto;
            text-align: center;
            font-size: 8px;
            font-weight: 400;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-bottom: 0;
        }
    }

    h1 { font-size: 67.08px; font-weight: 600; line-height: 73px; margin: 0; }
    h2 {
        font-size: 16pt; font-weight: bold;
        border-bottom: 1px solid #000;
        padding-bottom: 10px;
        margin: 0 0 20px 0;
        text-transform: uppercase;
    }
    h3 { font-size: 14pt; margin: 0 0 10px 0; }
    p { font-size: 11pt; line-height: 1.6; margin: 0 0 15px 0; color: #444; }

    .subtitle { font-size: 10.32px; font-weight: 400; text-transform: uppercase; color: #666; margin: 0; }
    .logo-mark {
        position: absolute; top: 40px; right: 40px;
    }
    .client-title { font-size: 25.8px; font-weight: 500; margin: 0; }
    .copyright-text { font-size: 11px; font-weight: 400; color: #888; margin: 0; }

    .cover-group { display: flex; flex-direction: column; gap: 14px; }
    .cover-group-last { display: flex; flex-direction: column; gap: 6px; }
    .cover-divider { border-top: 1px solid #555555; margin: 31px 0; }

    /* Page 3 cards */
    .page3-card h3 { font-weight: 700; font-size: 15px; margin-bottom: 8px; }
    .page3-card p { font-weight: 400; font-size: 13px; color: #555555; }

    /* 6-Column Grid System — gutter 30px */
    .grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 30px;
    }
    .col-span-2 { grid-column: span 2; }
    .col-span-3 { grid-column: span 3; }
    .col-span-4 { grid-column: span 4; }
    .col-span-6 { grid-column: span 6; }

    /* Scope grid */
    .scope-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    .scope-grid .grid-box {
        width: 249.77px;
        height: auto;
        padding: 7.72px 10.3px;
        display: flex;
        flex-direction: column;
    }
    .scope-grid .grid-box h3 {
        font-size: 17px;
        font-weight: 600;
        margin: 0 0 27px 0;
    }
    .scope-grid .grid-box p {
        font-size: 12px;
        font-weight: 400;
        margin: 0;
    }
    .peach { background-color: #d98a71; color: #000; }
    .peach p { color: #000; }
    .black { background-color: #0b0c0a; color: #d98a71; }
    .black h3, .black p { color: #d98a71; }

    /* Timeline */
    .timeline-item { position: relative; padding-left: 25px; margin-bottom: 25px; }
    .bullet {
        position: absolute; left: 0; top: 5px;
        width: 12px; height: 12px; background-color: #d98a71; border-radius: 50%;
    }
    .timeline-item h3 { font-size: 13pt; margin-bottom: 5px; color: #000; }
    .timeline-item p { font-weight: 400; font-size: 13px; color: #555555; }

    /* Pricing */
    .pricing-table {
        background-color: #f9f9f9; padding: 30px;
    }
    .price-row {
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 1px solid #e0e0e0; padding: 14px 0;
    }
    .price-row:last-of-type {
        border-bottom: none; border-top: none; margin-top: 10px; padding-top: 18px;
    }
    .price-left { font-size: 11pt; padding-right: 15px; }
    .price-right { font-size: 11pt; white-space: nowrap; }
    .price-total .price-left { font-size: 14pt; font-weight: bold; }
    .price-total .price-right { font-size: 16pt; font-weight: bold; }
    .payment-terms { font-size: 13px; font-weight: 400; color: #666; margin-top: 20px; }

    .fill { display: inline-block; min-width: 250px; height: 28px; border-bottom: 1.5px solid #1a1a1a; vertical-align: bottom; }
    .client-label { font-size: 10.32px; font-weight: 400; text-transform: uppercase; color: #666; margin: 0; }

    .fill-sm { display: inline-block; min-width: 130px; height: 15px; border-bottom: 1px solid #000; vertical-align: bottom; }
    .compare { background-color: #f9f9f9; padding: 20px 24px; }
    .compare-row { display: grid; grid-template-columns: 1fr 1.25fr 1.25fr; gap: 14px; padding: 11px 0; border-bottom: 1px solid #e0e0e0; font-size: 12px; line-height: 1.5; color: #444; }
    .compare-row:last-child { border-bottom: none; }
    .compare-head { font-weight: 700; color: #000; padding-top: 0; }
    .compare-row .k { font-weight: 600; color: #000; }
    .edu .timeline-item { margin-bottom: 14px; }
    .edu .timeline-item h3 { font-size: 11.5pt; }
    .edu .timeline-item p { font-size: 12px; }
    .tight .timeline-item { margin-bottom: 18px; }
    .scope-grid .grid-box { min-height: 140px; box-sizing: border-box; }
    .tier { display: grid; grid-template-columns: 170px 1fr; gap: 22px; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
    .tier:last-child { border-bottom: none; }
    .tier .tn { font-size: 16px; font-weight: 700; color: #000; margin-bottom: 4px; }
    .tier .tt { font-size: 11.5px; line-height: 1.5; color: #777; }
    .tier .ti { font-size: 12px; line-height: 1.6; color: #444; }
    .addon { padding: 9px 0; border-top: 1px solid #e0e0e0; font-size: 12px; line-height: 1.55; color: #555; }
    .addon b { display: block; font-size: 13px; color: #000; margin-bottom: 2px; }
    .faq-item { margin-bottom: 16px; }
    .faq-item h3 { font-size: 13px; font-weight: 700; margin: 0 0 4px 0; color: #000; }
    .faq-item p { font-size: 12px; margin: 0; color: #555555; line-height: 1.55; }

    .divider { border-top: 1px solid #6B6B6B; margin: 16.5px 0; }

    .browser-footer {
        margin-top: 50px; text-align: center; font-size: 8px; font-weight: 400; color: #999;
        border-top: 1px solid #eee; padding-top: 15px; margin-bottom: 0;
    }
</style>
</head>
<body>

<!-- Page 1 -->
<div class="a4-page">
    <svg class="logo-mark" width="41" height="52" viewBox="0 0 41 52" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M20.1125 0C9.03757 0 0 9.13935 0 20.3456V51.629H40.2304V20.3456C40.2239 9.13935 31.1863 0 20.1114 0H20.1125ZM32.5922 33.8585V44.0275H7.63276V42.9979C7.63276 42.8675 28.8923 37.2025 28.8923 37.2025V30.3776L7.63276 36.0426V33.9823L23.677 29.7325V22.9075L7.63276 27.1574V25.0971L18.3275 22.2613V15.4363L7.89054 18.1418C8.39961 15.697 9.54772 13.376 11.3262 11.5775C13.6213 9.25655 16.7992 7.84249 20.1125 7.84249C23.4258 7.84249 26.6036 9.12621 28.8988 11.5775C31.1874 13.8985 32.5922 17.1122 32.5922 20.4628V33.8585Z" fill="black"/>
</svg>
    <div class="cover-group">
        <div class="subtitle">PROPOSAL PENAWARAN</div>
        <h1>Website<br>Sekolah</h1>
        <div class="cover-group-last">
            <div class="client-label">Disiapkan untuk</div>
            <div class="client-title">{{ $companyName }}</div>
            <div class="copyright-text">{{ $contactName }}{{ $industry !== '' ? ' · '.$industry : '' }}</div>
            <div class="client-label" style="margin-top: 14px;">No. Proposal {{ $proposalNumber }} &nbsp;|&nbsp; Tanggal {{ $proposalDate }}</div>
            <div class="copyright-text">©{{ $year }} {{ $senderCompany }}® | {{ $senderName }}</div>
        </div>
    </div>

    <div class="cover-divider"></div>

    <h2 style="border-bottom: none; padding-bottom: 0; font-weight: 600; font-size: 17.57px;">GAMBARAN UMUM</h2>
    <p style="font-weight: 400; font-size: 12px;">Hari ini, sebagian besar orang tua mencari informasi sekolah lewat HP sebelum memutuskan untuk datang langsung. Yang mereka cari sederhana: profil sekolah, biaya, jadwal pendaftaran, dan bukti bahwa sekolah tersebut dikelola dengan baik. Website resmi menjawab semua itu di satu tempat, setiap saat, tanpa menunggu jam kantor.</p>
    <p style="font-weight: 400; font-size: 12px;">{{ $senderCompany }} mengusulkan pembuatan website resmi untuk sekolah Bapak/Ibu: tampilan profesional yang sesuai identitas sekolah, mudah dikelola, dan siap menjadi pusat informasi untuk orang tua, calon siswa, dan masyarakat sekitar.</p>
    <p style="font-weight: 400; font-size: 12px;">Website terdiri dari tujuh halaman inti — Beranda, Profil Sekolah, Guru &amp; Staf, Berita &amp; Pengumuman, PPDB, Galeri, dan Kontak. Seluruhnya dirancang agar nyaman dibuka di HP, karena di sanalah mayoritas pengunjung berada.</p>
    <p style="font-weight: 400; font-size: 12px;">Kami adalah tim kreatif yang menangani desain, branding, dan pengembangan web dalam satu atap, sehingga sekolah tidak perlu berurusan dengan banyak pihak. Di akhir proyek, seluruh file website diserahkan lengkap kepada sekolah.</p>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page 2 (Edukasi) -->
<div class="a4-page edu">
    <h2 style="border-bottom: none; padding-bottom: 0; margin-bottom: 14px; font-weight: 600; font-size: 17px;">SEBELUM MEMBACA PENAWARAN</h2>
    <p style="font-weight: 400; font-size: 12px;">Banyak pengelola sekolah belum pernah berurusan langsung dengan website, dan itu hal yang wajar. Halaman ini merangkum hal-hal dasar dengan bahasa sederhana, supaya Bapak/Ibu bisa menilai penawaran ini dengan nyaman.</p>

    <h3 style="font-size: 15px; font-weight: 700; margin: 8px 0 16px 0;">Tiga istilah yang sering membingungkan</h3>
    <div class="timeline-item">
        <div class="bullet"></div>
        <h3>Website = bangunan sekolah di internet</h3>
        <p style="margin-bottom: 0;">Berisi profil, pengumuman, foto kegiatan, dan informasi pendaftaran. Bisa dibuka siapa saja, kapan saja, lewat HP.</p>
    </div>
    <div class="timeline-item">
        <div class="bullet"></div>
        <h3>Domain = alamat bangunan itu</h3>
        <p style="margin-bottom: 0;">Contohnya namasekolah.sch.id. Alamat inilah yang dicari orang tua untuk menemukan sekolah.</p>
    </div>
    <div class="timeline-item" style="margin-bottom: 0;">
        <div class="bullet"></div>
        <h3>Hosting = lahan tempat bangunan berdiri</h3>
        <p style="margin-bottom: 0;">Seperti sewa lahan, ada biaya setahun sekali. Jika tidak diperpanjang, website tidak bisa dibuka. Itu sebabnya kami menjelaskan biaya ini sejak awal.</p>
    </div>

    <div class="divider" style="margin: 16px 0; border-top: 1px solid #555555;"></div>

    <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 12px 0;">Website atau media sosial?</h3>
    <div class="compare">
        <div class="compare-row compare-head"><div></div><div>Media sosial</div><div>Website resmi</div></div>
        <div class="compare-row"><div class="k">Kepemilikan</div><div>Menumpang di platform milik pihak lain</div><div>Milik sekolah sepenuhnya</div></div>
        <div class="compare-row"><div class="k">Penataan</div><div>Informasi lama tertimbun postingan baru</div><div>Tertata per halaman, mudah dicari kembali</div></div>
        <div class="compare-row"><div class="k">Peran</div><div>Kabar harian dan kedekatan dengan orang tua</div><div>Rujukan resmi: profil, syarat, jadwal, kontak</div></div>
    </div>
    <p style="font-weight: 400; font-size: 12px; margin-top: 14px; margin-bottom: 0;">Keduanya saling melengkapi. Website bukan pengganti media sosial yang sudah dimiliki sekolah, melainkan pusat informasi yang bisa dirujuk semua pihak.</p>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page Cara -->
<div class="a4-page">
    <h2 style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px; font-weight: 600; font-size: 17px;">CARA ORANG TUA MEMILIH SEKOLAH SEKARANG</h2>
    <p style="font-weight: 400; font-size: 12px;">Rekomendasi dari kerabat dan tetangga tetap yang paling berpengaruh, dan itu tidak berubah. Yang berubah: setelah mendengar nama sebuah sekolah, orang tua sering mengeceknya lagi lewat HP sebelum datang.</p>
    <div class="compare" style="margin-top: 16px;">
        <div class="compare-row compare-head"><div>Langkah orang tua</div><div>Tanpa website</div><div>Dengan website resmi</div></div>
        <div class="compare-row"><div class="k">Mendengar nama sekolah</div><div>Cerita berhenti di situ</div><div>Ada alamat resmi yang bisa dicatat</div></div>
        <div class="compare-row"><div class="k">Mencari di HP</div><div>Hanya kabar dari pihak lain</div><div>Menemukan halaman resmi sekolah</div></div>
        <div class="compare-row"><div class="k">Mencari syarat dan jadwal</div><div>Harus bertanya atau datang langsung</div><div>Terbaca sendiri, kapan saja</div></div>
        <div class="compare-row"><div class="k">Menghubungi sekolah</div><div>Mencari nomor ke sana kemari</div><div>Satu tombol langsung ke WhatsApp</div></div>
    </div>
    <h3 style="font-size: 15px; font-weight: 700; margin: 22px 0 12px 0;">Enam ciri website sekolah yang dipercaya</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 28px;">
    <div class="faq-item"><h3>Identitas jelas</h3><p>Nama, logo, dan alamat sekolah langsung terlihat.</p></div>
    <div class="faq-item"><h3>Informasi terbaru</h3><p>Pengumuman bertanggal, bukan kabar tahun lalu.</p></div>
    <div class="faq-item"><h3>Foto asli</h3><p>Gedung dan kegiatan sendiri, bukan foto stok.</p></div>
    <div class="faq-item"><h3>Ada orangnya</h3><p>Kepala sekolah dan guru tampil dengan nama dan foto.</p></div>
    <div class="faq-item"><h3>Mudah dihubungi</h3><p>Nomor WhatsApp dan peta lokasi tidak perlu dicari.</p></div>
    <div class="faq-item"><h3>Nyaman di HP</h3><p>Terbuka cepat dan terbaca tanpa dibesarkan.</p></div>
    </div>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page Mati -->
<div class="a4-page">
    <h2 style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px; font-weight: 600; font-size: 17px;">MENGAPA WEBSITE SEKOLAH SERING MATI</h2>
    <p style="font-weight: 400; font-size: 12px;">Banyak sekolah pernah punya website, lalu ditinggalkan. Masalahnya jarang di tampilan; hampir selalu di perawatan setelah website jadi.</p>
    <div class="compare" style="margin-top: 14px;">
        <div class="compare-row compare-head"><div>Penyebab umum</div><div>Yang terjadi</div><div>Yang kami lakukan</div></div>
        <div class="compare-row"><div class="k">Masa sewa habis</div><div>Domain atau hosting lupa diperpanjang</div><div>Kami mengingatkan sebelum jatuh tempo</div></div>
        <div class="compare-row"><div class="k">Pembuat sulit dihubungi</div><div>Setelah proyek selesai, hilang kabar</div><div>Email dan WhatsApp resmi tetap aktif</div></div>
        <div class="compare-row"><div class="k">Satu orang yang tahu</div><div>Orangnya pindah, website terbengkalai</div><div>Panduan tertulis yang sederhana</div></div>
        <div class="compare-row"><div class="k">Akun dipegang pihak lain</div><div>Sekolah tidak punya akses</div><div>Semua file dan akses diserahkan</div></div>
    </div>
    <h3 style="font-size: 15px; font-weight: 700; margin: 26px 0 14px 0;">Kekhawatiran yang sering kami dengar</h3>
    <div class="faq-item"><h3>Apakah perlu orang IT di sekolah?</h3><p>Tidak. Cukup HP atau laptop biasa dan kemauan memperbarui pengumuman.</p></div>
    <div class="faq-item"><h3>Bagaimana kalau guru pengelolanya diganti?</h3><p>Panduan tertulis tersedia, dan kami tetap bisa dihubungi untuk membantu.</p></div>
    <div class="faq-item"><h3>Apakah data siswa akan ditampilkan?</h3><p>Tidak. Hanya informasi yang disetujui sekolah untuk umum; data pribadi siswa tidak dipajang.</p></div>
    <div class="faq-item"><h3>Sekolah kami kecil, apakah cocok?</h3><p>Cocok. Website tidak perlu besar; yang penting jelas dan mudah dihubungi. Cakupannya kami sesuaikan.</p></div>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page Tentang -->
<div class="a4-page">
    <h2 style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px; font-weight: 600; font-size: 17px;">KENALAN DENGAN {{ mb_strtoupper($senderCompany) }}</h2>
    <p style="font-weight: 400; font-size: 12px;">Kami adalah {{ $senderCompany }}. Kami kolektif kreator: desain, branding, video, dan pengembangan web bekerja sebagai satu tim. Bapak/Ibu berbicara langsung dengan orang yang mengerjakan, bukan perantara.</p>
        <div class="compare" style="margin-top: 14px; padding: 18px 24px; font-size: 12px; line-height: 1.8; color: #444;"><b style="color: #000; font-size: 13px;">Yang kami kerjakan</b><br>Desain antarmuka (UI/UX) · Branding dan identitas · Video dan motion · Pengelolaan media sosial · Pengembangan web · Solusi IT. Semuanya ditangani satu tim, sehingga tampilan, konten, dan teknis website sekolah selaras dari awal.</div>
    <h3 style="font-size: 15px; font-weight: 700; margin: 24px 0 12px 0;">Cara kami bekerja</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 28px;">
    <div class="faq-item"><h3>Satu tim untuk semua</h3><p>Desain, web, dan konten di satu atap, sehingga sekolah tidak mengurus banyak pihak.</p></div>
    <div class="faq-item"><h3>Proses jelas</h3><p>Apa yang dibuat, biayanya, dan kapan selesai diketahui sebelum kami mulai.</p></div>
    <div class="faq-item"><h3>Akses langsung</h3><p>Bertanya langsung kepada orang yang mengerjakan website sekolah.</p></div>
    <div class="faq-item"><h3>Tidak berbelit</h3><p>Kami mulai pada minggu sekolah siap, tanpa rapat berbulan-bulan.</p></div>
    </div>
    <h3 style="font-size: 15px; font-weight: 700; margin: 4px 0 8px 0;">Janji kami kepada sekolah</h3>
    <div style="font-size: 12px; line-height: 1.95; color: #555;"><b style="color: #000;">Tertulis sejak awal.</b> Rincian pekerjaan dan biaya disampaikan sebelum proyek dimulai.<br><b style="color: #000;">Dua kali revisi</b> tanpa biaya tambahan.<br><b style="color: #000;">Milik sekolah.</b> Seluruh file dan akses diserahkan kepada sekolah.<br><b style="color: #000;">Tetap terhubung.</b> Kami bisa dihubungi setelah website tayang.</div>
    <div style="display: flex; gap: 14px; margin-top: 16px;">
        <div style="flex: 1; height: 90px; border: 2px dashed #bbb; display: flex; align-items: center; justify-content: center; text-align: center; padding: 10px; font-size: 11px; color: #888;">[Tempel contoh karya / website yang pernah dikerjakan]</div>
        <div style="flex: 1; height: 90px; border: 2px dashed #bbb; display: flex; align-items: center; justify-content: center; text-align: center; padding: 10px; font-size: 11px; color: #888;">[Tempel contoh karya / website yang pernah dikerjakan]</div>
    </div>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page 3 (Ruang Lingkup) -->
<div class="a4-page">
    <h2 style="border-bottom: none; padding-bottom: 0; margin-bottom: 18px; font-weight: 600; font-size: 17px;">RUANG LINGKUP PEKERJAAN</h2>
    <div class="scope-grid">
        <div class="grid-box peach">
            <h3>Desain Custom Sesuai Identitas</h3>
            <p>Desain antarmuka dibuat khusus mengikuti logo, warna, dan karakter sekolah — bukan template pasaran</p>
        </div>
        <div class="grid-box black">
            <h3>Profil Sekolah</h3>
            <p>Sambutan kepala sekolah, sejarah, visi &amp; misi, fasilitas, dan prestasi dalam satu halaman yang rapi</p>
        </div>
        <div class="grid-box black">
            <h3>Guru &amp; Tenaga Kependidikan</h3>
            <p>Halaman profil guru dan staf lengkap dengan foto, jabatan, dan mata pelajaran</p>
        </div>
        <div class="grid-box peach">
            <h3>Berita &amp; Pengumuman</h3>
            <p>Kabar kegiatan, pengumuman resmi, dan agenda sekolah yang tampil terurut dan mudah dibaca</p>
        </div>
        <div class="grid-box peach">
            <h3>Informasi PPDB</h3>
            <p>Syarat, jadwal, alur pendaftaran, dan tombol langsung ke WhatsApp panitia atau formulir pendaftaran</p>
        </div>
        <div class="grid-box black">
            <h3>Galeri Foto &amp; Kegiatan</h3>
            <p>Dokumentasi kegiatan belajar, ekstrakurikuler, dan acara sekolah dalam tampilan galeri</p>
        </div>
        <div class="grid-box black">
            <h3>Responsif &amp; Cepat</h3>
            <p>Nyaman dibuka di HP, tablet, dan laptop, dengan waktu muat yang ringan di koneksi biasa</p>
        </div>
        <div class="grid-box peach">
            <h3>Kontak &amp; Lokasi</h3>
            <p>Alamat, nomor WhatsApp, media sosial, peta lokasi, dan formulir pesan untuk orang tua</p>
        </div>
    </div>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page 3 -->
<div class="a4-page">
    <div class="grid">
        <div class="col-span-3">
            <h2 style="font-weight: 700; font-size: 16px; border-bottom: none; padding-bottom: 0; margin-bottom: 26px;">MENGAPA SEKOLAH PERLU WEBSITE?</h2>
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>Kesan pertama yang meyakinkan</h3>
                <p>Orang tua menilai sekolah dari tampilan informasinya. Website yang rapi menunjukkan sekolah yang tertata.</p>
            </div>
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>Informasi satu pintu</h3>
                <p>Jadwal, pengumuman, dan syarat PPDB tersedia di satu alamat, sehingga pertanyaan berulang ke panitia berkurang.</p>
            </div>
        </div>
        <div class="col-span-3">
            <h2 style="border-bottom:none; margin-bottom: 10px;">&nbsp;</h2>
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>Dibuka kapan saja</h3>
                <p>Calon orang tua bisa melihat profil dan prosedur pendaftaran kapan pun, tanpa harus datang ke sekolah.</p>
            </div>
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>Aset milik sekolah</h3>
                <p>Berbeda dengan media sosial yang aturannya bisa berubah, website adalah milik sekolah sepenuhnya.</p>
            </div>
        </div>
    </div>

    <div class="divider" style="margin-bottom: 34px;"></div>

    <h2 style="font-weight: 700; font-size: 16px; border-bottom: none; padding-bottom: 0; margin-bottom: 26px;">YANG TIDAK TERMASUK</h2>
    <div class="grid">
        <div class="col-span-3">
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>— Domain &amp; Hosting</h3>
                <p>Pembelian nama domain dan layanan hosting tidak termasuk, namun dapat kami bantu urus secara terpisah.</p>
            </div>
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>— Sistem Akademik</h3>
                <p>Rapor online, e-learning, dan pembayaran SPP online tidak termasuk dan dapat dibuatkan penawaran sendiri.</p>
            </div>
        </div>
        <div class="col-span-3">
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>— Foto &amp; Video</h3>
                <p>Produksi foto dan video profesional tidak termasuk. Sekolah menyediakan materi yang sudah ada.</p>
            </div>
            <div class="page3-card" style="margin-bottom: 20px;">
                <h3>— Halaman Tambahan</h3>
                <p>Halaman di luar tujuh halaman yang disepakati akan dihitung sebagai pekerjaan tambahan.</p>
            </div>
        </div>
    </div>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page 4 -->
<div class="a4-page tight">
    <h2 style="font-weight: 600; font-size: 20px; border-bottom: none; padding-bottom: 0;">JADWAL PENGERJAAN</h2>
    <div class="timeline-item">
        <div class="bullet"></div>
        <h3>Kickoff &amp; pengumpulan materi</h3>
        <p>Hari 1–3 · Sekolah menyerahkan logo, foto, teks profil, dan data guru sesuai daftar materi yang kami berikan</p>
    </div>
    <div class="timeline-item">
        <div class="bullet"></div>
        <h3>Desain tampilan</h3>
        <p>Hari 3–7 · Perancangan tampilan Beranda dan halaman utama sesuai identitas sekolah</p>
    </div>
    <div class="timeline-item">
        <div class="bullet"></div>
        <h3>Pengembangan website</h3>
        <p>Hari 7–12 · Pembuatan seluruh halaman, galeri, formulir, dan tampilan HP serta tablet</p>
    </div>
    <div class="timeline-item">
        <div class="bullet"></div>
        <h3>Review &amp; revisi</h3>
        <p>Hari 12–14 · Sekolah memeriksa hasil, termasuk hingga 2 putaran revisi</p>
    </div>
    <div class="timeline-item" style="margin-bottom: 0;">
        <div class="bullet"></div>
        <h3>Peluncuran &amp; serah terima</h3>
        <p style="margin-bottom: 0;">Hari 15 · Website tayang dan seluruh file diserahkan kepada sekolah</p>
    </div>

    <div class="divider" style="margin: 16px 0; border-top: 1px solid #555555;"></div>

    <p style="font-weight: 400; font-size: 12px; color: #555555;">Sekolah adalah tempat anak-anak tumbuh, dan wajah digitalnya layak sebaik kerja keras guru dan tenaga kependidikan di dalamnya. Website bukan sekadar pajangan, melainkan sarana agar sekolah lebih mudah dikenal, dipercaya, dan dihubungi.</p>
    <p style="font-weight: 400; font-size: 12px; color: #555555;">Sebagai tim yang menangani desain sekaligus pengembangan, kami memastikan tampilan yang disetujui di awal sama dengan hasil akhirnya, tanpa kesalahpahaman antar pihak. Kami bekerja dengan proses yang jelas: Bapak/Ibu tahu apa yang dibuat, berapa biayanya, dan kapan selesai sebelum proyek dimulai.</p>
    <p style="font-weight: 400; font-size: 12px; color: #555555;">Kami akan dengan senang hati mendampingi sekolah Bapak/Ibu hingga website siap digunakan, dan menunggu kabar baik dari Bapak/Ibu.</p>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page Layanan -->
<div class="a4-page">
    <h2 style="font-weight: 600; font-size: 20px; border-bottom: none; padding-bottom: 0; margin-bottom: 10px;">PILIHAN LAYANAN</h2>
    <p style="font-weight: 400; font-size: 12px; line-height: 1.7; margin-bottom: 14px;">Setiap sekolah punya kebutuhan dan kemampuan yang berbeda. Tiga tingkat berikut adalah titik awal; cakupan dan biaya kami susun bersama setelah berdiskusi.</p>
    <div class="compare" style="padding: 4px 24px;">
        <div class="tier"><div><div class="tn">Ringkas</div><div class="tt">Untuk sekolah kecil yang baru mulai</div></div><div class="ti">– 4 halaman: Beranda, Profil, Berita, Kontak<br>– Desain sesuai logo sekolah<br>– Tampilan nyaman di HP dan laptop<br>– 2 putaran revisi</div></div>
        <div class="tier"><div><div class="tn">Standar</div><div class="tt">Cakupan lengkap untuk kebutuhan umum sekolah</div></div><div class="ti">– 7 halaman inti sekolah<br>– Info penerimaan murid + WhatsApp<br>– Galeri kegiatan dan profil guru<br>– Peta lokasi dan formulir pesan<br>– Panduan singkat pengelolaan</div></div>
        <div class="tier"><div><div class="tn">Lengkap</div><div class="tt">Untuk sekolah yang ingin tampil maksimal</div></div><div class="ti">– Semua isi paket Standar<br>– Pembaruan logo sederhana<br>– Sesi foto kegiatan sekolah<br>– Pengaturan Google Maps sekolah<br>– Pendampingan 1 bulan setelah tayang</div></div>
    </div>
    <h3 style="font-size: 15px; font-weight: 700; margin: 20px 0 6px 0;">Layanan tambahan</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 28px;">
    <div class="addon"><b>Pengelolaan media sosial</b>Konten dan jadwal unggah rutin untuk Instagram atau Facebook sekolah.</div>
    <div class="addon"><b>Video profil sekolah</b>Video 1 sampai 2 menit untuk media sosial dan acara penerimaan murid.</div>
    <div class="addon"><b>Logo, brosur, dan spanduk</b>Identitas visual yang seragam di web dan materi cetak.</div>
    <div class="addon"><b>Sistem pendaftaran mandiri</b>Pendaftaran online untuk sekolah swasta, sesuai kebutuhan.</div>
    </div>
    <p style="font-size: 12.5px; line-height: 1.65; color: #555; margin-top: 16px;"><b style="color: #000;">Soal biaya:</b> rincian tertulis kami susun setelah memahami kebutuhan sekolah, termasuk domain dan hosting setahun sekali. Hubungi kami lewat WhatsApp atau email resmi {{ $senderCompany }} untuk mendapatkannya.</p>
    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

<!-- Page Akhir -->
<div class="a4-page">
    <h2 style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px; font-weight: 600; font-size: 17px;">CATATAN</h2>
    <div class="timeline-item" style="margin-bottom: 12px;">
        <div class="bullet"></div>
        <p style="margin: 0; padding-top: 0; font-size: 12px;">Pengerjaan dimulai setelah rincian disepakati dan seluruh materi (logo, foto, teks) kami terima.</p>
    </div>
    <div class="timeline-item" style="margin-bottom: 12px;">
        <div class="bullet"></div>
        <p style="margin: 0; padding-top: 0; font-size: 12px;">Jadwal mengasumsikan sekolah menanggapi setiap tahap review dalam 24 jam.</p>
    </div>
    <div class="timeline-item" style="margin-bottom: 12px;">
        <div class="bullet"></div>
        <p style="margin: 0; padding-top: 0; font-size: 12px;">Seluruh file dan hak atas website diserahkan kepada sekolah setelah pembayaran selesai.</p>
    </div>
    <div class="timeline-item" style="margin-bottom: 12px;">
        <div class="bullet"></div>
        <p style="margin: 0; padding-top: 0; font-size: 12px;">Penawaran ini berlaku 14 hari sejak tanggal diterbitkan.</p>
    </div>

    <h3 style="margin-top: 30px; font-weight: 700; font-size: 16px;">Ngobrol dulu, tanpa komitmen</h3>
    <p style="font-size: 13px; line-height: 1.7;">Kami paham bahwa memutuskan sesuatu yang baru tidak selalu mudah, apalagi jika Bapak/Ibu belum pernah berurusan dengan website sebelumnya. Itu sebabnya kami tidak meminta keputusan hari ini. Kami hanya mengajak Bapak/Ibu berbincang santai selama 15–30 menit, tanpa biaya dan tanpa kewajiban apa pun.</p>
    <p style="font-size: 13px; line-height: 1.7;">Dalam perbincangan itu, kami akan menjelaskan dengan bahasa sederhana apa yang bisa dilakukan website untuk sekolah Bapak/Ibu, menjawab pertanyaan sekecil apa pun, lalu menyusun penawaran yang sesuai dengan kebutuhan dan kemampuan sekolah. Jika setelahnya Bapak/Ibu merasa belum perlu, tidak masalah sama sekali.</p>
    <p style="font-size: 13px; line-height: 1.7;">Silakan hubungi kami lewat email di <strong style="border-bottom: 1px solid #000;">{{ $senderEmail }}</strong> atau WhatsApp {{ $senderPhone !== '' ? $senderPhone : '—' }}. Untuk mengenal kami lebih jauh, kunjungi <strong style="border-bottom: 1px solid #000;">{{ $senderWebsite }}</strong>. Kami menunggu kabar dari Bapak/Ibu dan siap mendampingi sekolah sampai website siap digunakan.</p>

    <div class="browser-footer">© {{ $year }} {{ $senderCompany }}® | {{ $senderName }}.<br>{{ $footerText }}</div>
</div>

</body>
</html>
