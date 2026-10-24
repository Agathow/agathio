<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$sum = $pdo
    ->query("
        SELECT *
        FROM v_dashboard_summary
    ")
    ->fetch();

$dailyRows = $pdo
    ->query("
        SELECT
            DATE(created_at) AS d,
            COUNT(*) AS total
        FROM reports
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
          AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ")
    ->fetchAll(PDO::FETCH_ASSOC);

$dailyByDate = [];
foreach ($dailyRows as $row) {
    $dailyByDate[$row['d']] = (int) $row['total'];
}

$daily = [];
$periodStart = new DateTimeImmutable('today -29 days');
$periodEnd = new DateTimeImmutable('today');

for ($date = $periodStart; $date <= $periodEnd; $date = $date->modify('+1 day')) {
    $day = $date->format('Y-m-d');
    $daily[] = [
        'd' => $day,
        'total' => $dailyByDate[$day] ?? 0,
    ];
}

$patterns = $pdo
    ->query("
        SELECT
            ps.name,
            COUNT(*) AS total,
            COALESCE(
                SUM(r.discrepancy_amount),
                0
            ) AS diff
        FROM reports r
        LEFT JOIN public_services ps
            ON ps.id = r.service_id
        GROUP BY ps.id
        ORDER BY total DESC
        LIMIT 4
    ")
    ->fetchAll();

admin_header(
    'Government Intelligence',
    'dashboard'
);

?>

<style>
.dashboard-pattern-section {
    padding: 20px 0;
}

.dashboard-pattern-section > .toolbar {
    margin-bottom: 18px;
}

.dashboard-pattern-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    align-items: start;
}

.dashboard-pattern-card {
    height: auto !important;
    min-height: 0 !important;
    align-self: start;
    padding: 24px 26px;
}

.dashboard-pattern-card h2 {
    margin: 18px 0 8px;
}

.dashboard-pattern-card > .muted {
    margin-bottom: 18px;
}

.dashboard-pattern-card .pattern-stat {
    margin-top: 0;
}

.dashboard-risk-breakdown {
    padding: 24px 26px;
    margin-top: 4px;
}

.dashboard-risk-breakdown > .toolbar {
    margin-bottom: 18px;
    align-items: center;
}

.dashboard-risk-map-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border: 1px solid #BFEBD9;
    border-radius: 12px;
    background: #E4F8EF;
    color: #087653;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.3;
    text-decoration: none;
    white-space: nowrap;
    transition: background-color .2s ease, border-color .2s ease,
                color .2s ease, transform .2s ease, box-shadow .2s ease;
}

.dashboard-risk-map-link:hover {
    background: #087653;
    border-color: #087653;
    color: #FFFFFF;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(8, 118, 83, .16);
}

.dashboard-risk-map-link:focus-visible {
    outline: 3px solid rgba(8, 118, 83, .25);
    outline-offset: 3px;
}

@media (max-width: 900px) {
    .dashboard-pattern-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .dashboard-pattern-card {
        padding: 22px;
    }
}

@media (max-width: 560px) {
    .dashboard-pattern-section {
        padding: 14px 0;
    }

    .dashboard-pattern-section > .toolbar,
    .dashboard-risk-breakdown > .toolbar {
        align-items: flex-start;
        gap: 12px;
        flex-direction: column;
    }

    .dashboard-risk-breakdown {
        padding: 20px 16px;
    }

    .dashboard-risk-map-link {
        align-self: flex-start;
    }
}

</style>

<div class="kpi">

    <div class="card kpi-card">
        <div class="kpi-icon">
            <span class="material-symbols-rounded">assignment</span>
        </div>
        <div class="kpi-label">Total Laporan</div>
        <div class="kpi-number">
            <?= number_format($sum['total_reports']) ?>
        </div>
        <div class="muted">Akumulasi laporan warga terverifikasi</div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon">
            <span class="material-symbols-rounded">task_alt</span>
        </div>
        <div class="kpi-label">Laporan Ditindaklanjuti</div>
        <div class="kpi-number">
            <?= number_format($sum['active_reports']) ?>
        </div>
        <div class="muted">Rekomendasi &amp; inspeksi berjalan</div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon">
            <span class="material-symbols-rounded">pending_actions</span>
        </div>
        <div class="kpi-label">Dalam Peninjauan</div>
        <div class="kpi-number">
            <?= number_format($sum['pending_reports']) ?>
        </div>
        <div class="muted">Menunggu validasi data</div>
    </div>

    <div class="card kpi-card">
        <div class="kpi-icon">
            <span class="material-symbols-rounded">location_on</span>
        </div>
        <div class="kpi-label">Titik Layanan Terpantau</div>
        <div class="kpi-number">
            <?= number_format($sum['affected_locations']) ?>
        </div>
        <div class="muted">Terhubung sensor &amp; laporan warga</div>
    </div>

</div>

<div class="card chart-card">

    <div class="toolbar">
        <div>
            <h2>Tren Laporan Pungutan Tidak Resmi</h2>
            <p class="muted">
                Perubahan jumlah laporan berdasarkan periode pengamatan
            </p>
        </div>

        <span class="eyebrow">
            <span class="material-symbols-rounded">calendar_month</span>
            30 Hari
        </span>
    </div>

    <div class="chart-area">
        <canvas id="trend"></canvas>
    </div>

</div>

<div class="section dashboard-pattern-section">

    <div class="toolbar">
        <div>
            <h2>Pola yang Terdeteksi</h2>
            <p class="muted">
                Pola laporan yang membutuhkan perhatian lebih lanjut.
            </p>
        </div>

        <a class="btn btn-primary" href="/admin/pattern.php">
            Lihat Semua →
        </a>
    </div>

    <div class="pattern-grid dashboard-pattern-grid">

        <?php foreach ($patterns as $p): ?>

            <div class="card pattern-card dashboard-pattern-card">

                <div class="action-row">
                    <span class="badge badge-elevated">Elevated</span>
                    <span class="status">Requires Review</span>
                </div>

                <h2><?= e($p['name'] ?? 'Layanan tidak diketahui') ?></h2>

                <p class="muted">
                    <?= number_format((int) $p['total']) ?>
                    laporan terdeteksi pada periode analisis.
                </p>

                <div class="pattern-stat">

                    <div>
                        <small>Volume</small>
                        <b><?= number_format((int) $p['total']) ?></b>
                    </div>

                    <div>
                        <small>Selisih</small>
                        <b><?= rupiah($p['diff']) ?></b>
                    </div>

                    <div>
                        <small>Status</small>
                        <b>Review</b>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<div class="card dashboard-risk-breakdown">

    <div class="toolbar">
        <div>
            <h2>Risk Breakdown</h2>
        </div>

        <a class="dashboard-risk-map-link" href="/admin/risk_map.php">
            <span>Buka Risk Map</span>
            <span aria-hidden="true">→</span>
        </a>
    </div>

    <div class="grid-4">

        <div class="risk-box">
            <span class="badge badge-low">Low</span>
            <b><?= number_format((int) $sum['low_risk']) ?></b>
        </div>

        <div class="risk-box">
            <span class="badge badge-moderate">Moderate</span>
            <b><?= number_format((int) $sum['moderate_risk']) ?></b>
        </div>

        <div class="risk-box">
            <span class="badge badge-elevated">Elevated</span>
            <b><?= number_format((int) $sum['elevated_risk']) ?></b>
        </div>

        <div class="risk-box">
            <span class="badge badge-high">High</span>
            <b><?= number_format((int) $sum['high_risk']) ?></b>
        </div>

    </div>

</div> 

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const trendCanvas = document.getElementById('trend');

new Chart(trendCanvas, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($daily, 'd')) ?>,
        datasets: [{
            label: 'Total Laporan',
            data: <?= json_encode(array_map('intval', array_column($daily, 'total'))) ?>,
            tension: 0.35,
            fill: true,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    stepSize: 1
                }
            },
            x: {
                ticks: {
                    maxTicksLimit: 10
                }
            }
        }
    }
});
</script>

<?php
admin_footer();
?>