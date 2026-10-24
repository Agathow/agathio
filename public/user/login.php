<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $pdo->prepare(
        "
        SELECT *
        FROM users
        WHERE email = ?
        LIMIT 1
        "
    );

    $statement->execute([$email]);

    $user = $statement->fetch();


    $isValid = $user && (
        hash(
            'sha256',
            $password
        ) === $user['password_hash']
        ||
        password_verify(
            $password,
            $user['password_hash']
        )
    );


    if ($isValid) {

        $_SESSION['user'] = $user;


        if (
            in_array(
                $user['role'],
                ['admin', 'inspector'],
                true
            )
        ) {

            redirect('/admin/dashboard.php');
        }

        redirect('/user/profile.php');
    }


    $error = 'Email atau password salah.';
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
        Masuk — SIGAP
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/login.css">

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>


<body>

    <div class="form-page">

        <div class="container">

            <div class="two-col">

                <div class="card form-card">

                    <div class="logo">
                        <img
                            src="/assets/images/logo.png"
                            alt="SIGAP"
                        >
                    </div>

                    <h1>
                        Selamat Datang Kembali 👋
                    </h1>

                    <p class="muted">
                        Masuk untuk mengakses laporan dan pengaturan
                        akun kamu.
                    </p>

                    <?php if ($error): ?>

                        <div
                            class="notice"
                            style="background: #ffe3e3; color: #a52222"
                        >
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

                            <label for="email">
                                Email
                            </label>

                            <input
                                class="input"
                                id="email"
                                name="email"
                                type="email"
                                placeholder="nama@email.com"
                                required
                            >
                        </div>


                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>

                            <input
                                class="input"
                                id="password"
                                name="password"
                                type="password"
                                placeholder="••••••••"
                                required
                            >
                        </div>


                        <button
                            class="btn btn-primary"
                            style="width: 100%"
                            type="submit"
                        >
                            Masuk →
                        </button>

                    </form>

                    <hr
                        style="
                            border: 0;
                            border-top: 1px solid var(--line);
                            margin: 24px 0;
                        "
                    >

                    <p>

                        Belum punya akun?

                        <a
                            href="/user/register.php"
                            style="color: #08734f; font-weight: 800"
                        >
                            Daftar sekarang →
                        </a>

                    </p>


                    <p>

                        <a href="/" class="login-back-home">
                            ← Kembali ke beranda
                        </a>

                    </p>

                </div>

                <div>

                    <div
                        class="card"
                        style="background: #d9f5e8"
                    >

                        <h2>
                            Pelaporan Tetap Bisa Dilakukan Tanpa Akun
                        </h2>

                        <p class="muted">
                            SIGAP tidak mewajibkan pembuatan akun
                            untuk melaporkan dugaan pungutan secara
                            aman dan terenkripsi.
                        </p>

                        <div class="eyebrow">
                            <span class="material-symbols-rounded">lock</span>
                            Enkripsi 256-bit
                        </div>

                        <div class="eyebrow">
                            <span class="material-symbols-rounded">shield</span>
                            Identitas Rahasia
                        </div>

                        <div class="eyebrow">
                            <span class="material-symbols-rounded">verified</span>
                            Resmi &amp; Terlindungi
                        </div>

                    </div>

                    <div class="card mascot-login-card">

                        <div class="asset-placeholder mascot-login-placeholder">

                            REPLACE WITH
                            <br>
                            mascot.png

                        </div>

                        <p class="mascot-quote">
                            “Kamu tetap bebas memilih lapor anonim
                            kapan pun!”
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>