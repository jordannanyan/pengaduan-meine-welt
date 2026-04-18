<?php
// ================================================================
// PROSES SUBMIT PENGADUAN
// 1. Validasi input
// 2. Simpan ke database
// 3. Upload lampiran (jika ada)
// 4. Panggil predict.py untuk klasifikasi
// 5. Update hasil klasifikasi ke DB
// 6. Redirect ke halaman sukses
// ================================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/public/index.php');
}

// ----------------------------------------------------------------
// 1. Validasi input
// ----------------------------------------------------------------
$complaint_text = trim($_POST['complaint_text'] ?? '');
$is_anonymous   = isset($_POST['is_anonymous']) ? 1 : 0;
$reporter_name  = $is_anonymous ? null : trim($_POST['reporter_name'] ?? '');
$reporter_contact = $is_anonymous ? null : trim($_POST['reporter_contact'] ?? '');

if (strlen($complaint_text) < 10) {
    flash_set('submit', 'Deskripsi pengaduan terlalu pendek (minimal 10 karakter).', 'danger');
    redirect(BASE_URL . '/public/index.php');
}
if (strlen($complaint_text) > 2000) {
    flash_set('submit', 'Deskripsi pengaduan terlalu panjang (maksimal 2000 karakter).', 'danger');
    redirect(BASE_URL . '/public/index.php');
}

// ----------------------------------------------------------------
// 2. Simpan pengaduan awal
// ----------------------------------------------------------------
try {
    $pdo->beginTransaction();

    $ticket_code = generate_ticket_code();

    // Pastikan ticket code unik
    $check = $pdo->prepare('SELECT id FROM complaints WHERE ticket_code = ?');
    $check->execute([$ticket_code]);
    $attempts = 0;
    while ($check->fetch() && $attempts < 5) {
        $ticket_code = generate_ticket_code();
        $check->execute([$ticket_code]);
        $attempts++;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO complaints
            (ticket_code, reporter_name, reporter_contact, is_anonymous, complaint_text, status)
         VALUES (?, ?, ?, ?, ?, "new")'
    );
    $stmt->execute([
        $ticket_code,
        $reporter_name,
        $reporter_contact,
        $is_anonymous,
        $complaint_text,
    ]);
    $complaint_id = (int)$pdo->lastInsertId();

    // Catat status awal
    $stmt = $pdo->prepare(
        'INSERT INTO status_history (complaint_id, from_status, to_status, reason)
         VALUES (?, NULL, "new", "Pengaduan baru diterima")'
    );
    $stmt->execute([$complaint_id]);

    // ----------------------------------------------------------------
    // 3. Upload lampiran (jika ada)
    // ----------------------------------------------------------------
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];

        // Validasi
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception('Ukuran file melebihi batas 5 MB.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ALLOWED_MIME)) {
            throw new Exception('Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, PDF.');
        }

        // Simpan file
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_name = $ticket_code . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safe_name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Gagal menyimpan lampiran.');
        }

        // Catat lampiran di DB
        $stmt = $pdo->prepare(
            'INSERT INTO attachments (complaint_id, file_name, file_path, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $complaint_id,
            $file['name'],
            $safe_name,
            $mime,
            $file['size']
        ]);
    }

    // ----------------------------------------------------------------
    // 4. Klasifikasi dengan model Naive Bayes
    // ----------------------------------------------------------------
    $classification = classify_complaint($complaint_text);

    if (!empty($classification['success'])) {
        $predicted_codes      = $classification['kode_string'] ?? null;
        $predicted_confidence = $classification['primary_confidence'] ?? null;

        $stmt = $pdo->prepare(
            'UPDATE complaints
             SET predicted_category_code = ?,
                 predicted_confidence    = ?,
                 final_category_code     = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $predicted_codes,
            $predicted_confidence,
            $predicted_codes,  // default: final = predicted (bisa di-override petugas/admin)
            $complaint_id
        ]);
    } else {
        // Tetap simpan pengaduan walau klasifikasi gagal
        // Petugas bisa set kategori manual nanti
        error_log('Klasifikasi gagal untuk pengaduan ' . $ticket_code . ': ' . json_encode($classification));
    }

    // Log aktivitas
    log_activity($pdo, null, 'submit_complaint', 'complaint', (string)$complaint_id,
        'Pengaduan baru dengan kode ' . $ticket_code);

    $pdo->commit();

    // ----------------------------------------------------------------
    // 5. Simpan hasil untuk ditampilkan di halaman sukses
    // ----------------------------------------------------------------
    $_SESSION['last_submission'] = [
        'ticket_code'     => $ticket_code,
        'classification'  => $classification,
    ];

    redirect(BASE_URL . '/public/success.php');

} catch (Exception $e) {
    $pdo->rollBack();
    flash_set('submit', 'Gagal mengirim pengaduan: ' . $e->getMessage(), 'danger');
    redirect(BASE_URL . '/public/index.php');
}
