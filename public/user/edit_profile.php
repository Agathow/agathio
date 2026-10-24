<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

require_login();

$userId = (int) $_SESSION['user']['id'];

$error = '';

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
    LIMIT 1
    "
);

$statement->execute([
    $userId,
]);

$user = $statement->fetch();

if (!$user) {
    redirect('/user/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $passwordStatement = $pdo->prepare(
            "SELECT password_hash FROM users WHERE id = ? LIMIT 1"
        );
        $passwordStatement->execute([$userId]);
        $passwordRow = $passwordStatement->fetch();

        if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password_hash'])) {
            $error = 'Password saat ini tidak sesuai.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password baru tidak sama.';
        } elseif ($currentPassword === $newPassword) {
            $error = 'Password baru harus berbeda dari password saat ini.';
        } else {
            try {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePassword = $pdo->prepare(
                    "UPDATE users SET password_hash = ? WHERE id = ?"
                );
                $updatePassword->execute([$newHash, $userId]);
                redirect('/user/profile.php?password_updated=1');
            } catch (PDOException $exception) {
                $error = 'Password gagal diperbarui. Silakan coba lagi.';
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $error = 'Nama wajib diisi.';
        } elseif (mb_strlen($name) < 3) {
            $error = 'Nama minimal 3 karakter.';
        }

        if ($error === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        }

        if ($error === '') {
            $statement = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
            );
            $statement->execute([$email, $userId]);
            if ($statement->fetch()) {
                $error = 'Email tersebut sudah digunakan oleh akun lain.';
            }
        }

        if ($error === '') {
            try {
                $statement = $pdo->prepare(
                    "UPDATE users SET name = ?, email = ? WHERE id = ?"
                );
                $statement->execute([$name, $email, $userId]);

                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;

                redirect('/user/profile.php?updated=1');
            } catch (PDOException $exception) {
                $error = 'Profil gagal diperbarui. Silakan coba lagi.';
            }
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
        Edit Profil — SIGAP
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/edit_profile.css">

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>


<body class="edit-profile-page">

    <main>

        <section class="edit-profile-section">

            <div class="edit-profile-container">

                <a
                    href="/user/profile.php"
                    class="profile-back-link"
                >
                    ← Kembali ke Profil
                </a>

                <div class="edit-profile-header">

                    <h1>
                        Edit Profil
                    </h1>

                    <p>
                        Perbarui informasi akun kamu melalui SIGAP.
                    </p>

                </div>

                <?php if ($error !== ''): ?>

                    <div class="notice notice-error">

                        <strong>
                            Profil belum dapat diperbarui.
                        </strong>

                        <p>
                            <?= e($error) ?>
                        </p>

                    </div>

                <?php endif; ?>

                <div class="edit-profile-card">

                    <form method="post">

                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="edit-profile-form-header">

                            <span>
                                Informasi Pribadi
                            </span>

                            <h2>
                                Data Akun
                            </h2>

                            <p>
                                Perbarui nama dan email yang digunakan
                                pada akun SIGAP kamu.
                            </p>

                        </div>

                        <div class="form-group">

                            <label for="name">
                                Nama
                            </label>

                            <input
                                class="input"
                                type="text"
                                id="name"
                                name="name"
                                value="<?= e($user['name']) ?>"
                                minlength="3"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="email">
                                Email
                            </label>

                            <input
                                class="input"
                                type="email"
                                id="email"
                                name="email"
                                value="<?= e($user['email']) ?>"
                                required
                            >

                        </div>

                        <div class="edit-profile-info-grid">

                            <div class="edit-profile-info">

                                <span>
                                    Status Akun
                                </span>

                                <strong>
                                    Warga Terdaftar
                                </strong>

                            </div>


                            <div class="edit-profile-info">

                                <span>
                                    Bergabung Sejak
                                </span>

                                <strong>
                                    <?= e($user['created_at']) ?>
                                </strong>

                            </div>

                        </div>

                        <div class="edit-profile-actions">

                            <a
                                href="/user/profile.php"
                                class="btn btn-white"
                            >
                                Batal
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Simpan Perubahan
                            </button>

                        </div>

                    </form>

                </div>

                <section class="edit-password-card" aria-labelledby="editPasswordTitle">
                    <div class="edit-profile-form-header">
                        <span>Keamanan Akun</span>
                        <h2 id="editPasswordTitle">Ubah Password</h2>
                        <p>Gunakan password yang kuat dan jangan membagikannya kepada orang lain.</p>
                    </div>

                    <form method="post" class="edit-password-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_password">

                        <div class="form-group">
                            <label for="current_password">Password Saat Ini</label>
                            <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input class="input" type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                            <small class="edit-password-hint">Minimal 8 karakter.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input class="input" type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                        </div>

                        <div class="edit-profile-actions">
                            <button type="submit" class="btn btn-primary">Perbarui Password</button>
                        </div>
                    </form>
                </section>

            </div>

        </section>

    </main>


</body>

</html>