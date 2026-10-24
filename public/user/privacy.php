
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$pageTitle = 'Privasi Pelapor';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | SIGAP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/privacy.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0">
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
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
        <span class="help-eyebrow">KEAMANAN DAN KEPERCAYAAN</span>
        <h1>Privasi Pelapor</h1>
        <p>Kenali hal-hal yang perlu diperhatikan saat menyampaikan laporan melalui SIGAP dan cara menjaga informasi laporanmu.</p>
    </section>

    <section class="help-content">
        <div class="privacy-intro">
            <h2>Privasi adalah hal yang penting</h2>
            <p>SIGAP menyediakan pilihan pelaporan anonim agar warga dapat menyampaikan informasi tanpa menampilkan identitasnya sebagai pelapor secara publik. Perlindungan aktual tetap bergantung pada cara sistem menyimpan, memproses, dan mengakses data.</p>
        </div>

        <div class="privacy-grid">
            <article class="privacy-card">
                <span class="material-symbols-rounded">visibility_off</span>
                <h3>Pelaporan Anonim</h3>
                <p>Kamu dapat memilih pelaporan anonim jika fitur tersebut tersedia pada formulir laporan. Periksa pilihan pelaporan sebelum mengirimkan laporan.</p>
            </article>

            <article class="privacy-card">
                <span class="material-symbols-rounded">person</span>
                <h3>Informasi Identitas</h3>
                <p>Hindari memasukkan nama, alamat, nomor telepon, atau informasi pribadi lain ke dalam kronologi jika informasi tersebut tidak diperlukan.</p>
            </article>

            <article class="privacy-card">
                <span class="material-symbols-rounded">key</span>
                <h3>Report ID dan Access Code</h3>
                <p>Simpan kedua informasi ini di tempat aman. Jangan membagikan Access Code karena kode tersebut dapat digunakan untuk mengakses informasi laporan.</p>
            </article>

            <article class="privacy-card">
                <span class="material-symbols-rounded">attach_file</span>
                <h3>Bukti dan Lampiran</h3>
                <p>Pastikan bukti yang diunggah relevan dengan kejadian. Periksa apakah gambar atau dokumen memuat informasi pribadi yang tidak perlu dibagikan.</p>
            </article>
        </div>

        <section class="privacy-section">
            <h2>Hal yang perlu kamu ketahui</h2>
            <ul>
                <li>Berikan informasi yang relevan dan sesuai dengan kejadian yang dilaporkan.</li>
                <li>Jangan mengunggah kata sandi, data finansial, atau dokumen identitas yang tidak diperlukan.</li>
                <li>Simpan Report ID dan Access Code untuk kebutuhan pelacakan laporan.</li>
                <li>Gunakan kanal bantuan resmi jika memiliki pertanyaan tentang laporan atau data yang dikirimkan.</li>
            </ul>
        </section>

        <div class="help-cta">
            <div>
                <h3>Siap menyampaikan laporan?</h3>
                <p>Pastikan informasi yang disampaikan jelas dan relevan.</p>
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
