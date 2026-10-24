<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $statement = $pdo->prepare(
                "INSERT INTO users (
                    name,
                    email,
                    password_hash,
                    role,
                    is_verified
                ) VALUES (?, ?, ?, 'citizen', 0)"
            );

            $statement->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
            ]);

            flash(
                'success',
                'Akun berhasil dibuat. Silakan masuk.'
            );

            redirect('/user/login.php');
        } catch (PDOException $exception) {
            $error = 'Email sudah digunakan.';
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

    <title>Buat Akun SIGAP</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/register.css">

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>

<body>

<div class="form-page">
    <div class="container">

        <div class="form-card">

            <div class="card">

                <div class="auth-header">
                    <a
                        href="/"
                        class="logo"
                        aria-label="SIGAP"
                    >
                        <img
                            src="/assets/images/logo.png"
                            alt="SIGAP"
                        >
                    </a>

                    <span class="eyebrow">
                        ● Sistem Integrasi Pengaduan & Aspirasi Publik
                    </span>

                    <h1>Buat Akun SIGAP</h1>

                    <p class="muted">
                        Daftar untuk mengelola laporan dan mendapatkan
                        pengalaman layanan yang lebih personal.
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="notice auth-error">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <div class="form-group">
                        <label for="name">Nama</label>

                        <input
                            class="input"
                            id="name"
                            name="name"
                            type="text"
                            placeholder="Nama lengkap kamu"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>

                        <input
                            class="input"
                            id="email"
                            name="email"
                            type="email"
                            placeholder="nama@email.com"
                            required
                        >
                    </div>

                    <div class="two-col">

                        <div class="form-group">
                            <label for="password">Password</label>

                            <input
                                class="input"
                                id="password"
                                name="password"
                                type="password"
                                minlength="8"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="confirm">Konfirmasi Password</label>

                            <input
                                class="input"
                                id="confirm"
                                name="confirm"
                                type="password"
                                minlength="8"
                                required
                            >
                        </div>

                    </div>

                    <button
                        class="btn btn-primary btn-full"
                        type="submit"
                    >
                        Buat Akun →
                    </button>

                </form>

                <p class="auth-footer-link">
                    Sudah punya akun?
                    <a
                        href="/user/login.php"
                        class="auth-link"
                    >
                        Masuk →
                    </a>
                </p>

            </div>

            <div class="notice privacy-card">

                <b>
                    Jaminan Privasi Warga
                </b>

                <span class="muted">
                    User Account ≠ Report Identity.
                    Pelaporan anonim tetap tersedia.
                </span>

            </div>

        </div>

    </div>
</div>

</body>
</html>