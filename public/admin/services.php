<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/_layout.php';

require_staff();

if (!is_admin()) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini hanya dapat diakses oleh admin.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$errors = [];
$fieldErrors = [];
$success = '';
$edit = null;

$form = [
    'category_id' => '',
    'name' => '',
    'institution' => '',
    'official_fee' => '0',
    'duration_minutes' => '60',
    'regulation' => '',
    'description' => '',
    'requirements' => '',
    'procedure_text' => '',
    'status' => 'active',
];

$editId = 0;

$flashMessage = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $idInput = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($action === 'delete') {
        if ($idInput === false || $idInput === null || $idInput < 1) {
            flash('error', 'ID layanan tidak valid.');
            redirect('/admin/services.php');
        }

        try {
            $stmt = $pdo->prepare(
                'DELETE FROM public_services WHERE id = ?'
            );
            $stmt->execute([$idInput]);

            flash(
                'success',
                $stmt->rowCount() > 0
                    ? 'Layanan berhasil dihapus.'
                    : 'Layanan tidak ditemukan atau sudah dihapus.'
            );
        } catch (PDOException $exception) {
            flash(
                'error',
                'Layanan gagal dihapus. Data mungkin masih digunakan oleh bagian lain.'
            );
        }

        redirect('/admin/services.php');
    }

    if (!in_array($action, ['create', 'update'], true)) {
        flash('error', 'Aksi tidak dikenali.');
        redirect('/admin/services.php');
    }

    if ($action === 'update') {
        if ($idInput === false || $idInput === null || $idInput < 1) {
            $errors[] = 'ID layanan yang akan diedit tidak valid.';
        } else {
            $editId = $idInput;
        }
    }

    foreach ($form as $key => $defaultValue) {
        if ($key === 'official_fee' || $key === 'duration_minutes') {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        } else {
            $form[$key] = trim((string) ($_POST[$key] ?? $defaultValue));
        }
    }

    if ($form['name'] === '') {
        $fieldErrors['name'] = 'Nama layanan wajib diisi.';
    } elseif (mb_strlen($form['name']) > 255) {
        $fieldErrors['name'] = 'Nama layanan maksimal 255 karakter.';
    }

    $categoryId = filter_var(
        $form['category_id'],
        FILTER_VALIDATE_INT
    );

    if (
        $form['category_id'] === '' ||
        $categoryId === false ||
        $categoryId < 1
    ) {
        $fieldErrors['category_id'] = 'Silakan pilih kategori layanan.';
    }

    if ($form['institution'] === '') {
        $fieldErrors['institution'] = 'Nama instansi wajib diisi.';
    } elseif (mb_strlen($form['institution']) > 255) {
        $fieldErrors['institution'] = 'Nama instansi maksimal 255 karakter.';
    }

    $officialFee = null;

    if (
        $form['official_fee'] === '' ||
        !preg_match('/^\d+$/', $form['official_fee'])
    ) {
        $fieldErrors['official_fee'] =
            'Tarif wajib diisi dengan bilangan bulat minimal 0.';
    } else {
        $validatedFee = filter_var(
            $form['official_fee'],
            FILTER_VALIDATE_INT
        );

        if ($validatedFee === false || $validatedFee < 0) {
            $fieldErrors['official_fee'] =
                'Tarif tidak valid. Masukkan bilangan bulat mulai dari 0.';
        } else {
            $officialFee = $validatedFee;
        }
    }

    $durationMinutes = null;

    if (
        $form['duration_minutes'] === '' ||
        !preg_match('/^\d+$/', $form['duration_minutes'])
    ) {
        $fieldErrors['duration_minutes'] =
            'Durasi wajib diisi dengan bilangan bulat positif.';
    } else {
        $validatedDuration = filter_var(
            $form['duration_minutes'],
            FILTER_VALIDATE_INT
        );

        if (
            $validatedDuration === false ||
            $validatedDuration < 1
        ) {
            $fieldErrors['duration_minutes'] =
                'Durasi minimal 1 menit.';
        } else {
            $durationMinutes = $validatedDuration;
        }
    }

    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $fieldErrors['status'] = 'Pilih status layanan yang valid.';
    }

    if (
        !isset($fieldErrors['category_id']) &&
        is_int($categoryId)
    ) {
        $categoryCheck = $pdo->prepare(
            'SELECT id FROM service_categories WHERE id = ? LIMIT 1'
        );
        $categoryCheck->execute([$categoryId]);

        if (!$categoryCheck->fetch()) {
            $fieldErrors['category_id'] =
                'Kategori yang dipilih tidak ditemukan.';
        }
    }

    foreach ($fieldErrors as $message) {
        $errors[] = $message;
    }

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO public_services (
                        category_id,
                        name,
                        institution,
                        official_fee,
                        regulation,
                        duration_minutes,
                        description,
                        requirements,
                        procedure_text,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $categoryId,
                    $form['name'],
                    $form['institution'],
                    $officialFee,
                    $form['regulation'] !== '' ? $form['regulation'] : null,
                    $durationMinutes,
                    $form['description'] !== '' ? $form['description'] : null,
                    $form['requirements'] !== '' ? $form['requirements'] : null,
                    $form['procedure_text'] !== '' ? $form['procedure_text'] : null,
                    $form['status'],
                ]);

                flash('success', 'Layanan berhasil ditambahkan.');
                redirect('/admin/services.php');
            }

            $exists = $pdo->prepare(
                'SELECT id FROM public_services WHERE id = ? LIMIT 1'
            );
            $exists->execute([$editId]);

            if (!$exists->fetch()) {
                $errors[] = 'Layanan yang ingin diedit tidak ditemukan.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE public_services SET
                        category_id = ?,
                        name = ?,
                        institution = ?,
                        official_fee = ?,
                        regulation = ?,
                        duration_minutes = ?,
                        description = ?,
                        requirements = ?,
                        procedure_text = ?,
                        status = ?
                    WHERE id = ?'
                );

                $stmt->execute([
                    $categoryId,
                    $form['name'],
                    $form['institution'],
                    $officialFee,
                    $form['regulation'] !== '' ? $form['regulation'] : null,
                    $durationMinutes,
                    $form['description'] !== '' ? $form['description'] : null,
                    $form['requirements'] !== '' ? $form['requirements'] : null,
                    $form['procedure_text'] !== '' ? $form['procedure_text'] : null,
                    $form['status'],
                    $editId,
                ]);

                flash('success', 'Layanan berhasil diperbarui.');
                redirect('/admin/services.php');
            }
        } catch (PDOException $exception) {
            $errors[] =
                'Data gagal disimpan. Periksa koneksi database dan struktur tabel.';
        }
    }

    if ($action === 'update') {
        $edit = ['id' => $editId] + $form;
    }
}

