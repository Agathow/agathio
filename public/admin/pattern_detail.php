<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$sid = (int) ($_GET['service'] ?? 0);

$st = $pdo->prepare("
    SELECT
        ps.*,
        sc.name AS category
    FROM public_services ps
    JOIN service_categories sc
        ON sc.id = ps.category_id
    WHERE ps.id = ?
");

$st->execute([$sid]);

$s = $st->fetch();

if (!$s) {
    exit('Pattern tidak ditemukan.');
}

$period = $_GET['period'] ?? '30d';

$allowedPeriods = [
    '7d',
    '30d',
    'quarter',
    '1y'
];

if (!in_array($period, $allowedPeriods, true)) {
    $period = '30d';
}

switch ($period) {

    case '7d':
        $dateCondition = "
            r.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        ";
        $periodLabel = '7 Hari';
        break;

    case 'quarter':
        $dateCondition = "
            r.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ";
        $periodLabel = 'Kuartal';
        break;

    case '1y':
        $dateCondition = "
            r.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        ";
        $periodLabel = '1 Tahun';
        break;

    case '30d':
    default:
        $dateCondition = "
            r.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        ";
        $periodLabel = '30 Hari';
        break;
}

$st = $pdo->prepare("
    SELECT
        r.*,
        l.city,
        l.district
    FROM reports r
    LEFT JOIN locations l
        ON l.id = r.location_id
    WHERE r.service_id = ?
    ORDER BY r.created_at DESC
");

$st->execute([$sid]);

$rows = $st->fetchAll();

$st = $pdo->prepare("
    SELECT
        DATE(r.created_at) AS report_date,
        COUNT(*) AS total
    FROM reports r
    WHERE r.service_id = ?
      AND $dateCondition
    GROUP BY DATE(r.created_at)
    ORDER BY report_date ASC
");

$st->execute([$sid]);

$chartRows = $st->fetchAll();

$loc = $pdo->prepare("
    SELECT
        l.id,
        l.district,
        l.city,
        COUNT(r.id) AS total
    FROM reports r
    JOIN locations l
        ON l.id = r.location_id
    WHERE r.service_id = ?
    GROUP BY
        l.id,
        l.district,
        l.city
    ORDER BY total DESC
");

$loc->execute([$sid]);

$locations = $loc->fetchAll();

$incidentTypes = [
    'additional_fee',
    'no_receipt',
    'delay',
    'broker',
    'other'
];

$incidentBreakdown = [];

foreach ($incidentTypes as $type) {

    $incidentBreakdown[$type] = count(
        array_filter(
            $rows,
            fn ($r) => $r['incident_type'] === $type
        )
    );
}

$totalReports = count($rows);

$totalDiscrepancy = array_sum(
    array_map(
        fn ($r) => (float) ($r['discrepancy_amount'] ?? 0),
        $rows
    )
);

$averageDiscrepancy = $totalReports > 0
    ? $totalDiscrepancy / $totalReports
    : 0;

switch ($period) {

    case '7d':
        $currentInterval = 7;
        break;

    case 'quarter':
        $currentInterval = 90;
        break;

    case '1y':
        $currentInterval = 365;
        break;

    case '30d':
    default:
        $currentInterval = 30;
        break;
}

$velocitySql = "
    SELECT
        SUM(
            CASE
                WHEN created_at >= DATE_SUB(
                    CURDATE(),
                    INTERVAL {$currentInterval} DAY
                )
                THEN 1
                ELSE 0
            END
        ) AS current_total,

        SUM(
            CASE
                WHEN created_at >= DATE_SUB(
                    CURDATE(),
                    INTERVAL " . ($currentInterval * 2) . " DAY
                )
                AND created_at < DATE_SUB(
                    CURDATE(),
                    INTERVAL {$currentInterval} DAY
                )
                THEN 1
                ELSE 0
            END
        ) AS previous_total

    FROM reports

    WHERE service_id = ?
";

$st = $pdo->prepare($velocitySql);

$st->execute([$sid]);

$velocityData = $st->fetch();

$currentTotal = (int) (
    $velocityData['current_total'] ?? 0
);

$previousTotal = (int) (
    $velocityData['previous_total'] ?? 0
);

if ($previousTotal > 0) {

    $velocityChange =
        (
            ($currentTotal - $previousTotal)
            / $previousTotal
        ) * 100;

} elseif ($currentTotal > 0) {

    $velocityChange = 100;

} else {

    $velocityChange = 0;
}

if ($velocityChange >= 50) {

    $velocityLabel = 'High';

} elseif ($velocityChange >= 20) {

    $velocityLabel = 'Elevated';

} elseif ($velocityChange > 0) {

    $velocityLabel = 'Moderate';

} else {

    $velocityLabel = 'Stable';
}

$recommendations = [];

if (($incidentBreakdown['additional_fee'] ?? 0) > 0) {

    $recommendations[] = [
        'title' => 'Perkuat transparansi tarif',
        'description' =>
            'Tampilkan tarif resmi secara digital dan mudah terlihat '
            . 'pada titik layanan untuk mengurangi potensi biaya tambahan.'
    ];
}

if (($incidentBreakdown['no_receipt'] ?? 0) > 0) {

    $recommendations[] = [
        'title' => 'Wajibkan bukti transaksi',
        'description' =>
            'Pastikan setiap transaksi menghasilkan bukti pembayaran '
            . 'yang dapat diverifikasi oleh masyarakat.'
    ];
}

if (($incidentBreakdown['broker'] ?? 0) > 0) {

    $recommendations[] = [
        'title' => 'Perketat pengawasan pihak ketiga',
        'description' =>
            'Perkuat kontrol akses dan pengawasan terhadap pihak '
            . 'yang berinteraksi dengan pemohon di sekitar titik layanan.'
    ];
}

if (($incidentBreakdown['delay'] ?? 0) > 0) {

    $recommendations[] = [
        'title' => 'Evaluasi waktu pelayanan',
        'description' =>
            'Lakukan pemeriksaan terhadap antrean dan waktu proses '
            . 'untuk mengidentifikasi potensi hambatan pelayanan.'
    ];
}


if ($averageDiscrepancy > 0) {

    $recommendations[] = [
        'title' => 'Lakukan verifikasi transaksi',
        'description' =>
            'Periksa transaksi dengan selisih biaya yang signifikan '
            . 'terhadap tarif resmi layanan.'
    ];
}

if ($velocityLabel === 'High') {

    $recommendations[] = [
        'title' => 'Prioritaskan inspeksi lapangan',
        'description' =>
            'Peningkatan laporan yang cepat menunjukkan perlunya '
            . 'verifikasi lapangan dan pemantauan lebih intensif.'
    ];
}

if (empty($recommendations)) {

    $recommendations[] = [
        'title' => 'Lanjutkan pemantauan',
        'description' =>
            'Belum ditemukan sinyal anomali dominan. '
            . 'Pertahankan pemantauan berkala terhadap layanan ini.'
    ];
}

admin_header(
    'Pattern Detail — ' . $s['name'],
    'pattern'
);

?>

<div class="pattern-detail-page">

    <div class="card pattern-intro">

        <div class="toolbar">

            <div>

                <span class="badge badge-elevated">
                    Elevated Risk Signal
                </span>

                <h2>
                    Analisis Mendalam Korelasi Anomali
                </h2>

                <p class="muted">
                    Analisis pola laporan pada layanan
                    <strong>
                        <?= e($s['name']) ?>
                    </strong>
                    untuk membantu proses verifikasi dan mitigasi.
                </p>

            </div>

            <span class="eyebrow">
                <?= e($s['category']) ?>
            </span>

        </div>


        <div class="grid-4">

            <div class="risk-box">

                <small>
                    Total Reports in Cluster
                </small>

                <b>
                    <?= number_format($totalReports) ?>
                </b>

                <span class="muted">
                    laporan dianalisis
                </span>

            </div>

            <div class="risk-box">

                <small>
                    Affected Service Points
                </small>

                <b>
                    <?= number_format(count($locations)) ?>
                </b>

                <span class="muted">
                    titik layanan terdampak
                </span>

            </div>

            <div class="risk-box">

                <small>
                    Average Discrepancy
                </small>

                <b>
                    <?= rupiah($averageDiscrepancy) ?>
                </b>

                <span class="muted">
                    rata-rata selisih transaksi
                </span>

            </div>

            <div class="risk-box">

                <small>
                    Risk Velocity
                </small>

                <b>
                    <?= e($velocityLabel) ?>
                </b>

                <span class="muted">

                    <?php if ($velocityChange > 0): ?>

                        +<?= number_format($velocityChange, 1) ?>%
                        dibanding periode sebelumnya

                    <?php elseif ($velocityChange < 0): ?>

                        <?= number_format(abs($velocityChange), 1) ?>%
                        lebih rendah

                    <?php else: ?>

                        Tidak ada perubahan signifikan

                    <?php endif; ?>

                </span>

            </div>

        </div>

    </div>

    <div class="detail-grid">

        <div>

            <div class="card pattern-chart-card">

                <div class="pattern-chart-header">

                    <div>

                        <h2>
                            Tren Laporan Anomali
                        </h2>

                        <p class="muted">
                            Perubahan jumlah laporan berdasarkan
                            periode pengamatan.
                        </p>

                    </div>


                    <form
                        method="get"
                        class="pattern-chart-filter"
                    >

                        <input
                            type="hidden"
                            name="service"
                            value="<?= $sid ?>"
                        >

                        <select
                            class="select"
                            name="period"
                            onchange="this.form.submit()"
                        >

                            <option
                                value="7d"
                                <?= $period === '7d'
                                    ? 'selected'
                                    : '' ?>
                            >
                                7 Hari
                            </option>

                            <option
                                value="30d"
                                <?= $period === '30d'
                                    ? 'selected'
                                    : '' ?>
                            >
                                30 Hari
                            </option>

                            <option
                                value="quarter"
                                <?= $period === 'quarter'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Kuartal
                            </option>

                            <option
                                value="1y"
                                <?= $period === '1y'
                                    ? 'selected'
                                    : '' ?>
                            >
                                1 Tahun
                            </option>

                        </select>

                    </form>

                </div>


                <div class="pattern-chart-wrapper">

                    <canvas id="patternChart"></canvas>

                </div>

            </div>

            <div class="card pattern-side-card">

                <div>

                    <h2>
                        Report Breakdown
                    </h2>

                    <p class="muted">
                        Komposisi jenis anomali yang ditemukan
                        dalam cluster ini.
                    </p>

                </div>


                <div class="pattern-breakdown">

                    <?php foreach (
                        $incidentBreakdown
                        as $type => $count
                    ): ?>

                        <?php

                        $percentage = $totalReports > 0
                            ? ($count / $totalReports) * 100
                            : 0;

                        ?>

                        <div class="pattern-breakdown-item">

                            <div>

                                <strong>
                                    <?= e(
                                        status_label($type)
                                    ) ?>
                                </strong>

                                <span class="muted">
                                    <?= number_format($count) ?>
                                    laporan
                                </span>

                            </div>


                            <div class="progress">

                                <i
                                    style="
                                        width:<?= min(
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

        </div>

        <div>
            <div class="card pattern-side-card">

                <div>

                    <h2>
                        Geographic Distribution
                    </h2>

                    <p class="muted">
                        Sebaran laporan berdasarkan titik layanan.
                    </p>

                </div>


                <?php if ($locations): ?>

                    <div class="pattern-location-list">

                        <?php foreach (
                            $locations
                            as $index => $location
                        ): ?>

                            <div class="pattern-location-item">

                                <div class="pattern-location-main">

                                    <div class="pattern-location-name">

                                        <span class="pattern-location-rank">
                                            <?= $index + 1 ?>
                                        </span>

                                        <?= e(
                                            $location['district']
                                        ) ?>

                                    </div>


                                    <div class="pattern-location-city">
                                        <?= e(
                                            $location['city']
                                        ) ?>
                                    </div>

                                </div>


                                <div class="pattern-location-count">

                                    <strong>
                                        <?= number_format(
                                            $location['total']
                                        ) ?>
                                    </strong>

                                    <span>
                                        laporan
                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="notice">
                        Belum ada data lokasi untuk cluster ini.
                    </div>

                <?php endif; ?>

            </div>

            <div class="card pattern-side-card">

                <div>

                    <h2>
                        Recommended Preventive Actions
                    </h2>

                    <p class="muted">
                        Rekomendasi tindakan berdasarkan pola
                        laporan yang terdeteksi.
                    </p>

                </div>


                <div class="recommendation-list">

                    <?php foreach (
                        $recommendations
                        as $index => $recommendation
                    ): ?>

                        <div class="recommendation-item">

                            <div class="recommendation-number">
                                <?= $index + 1 ?>
                            </div>


                            <div class="recommendation-content">

                                <strong class="recommendation-title">
                                    <?= e(
                                        $recommendation['title']
                                    ) ?>
                                </strong>

                                <p class="recommendation-description">
                                    <?= e(
                                        $recommendation['description']
                                    ) ?>
                                </p>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const chartLabels = <?= json_encode(
    array_column(
        $chartRows,
        'report_date'
    )
) ?>;

const chartValues = <?= json_encode(
    array_map(
        'intval',
        array_column(
            $chartRows,
            'total'
        )
    )
) ?>;

const chartCanvas =
    document.getElementById('patternChart');


if (chartCanvas) {

    new Chart(
        chartCanvas,
        {

            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [

                    {

                        label: 'Jumlah Laporan',

                        data: chartValues,

                        tension: 0.35,

                        fill: true,

                        borderWidth: 2,

                        pointRadius: 4,

                        pointHoverRadius: 6

                    }

                ]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {

                    intersect: false,

                    mode: 'index'

                },


                plugins: {

                    legend: {

                        display: false

                    },


                    tooltip: {

                        callbacks: {

                            label: function (context) {

                                return (
                                    ' ' +
                                    context.parsed.y +
                                    ' laporan'
                                );

                            }

                        }

                    }

                },


                scales: {

                    x: {

                        grid: {

                            display: false

                        },

                        ticks: {

                            maxTicksLimit: 8

                        }

                    },


                    y: {

                        beginAtZero: true,

                        ticks: {

                            precision: 0

                        }

                    }

                }

            }

        }
    );

}

</script>


<?php

admin_footer();

?>