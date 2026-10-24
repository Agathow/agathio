<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$risk = $_GET['risk'] ?? '';
$view = (($_GET['view'] ?? 'active') === 'archive') ? 'archive' : 'active';
$archiveStatuses = ['resolved', 'closed_no_response'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'status') {

        $newStatus = $_POST['status'] ?? '';

        $statement = $pdo->prepare(
            "SELECT status
             FROM reports
             WHERE id = ?"
        );

        $statement->execute([$id]);

        $oldStatus = $statement->fetchColumn();


        $statement = $pdo->prepare(
            "UPDATE reports
             SET status = ?,
                 updated_at = NOW()
             WHERE id = ?"
        );

        $statement->execute([
            $newStatus,
            $id
        ]);


        $statement = $pdo->prepare(
            "INSERT INTO report_status_history
            (
                report_id,
                old_status,
                new_status,
                note,
                changed_by
            )
            VALUES (?, ?, ?, ?, ?)"
        );

        $statement->execute([
            $id,
            $oldStatus,
            $newStatus,
            'Status diperbarui dari Report Management.',
            $_SESSION['user']['id']
        ]);


        flash(
            'success',
            'Status laporan diperbarui.'
        );

        redirect('/admin/reports.php');
    }

    if ($action === 'delete') {

        if (!is_admin()) {
            exit('Forbidden');
        }


        $statement = $pdo->prepare(
            "DELETE FROM reports
             WHERE id = ?"
        );

        $statement->execute([$id]);


        flash(
            'success',
            'Laporan dihapus.'
        );

        redirect('/admin/reports.php');
    }
}

$sql = "
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

    WHERE 1
";

if ($view === 'archive') {
    $sql .= " AND r.status IN ('resolved', 'closed_no_response') ";
} else {
    $sql .= " AND r.status NOT IN ('resolved', 'closed_no_response') ";
}


$params = [];

