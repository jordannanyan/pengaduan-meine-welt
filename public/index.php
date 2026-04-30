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
                        <label class="form-label fw-semibold">Tipe Pesanan <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="radio" class="btn-check" name="order_type" id="ot_dine_in" value="dine_in" checked required>
                            <label class="btn btn-outline-primary" for="ot_dine_in">
                                <i class="bi bi-shop"></i> Dine In
                            </label>

                            <input type="radio" class="btn-check" name="order_type" id="ot_take_away" value="take_away" required>
                            <label class="btn btn-outline-success" for="ot_take_away">
                                <i class="bi bi-bag-fill"></i> Take Away
                            </label>

                            <input type="radio" class="btn-check" name="order_type" id="ot_online" value="online" required>
                            <label class="btn btn-outline-info" for="ot_online">
                                <i class="bi bi-laptop"></i> Online
                            </label>
                        </div>
                        <div class="form-text">Pilih cara Anda memesan saat mengalami kendala.</div>
                    </div>

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

                    <div id="identitasBox">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Nama <span class="text-danger" id="namaRequiredMark">*</span></label>
                                <input type="text" name="reporter_name" id="reporterName" class="form-control" maxlength="100" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Kontak (Email/No. HP)</label>
                                <input type="text" name="reporter_contact" id="reporterContact" class="form-control" maxlength="100"
                                       placeholder="Opsional, supaya petugas bisa menghubungi">
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="anonimToggle" name="is_anonymous" value="1">
                        <label class="form-check-label fw-semibold" for="anonimToggle">
                            Laporkan secara anonim
                        </label>
                        <div class="form-text">Aktifkan jika tidak ingin mencantumkan nama dan kontak.</div>
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
(function() {
    const toggle  = document.getElementById('anonimToggle');
    const box     = document.getElementById('identitasBox');
    const nama    = document.getElementById('reporterName');
    const kontak  = document.getElementById('reporterContact');
    const mark    = document.getElementById('namaRequiredMark');

    function sync() {
        const anon = toggle.checked;
        box.style.display = anon ? 'none' : 'block';
        if (anon) {
            nama.required = false;
            nama.value = '';
            kontak.value = '';
            if (mark) mark.style.display = 'none';
        } else {
            nama.required = true;
            if (mark) mark.style.display = '';
        }
    }
    toggle.addEventListener('change', sync);
    sync();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
