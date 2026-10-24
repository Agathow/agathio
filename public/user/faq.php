
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$pageTitle = 'FAQ';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | SIGAP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/faq.css">
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0">
</head>
<body>

<div class="help-back-wrapper">
    <a href="/" class="help-back-button">
        <span class="material-symbols-rounded">arrow_back</span>
        <span>Kembali</span>
    </a>
</div>

<main class="help-page">
    <section class="help-hero">
        <span class="help-eyebrow">PUSAT BANTUAN SIGAP</span>
        <h1>Pertanyaan yang Sering Diajukan</h1>
        <p>Temukan jawaban seputar layanan publik, pelaporan, pelacakan laporan, dan perlindungan privasi di SIGAP.</p>
    </section>

    <section class="help-content">
        <div class="help-section-heading">
            <span class="material-symbols-rounded">help</span>
            <div>
                <h2>Pertanyaan Umum</h2>
                <p>Informasi penting untuk menggunakan SIGAP.</p>
            </div>
        </div>

        <div class="faq-list">
            <details class="faq-item">
                <summary>Apa itu SIGAP?</summary>
                <p>SIGAP adalah platform civic-tech yang membantu warga memperoleh informasi layanan publik, memeriksa tarif resmi, dan melaporkan dugaan pungutan liar.</p>
            </details>

            <details class="faq-item">
                <summary>Apakah saya harus membuat akun untuk melapor?</summary>
                <p>Tidak. Kamu dapat membuat laporan secara anonim tanpa masuk ke akun. Simpan Report ID dan Access Code yang diberikan setelah laporan berhasil dibuat.</p>
            </details>

            <details class="faq-item">
                <summary>Bagaimana cara memeriksa status laporan?</summary>
                <p>Buka menu Cek Status, masukkan Report ID dan Access Code, kemudian ikuti instruksi pada halaman.</p>
            </details>

            <details class="faq-item">
                <summary>Apa fungsi Access Code?</summary>
                <p>Access Code digunakan sebagai salah satu kredensial untuk mengakses informasi laporan. Jangan membagikannya kepada orang lain.</p>
            </details>

            <details class="faq-item">
                <summary>Bagaimana jika Access Code saya hilang?</summary>
                <p>Simpan Access Code di tempat aman setelah membuat laporan. Jika hilang, hubungi kanal bantuan resmi SIGAP untuk menanyakan pilihan bantuan yang tersedia.</p>
            </details>

            <details class="faq-item">
                <summary>Apakah identitas saya akan ditampilkan?</summary>
                <p>Laporan anonim ditujukan untuk menjaga agar identitas pelapor tidak ditampilkan secara publik. Baca halaman Privasi Pelapor untuk memahami penanganan data lebih lanjut.</p>
            </details>

            <details class="faq-item">
                <summary>Apakah membuat laporan dikenakan biaya?</summary>
                <p>Pengajuan laporan melalui SIGAP tidak dipungut biaya. Periksa informasi tarif resmi untuk layanan publik yang kamu gunakan.</p>
            </details>

            <details class="faq-item">
                <summary>Bagaimana jika saya menemukan dugaan pungutan liar?</summary>
                <p>Catat kronologi kejadian dan informasi layanan yang relevan. Kamu dapat membuat laporan melalui menu Buat Laporan. Hindari mengunggah data pribadi yang tidak diperlukan.</p>
            </details>
        </div>

        <div class="help-cta">
            <div>
                <h3>Belum menemukan jawaban?</h3>
                <p>Kamu dapat melanjutkan ke halaman pelaporan SIGAP.</p>
            </div>
            <a class="btn btn-primary" href="/user/report.php">
                Buat Laporan
                <span class="material-symbols-rounded">arrow_forward</span>
            </a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../../config/footer.php'; ?>

</body>
</html>
