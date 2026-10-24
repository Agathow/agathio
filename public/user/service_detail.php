<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$serviceId = (int) ($_GET['id'] ?? 0);

$statement = $pdo->prepare(
    "
    SELECT
        ps.*,
        sc.name AS category
    FROM public_services ps
    JOIN service_categories sc
        ON sc.id = ps.category_id
    WHERE ps.id = ?
    "
);

$statement->execute([$serviceId]);

$service = $statement->fetch();

if (!$service) {
    exit('Layanan tidak ditemukan.');
}

$requirements = array_filter(
    array_map(
        'trim',
        preg_split(
            '/[;\n]+/',
            $service['requirements'] ?? ''
        )
    )
);

$procedure = array_filter(
    array_map(
        'trim',
        preg_split(
            '/[;.\n]+/',
            $service['procedure_text'] ?? ''
        )
    )
);

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
        <?= e($service['name']) ?> — SIGAP
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/service_detail.css">

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>

<body>

    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>

    <main>

        <section class="section">

            <div class="container">

                <div class="service-detail-header">

                    <span class="eyebrow">
                        <?= e($service['category']) ?>
                    </span>

                    <h1 class="page-title">
                        <?= e($service['name']) ?>
                    </h1>

                    <p class="muted">
                        <?= e($service['institution']) ?>
                    </p>

                </div>

                <div class="detail-grid">

                    <div>


                        <div class="card">

                            <div class="three-col">

                                <div class="detail-stat">

                                    <small>
                                        Tarif Resmi Pemerintah
                                    </small>

                                    <h2>
                                        <?= rupiah(
                                            $service['official_fee']
                                        ) ?>
                                    </h2>

                                </div>

                                <div class="detail-stat">

                                    <small>
                                        Estimasi Durasi
                                    </small>

                                    <h2>
                                        <?= e(
                                            (string) $service['duration_minutes']
                                        ) ?>

                                        Menit
                                    </h2>

                                </div>

                                <div class="detail-stat">

                                    <small>
                                        Dasar Regulasi
                                    </small>

                                    <h3>
                                        <?= e(
                                            $service['regulation']
                                        ) ?>
                                    </h3>

                                </div>

                            </div>

                        </div>

                        <div class="card detail-card">

                            <h2>
                                Persyaratan Berkas Wajib
                            </h2>

                            <?php if ($requirements): ?>

                                <ul class="detail-list">

                                    <?php foreach ($requirements as $requirement): ?>

                                        <li>
                                            <?= e($requirement) ?>
                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            <?php else: ?>

                                <p class="muted">
                                    Tidak ada persyaratan yang tercatat.
                                </p>

                            <?php endif; ?>

                        </div>

                        <div class="card detail-card">

                            <h2>
                                Alur Prosedur Pelayanan
                            </h2>

                            <?php if ($procedure): ?>

                                <ol class="detail-list procedure-list">

                                    <?php foreach ($procedure as $step): ?>

                                        <li>
                                            <?= e($step) ?>
                                        </li>

                                    <?php endforeach; ?>

                                </ol>

                            <?php else: ?>

                                <p class="muted">
                                    Prosedur pelayanan belum tersedia.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                    <aside>

                        <div class="card detail-action-card">

                            <h3>
                                ⚠ Ketahui Sebelum Membayar
                            </h3>

                            <p class="muted">
                                Pastikan biaya yang diminta sesuai tarif
                                resmi dan selalu minta bukti pembayaran.
                            </p>

                            <a
                                class="btn btn-primary"
                                href="/user/check_payment.php"
                            >
                                Cek Biaya →
                            </a>

                        </div>

                        <div class="card detail-action-card">

                            <h3>
                                Temukan Kejanggalan?
                            </h3>

                            <p class="muted">
                                Laporkan dengan aman melalui SIGAP.
                            </p>

                            <a
                                class="btn btn-primary"
                                href="/user/report.php"
                            >
                                Laporkan →
                            </a>

                        </div>

                    </aside>

                </div>

            </div>

        </section>

    </main>

    <?php require_once __DIR__ . '/../../config/footer.php'; ?>

</body>

</html>