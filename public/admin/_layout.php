<?php

function admin_header(string $title, string $active = 'dashboard'): void
{
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
        <?= e($title ?: 'SIGAP Intelligence Unit') ?>
        — SIGAP Intelligence Unit
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <link rel="icon" type="image/png"
      href="/assets/images/logo%20tab.png?v=2">
    <link rel="apple-touch-icon"
        href="/assets/images/logo%20tab.png?v=2">
    <?php
    $adminPageCss = [
        '/admin/dashboard.php' => '/assets/css/admin_dashboard.css',
        '/admin/pattern.php' => '/assets/css/admin_pattern.css',
        '/admin/pattern_detail.php' => '/assets/css/admin_pattern_detail.css',
        '/admin/risk_map.php' => '/assets/css/admin_risk_map.css',
        '/admin/reports.php' => '/assets/css/admin_reports.css',
        '/admin/report_detail.php' => '/assets/css/admin_report_detail.css',
        '/admin/report_form.php' => '/assets/css/admin_report_form.css',
        '/admin/services.php' => '/assets/css/admin_services.css',
        '/admin/users.php' => '/assets/css/admin_users.css',
    ];
    $currentAdminPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (isset($adminPageCss[$currentAdminPath])):
    ?>
        <link rel="stylesheet" href="<?= e($adminPageCss[$currentAdminPath]) ?>">
    <?php endif; ?>

    <script src="/assets/js/app.js"></script>

</head>

<body>

<div class="admin-layout">

    <aside class="sidebar">

        <div class="side-brand">

            <a
                href="/admin/dashboard.php"
                class="side-logo"
                aria-label="SIGAP Intelligence Unit"
            >
                <img
                    src="/assets/images/logo.png"
                    alt="SIGAP"
                >
            </a>

            <div class="side-sub">
                <span class="live-dot"></span>
                Govt Intelligence Center
            </div>

        </div>

        <div class="side-title">
            Navigasi Utama
        </div>

        <nav class="side-nav">

            <a
                class="<?= $active === 'dashboard' ? 'active' : '' ?>"
                href="/admin/dashboard.php"
            >
                <span class="material-symbols-rounded">
                    dashboard
                </span>

                <span>
                    Dashboard
                </span>
            </a>


            <a
                class="<?= $active === 'pattern' ? 'active' : '' ?>"
                href="/admin/pattern.php"
            >
                <span class="material-symbols-rounded">
                    analytics
                </span>

                <span>
                    Pattern Detection
                </span>
            </a>


            <a
                class="<?= $active === 'risk' ? 'active' : '' ?>"
                href="/admin/risk_map.php"
            >
                <span class="material-symbols-rounded">
                    location_on
                </span>

                <span>
                    Risk Map
                </span>
            </a>


            <a
                class="<?= $active === 'reports' ? 'active' : '' ?>"
                href="/admin/reports.php"
            >
                <span class="material-symbols-rounded">
                    description
                </span>

                <span>
                    Report Management
                </span>
            </a>


            <a
                class="<?= $active === 'services' ? 'active' : '' ?>"
                href="/admin/services.php"
            >
                <span class="material-symbols-rounded">
                    account_balance
                </span>

                <span>
                    Service Management
                </span>
            </a>


            <?php if (is_admin()): ?>

                <a
                    class="<?= $active === 'users' ? 'active' : '' ?>"
                    href="/admin/users.php"
                >
                    <span class="material-symbols-rounded">
                        group
                    </span>

                    <span>
                        User Management
                    </span>
                </a>

            <?php endif; ?>


            <a href="/">
                <span class="material-symbols-rounded">
                    open_in_new
                </span>

                <span>
                    Public Website
                </span>
            </a>

        </nav>

        <div class="side-bottom">

            <b>
                <span class="live-dot"></span>
                Live Sync
            </b>

            <div>
                Admin Pemerintah
            </div>

            <span class="muted">
                Sistem pemantauan aktif
            </span>

        </div>

    </aside>

    <main class="admin-main">

        <div class="admin-top">

            <div class="admin-title">

                <span class="eyebrow">
                    <span class="eyebrow-live-dot" aria-hidden="true"></span>
                    Sistem Pemantauan Aktif (Real-time Audit)
                </span>

                <?php if ($title !== ''): ?>

                    <h1>
                        <?= e($title) ?>
                    </h1>

                <?php endif; ?>

            </div>


            <div class="action-row">

                <a
                    href="#"
                    class="btn btn-light"
                    onclick="openLogoutModal(); return false;"
                >

                    <span class="material-symbols-rounded">
                        logout
                    </span>

                    Keluar

                </a>

            </div>

        </div>

        <?php if ($f = get_flash()): ?>

            <div class="toast">
                <?= e($f[1]) ?>
            </div>

        <?php endif; ?>


<?php
}


function admin_footer(): void
{
?>

    </main>

</div>

<div
    id="logoutModal"
    class="logout-modal"
    aria-hidden="true"
>

    <div
        class="logout-modal-backdrop"
        onclick="closeLogoutModal()"
    ></div>


    <div
        class="logout-modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logoutModalTitle"
    >

        <div class="logout-modal-icon">

            <span class="material-symbols-rounded">
                logout
            </span>

        </div>


        <h3 id="logoutModalTitle">
            Keluar dari Admin?
        </h3>


        <p>
            Sesi admin kamu akan diakhiri dan kamu akan
            kembali ke halaman utama SIGAP.
        </p>


        <div class="logout-modal-actions">

            <button
                type="button"
                class="logout-cancel"
                onclick="closeLogoutModal()"
            >
                Batal
            </button>


            <a
                href="/admin/logout.php"
                class="logout-confirm"
            >

                <span class="material-symbols-rounded">
                    logout
                </span>

                Ya, Keluar

            </a>

        </div>

    </div>

</div>


<script>

function openLogoutModal()
{
    const modal = document.getElementById('logoutModal');

    modal.classList.add('show');

    modal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.classList.add(
        'modal-open'
    );
}


function closeLogoutModal()
{
    const modal = document.getElementById('logoutModal');

    modal.classList.remove('show');

    modal.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.classList.remove(
        'modal-open'
    );
}


document.addEventListener(
    'keydown',
    function(event)
    {
        if (event.key === 'Escape') {
            closeLogoutModal();
        }
    }
);

</script>


</body>

</html>

<?php
}
?>