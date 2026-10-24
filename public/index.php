<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

?>

<!doctype html>
<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        SIGAP — Pelayanan Publik Harusnya Jelas.
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/home.css">

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,500,0,0"
    />

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
    >

    
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">


</head>

<body>

    <?php require_once __DIR__ . '/../config/navbar.php'; ?>

    <section class="hero">

        <div class="container hero-grid">

            <div class="hero-content">

                <div class="eyebrow">
                    <span class="material-symbols-rounded">support_agent</span>
                    Layanan Publik, Lebih Siap untuk Kamu
                </div>

                <h1>
                    Pelayanan Publik Harusnya
                    <u>Jelas.</u>
                </h1>

                <p>
                    Cek biaya resmi, pahami prosesnya, dan laporkan
                    pungutan tidak resmi dengan aman tanpa rasa khawatir.
                    Bersama mewujudkan birokrasi yang jujur, transparan,
                    dan manusiawi.
                </p>

                <div class="hero-actions">

                    <a
                        class="btn btn-primary"
                        href="/user/services.php"
                    >
                        Cek Layanan →
                    </a>

                    <a
                        class="btn btn-light"
                        href="/user/report.php"
                    >
                        Laporkan
                    </a>

                </div>

                <p class="hero-meta">

                    <span class="material-symbols-rounded">verified</span>
                    450+ tarif resmi terverifikasi

                    &nbsp;&nbsp;

                    <span class="material-symbols-rounded">lock</span>
                    100% anonim &amp; terenkripsi

                </p>

            </div>

            <div class="hero-visual">

                <div class="mascot-card">

                    <div class="mascot-copy">
                        <div class="mascot-title">
                            <span class="material-symbols-rounded">pets</span>
                            <strong>GAPI Siap Bantu Kamu!</strong>
                        </div>

                        <p>
                            "Hak kamu untuk mendapatkan layanan publik yang resmi, transparan, dan bebas dari pungutan liar."   
                        </p>
                    </div>

                    <div class="mascot-visual">
                        <img
                            src="/assets/images/maskot-sigap-index.png"
                            alt="Maskot SIGAP"
                            class="mascot-image"
                        >
                    </div>

                    <div class="mascot-footer">

                        <span class="mascot-status">
                            <span class="status-dot">●</span>
                            Sistem Aktif 24/7
                        </span>

                        <a
                            href="/user/report.php"
                            class="mascot-report-link"
                        >
                            Lapor Tanpa Cemas →
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>

    <section class="section">

        <div class="container">

            <div class="section-head">

                <span class="eyebrow">
                    Transparansi & Kemudahan
                </span>

                <h2 class="section-title">
                    Kenapa Harus SIGAP?
                </h2>

                <p class="muted">
                    SIGAP mendesentralisasi transparansi layanan publik,
                    memberikan kejelasan hak bagi setiap warga negara
                    secara independen dan aman.
                </p>

            </div>


            <div class="grid-3">

                <div class="card feature-card">

                    <div class="feature-icon">
                        <span class="material-symbols-outlined">search</span>
                    </div>

                    <h3>
                        Cek Layanan
                    </h3>

                    <p class="muted">
                        Ketahui persyaratan berkas, alur birokrasi,
                        dan biaya resmi sebelum melangkah ke kantor
                        layanan publik.
                    </p>

                    <a
                        class="btn btn-light btn-sm"
                        href="/user/services.php"
                    >
                        Pelajari Alur →
                    </a>

                </div>

                <div class="card feature-card">

                    <div class="feature-icon">
                        <span class="material-symbols-outlined">payments</span>
                    </div>

                    <h3>
                        Cek Biaya
                    </h3>

                    <p class="muted">
                        Bandingkan tarif yang diwajibkan oleh
                        undang-undang secara objektif dengan nominal
                        yang diminta.
                    </p>

                    <a
                        class="btn btn-light btn-sm"
                        href="/user/check_payment.php"
                    >
                        Hitung Estimasi →
                    </a>

                </div>

                <div class="card feature-card">

                    <div class="feature-icon">
                        <span class="material-symbols-outlined">campaign</span>
                    </div>

                    <h3>
                        Laporkan dengan Aman
                    </h3>

                    <p class="muted">
                        Laporkan dugaan pungutan tidak resmi tanpa
                        khawatir. Didukung perlindungan identitas.
                    </p>

                    <a
                        class="btn btn-light btn-sm"
                        href="/user/report.php"
                    >
                        Kirim Aduan →
                    </a>

                </div>

            </div>

        </div>

    </section>

    <section class="section">

        <div class="container">

            <div class="section-head">

                <span class="eyebrow">
                    Pengetahuan Lapangan
                </span>

                <h2 class="section-title">
                    Kenali Hak Kamu Saat Mengurus Layanan
                </h2>

            </div>


            <div class="grid-4">

                <div class="card knowledge-card">

                    <div class="knowledge-number">
                        01
                    </div>

                    <h3>
                        Ketahui Biaya Resmi
                    </h3>

                    <p class="muted">
                        Selalu cek tarif dasar sebelum membayar.
                    </p>

                </div>

                <div class="card knowledge-card">

                    <div class="knowledge-number">
                        02
                    </div>

                    <h3>
                        Pahami Prosedur
                    </h3>

                    <p class="muted">
                        Pastikan kamu memahami alur layanan.
                    </p>

                </div>

                <div class="card knowledge-card">

                    <div class="knowledge-number">
                        03
                    </div>

                    <h3>
                        Minta Bukti Sah
                    </h3>

                    <p class="muted">
                        Setiap pembayaran wajib memiliki bukti.
                    </p>

                </div>

                <div class="card knowledge-card">

                    <div class="knowledge-number">
                        04
                    </div>

                    <h3>
                        Laporkan Kejanggalan
                    </h3>

                    <p class="muted">
                        Gunakan SIGAP untuk membuat laporan aman.
                    </p>

                </div>

            </div>

        </div>

    </section>

    <section class="section">

        <div class="container">

            <div class="card cta-card">

                <div class="cta-content">

                    <div>

                        <span class="eyebrow">
                            Pusat Pengetahuan Administrasi
                        </span>

                        <h2>
                            Sudah tahu layanan yang kamu butuhkan?
                        </h2>

                        <p class="muted">
                            Jelajahi ratusan prosedur dan tarif resmi
                            pemerintah.
                        </p>

                    </div>


                    <div class="cta-actions">

                        <a
                            class="btn btn-primary"
                            href="/user/services.php"
                        >
                            Cari Layanan Sekarang
                        </a>

                        <a
                            class="btn btn-white"
                            href="/user/check_payment.php"
                        >
                            Simulasi Biaya
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>

    <?php require_once __DIR__ . '/../config/footer.php'; ?>

</body>

</html>