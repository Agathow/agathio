<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$services = $pdo
    ->query(
        "
        SELECT
            id,
            name,
            official_fee
        FROM public_services
        WHERE status = 'active'
        ORDER BY name
        "
    )
    ->fetchAll();

$locations = $pdo
    ->query(
        "
        SELECT
            id,
            city,
            district
        FROM locations
        ORDER BY city, district
        "
    )
    ->fetchAll();

$created = null;
$error = null;

$incidentTypeLabels = [
    'additional_fee' =>
        'Diminta biaya tambahan di luar tarif resmi',

    'no_receipt' =>
        'Diminta membayar tunai tanpa kuitansi / tanda terima',

    'delay' =>
        'Pelayanan sengaja diperlambat jika tidak memberi uang',

    'broker' =>
        'Diarahkan melalui perantara / calo tidak resmi',

    'other' =>
        'Lainnya',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $serviceId = (int) (
        $_POST['service_id'] ?? 0
    );

    $locationId = (int) ($_POST['location_id'] ?? 0);
    $latitudeInput = trim((string) ($_POST['latitude'] ?? ''));
    $longitudeInput = trim((string) ($_POST['longitude'] ?? ''));
    $mapProvince = trim((string) ($_POST['map_province'] ?? ''));
    $mapCity = trim((string) ($_POST['map_city'] ?? ''));
    $mapDistrict = trim((string) ($_POST['map_district'] ?? ''));
    $mapSubdistrict = trim((string) ($_POST['map_subdistrict'] ?? ''));
    $mapAddress = trim((string) ($_POST['map_address'] ?? ''));
    $hasMapPoint = $latitudeInput !== '' && $longitudeInput !== '';

    $incidentType = trim(
        $_POST['incident_type'] ?? ''
    );

    $title = trim(
        $_POST['title'] ?? ''
    );

    $chronology = trim(
        $_POST['chronology'] ?? ''
    );

    $requestedAmountInput = max(
        0,
        (float) (
            $_POST['requested_amount'] ?? 0
        )
    );

    $discrepancyAmount = 0;
    $requestedAmount = $requestedAmountInput;
    $officialAmount = 0;

    $incidentAt = !empty($_POST['incident_at'])
        ? $_POST['incident_at']
        : null;

    if (
        $serviceId <= 0 ||
        (!$hasMapPoint && $locationId <= 0) ||
        $title === '' ||
        $chronology === ''
    ) {

        $error =
            'Mohon lengkapi seluruh field yang wajib diisi.';
    }

    if ($error === null && $hasMapPoint) {
        if (!is_numeric($latitudeInput) || !is_numeric($longitudeInput)) {
            $error = 'Koordinat peta tidak valid. Silakan pilih titik lokasi kembali.';
        } else {
            $latitude = (float) $latitudeInput;
            $longitude = (float) $longitudeInput;
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                $error = 'Koordinat lokasi berada di luar rentang yang valid.';
            }
        }
        if ($error === null && $mapCity === '' && $mapAddress === '') {
            $error = 'Alamat titik peta belum berhasil ditemukan. Coba pilih titik lagi atau gunakan lokasi terdaftar.';
        }
    }

    if (
        $error === null &&
        mb_strlen($chronology) < 20
    ) {

        $error =
            'Kronologi kejadian minimal 20 karakter.';
    }

    if ($error === null) {

        $statement = $pdo->prepare(
            "
            SELECT
                official_fee
            FROM public_services
            WHERE id = ?
            AND status = 'active'
            LIMIT 1
            "
        );

        $statement->execute([
            $serviceId
        ]);

        $officialFee = $statement->fetchColumn();

        if ($officialFee === false) {

            $error =
                'Layanan yang dipilih tidak ditemukan.';

        } else {

            $officialAmount = (float) $officialFee;
        }
    }

    if ($error === null) {

        $discrepancyAmount = max(
            0,
            $requestedAmountInput - $officialAmount
        );

        $requestedAmount = $requestedAmountInput;
    }

    if ($error === null) {

        $riskLevel =
            $discrepancyAmount >= 100000
                ? 'high'
                : (
                    $discrepancyAmount >= 50000
                        ? 'elevated'
                        : 'moderate'
                );
    }


