<?php
$page_title = 'Pengaduan Terkirim';
require_once __DIR__ . '/../includes/header_public.php';

if (empty($_SESSION['last_submission'])) {
    redirect(BASE_URL . '/public/index.php');
}

$data = $_SESSION['last_submission'];
unset($_SESSION['last_submission']); // konsumsi sekali pakai

$ticket = $data['ticket_code'];
$classification = $data['classification'] ?? [];
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-form">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
                </div>
                <h2 class="mb-3">Pengaduan Berhasil Dikirim!</h2>
                <p class="text-muted mb-4">
                    Terima kasih atas laporan Anda. Petugas akan segera menindaklanjuti pengaduan Anda.
                </p>

                <div class="mb-4">
                    <div class="text-muted small mb-2">KODE LAPORAN ANDA:</div>
                    <div class="ticket-code"><?= h($ticket) ?></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                        Simpan kode ini untuk melacak status pengaduan.
                    </div>
                </div>

                <?php if (!empty($classification['success'])): ?>
                    <div class="kategori-preview text-start mb-4">
                        <h6 class="fw-bold mb-2">
                            <i class="bi bi-cpu-fill"></i> Hasil Klasifikasi Otomatis
                        </h6>
                        <p class="small text-muted mb-2">
                            Sistem AI (Naive Bayes) telah mengelompokkan pengaduan Anda ke aspek:
                        </p>
                        <div class="mb-2">
                            <?php foreach ($classification['kode'] as $code): ?>
                                <?php
                                $label = kategori_label($code);
                                $cls_map = ['PLY'=>'bg-info','PRD'=>'bg-success','HRG'=>'bg-warning text-dark','SUI'=>'bg-secondary'];
                                $cls = $cls_map[$code] ?? 'bg-light text-dark';
                                ?>
                                <span class="badge <?= $cls ?> badge-category me-1">
                                    <?= h($label) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted">
                            Tingkat kepercayaan utama:
                            <b><?= number_format(($classification['primary_confidence'] ?? 0) * 100, 1) ?>%</b>
                            (kategori <?= h(kategori_label($classification['primary_code'] ?? '')) ?>)
                        </div>

                        <?php if (!empty($classification['aspek_sentimen'])): ?>
                            <hr class="my-3">
                            <div class="small text-muted mb-2">Sentimen per aspek:</div>
                            <?= aspek_sentimen_render($classification['aspek_sentimen']) ?>
                        <?php elseif (!empty($classification['sentimen'])): ?>
                            <hr class="my-3">
                            <div class="small text-muted mb-1">Sentimen terdeteksi:</div>
                            <?= sentimen_badge($classification['sentimen'], $classification['sentimen_confidence'] ?? null) ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-start small">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Klasifikasi otomatis belum tersedia. Petugas akan mengelompokkan pengaduan Anda secara manual.
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= BASE_URL ?>/public/track.php?code=<?= urlencode($ticket) ?>" class="btn btn-primary-brand">
                        <i class="bi bi-search"></i> Lacak Status
                    </a>
                    <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
