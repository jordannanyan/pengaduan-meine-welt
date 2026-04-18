<?php
$page_title = 'Buat Pengaduan';
require_once __DIR__ . '/../includes/header_public.php';
?>

<div class="hero text-center">
    <h1 class="mb-3"><i class="bi bi-cup-hot-fill"></i> Suara Anda, Kualitas Kami</h1>
    <p class="lead mb-0">Sampaikan pengaduan Anda untuk membantu Meine Welt Kafe melayani lebih baik.</p>
</div>

<?= flash_render('submit') ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card card-form">
            <div class="card-body p-4">
                <h4 class="mb-3"><i class="bi bi-pencil-square text-primary"></i> Formulir Pengaduan</h4>
                <p class="text-muted">Silakan isi deskripsi pengaduan Anda. Sistem akan otomatis mengelompokkan kategori pengaduan (pelayanan, produk, harga, atau suasana).</p>

                <form action="submit.php" method="POST" enctype="multipart/form-data" id="formPengaduan">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi Pengaduan <span class="text-danger">*</span></label>
                        <textarea name="complaint_text" rows="6" class="form-control" required
                                  placeholder="Jelaskan pengaduan Anda secara rinci..."
                                  minlength="10" maxlength="2000"></textarea>
                        <div class="form-text">Minimal 10 karakter. Semakin rinci, semakin baik.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lampiran (Opsional)</label>
                        <input type="file" name="attachment" class="form-control"
                               accept="image/jpeg,image/png,image/gif,application/pdf">
                        <div class="form-text">Format: JPG, PNG, GIF, PDF. Maksimal 5 MB.</div>
                    </div>

                    <hr>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="anonimToggle" name="is_anonymous" value="1" checked>
                        <label class="form-check-label fw-semibold" for="anonimToggle">
                            Laporkan secara anonim
                        </label>
                        <div class="form-text">Aktifkan untuk tidak menyertakan identitas.</div>
                    </div>

                    <div id="identitasBox" style="display:none">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" name="reporter_name" class="form-control" maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kontak (Email/No. HP)</label>
                                <input type="text" name="reporter_contact" class="form-control" maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary-brand btn-lg">
                            <i class="bi bi-send-fill"></i> Kirim Pengaduan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-info-circle-fill text-primary"></i> Kategori Pengaduan</h6>
                <p class="small text-muted">Sistem akan mengklasifikasikan pengaduan Anda ke dalam salah satu atau beberapa aspek berikut:</p>
                <ul class="list-unstyled small">
                    <li><span class="badge bg-info me-1">Pelayanan</span> Staff, pelayan, kasir</li>
                    <li class="mt-2"><span class="badge bg-success me-1">Produk</span> Makanan, minuman, rasa</li>
                    <li class="mt-2"><span class="badge bg-warning text-dark me-1">Harga</span> Biaya, promo, diskon</li>
                    <li class="mt-2"><span class="badge bg-secondary me-1">Suasana</span> Tempat, fasilitas, kebersihan</li>
                </ul>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-search text-success"></i> Sudah Pernah Lapor?</h6>
                <p class="small text-muted">Pantau perkembangan pengaduan Anda dengan kode laporan.</p>
                <a href="<?= BASE_URL ?>/public/track.php" class="btn btn-outline-success btn-sm w-100">
                    <i class="bi bi-arrow-right"></i> Lacak Laporan
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('anonimToggle').addEventListener('change', function() {
    document.getElementById('identitasBox').style.display = this.checked ? 'none' : 'block';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
