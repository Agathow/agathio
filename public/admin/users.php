<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

if (!is_admin()) {
    http_response_code(403);
    exit('Admin only');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {

        if ($id === (int) $_SESSION['user']['id']) {
            flash(
                'error',
                'Akun yang sedang digunakan tidak dapat dihapus.'
            );

            redirect('/admin/users.php');
        }

        $stmt = $pdo->prepare(
            "DELETE FROM users WHERE id = ?"
        );

        $stmt->execute([$id]);

        flash(
            'success',
            'User berhasil dihapus.'
        );

        redirect('/admin/users.php');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'citizen';

    $verified = isset($_POST['is_verified'])
        ? 1
        : 0;


    if ($action === 'create') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Format email tidak valid.');
            redirect('/admin/users.php');
        }

        $checkEmail = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );
        $checkEmail->execute([$email]);

        if ($checkEmail->fetchColumn()) {
            flash('error', 'Email tersebut sudah terdaftar. Gunakan email lain.');
            redirect('/admin/users.php');
        }

        $password = $_POST['password'] ?? '';

        if ($password === '') {
            $password = 'user12345';
        }

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare(
            "INSERT INTO users
            (
                name,
                email,
                password_hash,
                role,
                is_verified
            )
            VALUES (?, ?, ?, ?, ?)"
        );

        try {
            $stmt->execute([
                $name,
                $email,
                $passwordHash,
                $role,
                $verified
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'Email tersebut sudah terdaftar. Gunakan email lain.');
                redirect('/admin/users.php');
            }

            throw $exception;
        }

        flash(
            'success',
            'User berhasil dibuat.'
        );

        redirect('/admin/users.php');
    }

    if ($action === 'update') {

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Format email tidak valid.');
            redirect('/admin/users.php?edit=' . $id);
        }

        $checkEmail = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1"
        );
        $checkEmail->execute([$email, $id]);

        if ($checkEmail->fetchColumn()) {
            flash('error', 'Email tersebut sudah digunakan oleh user lain. Gunakan email lain.');
            redirect('/admin/users.php?edit=' . $id);
        }

        $stmt = $pdo->prepare(
            "UPDATE users
             SET
                name = ?,
                email = ?,
                role = ?,
                is_verified = ?
             WHERE id = ?"
        );

        try {
            $stmt->execute([
                $name,
                $email,
                $role,
                $verified,
                $id
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'Email tersebut sudah digunakan oleh user lain. Gunakan email lain.');
                redirect('/admin/users.php?edit=' . $id);
            }

            throw $exception;
        }

        if (!empty($_POST['password'])) {

            $passwordHash = password_hash(
                $_POST['password'],
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET password_hash = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $passwordHash,
                $id
            ]);
        }

        flash(
            'success',
            'User berhasil diperbarui.'
        );

        redirect('/admin/users.php');
    }
}

$edit = null;

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    $stmt = $pdo->prepare(
        "SELECT *
         FROM users
         WHERE id = ?"
    );

    $stmt->execute([
        $editId
    ]);

    $edit = $stmt->fetch();
}

$search = trim(
    $_GET['q'] ?? ''
);

$roleFilter = trim(
    $_GET['role'] ?? ''
);

$verifiedFilter = trim(
    $_GET['verified'] ?? ''
);


$perPage = 5;

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            name LIKE ?
            OR email LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}

if (
    in_array(
        $roleFilter,
        ['citizen', 'inspector', 'admin'],
        true
    )
) {

    $where[] = "role = ?";
    $params[] = $roleFilter;
}

if (
    $verifiedFilter === 'verified'
) {

    $where[] = "is_verified = 1";

} elseif (
    $verifiedFilter === 'unverified'
) {

    $where[] = "is_verified = 0";
}

$whereSql = '';

if (!empty($where)) {

    $whereSql = 'WHERE ' . implode(
        ' AND ',
        $where
    );
}

$countSql = "
    SELECT COUNT(*)
    FROM users
    $whereSql
";

$stmt = $pdo->prepare(
    $countSql
);

$stmt->execute(
    $params
);

$totalUsers = (int) $stmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil(
        $totalUsers / $perPage
    )
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = (
    $page - 1
) * $perPage;

$sql = "
    SELECT
        id,
        name,
        email,
        role,
        is_verified,
        created_at
    FROM users
    $whereSql
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare(
    $sql
);

$stmt->execute(
    $params
);

$rows = $stmt->fetchAll();

$startUser = $totalUsers > 0
    ? $offset + 1
    : 0;

$endUser = min(
    $offset + $perPage,
    $totalUsers
);

admin_header(
    'User Management',
    'users'
);

?>