if ($q !== '') {

    $sql .= "
        AND (
            r.report_code LIKE ?
            OR r.title LIKE ?
            OR ps.name LIKE ?
        )
    ";

    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

if ($status !== '') {

    $sql .= "
        AND r.status = ?
    ";

    $params[] = $status;
}

if ($risk !== '') {

    $sql .= "
        AND r.risk_level = ?
    ";

    $params[] = $risk;
}

$sql .= "
    ORDER BY r.created_at DESC
    LIMIT 250
";


$statement = $pdo->prepare($sql);

$statement->execute($params);

$rows = $statement->fetchAll();

$archiveCountStmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status IN ('resolved', 'closed_no_response')");
$archiveCount = (int) $archiveCountStmt->fetchColumn();

admin_header(
    $view === 'archive' ? 'Arsip Laporan' : 'Report Management',
    'reports'
);

?>

<div class="card report-management-header">

    <div class="toolbar">

        <div>

            <span class="eyebrow">

                <span class="material-symbols-rounded">
                    fact_check
                </span>

                Govt Audit Mode

            </span>


            <h2>
                <?= $view === 'archive' ? 'Arsip Laporan Selesai' : 'Review and manage reports submitted by citizens' ?>
            </h2>

        </div>


        <a
            class="btn btn-primary"
            href="/admin/report_form.php"
        >

            <span class="material-symbols-rounded">
                add
            </span>

            Report Baru

        </a>

    </div>

    <form
        class="three-col report-filter"
        method="get"
    >

        <div class="input-with-icon">

            <span class="material-symbols-rounded">
                search
            </span>

            <input
                class="input"
                type="search"
                name="q"
                value="<?= e($q) ?>"
                placeholder="Search Report ID, service..."
            >

        </div>

        <select
            class="select"
            name="status"
        >

            <option value="">
                Semua Status
            </option>

            <?php foreach (
                [
                    'new',
                    'pending_review',
                    'under_review',
                    'follow_up',
                    'follow_up_in_progress',
                    'awaiting_reporter_confirmation',
                    'reporter_disputed',
                    'resolved',
                    'closed_no_response',
                    'rejected'
                ] as $option
            ): ?>

                <option
                    value="<?= e($option) ?>"
                    <?= $status === $option ? 'selected' : '' ?>
                >
                    <?= e(status_label($option)) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <select
            class="select"
            name="risk"
        >

            <option value="">
                Semua Risiko
            </option>

            <?php foreach (
                [
                    'low',
                    'moderate',
                    'elevated',
                    'high'
                ] as $option
            ): ?>

                <option
                    value="<?= e($option) ?>"
                    <?= $risk === $option ? 'selected' : '' ?>
                >
                    <?= ucfirst($option) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button
            type="submit"
            class="btn btn-light"
        >

            <span class="material-symbols-rounded">
                filter_alt
            </span>

            Filter

        </button>

    </form>

</div>

<div
    class="card report-management-list"
    style="margin-top:18px"
>

    <div class="toolbar">

        <div>

            <h2>
                <?= $view === 'archive' ? 'Daftar Arsip Laporan' : 'Daftar Laporan Aktif' ?>
            </h2>

            <p class="muted">
                <?= $view === 'archive' ? 'Laporan yang telah selesai atau ditutup. Data tetap disimpan untuk kebutuhan audit.' : 'Laporan yang masih membutuhkan pemantauan atau tindakan.' ?>
            </p>

        </div>


        <a class="eyebrow" href="/admin/reports.php<?= $view === 'archive' ? '' : '?view=archive' ?>" style="text-decoration:none;">
            <span class="material-symbols-rounded">inventory_2</span>
            <?= $view === 'archive' ? 'Kembali ke Laporan Aktif' : number_format($archiveCount) . ' Arsip' ?>
        </a>

    </div>

    <div class="table-wrap">

        <table class="table">

            <thead>

                <tr>

                    <th>
                        Report ID
                    </th>

                    <th>
                        Layanan & Wilayah
                    </th>

                    <th>
                        Jenis
                    </th>

                    <th>
                        Risiko
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (empty($rows)): ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;padding:40px"
                        >

                            <span
                                class="material-symbols-rounded"
                                style="font-size:32px;display:block;margin-bottom:8px"
                            >
                                search_off
                            </span>

                            <strong>
                                Tidak ada laporan ditemukan.
                            </strong>

                            <div class="muted">
                                Coba ubah kata kunci atau filter.
                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach ($rows as $row): ?>

                    <tr>
                        <td>

                            <b>
                                <?= e($row['report_code']) ?>
                            </b>

                            <br>

                            <small>
                                <?= e(
                                    date(
                                        'd M Y H:i',
                                        strtotime($row['created_at'])
                                    )
                                ) ?>
                            </small>

                        </td>
                        <td>

                            <?= e(
                                $row['service'] ?: 'Layanan tidak tersedia'
                            ) ?>

                            <br>

                            <small>

                                <?= e(
                                    trim(
                                        ($row['district'] ?? '')
                                        . ', '
                                        . ($row['city'] ?? '')
                                    )
                                ) ?>

                            </small>

                        </td>
                        <td>

                            <?= e(
                                status_label(
                                    $row['incident_type']
                                )
                            ) ?>

                        </td>
                        <td>

                            <?= risk_badge(
                                $row['risk_level']
                            ) ?>

                        </td>
                        <td>

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="status"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $row['id'] ?>"
                                >


                                <select
                                    class="select"
                                    name="status"
                                    onchange="this.form.submit()"
                                    style="padding:7px"
                                >

                                    <option
                                        value="<?= e($row['status']) ?>"
                                    >
                                        <?= e(
                                            status_label(
                                                $row['status']
                                            )
                                        ) ?>
                                    </option>


                                    <?php foreach (
                                        [
                                            'new',
                                            'pending_review',
                                            'under_review',
                                            'follow_up',
                                            'resolved',
                                            'rejected'
                                        ] as $option
                                    ): ?>

                                        <?php
                                        if (
                                            $option ===
                                            $row['status']
                                        ) {
                                            continue;
                                        }
                                        ?>

                                        <option
                                            value="<?= e($option) ?>"
                                        >
                                            <?= e(
                                                status_label(
                                                    $option
                                                )
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </form>

                        </td>
                        <td>

                            <div class="report-actions">
                                <a
                                    class="btn btn-light btn-sm"
                                    href="/admin/report_detail.php?id=<?= (int) $row['id'] ?>"
                                >

                                    <span class="material-symbols-rounded">
                                        visibility
                                    </span>

                                    View

                                </a>

                                <a
                                    class="btn btn-light btn-sm"
                                    href="/admin/report_form.php?id=<?= (int) $row['id'] ?>"
                                >

                                    <span class="material-symbols-rounded">
                                        edit
                                    </span>

                                    Edit

                                </a>

                                <?php if (is_admin()): ?>

                                    <form
                                        method="post"
                                        style="display:inline"
                                        onsubmit="return confirmDelete('Hapus laporan ini?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $row['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                        >

                                            <span class="material-symbols-rounded">
                                                delete
                                            </span>

                                            Delete

                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>


<?php

admin_footer();

?>