<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$rows = $pdo
    ->query("
        SELECT
            ps.id,
            ps.name,
            COUNT(r.id) AS volume,
            COUNT(DISTINCT r.location_id) AS points,
            COALESCE(
                SUM(r.discrepancy_amount),
                0
            ) AS discrepancy
        FROM public_services ps
        LEFT JOIN reports r
            ON r.service_id = ps.id
        GROUP BY ps.id
        ORDER BY volume DESC
    ")
    ->fetchAll();

$types = $pdo
    ->query("
        SELECT
            incident_type,
            COUNT(*) AS total
        FROM reports
        GROUP BY incident_type
        ORDER BY total DESC
    ")
    ->fetchAll();

$totalReports = array_sum(
    array_column(
        $rows,
        'volume'
    )
);

$totalTypes = count($types);

$detectedClusters = count($rows);

$grandTotal = max(
    1,
    array_sum(
        array_column(
            $types,
            'total'
        )
    )
);

admin_header(
    'Pattern Detection',
    'pattern'
);

?>

<div class="card pattern-intro-card">

    <div class="toolbar">

        <div>

            <span class="eyebrow">

                <span class="material-symbols-rounded">
                    hub
                </span>

                Algoritma Deteksi Korelasi Berulang v2.4

            </span>


            <h2>
                Temukan pola berulang dari laporan masyarakat
            </h2>


            <p class="muted">
                Identifikasi area pelayanan yang membutuhkan
                perhatian audit dan mitigasi proaktif.
            </p>

        </div>


        <span class="eyebrow">

            <span class="material-symbols-rounded">
                account_tree
            </span>

            Layanan Terpetakan
            <?= number_format($detectedClusters) ?>

        </span>

    </div>

    <div class="grid-4 pattern-summary">
        <div class="risk-box">

            <small>
                Total Reports
            </small>

            <b>
                <?= number_format($totalReports) ?>
            </b>

        </div>

        <div class="risk-box">

            <small>
                Report Types
            </small>

            <b>
                <?= number_format($totalTypes) ?>
            </b>

        </div>

        <div class="risk-box">

            <small>
                Detected Clusters
            </small>

            <b>
                <?= number_format($detectedClusters) ?>
            </b>

        </div>

        <div class="risk-box">

            <small>
                Analysis Window
            </small>

            <b>
                30 days
            </b>

        </div>

    </div>

</div>

<div
    class="pattern-grid"
    style="margin-top: 18px"
>

    <?php foreach ($rows as $r): ?>

        <div class="card pattern-card">

            <div class="action-row">

                <span class="badge badge-elevated">
                    Elevated
                </span>

                <span class="status">
                    Requires Review
                </span>

            </div>

            <small class="pattern-id">

                KLASTER POLA #SIG-
                <?= str_pad(
                    (string) $r['id'],
                    3,
                    '0',
                    STR_PAD_LEFT
                ) ?>

            </small>

            <h2>
                <?= e($r['name']) ?>
            </h2>


            <p class="muted">
                Pola laporan berulang dengan indikasi
                anomali pada titik layanan publik.
            </p>


            <div class="pattern-stat">

                <div>

                    <small>
                        Volume
                    </small>

                    <b>
                        <?= number_format($r['volume']) ?>
                    </b>

                    <span class="muted">
                        laporan
                    </span>

                </div>

                <div>

                    <small>
                        Titik
                    </small>

                    <b>
                        <?= number_format($r['points']) ?>
                    </b>

                    <span class="muted">
                        layanan
                    </span>

                </div>

                <div>

                    <small>
                        Selisih
                    </small>

                    <b>
                        <?= rupiah($r['discrepancy']) ?>
                    </b>

                </div>

            </div>

            <div class="pattern-card-action">

                <a
                    class="btn btn-primary btn-sm"
                    href="/admin/pattern_detail.php?service=<?= $r['id'] ?>"
                >
                    Buka Detail Pola &amp; Analisis →
                </a>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<div class="card pattern-distribution-card">

    <div class="toolbar">

        <div>

            <h2>
                Distribusi Laporan berdasarkan Kategori
            </h2>

            <p class="muted">
                Distribusi laporan berdasarkan jenis insiden
                yang tercatat dalam sistem.
            </p>

        </div>


        <span class="eyebrow">

            <?= number_format($grandTotal) ?>
            Laporan

        </span>

    </div>

    <div class="distribution-list">

        <?php foreach ($types as $index => $t): ?>

            <?php

            $percentage = (
                $t['total'] / $grandTotal
            ) * 100;

            ?>


            <div class="distribution-item">

                <div class="distribution-header">

                    <div class="distribution-name">

                        <span class="distribution-rank">

                            <?= str_pad(
                                $index + 1,
                                2,
                                '0',
                                STR_PAD_LEFT
                            ) ?>

                        </span>


                        <strong>

                            <?= e(
                                status_label(
                                    $t['incident_type']
                                )
                            ) ?>

                        </strong>

                    </div>

                    <div class="distribution-value">

                        <strong>

                            <?= number_format(
                                $t['total']
                            ) ?>

                        </strong>


                        <span>
                            laporan
                        </span>


                        <em>

                            <?= number_format(
                                $percentage,
                                1
                            ) ?>%

                        </em>

                    </div>

                </div>

                <div class="progress distribution-progress">

                    <i
                        style="
                            width: <?= min(
                                100,
                                $percentage
                            ) ?>%;
                        "
                    ></i>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>


<?php

admin_footer();

?>