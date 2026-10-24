<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $reportCode = trim($_POST['report_code'] ?? '');
    $accessCode = trim($_POST['access_code'] ?? '');

    $statement = $pdo->prepare(
        "
        SELECT
            r.*,
            ps.name AS service,
            l.city,
            l.district
        FROM reports r

        LEFT JOIN public_services ps
            ON ps.id = r.service_id

        LEFT JOIN locations l
            ON l.id = r.location_id

        WHERE r.report_code = ?
          AND r.access_code = ?
        "
    );

    $statement->execute([
        $reportCode,
        $accessCode,
    ]);

    $report = $statement->fetch();
}

?>

<!doctype html>
<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Cek Status Laporan — SIGAP</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/track.css">

    
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>

<body>

    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>

    <main class="form-page">

        <div class="container">

            <div class="form-card">

                <div class="card">

                    <div class="auth-header">

                        <span class="eyebrow">
                            Status Pengaduan
                        </span>

                        <h1>
                            Cek Status Laporan
                        </h1>

                        <p class="muted">
                            Masukkan Report ID dan Access Code yang kamu
                            simpan saat membuat laporan.
                        </p>

                    </div>

                    <form method="post">

                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <div class="form-group">

                            <label for="report_code">
                                Report ID
                            </label>

                            <input
                                id="report_code"
                                class="input"
                                type="text"
                                name="report_code"
                                placeholder="SIGAP-26-001284"
                                value="<?= e($_POST['report_code'] ?? '') ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="access_code">
                                Access Code
                            </label>

                            <input
                                id="access_code"
                                class="input"
                                type="text"
                                name="access_code"
                                placeholder="X7K9-PQ28"
                                value="<?= e($_POST['access_code'] ?? '') ?>"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary btn-full"
                        >
                            Lihat Status →
                        </button>

                    </form>

                    <?php if ($report): ?>

                        <hr class="form-divider">


                        <div class="notice report-result">

                            <b class="report-code">
                                <?= e($report['report_code']) ?>
                            </b>

                            <h2>
                                <?= e($report['title']) ?>
                            </h2>

                            <p>
                                <strong>Layanan:</strong>
                                <?= e($report['service'] ?? '-') ?>
                            </p>

                            <p>
                                <strong>Lokasi:</strong>

                                <?= e(
                                    trim(
                                        ($report['district'] ?? '') .
                                        ', ' .
                                        ($report['city'] ?? ''),
                                        ', '
                                    )
                                ) ?>
                            </p>

                            <p>

                                <strong>Status:</strong>

                                <span class="status">
                                    <?= e(
                                        status_label(
                                            $report['status']
                                        )
                                    ) ?>
                                </span>

                            </p>

                            <p>

                                <strong>Risiko:</strong>

                                <?= risk_badge(
                                    $report['risk_level']
                                ) ?>

                            </p>

                        </div>


                    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>

                        <div class="notice notice-error">

                            Report ID atau Access Code
                            tidak ditemukan.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

    <?php require_once __DIR__ . '/../../config/footer.php'; ?>

</body>

</html>