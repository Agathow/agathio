
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$navItems = [
    [
        'label' => 'Beranda',
        'url' => '/',
    ],
    [
        'label' => 'Cek Layanan',
        'url' => '/user/services.php',
    ],
    [
        'label' => 'Cek Biaya',
        'url' => '/user/check_payment.php',
    ],
    [
        'label' => 'Cek Status',
        'url' => '/user/check_status.php',
    ],
    [
        'label' => 'Laporkan',
        'url' => '/user/report.php',
    ],
];

?>

<header class="topbar">
    <div class="container inner">

        <a href="/" class="logo" aria-label="SIGAP - Beranda">
            <img src="/assets/images/logo.png" alt="SIGAP">
        </a>

        <button
            type="button"
            class="mobile-menu-toggle"
            id="mobileMenuToggle"
            aria-label="Buka menu navigasi"
            aria-controls="publicNav"
            aria-expanded="false"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="public-nav" id="publicNav" aria-label="Navigasi utama">

            <?php foreach ($navItems as $item): ?>
                <a
                    href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                    class="<?= $currentPath === $item['url'] ? 'active' : '' ?>"
                    <?= $currentPath === $item['url'] ? 'aria-current="page"' : '' ?>
                >
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>

            <div class="mobile-account">
                <?php if (isset($_SESSION['user'])): ?>

                    <a
                        href="/user/profile.php"
                        class="mobile-account-link <?= $currentPath === '/user/profile.php' ? 'active' : '' ?>"
                    >
                        <?= htmlspecialchars(
                            $_SESSION['user']['name'] ?? 'Akun Saya',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </a>

                <?php else: ?>

                    <a href="/user/login.php" class="mobile-account-link">
                        Masuk
                    </a>

                <?php endif; ?>
            </div>

        </nav>

        <div class="topbar-actions">
            <?php if (isset($_SESSION['user'])): ?>

                <a
                    href="/user/profile.php"
                    class="login-link <?= $currentPath === '/user/profile.php' ? 'active' : '' ?>"
                >
                    <?= htmlspecialchars(
                        $_SESSION['user']['name'] ?? 'Akun Saya',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </a>

            <?php else: ?>

                <a href="/user/login.php" class="login-link">
                    Masuk
                </a>

            <?php endif; ?>
        </div>

    </div>
</header>

<script>
(() => {
    const toggle = document.getElementById('mobileMenuToggle');
    const nav = document.getElementById('publicNav');

    if (!toggle || !nav) return;

    function closeMenu() {
        toggle.classList.remove('is-open');
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka menu navigasi');
    }

    function openMenu() {
        toggle.classList.add('is-open');
        nav.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Tutup menu navigasi');
    }

    toggle.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeMenu();
    });
})();
</script>