<div class="detail-grid">
    <div class="card">

        <div class="user-form-header">

            <div class="user-section-icon">
                <span class="material-symbols-rounded">
                    person_add
                </span>
            </div>

            <div>

                <span class="eyebrow">
                    <?= $edit ? 'EDIT DATA' : 'CREATE DATA' ?>
                </span>

                <h2>
                    <?= $edit ? 'Edit User' : 'Tambah User' ?>
                </h2>

                <p class="muted">
                    <?= $edit
                        ? 'Perbarui informasi akun pengguna.'
                        : 'Tambahkan akun pengguna baru ke dalam sistem.'
                    ?>
                </p>

            </div>

        </div>

        <form method="post">

            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= $edit ? 'update' : 'create' ?>"
            >

            <?php if ($edit): ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $edit['id'] ?>"
                >

            <?php endif; ?>

            <div class="form-group">

                <label for="name">
                    Nama Lengkap
                </label>

                <input
                    id="name"
                    class="input"
                    type="text"
                    name="name"
                    value="<?= e($edit['name'] ?? '') ?>"
                    placeholder="Contoh: Bagas Prakoso"
                    required
                >

            </div>

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    id="email"
                    class="input"
                    type="email"
                    name="email"
                    value="<?= e($edit['email'] ?? '') ?>"
                    placeholder="nama@email.com"
                    required
                >

            </div>

            <div class="two-col">

                <div class="form-group">

                    <label for="role">
                        Role
                    </label>

                    <select
                        id="role"
                        class="select"
                        name="role"
                    >

                        <?php foreach (
                            [
                                'citizen',
                                'inspector',
                                'admin'
                            ] as $x
                        ): ?>

                            <option
                                value="<?= e($x) ?>"
                                <?= (
                                    ($edit['role'] ?? 'citizen')
                                    === $x
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                <?= ucfirst($x) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                        <?= $edit ? '(opsional)' : '' ?>
                    </label>

                    <input
                        id="password"
                        class="input"
                        type="password"
                        name="password"
                        placeholder="Password pengguna"
                    >

                    <?php if (!$edit): ?>

                        <small class="field-help">
                            Jika dikosongkan, password default:
                            <strong>user12345</strong>
                        </small>

                    <?php else: ?>

                        <small class="field-help">
                            Kosongkan jika tidak ingin mengubah password.
                        </small>

                    <?php endif; ?>

                </div>

            </div>

            <div class="user-verification-box">

                <label class="checkbox-row">

                    <input
                        type="checkbox"
                        name="is_verified"
                        <?= (
                            $edit['is_verified'] ?? 0
                        )
                            ? 'checked'
                            : ''
                        ?>
                    >

                    <span>

                        <strong>
                            Akun Terverifikasi
                        </strong>

                        <small>
                            Tandai akun sebagai pengguna yang telah
                            diverifikasi.
                        </small>

                    </span>

                </label>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= $edit
                        ? 'Simpan Perubahan'
                        : 'Create User'
                    ?>
                </button>

                <?php if ($edit): ?>

                    <a
                        href="/admin/users.php"
                        class="btn btn-light"
                    >
                        Batal
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>

    <div class="card user-database-card">

        <div class="user-database-header">

            <div class="user-section-icon">

                <span class="material-symbols-rounded">
                    group
                </span>

            </div>

            <div>

                <span class="eyebrow">
                    USER DATABASE
                </span>

                <h2>
                    Daftar User
                </h2>

                <p class="muted">
                    <?= number_format($totalUsers) ?>
                    pengguna terdaftar dalam sistem.
                </p>

            </div>

        </div>

        <form
            method="get"
            class="user-filter-form"
        >

            <div class="user-search">

                <span class="material-symbols-rounded">
                    search
                </span>

                <input
                    class="input"
                    type="text"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Cari nama atau email..."
                >

            </div>


            <select
                class="select"
                name="role"
            >

                <option value="">
                    Semua Role
                </option>

                <option
                    value="citizen"
                    <?= $roleFilter === 'citizen'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Citizen
                </option>

                <option
                    value="inspector"
                    <?= $roleFilter === 'inspector'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Inspector
                </option>

                <option
                    value="admin"
                    <?= $roleFilter === 'admin'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Admin
                </option>

            </select>


            <select
                class="select"
                name="verified"
            >

                <option value="">
                    Semua Status
                </option>

                <option
                    value="verified"
                    <?= $verifiedFilter === 'verified'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Verified
                </option>

                <option
                    value="unverified"
                    <?= $verifiedFilter === 'unverified'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Unverified
                </option>

            </select>


            <button
                type="submit"
                class="btn btn-light"
            >
                Filter
            </button>

        </form>

        <div class="user-result-info">

            <span>
                Menampilkan
                <strong><?= $startUser ?></strong>
                –
                <strong><?= $endUser ?></strong>
                dari
                <strong><?= number_format($totalUsers) ?></strong>
                user
            </span>

            <?php if (
                $search !== ''
                || $roleFilter !== ''
                || $verifiedFilter !== ''
            ): ?>

                <a href="/admin/users.php">
                    Reset Filter
                </a>

            <?php endif; ?>

        </div>

        <div class="user-list">

            <?php if (empty($rows)): ?>

                <div class="user-empty">

                    <span class="material-symbols-rounded">
                        person_off
                    </span>

                    <strong>
                        User tidak ditemukan
                    </strong>

                    <p class="muted">
                        Coba ubah kata pencarian atau filter.
                    </p>

                </div>

            <?php else: ?>

                <?php foreach ($rows as $r): ?>

                    <?php

                    $isCurrentUser =
                        (int) $r['id']
                        === (int) $_SESSION['user']['id'];

                    ?>

                    <div class="user-item">

                        <div class="user-avatar">

                            <span class="material-symbols-rounded">
                                person
                            </span>

                        </div>

                        <div class="user-info">

                            <strong
                                class="user-name"
                                title="<?= e($r['name']) ?>"
                            >
                                <?= e($r['name']) ?>
                            </strong>

                            <span class="user-email">
                                <?= e($r['email']) ?>
                            </span>

                            <div class="user-tags">

                                <?php if ($r['role'] === 'admin'): ?>

                                    <span class="user-tag tag-admin">

                                        <span class="material-symbols-rounded">
                                            admin_panel_settings
                                        </span>

                                        Admin

                                    </span>

                                <?php elseif (
                                    $r['role'] === 'inspector'
                                ): ?>

                                    <span class="user-tag tag-inspector">

                                        <span class="material-symbols-rounded">
                                            verified_user
                                        </span>

                                        Inspector

                                    </span>

                                <?php else: ?>

                                    <span class="user-tag tag-citizen">

                                        <span class="material-symbols-rounded">
                                            person
                                        </span>

                                        Citizen

                                    </span>

                                <?php endif; ?>


                                <?php if ($r['is_verified']): ?>

                                    <span class="user-tag tag-verified">

                                        <span class="material-symbols-rounded">
                                            verified
                                        </span>

                                        Verified

                                    </span>

                                <?php else: ?>

                                    <span class="user-tag tag-unverified">

                                        <span class="material-symbols-rounded">
                                            help
                                        </span>

                                        Unverified

                                    </span>

                                <?php endif; ?>


                                <?php if ($isCurrentUser): ?>

                                    <span class="user-tag tag-current">

                                        Akun Anda

                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                        <div class="user-actions">

                            <a
                                href="?edit=<?= (int) $r['id'] ?>"
                                class="btn btn-light btn-sm"
                            >

                                <span class="material-symbols-rounded">
                                    edit
                                </span>

                                Edit

                            </a>


                            <?php if (!$isCurrentUser): ?>

                                <form
                                    method="post"
                                    onsubmit="return confirmDelete('Hapus user ini?')"
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
                                        value="<?= (int) $r['id'] ?>"
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

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <?php if ($totalPages > 1): ?>

            <div class="user-pagination">

                <?php

                $paginationParams = [];

                if ($search !== '') {
                    $paginationParams['q'] = $search;
                }

                if ($roleFilter !== '') {
                    $paginationParams['role'] = $roleFilter;
                }

                if ($verifiedFilter !== '') {
                    $paginationParams['verified'] = $verifiedFilter;
                }

                ?>

                <?php if ($page > 1): ?>

                    <?php
                    $paginationParams['page'] = $page - 1;
                    ?>

                    <a
                        href="?<?= http_build_query($paginationParams) ?>"
                        class="pagination-btn"
                        aria-label="Halaman sebelumnya"
                    >
                        <span class="material-symbols-rounded">
                            chevron_left
                        </span>
                    </a>

                <?php endif; ?>


                <?php

                $startPage = max(
                    1,
                    $page - 2
                );

                $endPage = min(
                    $totalPages,
                    $page + 2
                );

                ?>


                <?php if ($startPage > 1): ?>

                    <?php
                    $paginationParams['page'] = 1;
                    ?>

                    <a
                        href="?<?= http_build_query($paginationParams) ?>"
                        class="pagination-btn"
                    >
                        1
                    </a>

                    <?php if ($startPage > 2): ?>

                        <span class="pagination-dots">
                            …
                        </span>

                    <?php endif; ?>

                <?php endif; ?>


                <?php for (
                    $i = $startPage;
                    $i <= $endPage;
                    $i++
                ): ?>

                    <?php
                    $paginationParams['page'] = $i;
                    ?>

                    <a
                        href="?<?= http_build_query($paginationParams) ?>"
                        class="
                            pagination-btn
                            <?= $i === $page
                                ? 'active'
                                : ''
                            ?>
                        "
                    >
                        <?= $i ?>
                    </a>

                <?php endfor; ?>


                <?php if ($endPage < $totalPages): ?>

                    <?php if ($endPage < $totalPages - 1): ?>

                        <span class="pagination-dots">
                            …
                        </span>

                    <?php endif; ?>


                    <?php
                    $paginationParams['page'] = $totalPages;
                    ?>

                    <a
                        href="?<?= http_build_query($paginationParams) ?>"
                        class="pagination-btn"
                    >
                        <?= $totalPages ?>
                    </a>

                <?php endif; ?>


                <?php if ($page < $totalPages): ?>

                    <?php
                    $paginationParams['page'] = $page + 1;
                    ?>

                    <a
                        href="?<?= http_build_query($paginationParams) ?>"
                        class="pagination-btn"
                        aria-label="Halaman berikutnya"
                    >
                        <span class="material-symbols-rounded">
                            chevron_right
                        </span>
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php

admin_footer();

?>