<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$locs = $pdo->query("
    SELECT
        l.id,
        l.province,
        l.city,
        l.district,
        l.subdistrict,
        l.address,
        l.latitude,
        l.longitude,

        COUNT(r.id) AS reports,

        COALESCE(
            SUM(r.risk_level = 'high'),
            0
        ) AS high_risk,

        COALESCE(
            SUM(r.risk_level = 'elevated'),
            0
        ) AS elevated_risk,

        COALESCE(
            SUM(r.risk_level = 'moderate'),
            0
        ) AS moderate_risk,

        COALESCE(
            SUM(r.risk_level = 'low'),
            0
        ) AS low_risk

    FROM locations l

    LEFT JOIN reports r
        ON r.location_id = l.id

    GROUP BY
        l.id,
        l.province,
        l.city,
        l.district,
        l.subdistrict,
        l.address,
        l.latitude,
        l.longitude

    ORDER BY reports DESC
")->fetchAll();

$attentionPerPage = 5;
$attentionTotal = count($locs);
$attentionTotalPages = max(1, (int) ceil($attentionTotal / $attentionPerPage));
$attentionPage = filter_input(INPUT_GET, 'attention_page', FILTER_VALIDATE_INT);
$attentionPage = ($attentionPage === false || $attentionPage === null) ? 1 : $attentionPage;
$attentionPage = min(max(1, $attentionPage), $attentionTotalPages);
$attentionOffset = ($attentionPage - 1) * $attentionPerPage;
$attentionLocations = array_slice($locs, $attentionOffset, $attentionPerPage);

$sum = $pdo->query("
    SELECT
        risk_level,
        COUNT(*) AS total
    FROM reports
    GROUP BY risk_level
")->fetchAll();

function getRiskLevel(array $location): string
{
    if ((int) $location['high_risk'] > 0) {
        return 'high';
    }

    if ((int) $location['elevated_risk'] > 0) {
        return 'elevated';
    }

    if ((int) $location['moderate_risk'] > 0) {
        return 'moderate';
    }

    return 'low';
}


function getRiskLabel(string $risk): string
{
    return match ($risk) {
        'high' => 'High Risk',
        'elevated' => 'Elevated Risk',
        'moderate' => 'Moderate Risk',
        default => 'Low Risk',
    };
}

$mapLocations = [];

foreach ($locs as $location) {

    if (
        $location['latitude'] === null ||
        $location['longitude'] === null
    ) {
        continue;
    }

    $risk = getRiskLevel($location);

    $mapLocations[] = [
        'id' => (int) $location['id'],

        'name' => trim(
            ($location['district'] ?? '') !== ''
                ? $location['district']
                : 'Titik Layanan Publik'
        ),

        'city' => $location['city'] ?? '',

        'district' => $location['district'] ?? '',

        'subdistrict' => $location['subdistrict'] ?? '',

        'address' => $location['address'] ?? '',

        'latitude' => (float) $location['latitude'],

        'longitude' => (float) $location['longitude'],

        'reports' => (int) $location['reports'],

        'risk' => $risk,

        'riskLabel' => getRiskLabel($risk),
    ];
}


admin_header(
    'Risk Map',
    'risk'
);

?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<div class="card risk-intro-card">

    <div class="risk-intro-top">
        <span class="eyebrow">
            <span class="material-symbols-rounded">map</span>
            Sistem Informasi Geospasial Akuntabilitas Publik
        </span>

        <span class="eyebrow risk-live-status">
            <span class="material-symbols-rounded">sync</span>
            Pembaruan Real-time ·
            <?= number_format(count($mapLocations)) ?>
            Titik Pantau
        </span>
    </div>

        <div>

            <h2>
                Visualisasi persebaran sinyal risiko pada titik pelayanan publik.
            </h2>

        </div>

</div>

<div
    class="risk-layout"
>

    <div class="card risk-map-panel">

        <div class="risk-map-toolbar">

            <div class="risk-map-toolbar-title">

                <strong>

                    <span class="material-symbols-rounded">
                        location_on
                    </span>

                    Persebaran Risiko Layanan

                </strong>

                <span>
                    Klik marker untuk melihat detail titik layanan.
                </span>

            </div>


            <span class="map-location-count">

                <span class="material-symbols-rounded">
                    pin_drop
                </span>

                <?= number_format(count($mapLocations)) ?>
                titik terpetakan

            </span>

        </div>


        <div
            id="riskMap"
            class="risk-map-container"
        ></div>

        <div class="risk-map-legend">

            <span class="risk-map-legend-title">
                Tingkat Risiko
            </span>


            <span class="risk-map-legend-item">

                <span class="risk-map-legend-dot low"></span>

                Low

            </span>


            <span class="risk-map-legend-item">

                <span class="risk-map-legend-dot moderate"></span>

                Moderate

            </span>


            <span class="risk-map-legend-item">

                <span class="risk-map-legend-dot elevated"></span>

                Elevated

            </span>


            <span class="risk-map-legend-item">

                <span class="risk-map-legend-dot high"></span>

                High

            </span>

        </div>

    </div>

    <div class="risk-sidebar">

        <div class="card risk-breakdown-card">

            <h2>
                Risk Breakdown
            </h2>


            <div class="risk-breakdown-grid">

                <?php

                $riskTypes = [
                    'low' => 0,
                    'moderate' => 0,
                    'elevated' => 0,
                    'high' => 0,
                ];

                foreach ($sum as $item) {

                    $riskLevel = strtolower(
                        $item['risk_level']
                    );

                    if (
                        array_key_exists(
                            $riskLevel,
                            $riskTypes
                        )
                    ) {
                        $riskTypes[$riskLevel] =
                            (int) $item['total'];
                    }
                }

                ?>


                <?php foreach ($riskTypes as $risk => $total): ?>

                    <div class="risk-breakdown-item risk-<?= e($risk) ?>">

                        <?= risk_badge($risk) ?>

                        <strong>
                            <?= number_format($total) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

        <div class="card attention-card">

            <div class="toolbar attention-toolbar">

                <h2>
                    Areas Requiring Attention
                </h2>

            </div>


            <div class="attention-list">

                <?php if (empty($attentionLocations)): ?>
                    <p class="muted" style="padding:16px 4px;">Belum ada data lokasi yang dapat ditampilkan.</p>
                <?php endif; ?>

                <?php foreach ($attentionLocations as $index => $location): ?>

                    <?php

                    $risk = getRiskLevel(
                        $location
                    );

                    $riskLabel =
                        getRiskLabel($risk);

                    ?>

                    <div class="attention-item">

                        <div class="attention-main">

                            <strong>

                                #<?= $attentionOffset + $index + 1 ?>

                                <?= e(
                                    $location['district']
                                    ?: 'Titik Layanan'
                                ) ?>

                            </strong>

                            <span>

                                <?= number_format(
                                    (int) $location['reports']
                                ) ?>

                                laporan ·

                                <?= e(
                                    $location['city']
                                ) ?>

                            </span>

                        </div>


                        <div class="attention-right">
                            <span class="attention-risk-badge <?= e($risk) ?>">
                                <?= e(match ($risk) {
                                    'high' => 'High',
                                    'elevated' => 'Elevated',
                                    'moderate' => 'Moderate',
                                    default => 'Low',
                                }) ?>
                            </span>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php if ($attentionTotalPages > 1): ?>
                <?php
                    $attentionUrl = static function (int $page): string {
                        $params = $_GET;
                        $params['attention_page'] = $page;
                        return '?' . http_build_query($params);
                    };
                ?>
                <div class="attention-pagination">
                    <span class="attention-page-label">
                        Halaman <?= $attentionPage ?> dari <?= $attentionTotalPages ?>
                    </span>
                    <div class="attention-page-actions">
                        <?php if ($attentionPage > 1): ?>
                            <a href="<?= e($attentionUrl($attentionPage - 1)) ?>" class="btn attention-page-button">‹ Sebelumnya</a>
                        <?php else: ?>
                            <span class="attention-page-button is-disabled" aria-disabled="true">‹ Sebelumnya</span>
                        <?php endif; ?>

                        <?php if ($attentionPage < $attentionTotalPages): ?>
                            <a href="<?= e($attentionUrl($attentionPage + 1)) ?>" class="btn btn-primary attention-page-button">Berikutnya ›</a>
                        <?php else: ?>
                            <span class="attention-page-button is-disabled" aria-disabled="true">Berikutnya ›</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<div
    class="card risk-table-card"
>

    <div class="toolbar">

        <div>

            <h2>
                Service Point Analysis Matrix
            </h2>

            <p class="muted">
                Ringkasan tingkat risiko pada setiap titik
                pelayanan yang terpantau.
            </p>

        </div>

    </div>


    <div class="table-wrap">

        <table class="table">

            <thead>

                <tr>

                    <th>
                        Satker / Loket Layanan
                    </th>

                    <th>
                        Volume
                    </th>

                    <th>
                        High
                    </th>

                    <th>
                        Elevated
                    </th>

                    <th>
                        Moderate
                    </th>

                    <th>
                        Low
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php foreach ($locs as $location): ?>

                    <tr>

                        <td>

                            <b>
                                <?= e(
                                    $location['district']
                                    ?: 'Titik Layanan'
                                ) ?>
                            </b>

                            <br>

                            <small>
                                <?= e(
                                    $location['city']
                                ) ?>
                            </small>

                        </td>


                        <td>

                            <?= number_format(
                                (int) $location['reports']
                            ) ?>

                            laporan

                        </td>


                        <td>
                            <?= number_format(
                                (int) $location['high_risk']
                            ) ?>
                        </td>


                        <td>
                            <?= number_format(
                                (int) $location['elevated_risk']
                            ) ?>
                        </td>


                        <td>
                            <?= number_format(
                                (int) $location['moderate_risk']
                            ) ?>
                        </td>


                        <td>
                            <?= number_format(
                                (int) $location['low_risk']
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const mapElement =
        document.getElementById('riskMap');


    if (!mapElement) {
        return;
    }

    const locations =
        <?= json_encode(
            $mapLocations,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>;

    const defaultCenter = [
        -6.200000,
        106.816666
    ];


    const map = L.map(
        'riskMap',
        {
            center: defaultCenter,
            zoom: 11,

            zoomControl: true,

            scrollWheelZoom: true,

            dragging: true,

            doubleClickZoom: true,

            touchZoom: true,

            boxZoom: true
        }
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,

            attribution:
                '&copy; OpenStreetMap contributors'
        }
    ).addTo(map);

    function createMarkerIcon(risk) {

        return L.divIcon({

            className: '',

            html: `
                <div class="sigap-map-marker ${risk}">
                    <span class="material-symbols-rounded">
                        location_on
                    </span>
                </div>
            `,

            iconSize: [34, 34],

            iconAnchor: [17, 17],

            popupAnchor: [0, -18]
        });

    }

    function createPopup(location) {

        const safeName =
            escapeHtml(
                location.name
            );

        const safeAddress =
            escapeHtml(
                location.address || 'Alamat tidak tersedia'
            );

        const safeCity =
            escapeHtml(
                location.city || ''
            );

        return `

            <div class="sigap-popup">

                <span class="sigap-popup-risk ${location.risk}">
                    ${escapeHtml(location.riskLabel)}
                </span>


                <h3>
                    ${safeName}
                </h3>


                <p class="sigap-popup-address">

                    ${safeAddress}

                    ${safeCity
                        ? '<br>' + safeCity
                        : ''
                    }

                </p>


                <div class="sigap-popup-stat">

                    <strong>
                        ${Number(location.reports).toLocaleString('id-ID')}
                    </strong>

                    <span>
                        laporan terdeteksi
                    </span>

                </div>


                <a
                    href="/admin/pattern.php"
                    class="sigap-popup-link"
                >
                    Lihat Analisis →
                </a>

            </div>

        `;

    }

    function escapeHtml(value) {

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }

    const bounds = [];


    locations.forEach(function (location) {

        const lat =
            Number(location.latitude);

        const lng =
            Number(location.longitude);


        if (
            !Number.isFinite(lat) ||
            !Number.isFinite(lng)
        ) {
            return;
        }


        const marker =
            L.marker(
                [lat, lng],
                {
                    icon:
                        createMarkerIcon(
                            location.risk
                        )
                }
            );


        marker
            .bindPopup(
                createPopup(location),
                {
                    maxWidth: 340,

                    minWidth: 280,

                    closeButton: true
                }
            )
            .addTo(map);


        bounds.push([
            lat,
            lng
        ]);

    });

    if (bounds.length > 0) {

        const mapBounds =
            L.latLngBounds(bounds);


        map.fitBounds(
            mapBounds,
            {
                padding: [40, 40],

                maxZoom: 13
            }
        );

    }

    setTimeout(function () {

        map.invalidateSize();

    }, 250);

});

</script>


<?php

admin_footer();

?>