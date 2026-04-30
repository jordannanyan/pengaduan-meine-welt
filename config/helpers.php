<?php
// ================================================================
// HELPER FUNCTIONS
// ================================================================

require_once __DIR__ . '/config.php';

/**
 * Sanitasi output HTML
 */
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect ke URL
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Flash message untuk satu request berikutnya
 */
function flash_set(string $key, string $message, string $type = 'info'): void
{
    $_SESSION['_flash'][$key] = ['msg' => $message, 'type' => $type];
}

function flash_get(string $key): ?array
{
    if (isset($_SESSION['_flash'][$key])) {
        $data = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $data;
    }
    return null;
}

function flash_render(string $key): string
{
    $flash = flash_get($key);
    if (!$flash) return '';
    $class = 'alert-' . $flash['type'];
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">'
        . h($flash['msg']) .
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

/**
 * Generate ticket code unik untuk pengaduan
 * Format: MWK-YYMMDD-XXXX (4 karakter random)
 */
function generate_ticket_code(): string
{
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return 'MWK-' . date('ymd') . '-' . $random;
}

/**
 * Panggil predict.py via shell_exec untuk klasifikasi pengaduan
 * Return array: ['success'=>bool, 'kategori'=>[], 'primary'=>'', ...]
 */
function classify_complaint(string $text): array
{
    $safe_text = escapeshellarg($text);
    $python_path = escapeshellarg(PYTHON_PATH);
    $script_path = escapeshellarg(PREDICT_SCRIPT);

    $cmd = "$python_path $script_path $safe_text 2>&1";
    $output = shell_exec($cmd);

    if ($output === null || $output === '') {
        return [
            'success' => false,
            'error'   => 'Gagal memanggil Python (output kosong). Cek PYTHON_PATH di config.'
        ];
    }

    // Python bisa print warnings sebelum JSON, ambil baris JSON terakhir
    $lines = array_filter(array_map('trim', explode("\n", $output)));
    $json_line = end($lines);

    $result = json_decode($json_line, true);
    if (!is_array($result)) {
        return [
            'success'   => false,
            'error'     => 'Output Python bukan JSON valid',
            'raw'       => substr($output, 0, 500),
        ];
    }
    return $result;
}

/**
 * Ambil label kategori dari kode (PLY, PRD, HRG, SUI) -> 'pelayanan', dst
 */
function kategori_label(string $code): string
{
    $map = [
        'PLY' => 'Pelayanan',
        'PRD' => 'Produk',
        'HRG' => 'Harga',
        'SUI' => 'Suasana',
    ];
    return $map[$code] ?? $code;
}

/**
 * Ubah kode multi-label "PRD,HRG" jadi "Produk, Harga"
 */
function kategori_label_list(?string $codes): string
{
    if (!$codes) return '-';
    $arr = array_map('trim', explode(',', $codes));
    $labels = array_map('kategori_label', $arr);
    return implode(', ', $labels);
}

/**
 * Badge Bootstrap untuk status pengaduan
 */
function status_badge(string $status): string
{
    $map = [
        'new'         => ['bg-primary', 'Baru'],
        'in_progress' => ['bg-warning text-dark', 'Diproses'],
        'resolved'    => ['bg-success', 'Selesai'],
        'cancelled'   => ['bg-secondary', 'Dibatalkan'],
    ];
    $data = $map[$status] ?? ['bg-light text-dark', $status];
    return '<span class="badge ' . $data[0] . '">' . h($data[1]) . '</span>';
}

/**
 * Label sentimen Indonesia
 */
function sentimen_label(?string $code): string
{
    if (!$code) return '-';
    $map = [
        'positif' => 'Positif',
        'negatif' => 'Negatif',
        'netral'  => 'Netral',
        'POS'     => 'Positif',
        'NEG'     => 'Negatif',
        'NET'     => 'Netral',
    ];
    return $map[$code] ?? ucfirst($code);
}

/**
 * Badge Bootstrap untuk sentimen
 */
function sentimen_badge(?string $code, ?float $confidence = null): string
{
    if (!$code) return '<span class="text-muted">-</span>';
    $key = strtolower($code);
    $map = [
        'positif' => ['bg-success', 'bi-emoji-smile-fill', 'Positif'],
        'negatif' => ['bg-danger',  'bi-emoji-frown-fill', 'Negatif'],
        'netral'  => ['bg-secondary','bi-emoji-neutral-fill', 'Netral'],
    ];
    $data = $map[$key] ?? ['bg-light text-dark', 'bi-emoji-expressionless', ucfirst($code)];
    $conf = $confidence !== null
        ? ' <span class="opacity-75">(' . number_format($confidence * 100, 1) . '%)</span>'
        : '';
    return '<span class="badge ' . $data[0] . '"><i class="bi ' . $data[1] . '"></i> ' . h($data[2]) . $conf . '</span>';
}

/**
 * Badge Bootstrap untuk kategori
 */
function kategori_badge(?string $codes): string
{
    if (!$codes) return '<span class="text-muted">-</span>';
    $map = [
        'PLY' => 'bg-info',
        'PRD' => 'bg-success',
        'HRG' => 'bg-warning text-dark',
        'SUI' => 'bg-secondary',
    ];
    $arr = array_map('trim', explode(',', $codes));
    $html = '';
    foreach ($arr as $code) {
        $cls = $map[$code] ?? 'bg-light text-dark';
        $html .= '<span class="badge ' . $cls . ' me-1">' . h(kategori_label($code)) . '</span>';
    }
    return $html;
}

/**
 * Label tipe pesanan (dine_in / take_away / online)
 */
function order_type_label(?string $code): string
{
    $map = [
        'dine_in'   => 'Dine In',
        'take_away' => 'Take Away',
        'online'    => 'Online',
    ];
    return $map[$code] ?? '-';
}

/**
 * Badge Bootstrap untuk tipe pesanan
 */
function order_type_badge(?string $code): string
{
    if (!$code) return '<span class="text-muted">-</span>';
    $map = [
        'dine_in'   => ['bg-primary',  'bi-shop',           'Dine In'],
        'take_away' => ['bg-success',  'bi-bag-fill',       'Take Away'],
        'online'    => ['bg-info',     'bi-laptop',         'Online'],
    ];
    $data = $map[$code] ?? ['bg-light text-dark', 'bi-question-circle', $code];
    return '<span class="badge ' . $data[0] . '"><i class="bi ' . $data[1] . '"></i> ' . h($data[2]) . '</span>';
}

/**
 * Catat ke activity_logs
 */
function log_activity(PDO $pdo, ?int $user_id, string $action, string $entity_type, ?string $entity_id, string $detail = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (actor_user_id, action, entity_type, entity_id, detail)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $action, $entity_type, $entity_id, $detail]);
}

/**
 * Format tanggal ke format Indonesia
 */
function tgl_id(string $datetime): string
{
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('d', $ts) . ' ' . $bulan[date('n', $ts) - 1] . ' ' . date('Y H:i', $ts);
}
