<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$query = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['cat'] ?? 0);

$sql = "
    SELECT
        ps.*,
        sc.name AS category
    FROM public_services ps
    JOIN service_categories sc
        ON sc.id = ps.category_id
    WHERE ps.status = 'active'
";

$params = [];

if ($query !== '') {

    $sql .= "
        AND (
            ps.name LIKE ?
            OR ps.institution LIKE ?
        )
    ";

    $searchTerm = "%{$query}%";

    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryId > 0) {

    $sql .= "
        AND ps.category_id = ?
    ";

    $params[] = $categoryId;
}


$sql .= "
    ORDER BY ps.name
";

$statement = $pdo->prepare($sql);
$statement->execute($params);

$services = $statement->fetchAll();

$categories = $pdo
    ->query("
        SELECT *
        FROM service_categories
        ORDER BY name
    ")
    ->fetchAll();

?>

<!doctype html>
<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Cari Layanan Publik — SIGAP</title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >
    <link rel="stylesheet" href="/assets/css/services.css">

    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
</head>

<body>

    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>

    <main>

        <section class="section">

            <div class="container">

                <div class="card service-search-card">

                    <span class="eyebrow">
                        <span class="material-symbols-rounded" aria-hidden="true">pageview</span>
                        Katalog Transparansi Layanan
                    </span>

                    <h1 class="page-title">
                        Cari Layanan Publik
                    </h1>

                    <p class="muted">
                        Temukan informasi resmi, regulasi, dan rincian
                        biaya sebelum kamu datang ke loket.
                    </p>

                    <form
                        class="service-search-form"
                        method="get"
                    >

                        <input
                            class="input"
                            type="search"
                            name="q"
                            value="<?= e($query) ?>"
                            placeholder="Cari layanan, misalnya KTP, SIM, paspor..."
                        >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Cari
                        </button>

                    </form>

                    <div class="service-categories">

                        <?php foreach ($categories as $category): ?>

                            <a
                                class="service-category <?= $categoryId === (int) $category['id'] ? 'active' : '' ?>"
                                href="?cat=<?= (int) $category['id'] ?>"
                            >
                                <?= e($category['name']) ?>
                            </a>

                        <?php endforeach; ?>

                    </div>

                </div>

                <section class="service-list-section">

                    <div class="section-head section-head-left">

                        <h2 class="section-title">
                            Daftar Layanan Publik
                        </h2>

                    </div>


                    <div class="grid-3">

                        <?php if ($services): ?>

                            <?php foreach ($services as $service): ?>

                                <article class="card service-card">

                                    <span class="eyebrow">
                                        <?= e($service['category']) ?>
                                    </span>

                                    <h2>
                                        <?= e($service['name']) ?>
                                    </h2>

                                    <p class="muted">
                                        <?= e($service['institution']) ?>
                                    </p>

                                    <div class="service-fee">
                                        <?= rupiah($service['official_fee']) ?>
                                    </div>

                                    <p class="muted">
                                        <?= e($service['description']) ?>
                                    </p>

                                    <p class="service-duration">

                                        <strong>
                                            Durasi:
                                        </strong>

                                        <?= e(
                                            (string) $service['duration_minutes']
                                        ) ?>

                                        menit

                                    </p>

                                    <a
                                        class="btn btn-primary btn-sm"
                                        href="/user/service_detail.php?id=<?= (int) $service['id'] ?>"
                                    >
                                        Lihat Detail →
                                    </a>

                                </article>

                            <?php endforeach; ?>


                        <?php else: ?>

                            <div class="card service-empty">

                                <h3>
                                    Layanan tidak ditemukan
                                </h3>

                                <p class="muted">
                                    Coba gunakan kata kunci atau kategori
                                    yang berbeda.
                                </p>

                                <a
                                    class="btn btn-light btn-sm"
                                    href="/user/services.php"
                                >
                                    Reset Pencarian
                                </a>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>

            </div>

        </section>

    </main>

    <?php require_once __DIR__ . '/../../config/footer.php'; ?>

</body>

</html>