if ($edit === null && isset($_GET['edit'])) {
    $requestedId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT
    );

    if ($requestedId === false || $requestedId === null || $requestedId < 1) {
        $errors[] = 'ID layanan yang diminta tidak valid.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM public_services WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$requestedId]);
        $edit = $stmt->fetch() ?: null;

        if (!$edit) {
            $errors[] = 'Layanan yang ingin diedit tidak ditemukan.';
        } else {
            $editId = (int) $edit['id'];

            foreach ($form as $key => $defaultValue) {
                if (array_key_exists($key, $edit)) {
                    $form[$key] = (string) ($edit[$key] ?? '');
                }
            }

            $form['official_fee'] = (string) (int) ($edit['official_fee'] ?? 0);
            $form['duration_minutes'] =
                (string) (int) ($edit['duration_minutes'] ?? 60);
        }
    }
}

$isEditing = is_array($edit) && !empty($edit['id']);

$categories = $pdo->query(
    'SELECT id, name FROM service_categories ORDER BY name ASC'
)->fetchAll();

$search = trim((string) ($_GET['q'] ?? ''));
$filterCategory = trim((string) ($_GET['category'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = ($page !== false && $page > 0) ? $page : 1;

$perPage = 6;
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(ps.name LIKE ? OR ps.institution LIKE ? OR sc.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}

if ($filterCategory !== '' && ctype_digit($filterCategory)) {
    $where[] = 'ps.category_id = ?';
    $params[] = (int) $filterCategory;
}

if (in_array($filterStatus, ['active', 'inactive'], true)) {
    $where[] = 'ps.status = ?';
    $params[] = $filterStatus;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM public_services ps
     LEFT JOIN service_categories sc ON sc.id = ps.category_id'
    . $whereSql
);
$countStmt->execute($params);
$totalServices = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalServices / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    'SELECT
        ps.id,
        ps.category_id,
        ps.name,
        ps.institution,
        ps.official_fee,
        ps.duration_minutes,
        ps.status,
        sc.name AS category_name
     FROM public_services ps
     LEFT JOIN service_categories sc ON sc.id = ps.category_id'
    . $whereSql .
    ' ORDER BY ps.id DESC LIMIT ' . (int) $perPage .
    ' OFFSET ' . (int) $offset
);
$listStmt->execute($params);
$services = $listStmt->fetchAll();

admin_header('Service Management', 'services');
?>

<div class="admin-page-header service-page-header">
    <div>
        <p class="muted">
            Kelola informasi layanan publik yang tersedia pada platform SIGAP.
        </p>
    </div>
</div>

<?php if ($flashMessage): ?>
    <div class="service-alert <?= ($flashMessage[0] ?? '') === 'error'
        ? 'service-alert-error'
        : 'service-alert-success' ?>" role="status">
        <span class="material-symbols-rounded">
            <?= ($flashMessage[0] ?? '') === 'error' ? 'error' : 'check_circle' ?>
        </span>
        <div><?= e($flashMessage[1] ?? '') ?></div>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="service-alert service-alert-error" role="alert">
        <span class="material-symbols-rounded">error</span>
        <div>
            <strong>Periksa kembali data berikut.</strong>
            <ul>
                <?php foreach (array_unique($errors) as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="service-management-grid">

    <section class="card service-form-card">
        <div class="service-section-header">
            <div class="service-section-icon">
                <span class="material-symbols-rounded">
                    <?= $isEditing ? 'edit' : 'add_business' ?>
                </span>
            </div>
            <div>
                <span class="eyebrow">
                    <?= $isEditing ? 'UPDATE DATA' : 'CREATE DATA' ?>
                </span>
                <h2><?= $isEditing ? 'Edit Layanan' : 'Tambah Layanan' ?></h2>
                <p class="muted">
                    Kelola informasi layanan publik SIGAP.
                </p>
            </div>
        </div>

        <p class="form-required-note">
            <span class="required-star">*</span> Wajib diisi.
        </p>

        <form method="post" action="/admin/services.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input
                type="hidden"
                name="action"
                value="<?= $isEditing ? 'update' : 'create' ?>"
            >

            <?php if ($isEditing): ?>
                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name">
                    Nama Layanan <span class="required-star">*</span>
                </label>
                <input
                    class="input <?= isset($fieldErrors['name']) ? 'is-invalid' : '' ?>"
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($form['name']) ?>"
                    placeholder="Contoh: Pembuatan SIM"
                    maxlength="255"
                    required
                    aria-invalid="<?= isset($fieldErrors['name']) ? 'true' : 'false' ?>"
                >
                <?php if (isset($fieldErrors['name'])): ?>
                    <small class="field-error"><?= e($fieldErrors['name']) ?></small>
                <?php endif; ?>
            </div>

            <div class="two-col">
                <div class="form-group">
                    <label for="category_id">
                        Kategori <span class="required-star">*</span>
                    </label>
                    <select
                        class="select <?= isset($fieldErrors['category_id']) ? 'is-invalid' : '' ?>"
                        id="category_id"
                        name="category_id"
                        required
                        aria-invalid="<?= isset($fieldErrors['category_id']) ? 'true' : 'false' ?>"
                    >
                        <option value="">Pilih kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (string) $form['category_id'] === (string) $category['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($fieldErrors['category_id'])): ?>
                        <small class="field-error"><?= e($fieldErrors['category_id']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="institution">
                        Instansi <span class="required-star">*</span>
                    </label>
                    <input
                        class="input <?= isset($fieldErrors['institution']) ? 'is-invalid' : '' ?>"
                        type="text"
                        id="institution"
                        name="institution"
                        value="<?= e($form['institution']) ?>"
                        placeholder="Nama instansi"
                        maxlength="255"
                        required
                        aria-invalid="<?= isset($fieldErrors['institution']) ? 'true' : 'false' ?>"
                    >
                    <?php if (isset($fieldErrors['institution'])): ?>
                        <small class="field-error"><?= e($fieldErrors['institution']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="three-col">
                <div class="form-group">
                    <label for="official_fee">
                        Tarif Resmi (Rp) <span class="required-star">*</span>
                    </label>
                    <input
                        class="input <?= isset($fieldErrors['official_fee']) ? 'is-invalid' : '' ?>"
                        type="number"
                        id="official_fee"
                        name="official_fee"
                        min="0"
                        step="1"
                        inputmode="numeric"
                        value="<?= e($form['official_fee']) ?>"
                        placeholder="0"
                        required
                        aria-invalid="<?= isset($fieldErrors['official_fee']) ? 'true' : 'false' ?>"
                    >
                    <small class="field-help">Isi 0 jika gratis.</small>
                    <?php if (isset($fieldErrors['official_fee'])): ?>
                        <small class="field-error"><?= e($fieldErrors['official_fee']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="duration_minutes">
                        Durasi (menit) <span class="required-star">*</span>
                    </label>
                    <input
                        class="input <?= isset($fieldErrors['duration_minutes']) ? 'is-invalid' : '' ?>"
                        type="number"
                        id="duration_minutes"
                        name="duration_minutes"
                        min="1"
                        step="1"
                        value="<?= e($form['duration_minutes']) ?>"
                        placeholder="60"
                        required
                        aria-invalid="<?= isset($fieldErrors['duration_minutes']) ? 'true' : 'false' ?>"
                    >
                    <?php if (isset($fieldErrors['duration_minutes'])): ?>
                        <small class="field-error"><?= e($fieldErrors['duration_minutes']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="status">
                        Status <span class="required-star">*</span>
                    </label>
                    <select
                        class="select <?= isset($fieldErrors['status']) ? 'is-invalid' : '' ?>"
                        id="status"
                        name="status"
                        required
                    >
                        <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>
                            Active
                        </option>
                        <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                    <?php if (isset($fieldErrors['status'])): ?>
                        <small class="field-error"><?= e($fieldErrors['status']) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="regulation">
                    Regulasi <span class="optional-label">Opsional</span>
                </label>
                <input
                    class="input"
                    type="text"
                    id="regulation"
                    name="regulation"
                    value="<?= e($form['regulation']) ?>"
                    placeholder="Dasar hukum atau regulasi"
                >
            </div>

            <div class="form-group">
                <label for="description">
                    Deskripsi <span class="optional-label">Opsional</span>
                </label>
                <textarea
                    class="textarea"
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="Jelaskan layanan publik"
                ><?= e($form['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="requirements">
                    Persyaratan <span class="optional-label">Opsional</span>
                </label>
                <textarea
                    class="textarea"
                    id="requirements"
                    name="requirements"
                    rows="4"
                    placeholder="Persyaratan layanan"
                ><?= e($form['requirements']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="procedure_text">
                    Prosedur <span class="optional-label">Opsional</span>
                </label>
                <textarea
                    class="textarea"
                    id="procedure_text"
                    name="procedure_text"
                    rows="4"
                    placeholder="Langkah-langkah pelayanan"
                ><?= e($form['procedure_text']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-rounded">
                        <?= $isEditing ? 'save' : 'add' ?>
                    </span>
                    <?= $isEditing ? 'Simpan Perubahan' : 'Tambah Layanan' ?>
                </button>

                <?php if ($isEditing): ?>
                    <a href="/admin/services.php" class="btn btn-light">
                        Batal
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card service-list-card">
        <div class="service-section-header">
            <div class="service-section-icon">
                <span class="material-symbols-rounded">list_alt</span>
            </div>
            <div>
                <span class="eyebrow">SERVICE DATABASE</span>
                <h2>Daftar Layanan</h2>
                <p class="muted">
                    <?= number_format($totalServices, 0, ',', '.') ?>
                    layanan terdaftar.
                </p>
            </div>
        </div>

        <form class="service-search-form" method="get" action="/admin/services.php">
            <div class="service-search-input">
                <span class="material-symbols-rounded">search</span>
                <input
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Cari nama layanan atau instansi..."
                    aria-label="Cari layanan"
                >
            </div>

            <div class="service-filter-row">
                <select name="category" class="select" aria-label="Filter kategori">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= $filterCategory === (string) $category['id'] ? 'selected' : '' ?>
                        >
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="select" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>

                <button class="btn btn-primary service-filter-button" type="submit">
                    Filter
                </button>
            </div>

            <?php if ($search !== '' || $filterCategory !== '' || $filterStatus !== ''): ?>
                <a class="service-reset-link" href="/admin/services.php">
                    Reset pencarian dan filter
                </a>
            <?php endif; ?>
        </form>

        <?php if (!$services): ?>
            <div class="empty-state">
                <span class="material-symbols-rounded">inventory_2</span>
                <strong>Tidak ada layanan ditemukan</strong>
                <p>Coba ubah kata kunci atau filter yang digunakan.</p>
            </div>
        <?php else: ?>
            <div class="service-list">
                <?php foreach ($services as $service): ?>
                    <article class="service-item">
                        <div class="service-item-icon">
                            <span class="material-symbols-rounded">apartment</span>
                        </div>

                        <div class="service-item-content">
                            <h3><?= e($service['name']) ?></h3>
                            <strong>
                                <?= e($service['category_name'] ?? 'Tanpa kategori') ?>
                            </strong>
                            <p><?= e($service['institution']) ?></p>
                            <p>
                                Durasi:
                                <?= (int) $service['duration_minutes'] ?> menit
                            </p>
                        </div>

                        <div class="service-item-meta">
                            <span class="status-badge <?= $service['status'] === 'active'
                                ? 'status-active'
                                : 'status-inactive' ?>">
                                <span class="material-symbols-rounded">
                                    <?= $service['status'] === 'active'
                                        ? 'check_circle'
                                        : 'pause_circle' ?>
                                </span>
                                <?= $service['status'] === 'active' ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>

                        <div class="service-item-actions">
                            <a
                                href="/admin/services.php?edit=<?= (int) $service['id'] ?>"
                                class="btn btn-light"
                            >
                                <span class="material-symbols-rounded">edit</span>
                                Edit
                            </a>

                            <form
                                method="post"
                                action="/admin/services.php"
                                onsubmit="return confirm('Yakin ingin menghapus layanan ini? Tindakan ini tidak dapat dibatalkan.');"
                            >
                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrf_token()) ?>"
                                >
                                <input type="hidden" name="action" value="delete">
                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $service['id'] ?>"
                                >
                                <button type="submit" class="btn btn-danger">
                                    <span class="material-symbols-rounded">delete</span>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="service-pagination" aria-label="Navigasi halaman layanan">
                    <span class="service-pagination-info">
                        Halaman <?= $page ?> dari <?= $totalPages ?>
                    </span>

                    <div class="service-pagination-buttons">
                        <?php
                        $queryBase = [
                            'q' => $search,
                            'category' => $filterCategory,
                            'status' => $filterStatus,
                        ];
                        ?>

                        <?php if ($page > 1): ?>
                            <a
                                class="service-page-link"
                                href="?<?= e(http_build_query($queryBase + ['page' => $page - 1])) ?>"
                            >
                                <span class="material-symbols-rounded">chevron_left</span>
                                Sebelumnya
                            </a>
                        <?php else: ?>
                            <span class="service-page-link is-disabled">
                                <span class="material-symbols-rounded">chevron_left</span>
                                Sebelumnya
                            </span>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a
                                class="service-page-link"
                                href="?<?= e(http_build_query($queryBase + ['page' => $page + 1])) ?>"
                            >
                                Berikutnya
                                <span class="material-symbols-rounded">chevron_right</span>
                            </a>
                        <?php else: ?>
                            <span class="service-page-link is-disabled">
                                Berikutnya
                                <span class="material-symbols-rounded">chevron_right</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php admin_footer(); ?>
