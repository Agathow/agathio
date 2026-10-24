<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

require_login();

$userId = (int) $_SESSION['user']['id'];

$statement = $pdo->prepare(
    "
    SELECT
        id,
        name,
        email,
        role,
        is_verified,
        created_at
    FROM users
    WHERE id = ?
    "
);

$statement->execute([
    $userId,
]);

$user = $statement->fetch();

if (!$user) {
    redirect('/user/login.php');
}

$reportStatement = $pdo->prepare(
    "
    SELECT
        r.id,
        r.report_code,
        r.title,
        r.status,
        r.created_at,
        ps.name AS service
    FROM reports r

    LEFT JOIN public_services ps
        ON ps.id = r.service_id

    WHERE r.user_id = ?

    ORDER BY r.created_at DESC
    "
);

$reportStatement->execute([
    $userId,
]);

$reports = $reportStatement->fetchAll();

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
        Profil Saya — SIGAP
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/profile.css">

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">

</head>


<body>

    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>

    <main>

        <section class="section profile-page">

            <div class="container">

                <?php if (isset($_GET['updated'])): ?>
                    <div class="notice notice-success profile-update-notice" role="status">
                        <strong>Profil berhasil diperbarui.</strong>
                        <p>Informasi akun kamu sudah berhasil disimpan.</p>
                    </div>
                <?php elseif (isset($_GET['password_updated'])): ?>
                    <div class="notice notice-success profile-update-notice" role="status">
                        <strong>Password berhasil diperbarui.</strong>
                        <p>Password akun kamu sudah berhasil diganti.</p>
                    </div>
                <?php endif; ?>

                <div class="profile-header">

                    <span class="eyebrow">
                        Profil Warga
                    </span>

                    <h1 class="page-title">
                        Halo, <?= e($user['name']) ?>!
                    </h1>

                    <p class="muted">
                        Kelola akun dan lihat aktivitas laporan kamu
                        melalui SIGAP.
                    </p>

                </div>

                <div class="profile-grid">

                    <div class="card profile-account-card">

                        <h2>
                            Informasi Akun
                        </h2>

                        <div class="profile-info-list">

                            <div class="profile-info-item">

                                <span class="profile-info-label">
                                    Nama
                                </span>

                                <strong>
                                    <?= e($user['name']) ?>
                                </strong>

                            </div>


                            <div class="profile-info-item">

                                <span class="profile-info-label">
                                    Email
                                </span>

                                <strong>
                                    <?= e($user['email']) ?>
                                </strong>

                            </div>


                            <div class="profile-info-item">

                                <span class="profile-info-label">
                                    Status Akun
                                </span>

                                <span class="badge badge-low">
                                    Warga Terdaftar
                                </span>

                            </div>


                            <div class="profile-info-item">

                                <span class="profile-info-label">
                                    Bergabung Sejak
                                </span>

                                <strong>
                                    <?= e($user['created_at']) ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <div class="card profile-action-card">

                        <h2>
                            Akun Saya
                        </h2>

                        <p class="muted">
                            Kelola informasi akun kamu dan akses kembali
                            ke halaman utama SIGAP.
                        </p>


                        <div class="profile-actions">

                            <a
                                href="/user/edit_profile.php"
                                class="btn btn-primary"
                            >
                                Edit Profil
                            </a>

                            <a
                                href="/"
                                class="btn btn-white"
                            >
                                Kembali
                            </a>

                            <button
                                type="button"
                                class="btn btn-danger"
                                id="logout-button"
                            >
                                Logout
                            </button>

                        </div>

                    </div>

                </div>

                <div class="card profile-reports-card">

                    <div class="profile-section-header">

                        <div>

                            <span class="eyebrow">
                                Aktivitas
                            </span>

                            <h2>
                                Laporan Saya
                            </h2>

                        </div>

                    </div>


                    <?php if (!$reports): ?>

                        <div class="profile-empty">

                            <div class="profile-empty-icon">
                                ✓
                            </div>

                            <h3>
                                Belum Ada Laporan
                            </h3>

                            <p class="muted">
                                Kamu belum memiliki laporan yang
                                tersimpan di SIGAP.
                            </p>

                            <a
                                href="/user/report.php"
                                class="btn btn-primary"
                            >
                                Buat Laporan
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="report-list">

                            <?php foreach ($reports as $report): ?>

                                <a
                                    href="/user/report_detail.php?id=<?= (int) $report['id'] ?>"
                                    class="report-list-item"
                                >

                                    <div class="report-list-main">

                                        <span class="report-code">
                                            <?= e($report['report_code']) ?>
                                        </span>

                                        <h3>
                                            <?= e($report['title']) ?>
                                        </h3>

                                        <p class="muted">
                                            <?= e(
                                                $report['service']
                                                    ?? 'Layanan tidak tersedia'
                                            ) ?>
                                        </p>

                                    </div>


                                    <div class="report-list-meta">

                                        <span class="badge">
                                            <?= e(
                                                status_label(
                                                    $report['status']
                                                )
                                            ) ?>
                                        </span>

                                        <span class="report-date muted">
                                            <?= e($report['created_at']) ?>
                                        </span>

                                        <span class="report-arrow">
                                            →
                                        </span>

                                    </div>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </section>

    </main>

    <div
        class="modal-overlay"
        id="logout-modal"
        aria-hidden="true"
    >

        <div
            class="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="logout-modal-title"
        >

            <div class="modal-icon">
                ↪
            </div>

            <h2 id="logout-modal-title">
                Keluar dari SIGAP?
            </h2>

            <p class="muted">
                Anda yakin ingin keluar dari akun ini?
            </p>

            <div class="modal-actions">

                <button
                    type="button"
                    class="btn btn-white"
                    id="cancel-logout"
                >
                    Batal
                </button>

                <form
                    method="post"
                    action="/user/logout.php"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        Ya, Logout
                    </button>

                </form>

            </div>

        </div>

    </div>

    <?php require_once __DIR__ . '/../../config/footer.php'; ?>

    <script>

        const logoutButton =
            document.getElementById('logout-button');

        const logoutModal =
            document.getElementById('logout-modal');

        const cancelLogout =
            document.getElementById('cancel-logout');


        function openLogoutModal() {

            logoutModal.classList.add('is-open');

            logoutModal.setAttribute(
                'aria-hidden',
                'false'
            );

        }


        function closeLogoutModal() {

            logoutModal.classList.remove('is-open');

            logoutModal.setAttribute(
                'aria-hidden',
                'true'
            );

        }


        logoutButton.addEventListener(
            'click',
            openLogoutModal
        );


        cancelLogout.addEventListener(
            'click',
            closeLogoutModal
        );


        logoutModal.addEventListener(
            'click',
            function (event) {

                if (event.target === logoutModal) {
                    closeLogoutModal();
                }

            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {
                    closeLogoutModal();
                }

            }
        );

    </script>


</body>

</html>