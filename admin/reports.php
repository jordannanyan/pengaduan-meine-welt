<?php
$page_title = 'Laporan & Statistik';
require_once __DIR__ . '/../includes/header_admin.php';

// ---------- Filter periode ----------
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$params = [$from, $to . ' 23:59:59'];

// ---------- Ringkasan ----------
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status='new') AS new_cnt,
        SUM(status='in_progress') AS prog_cnt,
        SUM(status='resolved') AS done_cnt,
        SUM(status='cancelled') AS cancel_cnt,
        SUM(is_anonymous=1) AS anon_cnt
     FROM complaints
     WHERE submitted_at BETWEEN ? AND ?"
);
$stmt->execute($params);
$sum = $stmt->fetch();

// ---------- Per kategori ----------
$stmt = $pdo->prepare(
    "SELECT final_category_code FROM complaints
     WHERE submitted_at BETWEEN ? AND ?
       AND final_category_code IS NOT NULL AND final_category_code <> ''"
);
$stmt->execute($params);
$cat_count = ['PLY'=>0,'PRD'=>0,'HRG'=>0,'SUI'=>0];
foreach ($stmt->fetchAll() as $r) {
    foreach (explode(',', $r['final_category_code']) as $c) {
        $c = trim($c);
        if (isset($cat_count[$c])) $cat_count[$c]++;
    }
}

// ---------- Per hari ----------
$stmt = $pdo->prepare(
    "SELECT DATE(submitted_at) AS tgl, COUNT(*) AS cnt
     FROM complaints
     WHERE submitted_at BETWEEN ? AND ?
     GROUP BY DATE(submitted_at)
     ORDER BY tgl ASC"
);
$stmt->execute($params);
$harian = $stmt->fetchAll();

// ---------- Akurasi override ----------
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(predicted_category_code = final_category_code) AS match_cnt
     FROM complaints
     WHERE submitted_at BETWEEN ? AND ?
       AND predicted_category_code IS NOT NULL
       AND final_category_code IS NOT NULL"
);
$stmt->execute($params);
$akurasi = $stmt->fetch();
$match_pct = $akurasi['total'] > 0
    ? round($akurasi['match_cnt'] / $akurasi['total'] * 100, 1)
    : 0;
?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Dari Tanggal</label>
                <input type="date" name="from" class="form-control" value="<?= h($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Sampai Tanggal</label>
                <input type="date" name="to" class="form-control" value="<?= h($to) ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary-brand"><i class="bi bi-funnel"></i> Tampilkan</button>
                <a href="?" class="btn btn-outline-secondary">Reset</a>
            </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan -->
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Pengaduan</div>
                <h3 class="mb-0"><?= (int)$sum['total'] ?></h3>
                <small class="text-muted">Anonim: <b><?= (int)$sum['anon_cnt'] ?></b></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Selesai</div>
                <h3 class="mb-0 text-success"><?= (int)$sum['done_cnt'] ?></h3>
                <small class="text-muted">
                    <?= $sum['total'] > 0 ? round($sum['done_cnt']/$sum['total']*100, 1) : 0 ?>% dari total
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Diproses / Baru</div>
                <h3 class="mb-0 text-warning"><?= (int)($sum['prog_cnt'] + $sum['new_cnt']) ?></h3>
                <small class="text-muted">
                    Baru: <b><?= (int)$sum['new_cnt'] ?></b> &middot;
                    Proses: <b><?= (int)$sum['prog_cnt'] ?></b>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Akurasi Prediksi</div>
                <h3 class="mb-0 text-primary"><?= $match_pct ?>%</h3>
                <small class="text-muted">
                    Prediksi = Final: <b><?= (int)$akurasi['match_cnt'] ?></b>/<?= (int)$akurasi['total'] ?>
                </small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Per kategori -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Distribusi per Kategori (Multi-label)</h6>
                <?php
                $total_cat = array_sum($cat_count);
                $colors = ['PLY'=>'info','PRD'=>'success','HRG'=>'warning','SUI'=>'secondary'];
                foreach ($cat_count as $code => $cnt):
                    $pct = $total_cat > 0 ? round($cnt/$total_cat*100, 1) : 0;
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span><?= kategori_badge($code) ?></span>
                            <span><b><?= $cnt ?></b> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress mt-1" style="height:10px">
                            <div class="progress-bar bg-<?= $colors[$code] ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($total_cat === 0): ?>
                    <p class="text-muted small">Tidak ada data pada rentang ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Per status -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Distribusi per Status</h6>
                <?php
                $stat_data = [
                    'new'         => ['Baru',       $sum['new_cnt'],    'primary'],
                    'in_progress' => ['Diproses',   $sum['prog_cnt'],   'warning'],
                    'resolved'    => ['Selesai',    $sum['done_cnt'],   'success'],
                    'cancelled'   => ['Dibatalkan', $sum['cancel_cnt'], 'secondary'],
                ];
                foreach ($stat_data as $s):
                    $pct = $sum['total'] > 0 ? round($s[1]/$sum['total']*100, 1) : 0;
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span><?= h($s[0]) ?></span>
                            <span><b><?= (int)$s[1] ?></b> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress mt-1" style="height:10px">
                            <div class="progress-bar bg-<?= $s[2] ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Harian -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Volume Harian</h6>
                <?php if (!$harian): ?>
                    <p class="text-muted small mb-0">Tidak ada data.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Tanggal</th><th>Jumlah</th></tr></thead>
                            <tbody>
                                <?php foreach ($harian as $h): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($h['tgl'])) ?></td>
                                        <td><?= (int)$h['cnt'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_panel.php'; ?>