$userId =
    $_SESSION['user']['id'] ?? null;

    if ($userId) {

        $reportMode = $_POST['report_mode'] ?? 'anonymous';

        if (
            !in_array(
                $reportMode,
                ['anonymous', 'account'],
                true
            )
        ) {
            $reportMode = 'anonymous';
        }

    } else {

        $reportMode = 'anonymous';
    }


    $isAnonymous = (
        $reportMode === 'anonymous'
    ) ? 1 : 0;

    $uploadedFiles = [];

    if (
        $error === null &&
        isset($_FILES['evidence']) &&
        is_array($_FILES['evidence']['name'])
    ) {

        $allowedTypes = [
            'image/jpeg' =>
                'jpg',

            'image/png' =>
                'png',

            'application/pdf' =>
                'pdf',
        ];

        $maxFileSize =
            5 * 1024 * 1024;

        $maxFiles =
            5;

        $fileCount = count(
            array_filter(
                $_FILES['evidence']['name'],
                static fn ($name) =>
                    trim((string) $name) !== ''
            )
        );

        if ($fileCount > $maxFiles) {

            $error =
                'Maksimal 5 file bukti yang dapat diunggah.';
        }

        if ($error === null) {

            $finfo = finfo_open(
                FILEINFO_MIME_TYPE
            );

            foreach (
                $_FILES['evidence']['name']
                as $index => $originalName
            ) {

                if (
                    trim((string) $originalName) === '' ||
                    (
                        $_FILES['evidence']['error'][$index]
                        ?? UPLOAD_ERR_NO_FILE
                    ) === UPLOAD_ERR_NO_FILE
                ) {

                    continue;
                }

                $uploadError =
                    $_FILES['evidence']['error'][$index];

                $tmpName =
                    $_FILES['evidence']['tmp_name'][$index];

                $fileSize =
                    (int) $_FILES['evidence']['size'][$index];

                if (
                    $uploadError !== UPLOAD_ERR_OK
                ) {

                    $error =
                        'Salah satu file bukti gagal diunggah.';

                    break;
                }

                if ($fileSize > $maxFileSize) {

                    $error =
                        'Ukuran setiap file maksimal 5 MB.';

                    break;
                }

                $mimeType =
                    finfo_file(
                        $finfo,
                        $tmpName
                    );

                if (
                    !isset(
                        $allowedTypes[$mimeType]
                    )
                ) {

                    $error =
                        'Format bukti hanya boleh JPG, PNG, atau PDF.';

                    break;
                }

                $extension =
                    $allowedTypes[$mimeType];

                $safeName =
                    bin2hex(
                        random_bytes(16)
                    )
                    . '.'
                    . $extension;

                $uploadedFiles[] = [

                    'tmp_name' =>
                        $tmpName,

                    'original_name' =>
                        basename(
                            (string) $originalName
                        ),

                    'file_name' =>
                        $safeName,

                    'file_path' =>
                        'storage/reports/'
                        . $safeName,

                    'file_type' =>
                        $mimeType,

                    'file_size' =>
                        $fileSize,
                ];
            }

            finfo_close(
                $finfo
            );
        }
    }

    if ($error === null) {

        $reportCode =
            'SIGAP-26-'
            . str_pad(
                (string) random_int(
                    1,
                    999999
                ),
                6,
                '0',
                STR_PAD_LEFT
            );

        $accessCode =
            strtoupper(
                substr(
                    bin2hex(
                        random_bytes(4)
                    ),
                    0,
                    8
                )
            );

        $uploadDirectory =
            __DIR__
            . '/../../storage/reports';

        if (
            !is_dir(
                $uploadDirectory
            )
        ) {

            if (
                !mkdir(
                    $uploadDirectory,
                    0755,
                    true
                )
            ) {

                $error =
                    'Folder upload bukti tidak dapat dibuat.';
            }
        }
    }

    if ($error === null) {

        $movedFiles = [];

        try {

            $pdo->beginTransaction();

            if ($hasMapPoint) {
                $findLocation = $pdo->prepare(
                    "SELECT id FROM locations
                     WHERE latitude BETWEEN ? AND ?
                       AND longitude BETWEEN ? AND ?
                     LIMIT 1"
                );
                $epsilon = 0.00005;
                $findLocation->execute([
                    $latitude - $epsilon, $latitude + $epsilon,
                    $longitude - $epsilon, $longitude + $epsilon
                ]);
                $existingLocationId = $findLocation->fetchColumn();

                if ($existingLocationId !== false) {
                    $locationId = (int) $existingLocationId;
                } else {
                    $province = $mapProvince !== '' ? $mapProvince : 'Tidak diketahui';
                    $city = $mapCity !== '' ? $mapCity : 'Lokasi terpilih';
                    $district = $mapDistrict !== '' ? $mapDistrict : 'Tidak diketahui';
                    $subdistrict = $mapSubdistrict !== '' ? $mapSubdistrict : 'Tidak diketahui';
                    $address = $mapAddress !== '' ? $mapAddress : 'Titik dipilih melalui peta';

                    $insertLocation = $pdo->prepare(
                        "INSERT INTO locations
                            (province, city, district, subdistrict, address, latitude, longitude)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $insertLocation->execute([
                        $province, $city, $district, $subdistrict,
                        $address, $latitude, $longitude
                    ]);
                    $locationId = (int) $pdo->lastInsertId();
                }
            }

            $statement = $pdo->prepare(
                "
                INSERT INTO reports (
                    report_code,
                    access_code,
                    user_id,
                    service_id,
                    location_id,
                    report_mode,
                    incident_type,
                    title,
                    chronology,
                    requested_amount,
                    official_amount,
                    discrepancy_amount,
                    incident_at,
                    risk_level,
                    status,
                    is_anonymous
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, 'new', ?
                )
                "
            );

            $statement->execute([

                $reportCode,

                $accessCode,

                $userId,

                $serviceId,

                $locationId,

                $reportMode,

                $incidentType,

                $title,

                $chronology,

                $requestedAmount,

                $officialAmount,

                $discrepancyAmount,

                $incidentAt,

                $riskLevel,

                $isAnonymous,
            ]);

            $reportId =
                (int) $pdo->lastInsertId();

            if ($uploadedFiles) {

                $evidenceStatement =
                    $pdo->prepare(
                        "
                        INSERT INTO report_evidence (
                            report_id,
                            file_name,
                            file_path,
                            file_type,
                            file_size
                        )
                        VALUES (?, ?, ?, ?, ?)
                        "
                    );

                foreach (
                    $uploadedFiles
                    as $file
                ) {

                    $destination =
                        $uploadDirectory
                        . '/'
                        . $file['file_name'];

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {

                        throw new RuntimeException(
                            'Salah satu file bukti gagal disimpan.'
                        );
                    }

                    $movedFiles[] =
                        $destination;

                    $evidenceStatement->execute([

                        $reportId,

                        $file['original_name'],

                        $file['file_path'],

                        $file['file_type'],

                        $file['file_size'],
                    ]);
                }
            }

            $statement =
                $pdo->prepare(
                    "
                    INSERT INTO report_status_history (
                        report_id,
                        old_status,
                        new_status,
                        note,
                        changed_by
                    )
                    VALUES (?, ?, ?, ?, ?)
                    "
                );

            $statement->execute([

                $reportId,

                null,

                'new',

                'Laporan dibuat.',

                $userId,
            ]);

            $pdo->commit();

            $summaryStatement =
                $pdo->prepare(
                    "
                    SELECT
                        ps.name AS service_name,
                        l.city,
                        l.district
                    FROM reports r
                    LEFT JOIN public_services ps
                        ON ps.id = r.service_id
                    LEFT JOIN locations l
                        ON l.id = r.location_id
                    WHERE r.id = ?
                    LIMIT 1
                    "
                );

            $summaryStatement->execute([
                $reportId
            ]);

            $summary =
                $summaryStatement->fetch();

            $created = [

                'report_code' =>
                    $reportCode,

                'access_code' =>
                    $accessCode,

                'report_mode' =>
                    $reportMode,

                'is_anonymous' =>
                    $isAnonymous,

                'title' =>
                    $title,

                'incident_type' =>
                    $incidentType,

                'chronology' =>
                    $chronology,

                'incident_at' =>
                    $incidentAt,

                'discrepancy_amount' =>
                    $discrepancyAmount,

                'service_name' =>
                    $summary['service_name']
                    ?? 'Tidak tersedia',

                'location' =>
                    trim(
                        ($summary['district'] ?? '')
                        .
                        (
                            !empty(
                                $summary['district']
                            )
                            &&
                            !empty(
                                $summary['city']
                            )
                                ? ', '
                                : ''
                        )
                        .
                        ($summary['city'] ?? '')
                    ),
            ];

        } catch (
            Throwable $exception
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }

            foreach (
                $movedFiles
                as $filePath
            ) {

                if (
                    is_file(
                        $filePath
                    )
                ) {

                    @unlink(
                        $filePath
                    );
                }
            }

            $error =
                'Laporan gagal disimpan. Silakan coba lagi.';
        }
    }
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

    <title>
        Laporkan dengan Aman — SIGAP
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/report.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        .report-map-picker { margin-top: 14px; }
        #reportLocationMap { width: 100%; height: 360px; border-radius: 14px; border: 1px solid #dbe4df; z-index: 1; }
        .map-picker-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:10px 0; }
        .map-picker-status { color:#53645b; font-size:.9rem; margin:8px 0; }
        .map-coordinates { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .map-coordinates .input { background:#f7faf8; }
        @media(max-width:640px){ #reportLocationMap{height:300px;} .map-coordinates{grid-template-columns:1fr;} }
    </style>

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0"
    >

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">

</head>

<body>

    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>

    <main>

        <section class="section">

            <div class="container">

                <div class="report-page-header">

                    <span class="eyebrow">
                        <span class="material-symbols-rounded" aria-hidden="true">lock</span>
                        Saluran Pengaduan 100% Anonim &amp; Terenkripsi
                    </span>

                    <h1 class="page-title">
                        Laporkan dengan Aman
                    </h1>

                    <p class="muted report-intro">
                        Bantu menjaga pelayanan publik tetap transparan
                        dan bebas dari pungutan tidak resmi tanpa khawatir
                        identitasmu terungkap.
                    </p>

                </div>


                <?php if ($error): ?>

                    <div class="notice notice-error">

                        <strong>
                            Laporan belum dapat dikirim.
                        </strong>

                        <p>
                            <?= e($error) ?>
                        </p>

                    </div>

                <?php endif; ?>


                <?php if ($created): ?>

                    <div class="notice report-success" id="reportSuccessPanel">

                        <div class="report-success-heading">
                            <span class="material-symbols-rounded report-success-icon" aria-hidden="true">check_circle</span>
                            <div>
                                <strong>Laporan berhasil dikirim.</strong>
                                <p>Berikut kode yang kamu perlukan untuk mengecek status laporan.</p>
                            </div>
                        </div>

                        <div class="report-codes-row">
                            <div class="report-code-block">
                                <p>Report ID</p>
                                <h2><?= e($created['report_code']) ?></h2>
                            </div>

                            <div class="report-code-block">
                                <p>Access Code</p>
                                <h2><?= e($created['access_code']) ?></h2>
                            </div>
                        </div>

                        <div class="report-code-warning" role="alert">
                            <strong><span class="material-symbols-rounded report-warning-icon" aria-hidden="true">warning</span> PENTING: Simpan kedua kode ini sekarang!</strong>
                            <p>Access Code tidak dapat dipulihkan jika hilang. Pastikan kamu menyalin dan menyimpan Report ID serta Access Code sebelum meninggalkan halaman.</p>
                        </div>

                        <button
                            type="button"
                            class="btn report-copy-codes"
                            id="copyReportCodes"
                            data-report-code="<?= e($created['report_code']) ?>"
                            data-access-code="<?= e($created['access_code']) ?>"
                        >
                            <span class="material-symbols-rounded" aria-hidden="true">content_copy</span>
                            Salin Report ID &amp; Access Code
                        </button>
                        <p class="report-copy-feedback" id="copyReportCodesFeedback" aria-live="polite"></p>

                        <a class="btn btn-primary report-check-status" href="/user/check_status.php">
                            Cek Status Laporan
                        </a>
                    </div>

                    <div class="report-leave-modal" id="reportLeaveModal" aria-hidden="true">
                        <div class="report-leave-backdrop" data-close-modal="false"></div>
                        <section class="report-leave-dialog" role="dialog" aria-modal="true" aria-labelledby="reportLeaveTitle">
                            <div class="report-leave-icon material-symbols-rounded" aria-hidden="true">warning</div>
                            <h2 id="reportLeaveTitle">Jangan sampai kode akses hilang</h2>
                            <p>Sudah menyalin dan menyimpan Report ID serta Access Code? Access Code tidak bisa dipulihkan jika hilang.</p>
                            <div class="report-leave-actions">
                                <button type="button" class="btn report-leave-copy" id="leaveCopyCodes">Salin kode dulu</button>
                                <button type="button" class="btn btn-primary" id="leaveConfirm">Sudah disimpan, lanjut</button>
                            </div>
                        </section>
                    </div>


                    <div class="card submitted-report-card">

                        <div class="submitted-report-header">

                            <span class="eyebrow">
                                Laporan Terkirim
                            </span>

                            <h2>
                                Ringkasan Laporan
                            </h2>

                            <p class="muted">
                                Berikut adalah ringkasan laporan yang
                                baru saja kamu kirim melalui SIGAP.
                            </p>

                        </div>


                        <div class="submitted-report-grid">

                            <div
                                class="submitted-report-item submitted-report-full"
                            >

                                <span>
                                    Judul Laporan
                                </span>

                                <strong>
                                    <?= e(
                                        $created['title']
                                    ) ?>
                                </strong>

                            </div>


                            <div class="submitted-report-item">

                                <span>
                                    Jenis Kejadian
                                </span>

                                <strong>
                                    <?= e(
                                        $incidentTypeLabels[
                                            $created['incident_type']
                                        ] ?? 'Lainnya'
                                    ) ?>
                                </strong>

                            </div>


                            <div class="submitted-report-item">

                                <span>
                                    Jenis Layanan
                                </span>

                                <strong>
                                    <?= e(
                                        $created['service_name']
                                    ) ?>
                                </strong>

                            </div>


                            <div class="submitted-report-item">

                                <span>
                                    Instansi / Lokasi
                                </span>

                                <strong>
                                    <?= e(
                                        $created['location']
                                        ?: 'Tidak tersedia'
                                    ) ?>
                                </strong>

                            </div>


                            <div class="submitted-report-item">

                                <span>
                                    Waktu Kejadian
                                </span>

                                <strong>

                                    <?php if (
                                        $created['incident_at']
                                    ): ?>

                                        <?= e(
                                            date(
                                                'd M Y, H:i',
                                                strtotime(
                                                    $created[
                                                        'incident_at'
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Tidak dicantumkan

                                    <?php endif; ?>

                                </strong>

                            </div>


                            <div
                                class="submitted-report-item submitted-report-full"
                            >

                                <span>
                                    Kronologi
                                </span>

                                <p>
                                    <?= nl2br(
                                        e(
                                            $created['chronology']
                                        )
                                    ) ?>
                                </p>

                            </div>


                            <?php if (
                                (float)
                                $created['discrepancy_amount']
                                > 0
                            ): ?>

                                <div class="submitted-report-item">

                                    <span>
                                        Pungutan Tidak Resmi
                                    </span>

                                    <strong>
                                        Rp
                                        <?= number_format(
                                            (float)
                                            $created[
                                                'discrepancy_amount'
                                            ],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                    </strong>

                                </div>

                            <?php endif; ?>


                        </div>


                        <div class="submitted-report-actions">

                            <p class="muted">
                                Ingin melaporkan kejadian lain?
                            </p>

                            <a
                                href="/user/report.php?new=1"
                                class="btn btn-primary"
                            >
                                + Buat Laporan Baru
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if (
                    !$created ||
                    isset($_GET['new'])
                ): ?>

                    <div class="card report-form-card">

                        <form
                            method="post"
                            enctype="multipart/form-data"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e(
                                    csrf_token()
                                ) ?>"
                            >


                            <?php if (
                                !empty(
                                    $_SESSION['user']
                                )
                            ): ?>

                                <div class="form-group privacy-choice-group">

                                    <label>
                                        Privasi Laporan
                                    </label>

                                    <p class="field-help">
                                        Pilih bagaimana identitasmu digunakan
                                        dalam laporan ini.
                                    </p>

                                    <div class="privacy-options">

                                        <label class="privacy-option">

                                            <input
                                                type="radio"
                                                name="report_mode"
                                                value="anonymous"
                                                <?= (
                                                    (
                                                        $_POST[
                                                            'report_mode'
                                                        ]
                                                        ??
                                                        'anonymous'
                                                    )
                                                    ===
                                                    'anonymous'
                                                )
                                                    ? 'checked'
                                                    : ''
                                                ?>
                                            >

                                            <div class="privacy-option-content">

                                                <strong>
                                                    <span class="material-symbols-rounded" aria-hidden="true">shield_person</span> Laporkan Secara Anonim
                                                </strong>

                                                <span>
                                                    Identitas akunmu tidak
                                                    ditampilkan kepada pihak
                                                    yang menangani laporan.
                                                </span>

                                            </div>

                                        </label>


                                        <label class="privacy-option">

                                            <input
                                                type="radio"
                                                name="report_mode"
                                                value="account"
                                                <?= (
                                                    (
                                                        $_POST[
                                                            'report_mode'
                                                        ]
                                                        ??
                                                        ''
                                                    )
                                                    ===
                                                    'account'
                                                )
                                                    ? 'checked'
                                                    : ''
                                                ?>
                                            >

                                            <div class="privacy-option-content">

                                                <strong>
                                                    <span class="material-symbols-rounded" aria-hidden="true">person</span> Sertakan Identitas Saya
                                                </strong>

                                                <span>
                                                    Nama dan informasi akun
                                                    dapat digunakan untuk
                                                    keperluan tindak lanjut
                                                    laporan.
                                                </span>

                                            </div>

                                        </label>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <div class="form-group">

                                <label for="incident_type">

                                    1. Apa yang terjadi di loket pelayanan?

                                </label>

                                <select
                                    class="select"
                                    id="incident_type"
                                    name="incident_type"
                                >

                                    <option
                                        value="additional_fee"
                                        <?= (
                                            $_POST[
                                                'incident_type'
                                            ] ?? ''
                                        ) === 'additional_fee'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Diminta biaya tambahan
                                        di luar tarif resmi
                                    </option>


                                    <option
                                        value="no_receipt"
                                        <?= (
                                            $_POST[
                                                'incident_type'
                                            ] ?? ''
                                        ) === 'no_receipt'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Diminta membayar tunai
                                        tanpa kuitansi / tanda terima
                                    </option>


                                    <option
                                        value="delay"
                                        <?= (
                                            $_POST[
                                                'incident_type'
                                            ] ?? ''
                                        ) === 'delay'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Pelayanan sengaja diperlambat
                                        jika tidak memberi uang
                                    </option>


                                    <option
                                        value="broker"
                                        <?= (
                                            $_POST[
                                                'incident_type'
                                            ] ?? ''
                                        ) === 'broker'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Diarahkan melalui perantara /
                                        calo tidak resmi
                                    </option>


                                    <option
                                        value="other"
                                        <?= (
                                            $_POST[
                                                'incident_type'
                                            ] ?? ''
                                        ) === 'other'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Lainnya
                                    </option>

                                </select>

                            </div>


                            <div class="two-col">

                                <div class="form-group">

                                    <label for="service_id">
                                        Jenis Layanan *
                                    </label>

                                    <select
                                        class="select"
                                        id="service_id"
                                        name="service_id"
                                        required
                                    >

                                        <option value="">
                                            Pilih
                                        </option>

                                        <?php foreach (
                                            $services
                                            as $service
                                        ): ?>

                                            <option
                                                value="<?= (int) $service['id'] ?>"
                                                data-official-fee="<?= (float) $service['official_fee'] ?>"
                                                <?= (
                                                    (int) (
                                                        $_POST[
                                                            'service_id'
                                                        ] ?? 0
                                                    )
                                                    ===
                                                    (int) $service['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                <?= e(
                                                    $service['name']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label for="location_id">
                                        Instansi / Lokasi *
                                    </label>

                                    <select
                                        class="select"
                                        id="location_id"
                                        name="location_id"
                                        required
                                    >

                                        <option value="">
                                            Pilih
                                        </option>

                                        <?php foreach (
                                            $locations
                                            as $location
                                        ): ?>

                                            <option
                                                value="<?= (int) $location['id'] ?>"
                                                <?= (
                                                    (int) (
                                                        $_POST[
                                                            'location_id'
                                                        ] ?? 0
                                                    )
                                                    ===
                                                    (int) $location['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                <?= e(
                                                    $location['district']
                                                    . ', '
                                                    . $location['city']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>
                                    <small class="field-help">Pilih lokasi terdaftar, atau tandai titik kejadian pada peta di bawah.</small>

                                </div>

                            </div>

                            <div class="form-group report-map-picker">
                                <label for="reportLocationMap">Atau pilih titik kejadian di peta</label>
                                <p class="field-help">Klik peta untuk menentukan koordinat. SIGAP akan mencoba mengenali alamat secara otomatis. Pastikan pin berada di lokasi kejadian yang benar.</p>
                                <div id="reportLocationMap" role="application" aria-label="Peta untuk memilih lokasi kejadian"></div>
                                <div class="map-picker-toolbar">
                                    <button type="button" class="btn" id="clearMapPoint">Hapus titik peta</button>
                                    <span class="map-picker-status" id="mapPickerStatus" aria-live="polite">Belum ada titik peta dipilih; lokasi dropdown akan digunakan.</span>
                                </div>
                                <div class="map-coordinates">
                                    <div><label for="latitudeDisplay">Latitude</label><input class="input" id="latitudeDisplay" type="text" readonly placeholder="Otomatis dari peta"></div>
                                    <div><label for="longitudeDisplay">Longitude</label><input class="input" id="longitudeDisplay" type="text" readonly placeholder="Otomatis dari peta"></div>
                                </div>
                                <input type="hidden" name="latitude" id="latitude" value="<?= e($_POST['latitude'] ?? '') ?>">
                                <input type="hidden" name="longitude" id="longitude" value="<?= e($_POST['longitude'] ?? '') ?>">
                                <input type="hidden" name="map_province" id="mapProvince" value="<?= e($_POST['map_province'] ?? '') ?>">
                                <input type="hidden" name="map_city" id="mapCity" value="<?= e($_POST['map_city'] ?? '') ?>">
                                <input type="hidden" name="map_district" id="mapDistrict" value="<?= e($_POST['map_district'] ?? '') ?>">
                                <input type="hidden" name="map_subdistrict" id="mapSubdistrict" value="<?= e($_POST['map_subdistrict'] ?? '') ?>">
                                <input type="hidden" name="map_address" id="mapAddress" value="<?= e($_POST['map_address'] ?? '') ?>">
                            </div>

                            <div class="two-col">

                                <div class="form-group">

                                    <label for="incident_at">
                                        Tanggal & Waktu Kejadian
                                    </label>

                                    <input
                                        class="input"
                                        id="incident_at"
                                        name="incident_at"
                                        type="datetime-local"
                                        value="<?= e(
                                            $_POST[
                                                'incident_at'
                                            ] ?? ''
                                        ) ?>"
                                    >

                                    <small class="field-help">
                                        Jika tidak ingat waktu persis,
                                        masukkan waktu perkiraan.
                                    </small>

                                </div>


                                <div class="form-group">

                                    <label for="requested_amount">
                                        Total Nominal yang Diminta / Dibayarkan (Rp) *
                                    </label>

                                    <input
                                        class="input"
                                        id="requested_amount"
                                        name="requested_amount"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        value="<?= e(
                                            $_POST[
                                                'requested_amount'
                                            ] ?? ''
                                        ) ?>"
                                        placeholder="Contoh: 100000"
                                        required
                                    >

                                    <small class="field-help">
                                        Masukkan total biaya yang diminta atau dibayarkan, bukan selisihnya.
                                    </small>



                                </div>

                            </div>


                            <div class="report-amount-summary" aria-live="polite">
                                <div class="report-amount-summary-item">
                                    <span>Tarif resmi</span>
                                    <strong id="officialFeeDisplay">Rp 0</strong>
                                </div>
                                <div class="report-amount-summary-item">
                                    <span>Total diminta</span>
                                    <strong id="requestedAmountDisplay">Rp 0</strong>
                                </div>
                                <div class="report-amount-summary-item">
                                    <span>Selisih pungutan</span>
                                    <strong id="discrepancyAmountDisplay">Rp 0</strong>
                                </div>
                            </div>

                            <div class="form-group">

                                <label for="title">
                                    Judul Laporan *
                                </label>

                                <input
                                    class="input"
                                    id="title"
                                    name="title"
                                    value="<?= e(
                                        $_POST['title'] ?? ''
                                    ) ?>"
                                    placeholder="Contoh: Pungutan tambahan pembuatan SIM"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="chronology">
                                    Kronologi Kejadian *
                                </label>

                                <textarea
                                    class="textarea"
                                    id="chronology"
                                    name="chronology"
                                    minlength="20"
                                    placeholder="Jelaskan kejadian secara kronologis. Hindari identitas pribadi jika ingin tetap anonim."
                                    required
                                ><?= e(
                                    $_POST['chronology'] ?? ''
                                ) ?></textarea>

                            </div>


                            <div class="form-group">

                                <label for="evidence">
                                    Bukti Pendukung
                                </label>

                                <div class="evidence-upload">

                                    <input
                                        id="evidence"
                                        name="evidence[]"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        multiple
                                        hidden
                                    >

                                    <label
                                        for="evidence"
                                        class="evidence-upload-box"
                                    >

                                        <span class="evidence-upload-icon">

                                            <span class="material-symbols-rounded">
                                                cloud_upload
                                            </span>

                                        </span>

                                        <strong class="evidence-upload-title">
                                            Tambahkan Bukti
                                        </strong>

                                        <span class="evidence-upload-description">
                                            Foto, bukti pembayaran,
                                            atau dokumen pendukung
                                        </span>

                                        <span class="evidence-upload-button">
                                            Pilih File
                                        </span>

                                    </label>

                                    <div
                                        class="evidence-file-list"
                                        id="evidenceFileList"
                                    ></div>

                                </div>

                                <small class="field-help">
                                    Opsional · Maksimal 5 file ·
                                    JPG, PNG, atau PDF ·
                                    Maksimal 5 MB per file.
                                </small>

                                <small class="field-help evidence-warning">
                                    Demi keamanan, hindari mengunggah
                                    NIK, nomor rekening, password,
                                    atau data pribadi yang tidak diperlukan.
                                </small>

                            </div>


                            <div class="notice privacy-notice">

                                <strong>
                                    <span class="material-symbols-rounded">shield</span>
                                    Perlindungan & Privasi Identitas
                                </strong>

                                <p>
                                    Mode anonim aktif secara default.
                                    Identitas akun tidak ditampilkan sebagai
                                    identitas pelapor publik.
                                </p>

                            </div>


                            <button
                                class="btn btn-primary report-submit"
                                type="submit"
                            >
                                ▶ Kirim Laporan dengan Aman
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>


    <?php require_once __DIR__ . '/../../config/footer.php'; ?>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('evidence');
    const list = document.getElementById('evidenceFileList');
    if (!input || !list) return;

    const maxFiles = 5;
    const maxBytes = 5 * 1024 * 1024;
    const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    let selectedFiles = [];

    const sizeLabel = (bytes) => {
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    function renderFiles() {
        list.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const card = document.createElement('div');
            card.className = 'evidence-preview-card';
            const isImage = file.type.startsWith('image/');
            if (isImage) {
                const image = document.createElement('img');
                image.className = 'evidence-preview-image';
                image.alt = 'Pratinjau ' + file.name;
                image.src = URL.createObjectURL(file);
                card.appendChild(image);
            } else {
                const preview = document.createElement('div');
                preview.className = 'evidence-preview-file';
                preview.innerHTML = '<span class="material-symbols-rounded">picture_as_pdf</span><strong>Dokumen PDF</strong>';
                card.appendChild(preview);
            }
            const name = document.createElement('span');
            name.className = 'evidence-preview-name';
            name.textContent = file.name;
            const size = document.createElement('span');
            size.className = 'evidence-preview-size';
            size.textContent = sizeLabel(file.size);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'evidence-preview-remove';
            remove.textContent = 'Hapus file';
            remove.addEventListener('click', function () {
                selectedFiles.splice(index, 1);
                syncInput();
                renderFiles();
            });
            card.append(name, size, remove);
            list.appendChild(card);
        });
    }

    function syncInput() {
        const transfer = new DataTransfer();
        selectedFiles.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
    }

    input.addEventListener('change', function () {
        const incoming = Array.from(input.files || []);
        const combined = selectedFiles.concat(incoming);
        const unique = [];
        const seen = new Set();
        for (const file of combined) {
            const key = [file.name, file.size, file.lastModified].join(':');
            if (!seen.has(key)) { unique.push(file); seen.add(key); }
        }

        let message = '';
        if (unique.length > maxFiles) message = 'Maksimal 5 file bukti.';
        else if (unique.some(file => file.size > maxBytes)) message = 'Ukuran setiap file maksimal 5 MB.';
        else if (unique.some(file => !allowed.includes(file.type))) message = 'Format yang didukung hanya JPG, PNG, dan PDF.';

        let error = document.getElementById('evidenceUploadError');
        if (!error) {
            error = document.createElement('div');
            error.id = 'evidenceUploadError';
            error.className = 'evidence-upload-error';
            list.parentNode.appendChild(error);
        }
        if (message) {
            error.textContent = message;
            selectedFiles = [];
            input.value = '';
        } else {
            error.textContent = '';
            selectedFiles = unique;
        }
        syncInput();
        renderFiles();
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const serviceSelect = document.getElementById('service_id');
    const requestedInput = document.getElementById('requested_amount');
    const officialDisplay = document.getElementById('officialFeeDisplay');
    const requestedDisplay = document.getElementById('requestedAmountDisplay');
    const discrepancyDisplay = document.getElementById('discrepancyAmountDisplay');

    if (!serviceSelect || !requestedInput || !officialDisplay || !requestedDisplay || !discrepancyDisplay) {
        return;
    }

    const rupiah = (amount) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(amount);

    function updateAmountSummary() {
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        const officialFee = selectedOption ? Number(selectedOption.dataset.officialFee || 0) : 0;
        const requestedAmount = Math.max(0, Number(requestedInput.value || 0));
        const discrepancy = Math.max(0, requestedAmount - officialFee);

        officialDisplay.textContent = rupiah(officialFee);
        requestedDisplay.textContent = rupiah(requestedAmount);
        discrepancyDisplay.textContent = rupiah(discrepancy);
    }

    serviceSelect.addEventListener('change', updateAmountSummary);
    requestedInput.addEventListener('input', updateAmountSummary);
    updateAmountSummary();
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('reportSuccessPanel');
    const copyButton = document.getElementById('copyReportCodes');
    const feedback = document.getElementById('copyReportCodesFeedback');
    const modal = document.getElementById('reportLeaveModal');
    const leaveCopyButton = document.getElementById('leaveCopyCodes');
    const leaveConfirmButton = document.getElementById('leaveConfirm');

    if (!panel || !copyButton || !feedback || !modal) return;

    let copied = false;
    let allowNavigation = false;
    let pendingUrl = null;

    async function copyCodes() {
        const reportCode = copyButton.dataset.reportCode || '';
        const accessCode = copyButton.dataset.accessCode || '';
        const textToCopy = 'Report ID: ' + reportCode + '\nAccess Code: ' + accessCode;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(textToCopy);
            } else {
                const temporary = document.createElement('textarea');
                temporary.value = textToCopy;
                temporary.setAttribute('readonly', '');
                temporary.style.position = 'fixed';
                temporary.style.left = '-9999px';
                document.body.appendChild(temporary);
                temporary.select();
                const didCopy = document.execCommand('copy');
                temporary.remove();
                if (!didCopy) throw new Error('copy_failed');
            }
            copied = true;
            feedback.textContent = 'Berhasil disalin! Simpan kode ini di tempat yang aman.';
            feedback.classList.add('is-success');
            feedback.classList.remove('is-error');
            return true;
        } catch (error) {
            feedback.textContent = 'Gagal menyalin otomatis. Silakan coba lagi atau salin kedua kode secara manual.';
            feedback.classList.add('is-error');
            feedback.classList.remove('is-success');
            return false;
        }
    }

    function openLeaveModal(url) {
        pendingUrl = url || null;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('report-modal-open');
        leaveCopyButton.focus();
    }

    function closeLeaveModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('report-modal-open');
        pendingUrl = null;
    }

    copyButton.addEventListener('click', copyCodes);

    leaveCopyButton.addEventListener('click', async function () {
        const success = await copyCodes();
        if (success) {
            leaveConfirmButton.textContent = 'Kode tersalin, lanjut';
            leaveConfirmButton.focus();
        }
    });

    leaveConfirmButton.addEventListener('click', function () {
        allowNavigation = true;
        const destination = pendingUrl;
        closeLeaveModal();
        if (destination) window.location.href = destination;
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[href]');
        if (!link || link.target === '_blank' || event.defaultPrevented ||
            event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.href === window.location.href) return;
        if (allowNavigation) return;

        event.preventDefault();
        openLeaveModal(url.href);
    });

    window.addEventListener('beforeunload', function (event) {
        if (copied || allowNavigation) return;
        event.preventDefault();
        event.returnValue = '';
    });
});
</script>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapElement = document.getElementById('reportLocationMap');
    if (!mapElement || typeof L === 'undefined') return;

    const map = L.map(mapElement).setView([-6.2000, 106.8167], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    const fields = {
        lat: document.getElementById('latitude'), lon: document.getElementById('longitude'),
        latDisplay: document.getElementById('latitudeDisplay'), lonDisplay: document.getElementById('longitudeDisplay'),
        province: document.getElementById('mapProvince'), city: document.getElementById('mapCity'),
        district: document.getElementById('mapDistrict'), subdistrict: document.getElementById('mapSubdistrict'),
        address: document.getElementById('mapAddress'), status: document.getElementById('mapPickerStatus'),
        locationSelect: document.getElementById('location_id')
    };

    function clearAddress() {
        [fields.province, fields.city, fields.district, fields.subdistrict, fields.address].forEach(el => el.value = '');
    }

    function areaName(a) {
        return a.city || a.town || a.municipality || a.county || a.state_district || a.village || '';
    }

    async function reverseGeocode(lat, lon) {
        fields.status.textContent = 'Mencari alamat dari titik peta…';
        clearAddress();
        try {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lon) + '&zoom=18&addressdetails=1&accept-language=id';
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('geocoding_failed');
            const data = await response.json();
            const a = data.address || {};
            fields.province.value = a.state || a.province || '';
            fields.city.value = areaName(a);
            fields.district.value = a.city_district || a.district || a.suburb || a.county || '';
            fields.subdistrict.value = a.suburb || a.neighbourhood || a.quarter || a.village || '';
            fields.address.value = data.display_name || '';
            if (!fields.city.value && fields.address.value) fields.city.value = 'Lokasi terpilih';
            fields.status.textContent = fields.address.value
                ? 'Titik terpilih: ' + fields.address.value
                : 'Koordinat tersimpan, tetapi alamat tidak ditemukan. Coba titik lain atau gunakan lokasi terdaftar.';
        } catch (err) {
            fields.status.textContent = 'Alamat tidak dapat ditemukan. Coba lagi beberapa saat atau gunakan lokasi terdaftar.';
            clearAddress();
        }
    }

    async function setPoint(latlng) {
        const lat = Number(latlng.lat.toFixed(7));
        const lon = Number(latlng.lng.toFixed(7));
        fields.lat.value = String(lat);
        fields.lon.value = String(lon);
        fields.latDisplay.value = lat.toFixed(7);
        fields.lonDisplay.value = lon.toFixed(7);
        if (marker) {
            marker.setLatLng([lat, lon]);
        } else {
            const sigapMarkerIcon = L.divIcon({
                className: 'sigap-map-marker-wrapper',
                html: '<div class="sigap-map-marker"><span></span></div>',
                iconSize: [28, 36],
                iconAnchor: [14, 32],
                popupAnchor: [0, -30]
            });
            marker = L.marker([lat, lon], {
                icon: sigapMarkerIcon,
                draggable: true,
                title: 'Titik lokasi kejadian',
                alt: 'Pin lokasi kejadian SIGAP'
            }).addTo(map);
        }
        marker.off('dragend');
        marker.on('dragend', function (event) { setPoint(event.target.getLatLng()); });
        if (fields.locationSelect) fields.locationSelect.required = false;
        await reverseGeocode(lat, lon);
    }

    map.on('click', function (event) { setPoint(event.latlng); });
    document.getElementById('clearMapPoint').addEventListener('click', function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        fields.lat.value = ''; fields.lon.value = '';
        fields.latDisplay.value = ''; fields.lonDisplay.value = '';
        clearAddress();
        fields.status.textContent = 'Titik peta dihapus; lokasi dropdown akan digunakan.';
        if (fields.locationSelect) fields.locationSelect.required = true;
    });

    const initialLat = Number(fields.lat.value);
    const initialLon = Number(fields.lon.value);
    if (fields.lat.value !== '' && fields.lon.value !== '' && Number.isFinite(initialLat) && Number.isFinite(initialLon)) {
        map.setView([initialLat, initialLon], 16);
        setPoint({ lat: initialLat, lng: initialLon });
    }
    setTimeout(() => map.invalidateSize(), 200);
    window.addEventListener('resize', () => map.invalidateSize());
});
</script>

</body>

</html>