<?php
// ============================================================
//  config/app.php
//  Konfigurasi Aplikasi & Helper Functions
// ============================================================

// --- Mulai session jika belum dimulai ---
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// --- Konstanta Aplikasi ---
define('APP_NAME',      'SampahLiar.ID');
define('APP_URL',       'http://localhost/sampah-liar');   // Sesuaikan
define('UPLOAD_DIR',    __DIR__ . '/../uploads/');
define('UPLOAD_URL',    APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);                  // 2 MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_EXTS',  ['jpg', 'jpeg', 'png']);

// ============================================================
//  HELPER FUNCTIONS
// ============================================================

/**
 * Escape output untuk mencegah XSS
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate kode laporan unik: LAP-YYYYMMDD-XXXX
 */
function generateKodeLaporan(PDO $pdo): string {
    $tanggal = date('Ymd');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM laporan WHERE DATE(created_at) = CURDATE()"
    );
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();
    return sprintf('LAP-%s-%04d', $tanggal, $count + 1);
}

/**
 * Upload foto laporan dengan validasi ketat.
 * Mengembalikan nama file yang disimpan atau false jika gagal.
 */
function uploadFoto(array $file): string|false {
    // 1. Pastikan tidak ada error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // 2. Validasi ukuran
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    // 3. Validasi MIME type (baca dari file, bukan dari $_FILES)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return false;
    }

    // 4. Validasi ekstensi
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTS, true)) {
        return false;
    }

    // 5. Buat nama file unik (timestamp + random)
    $newName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    // 6. Pastikan folder uploads ada
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // 7. Pindahkan file
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
        return false;
    }

    return $newName;
}

/**
 * Flash message (set)
 */
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/**
 * Flash message (get & hapus)
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Redirect helper
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Cek apakah admin sudah login
 */
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']);
}

/**
 * Guard untuk halaman admin
 */
function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        redirect(APP_URL . '/admin/login.php');
    }
}

/**
 * Cek apakah user (warga) sudah login
 */
function isUserLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Format tanggal ke Bahasa Indonesia
 */
function formatTanggal(string $datetime): string {
    $bulan = [
        1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
        7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'
    ];
    $ts = strtotime($datetime);
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y H:i', $ts);
}

/**
 * Status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'Pending'  => 'warning',
        'Diproses' => 'info',
        'Selesai'  => 'success',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . e($status) . '</span>';
}
