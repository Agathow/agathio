<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panduan Pengguna — SIGAP</title>

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">

    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/guide.css">
</head>
<body>
<main class="guide-page">
    <div class="container guide-container">
        <a class="guide-back" href="/" aria-label="Kembali ke Beranda">
            <span aria-hidden="true">←</span> Kembali ke Beranda
        </a>

        <header class="guide-header">
            <span class="eyebrow">Pusat Bantuan SIGAP</span>
            <h1 class="page-title">Panduan Pengguna</h1>
            <p class="muted">
                Ikuti panduan ini untuk menggunakan fitur SIGAP, mulai dari mencari layanan
                publik sampai memantau laporan yang sudah kamu kirim.
            </p>
        </header>

        <section class="guide-intro card">
            <div class="guide-intro-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/>
                    <path d="M4 5.5v16M8 7h8M8 11h8M8 15h5"/>
                </svg>
            </div>
            <div>
                <h2>Mulai menggunakan SIGAP</h2>
                <p class="muted">
                    SIGAP membantu kamu memahami layanan publik, membandingkan biaya dengan
                    tarif yang tercatat, dan menyampaikan dugaan penyimpangan secara lebih aman.
                </p>
            </div>
        </section>

        <section class="guide-section" aria-labelledby="guide-services">
            <div class="guide-section-heading">
                <span class="guide-step">01</span>
                <div>
                    <h2 id="guide-services">Cari informasi layanan</h2>
                    <p class="muted">Ketahui persyaratan dan alur sebelum datang ke instansi.</p>
                </div>
            </div>
            <div class="card guide-detail">
                <ol>
                    <li>Buka menu <strong>Cek Layanan</strong>.</li>
                    <li>Gunakan kolom pencarian atau filter kategori untuk menemukan layanan.</li>
                    <li>Pilih layanan untuk membaca informasi, persyaratan, alur, dan tarif yang tersedia.</li>
                    <li>Periksa kembali informasi pada situs atau kanal resmi instansi terkait, terutama jika ada perubahan ketentuan.</li>
                </ol>
                <a class="btn btn-light" href="/user/services.php">Buka Cek Layanan <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="guide-section" aria-labelledby="guide-cost">
            <div class="guide-section-heading">
                <span class="guide-step">02</span>
                <div>
                    <h2 id="guide-cost">Bandingkan biaya resmi</h2>
                    <p class="muted">Gunakan kalkulator untuk melihat perbedaan nominal.</p>
                </div>
            </div>
            <div class="card guide-detail">
                <ol>
                    <li>Buka menu <strong>Cek Biaya</strong>.</li>
                    <li>Pilih jenis layanan publik yang sesuai.</li>
                    <li>Masukkan nominal yang diminta atau kamu bayarkan.</li>
                    <li>Lihat perbandingan biaya dan catatan evaluasi yang muncul.</li>
                </ol>
                <p class="guide-note">
                    <strong>Penting:</strong> selisih nominal adalah tanda untuk melakukan pemeriksaan lebih lanjut,
                    bukan bukti otomatis bahwa pungli atau penipuan telah terjadi. Konfirmasikan tarif dan
                    komponen biaya kepada instansi resmi.
                </p>
                <a class="btn btn-light" href="/user/check_payment.php">Buka Cek Biaya <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="guide-section" aria-labelledby="guide-report">
            <div class="guide-section-heading">
                <span class="guide-step">03</span>
                <div>
                    <h2 id="guide-report">Buat laporan</h2>
                    <p class="muted">Sampaikan kronologi yang jelas dan relevan.</p>
                </div>
            </div>
            <div class="card guide-detail">
                <ol>
                    <li>Buka menu <strong>Laporkan</strong> dan pilih mode laporan yang tersedia.</li>
                    <li>Isi jenis kejadian, judul, kronologi, serta informasi layanan yang relevan.</li>
                    <li>Masukkan tanggal atau nominal jika diminta oleh formulir.</li>
                    <li>Jika tersedia, lampirkan bukti yang relevan. Hindari mengunggah data pribadi orang lain yang tidak diperlukan.</li>
                    <li>Periksa kembali isi laporan sebelum mengirimkannya.</li>
                </ol>
                <a class="btn btn-primary" href="/user/report.php">Buat Laporan <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="guide-section" aria-labelledby="guide-codes">
            <div class="guide-section-heading">
                <span class="guide-step">04</span>
                <div>
                    <h2 id="guide-codes">Simpan kode laporan</h2>
                    <p class="muted">Langkah penting setelah laporan berhasil dikirim.</p>
                </div>
            </div>
            <div class="card guide-detail">
                <ul>
                    <li><strong>Report ID</strong> digunakan untuk mengenali laporan kamu.</li>
                    <li><strong>Access Code</strong> digunakan untuk mengakses pelacakan laporan anonim, jika diminta.</li>
                    <li>Salin dan simpan kedua kode di tempat pribadi yang aman segera setelah laporan berhasil dikirim.</li>
                    <li>Jangan bagikan Access Code kepada pihak yang tidak berkepentingan. Kode yang hilang mungkin tidak dapat dipulihkan.</li>
                </ul>
                <div class="guide-warning">
                    <span class="guide-warning-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m12 3 10 18H2L12 3z"/><path d="M12 9v5"/><path d="M12 17h.01"/>
                        </svg>
                    </span>
                    <p><strong>Jangan tutup halaman sebelum menyimpan kode.</strong> Untuk laporan anonim, kode ini bisa menjadi satu-satunya cara untuk menemukan dan memeriksa laporan kembali.</p>
                </div>
            </div>
        </section>

        <section class="guide-section" aria-labelledby="guide-status">
            <div class="guide-section-heading">
                <span class="guide-step">05</span>
                <div>
                    <h2 id="guide-status">Pantau status laporan</h2>
                    <p class="muted">Gunakan informasi laporan untuk memeriksa perkembangannya.</p>
                </div>
            </div>
            <div class="card guide-detail">
                <ol>
                    <li>Buka menu <strong>Cek Status</strong>.</li>
                    <li>Masukkan Report ID dan Access Code jika diminta.</li>
                    <li>Baca status terbaru dan catatan tindak lanjut yang ditampilkan.</li>
                    <li>Jika diminta memberikan informasi tambahan, ikuti instruksi yang tersedia pada halaman status.</li>
                </ol>
                <a class="btn btn-light" href="/user/track_report.php">Cek Status Laporan <span aria-hidden="true">→</span></a>
            </div>
        </section>

        <section class="guide-help card">
            <div>
                <h2>Masih perlu bantuan?</h2>
                <p class="muted">Kembali ke SIGAP dan pilih fitur yang sesuai dengan kebutuhanmu.</p>
            </div>
            <a class="btn btn-primary" href="/">Kembali ke Beranda <span aria-hidden="true">→</span></a>
        </section>
    </div>
</main>

<?php
$footerPath = __DIR__ . '/../../config/footer.php';
if (file_exists($footerPath)) {
    require $footerPath;
}
?>
</body>
</html>
