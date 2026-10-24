<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$services = $pdo
    ->query("
        SELECT id, name, official_fee, institution, regulation
        FROM public_services
        WHERE status = 'active'
        ORDER BY name
    ")
    ->fetchAll();

$result = null;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $askedRaw = trim((string) ($_POST['asked'] ?? ''));

    if ($serviceId < 1 || $askedRaw === '' || !is_numeric($askedRaw) || (float) $askedRaw < 0) {
        $errorMessage = 'Pilih layanan dan masukkan nominal pembayaran yang valid.';
    } else {
        $askedAmount = (float) $askedRaw;

        $statement = $pdo->prepare("
            SELECT id, name, official_fee, institution, regulation
            FROM public_services
            WHERE id = ? AND status = 'active'
            LIMIT 1
        ");
        $statement->execute([$serviceId]);
        $service = $statement->fetch();

        if (!$service) {
            $errorMessage = 'Layanan tidak ditemukan atau sudah tidak aktif.';
        } else {
            $officialAmount = (float) $service['official_fee'];
            $difference = $askedAmount - $officialAmount;

            if ($difference > 0.005) {
                $status = 'overcharge';
            } elseif ($difference < -0.005) {
                $status = 'below';
            } else {
                $status = 'match';
            }

            $result = [
                'service' => $service,
                'asked' => $askedAmount,
                'official' => $officialAmount,
                'difference' => $difference,
                'status' => $status,
            ];
        }
    }
}

function sigapRupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cek Biaya Resmi — SIGAP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/png" href="/assets/images/logo tab.png">
    <link rel="apple-touch-icon" href="/assets/images/logo tab.png">
    <style>
        .payment-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(0, .88fr);
            gap: 26px;
            align-items: start;
        }
        .payment-form-card, .payment-result-card {
            min-width: 0;
            padding: 26px;
            border-radius: 22px;
        }
        .payment-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 148px;
            margin: 0 0 26px;
            padding: 24px 20px;
            border: 1px solid #c6eddd;
            border-radius: 18px;
            background: #d9f5e9;
            text-align: center;
        }
        .payment-preview small {
            display: block;
            color: #6d877d;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.5;
        }
        .payment-preview .amount-display {
            margin: 0;
            color: #14835f;
            font-size: clamp(27px, 2.3vw, 36px);
            font-weight: 850;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .payment-form-card .form-group { margin-bottom: 21px; }
        .payment-form-card label {
            display: block;
            margin-bottom: 9px;
            line-height: 1.45;
        }
        .payment-form-card select, .payment-form-card input {
            width: 100%;
            min-height: 52px;
            box-sizing: border-box;
        }
        .payment-form-card button[type="submit"] {
            min-height: 50px;
            max-width: 100%;
            white-space: normal;
        }
        .payment-result-card > .eyebrow {
            display: inline-flex;
            margin-bottom: 10px;
        }
        .payment-result-card h2 {
            margin: 0 0 22px;
            font-size: clamp(23px, 2vw, 30px);
            line-height: 1.22;
            letter-spacing: -.025em;
        }
        .payment-result-stack {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 13px;
        }
        .payment-result-stack .notice {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 7px;
            min-width: 0;
            min-height: 94px;
            box-sizing: border-box;
            padding: 17px 19px;
            border: 1px solid #c6eddd;
            border-radius: 16px;
            background: #d9f5e9;
            overflow-wrap: anywhere;
        }
        .payment-result-stack .notice small {
            display: block;
            color: #55776a;
            font-size: 11px;
            font-weight: 800;
            line-height: 1.45;
        }
        .payment-result-stack .amount-display {
            margin: 0;
            color: #12543e;
            font-size: clamp(22px, 1.8vw, 28px);
            font-weight: 850;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .payment-result-stack .notice.is-overcharge {
            background: #fff0f0;
            border-color: #f2aaaa;
        }
        .payment-result-stack .notice.is-overcharge small,
        .payment-result-stack .notice.is-overcharge .amount-display {
            color: #b42318;
        }
        .payment-result-stack .notice.is-match {
            background: #e1f7ec;
            border-color: #b8e8d0;
        }
        .payment-result-stack .notice.is-match small,
        .payment-result-stack .notice.is-match .amount-display {
            color: #087443;
        }
        .payment-result-stack .notice.is-below {
            background: #fff7e6;
            border-color: #f3d18a;
        }
        .payment-result-stack .notice.is-below small,
        .payment-result-stack .notice.is-below .amount-display {
            color: #925f08;
        }
        .payment-result-note {
            margin: 18px 0 0;
            padding: 14px 15px;
            border: 1px solid #dbece3;
            border-radius: 12px;
            background: #f5fbf7;
            color: #607b70;
            font-size: 14px;
            line-height: 1.65;
        }
        .payment-result-note.is-overcharge {
            border-color: #f2aaaa;
            background: #fff5f5;
            color: #a61b1b;
        }
        .payment-result-note.is-match {
            border-color: #b8e8d0;
            background: #f0fbf5;
            color: #176b46;
        }
        .payment-result-note.is-below {
            border-color: #f3d18a;
            background: #fff9ed;
            color: #80550b;
        }
        .payment-error {
            margin-bottom: 18px;
            padding: 13px 16px;
            border: 1px solid #f2aaaa;
            border-radius: 12px;
            background: #fff5f5;
            color: #a61b1b;
        }
        @media (max-width: 1100px) {
            .payment-layout { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 600px) {
            .payment-form-card, .payment-result-card {
                padding: 19px;
                border-radius: 18px;
            }
            .payment-preview {
                min-height: 130px;
                margin-bottom: 22px;
                padding: 21px 16px;
            }
            .payment-result-card h2 { margin-bottom: 18px; }
            .payment-result-stack .notice {
                min-height: 88px;
                padding: 15px 16px;
            }
            .payment-form-card button[type="submit"] { width: 100%; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../config/navbar.php'; ?>

    <main>
        <section class="section">
            <div class="container">
                <header class="payment-page-header">
                    <span class="eyebrow">
                        <span class="material-symbols-rounded" aria-hidden="true">calculate</span>
                        Kalkulator Transparansi Biaya
                    </span>
                    <h1 class="page-title">Cek Biaya Resmi</h1>
                    <p class="muted">
                        Pastikan biaya yang kamu bayarkan sesuai dengan tarif resmi yang tercatat.
                    </p>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <div class="payment-error" role="alert">
                        <?= e($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <div class="detail-grid payment-layout">
                    <section class="card payment-form-card">
                        <div class="notice payment-preview">
                            <small>BIAYA RESMI PEMERINTAH</small>
                            <div id="official-preview" class="amount-display">Pilih layanan</div>
                            <small>Dasar tarif terdaftar pada SIGAP</small>
                        </div>

                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                            <div class="form-group">
                                <label for="service_id">Pilih Jenis Layanan Publik</label>
                                <select class="select" id="service_id" name="service_id" required>
                                    <option value="">Pilih layanan</option>
                                    <?php foreach ($services as $item): ?>
                                        <option
                                            value="<?= (int) $item['id'] ?>"
                                            data-fee="<?= e((string) $item['official_fee']) ?>"
                                            <?= isset($_POST['service_id']) && (int) $_POST['service_id'] === (int) $item['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($item['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="asked">Berapa biaya yang diminta petugas / loket?</label>
                                <input
                                    class="input"
                                    id="asked"
                                    name="asked"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="Contoh: 170000"
                                    value="<?= e((string) ($_POST['asked'] ?? '')) ?>"
                                    required
                                >
                            </div>

                            <button class="btn btn-primary" type="submit">
                                Perbarui Perhitungan Komparasi
                            </button>
                        </form>
                    </section>

                    <section class="card payment-result-card" aria-live="polite">
                        <?php if ($result): ?>
                            <?php
                                $service = $result['service'];
                                $askedAmount = $result['asked'];
                                $difference = $result['difference'];
                                $status = $result['status'];

                                $statusClass = $status === 'overcharge'
                                    ? 'is-overcharge'
                                    : ($status === 'below' ? 'is-below' : 'is-match');
                            ?>

                            <span class="eyebrow">Evaluasi Real-Time</span>
                            <h2>Hasil Komparasi Transparansi</h2>

                            <div class="payment-result-stack">
                                <div class="notice <?= $statusClass ?>">
                                    <small>BIAYA DIMINTA</small>
                                    <div class="amount-display"><?= e(sigapRupiah($askedAmount)) ?></div>
                                </div>

                                <div class="notice <?= $statusClass ?>">
                                    <small>
                                        <?= $status === 'overcharge'
                                            ? 'POTENSI KELEBIHAN BIAYA'
                                            : ($status === 'below' ? 'SELISIH DI BAWAH TARIF RESMI' : 'POTENSI SELISIH')
                                        ?>
                                    </small>
                                    <div class="amount-display">
                                        <?= e(sigapRupiah(abs($difference))) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($status === 'overcharge'): ?>
                                <p class="payment-result-note is-overcharge">
                                    <strong>Biaya melebihi tarif resmi.</strong>
                                    Nominal yang diminta lebih tinggi sebesar <?= e(sigapRupiah($difference)) ?>.
                                    Simpan bukti pembayaran dan rincian biaya, lalu konfirmasi tarif kepada instansi terkait.
                                    Jika tidak ada dasar biaya resmi untuk selisih tersebut, kamu dapat melaporkannya melalui kanal pengaduan resmi.
                                    Perbedaan nominal saja belum cukup untuk memastikan terjadinya pungutan liar.
                                </p>
                            <?php elseif ($status === 'below'): ?>
                                <p class="payment-result-note is-below">
                                    <strong>Waspada: nominal jauh lebih rendah dari tarif resmi.</strong>
                                    Nominal yang dimasukkan lebih rendah sebesar <?= e(sigapRupiah(abs($difference))) ?>.
                                    Jangan langsung menyimpulkan penipuan, tetapi verifikasi langsung kepada instansi resmi sebelum membayar atau menyerahkan dokumen.
                                    Pastikan layanan, jenis dokumen, biaya tambahan, dan bukti pembayaran benar. Berhati-hatilah terhadap tawaran dokumen palsu atau jalur tidak resmi.
                                </p>
                            <?php else: ?>
                                <p class="payment-result-note is-match">
                                    <strong>Nominal sesuai dengan tarif resmi yang tercatat.</strong>
                                    Tetap simpan bukti pembayaran dan pastikan pembayaran dilakukan melalui kanal resmi instansi.
                                </p>
                            <?php endif; ?>

                            <p class="muted" style="margin-top:14px; font-size:12px; line-height:1.6;">
                                Layanan: <?= e($service['name']) ?>.
                                Hasil ini merupakan indikasi awal berdasarkan nominal yang dimasukkan, bukan penetapan hukum.
                            </p>
                        <?php else: ?>
                            <span class="eyebrow">Evaluasi Real-Time</span>
                            <h2>Hasil Komparasi Transparansi</h2>
                            <p class="muted">
                                Pilih layanan dan masukkan nominal biaya untuk membandingkannya dengan tarif resmi.
                            </p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/../../config/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const serviceSelect = document.getElementById('service_id');
        const officialPreview = document.getElementById('official-preview');

        function updateOfficialFee() {
            const option = serviceSelect.options[serviceSelect.selectedIndex];
            const fee = option ? option.dataset.fee : '';

            if (fee === '' || fee === undefined) {
                officialPreview.textContent = 'Pilih layanan';
                return;
            }

            officialPreview.textContent = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(Number(fee));
        }

        serviceSelect.addEventListener('change', updateOfficialFee);
        updateOfficialFee();
    });
    </script>
</body>
</html>
