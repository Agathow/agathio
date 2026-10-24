<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
$error = '';

$services = $pdo
    ->query("
        SELECT
            id,
            name,
            official_fee
        FROM public_services
        WHERE status = 'active'
        ORDER BY name
    ")
    ->fetchAll();

$locations = $pdo
    ->query("
        SELECT
            id,
            city,
            district
        FROM locations
        ORDER BY city, district
    ")
    ->fetchAll();

$r = [
    'report_code'       => '',
    'access_code'       => '',
    'service_id'        => '',
    'location_id'       => '',
    'incident_type'     => 'additional_fee',
    'title'             => '',
    'chronology'        => '',
    'requested_amount'  => '',
    'incident_at'       => '',
    'risk_level'        => 'moderate',
    'status'            => 'new',
    'is_anonymous'      => 1
];

if ($editing) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM reports
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $r = $stmt->fetch();

    if (!$r) {
        exit('Report tidak ditemukan.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $service = (int) ($_POST['service_id'] ?? 0);
    $location = (int) ($_POST['location_id'] ?? 0);

    $asked = (float) ($_POST['requested_amount'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT official_fee
        FROM public_services
        WHERE id = ?
    ");

    $stmt->execute([$service]);

    $official = (float) $stmt->fetchColumn();

    $diff = max(
        0,
        $asked - $official
    );

    $risk =
        $diff >= 100000
            ? 'high'
            : (
                $diff >= 50000
                    ? 'elevated'
                    : 'moderate'
            );


    if ($editing) {

        $stmt = $pdo->prepare("
            UPDATE reports
            SET
                service_id = ?,
                location_id = ?,
                incident_type = ?,
                title = ?,
                chronology = ?,
                requested_amount = ?,
                official_amount = ?,
                discrepancy_amount = ?,
                incident_at = ?,
                risk_level = ?,
                status = ?,
                is_anonymous = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $service,
            $location,
            $_POST['incident_type'] ?? 'other',
            trim($_POST['title'] ?? ''),
            trim($_POST['chronology'] ?? ''),
            $asked,
            $official,
            $diff,
            !empty($_POST['incident_at'])
                ? $_POST['incident_at']
                : null,
            $risk,
            $_POST['status'] ?? 'new',
            isset($_POST['is_anonymous']) ? 1 : 0,
            $id
        ]);

        flash(
            'success',
            'Laporan berhasil diperbarui.'
        );

        redirect(
            '/admin/report_detail.php?id=' . $id
        );
    }

    else {

        $code =
            'SIGAP-26-' .
            str_pad(
                (string) random_int(1, 999999),
                6,
                '0',
                STR_PAD_LEFT
            );

        $access = strtoupper(
            substr(
                bin2hex(
                    random_bytes(4)
                ),
                0,
                8
            )
        );


        $stmt = $pdo->prepare("
            INSERT INTO reports (
                report_code,
                access_code,
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
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $code,
            $access,
            $service,
            $location,
            'anonymous',
            $_POST['incident_type'] ?? 'other',
            trim($_POST['title'] ?? ''),
            trim($_POST['chronology'] ?? ''),
            $asked,
            $official,
            $diff,
            !empty($_POST['incident_at'])
                ? $_POST['incident_at']
                : null,
            $risk,
            $_POST['status'] ?? 'new',
            1
        ]);


        $newId = (int) $pdo->lastInsertId();

        flash(
            'success',
            'Report baru berhasil dibuat.'
        );

        redirect(
            '/admin/report_detail.php?id=' . $newId
        );
    }
}

admin_header(
    $editing ? 'Edit Report' : 'Create Report',
    'reports'
);

?>

<div class="card report-form-card">

    <div class="report-form-header">



        <a
            href="/admin/reports.php"
            class="report-form-back"
        >
            <span class="material-symbols-rounded">
                arrow_back
            </span>

            Kembali
        </a>

    </div>


    <?php if ($error): ?>

        <div class="notice">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <form method="post">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrf_token()) ?>"
        >

        <div class="two-col">

            <div class="form-group">

                <label>
                    Jenis Layanan
                </label>

                <select
                    class="select"
                    name="service_id"
                    required
                >

                    <?php foreach ($services as $s): ?>

                        <option
                            value="<?= $s['id'] ?>"
                            <?= (
                                $r['service_id'] == $s['id']
                            ) ? 'selected' : '' ?>
                        >

                            <?= e($s['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Lokasi
                </label>

                <select
                    class="select"
                    name="location_id"
                    required
                >

                    <?php foreach ($locations as $l): ?>

                        <option
                            value="<?= $l['id'] ?>"
                            <?= (
                                $r['location_id'] == $l['id']
                            ) ? 'selected' : '' ?>
                        >

                            <?= e(
                                $l['district']
                                . ', '
                                . $l['city']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <div class="two-col">

            <div class="form-group">

                <label>
                    Jenis Anomali
                </label>

                <select
                    class="select"
                    name="incident_type"
                >

                    <?php foreach (
                        [
                            'additional_fee',
                            'no_receipt',
                            'delay',
                            'broker',
                            'other'
                        ] as $x
                    ): ?>

                        <option
                            value="<?= $x ?>"
                            <?= (
                                $r['incident_type'] === $x
                            ) ? 'selected' : '' ?>
                        >

                            <?= e(
                                status_label($x)
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Status
                </label>

                <select
                    class="select"
                    name="status"
                >

                    <?php foreach (
                        [
                            'new',
                            'pending_review',
                            'under_review',
                            'follow_up',
                            'resolved',
                            'rejected'
                        ] as $x
                    ): ?>

                        <option
                            value="<?= $x ?>"
                            <?= (
                                $r['status'] === $x
                            ) ? 'selected' : '' ?>
                        >

                            <?= e(
                                status_label($x)
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <div class="form-group">

            <label>
                Judul
            </label>

            <input
                class="input"
                type="text"
                name="title"
                value="<?= e($r['title']) ?>"
                required
            >

        </div>

        <div class="two-col">

            <div class="form-group">

                <label>
                    Nominal Diminta
                </label>

                <input
                    class="input"
                    type="number"
                    name="requested_amount"
                    min="0"
                    step="1000"
                    value="<?= e(
                        (string) $r['requested_amount']
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    Tanggal Kejadian
                </label>

                <input
                    class="input"
                    type="datetime-local"
                    name="incident_at"
                    value="<?=
                        e(
                            !empty($r['incident_at'])
                                ? date(
                                    'Y-m-d\TH:i',
                                    strtotime(
                                        $r['incident_at']
                                    )
                                )
                                : ''
                        )
                    ?>"
                >

            </div>

        </div>

        <div class="form-group">

            <label>
                Kronologi
            </label>

            <textarea
                class="textarea"
                name="chronology"
                rows="6"
                required
            ><?= e($r['chronology']) ?></textarea>

        </div>

        <label class="report-anonymous">

            <input
                type="checkbox"
                name="is_anonymous"
                <?= $r['is_anonymous']
                    ? 'checked'
                    : '' ?>
            >

            Mode anonim

        </label>

        <div class="report-submit">

            <button
                type="submit"
                class="btn btn-primary"
            >

                <span class="material-symbols-rounded">
                    <?= $editing ? 'save' : 'add_circle' ?>
                </span>

                <?= $editing
                    ? 'Simpan Perubahan'
                    : 'Create Report'
                ?>

            </button>

        </div>

    </form>

</div>


<?php admin_footer(); ?>