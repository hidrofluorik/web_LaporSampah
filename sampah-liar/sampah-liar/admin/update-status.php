<?php
// ============================================================
//  admin/update-status.php
//  Handler AJAX untuk mengubah status laporan
//  Respons: JSON { success: bool, message: string }
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Paksa output JSON
header('Content-Type: application/json; charset=UTF-8');

// --- Fungsi helper respons JSON ---
function jsonResponse(bool $success, string $message): never {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// --- Guard: harus admin login ---
if (!isAdminLoggedIn()) {
    jsonResponse(false, 'Sesi tidak valid. Silakan login kembali.');
}

// --- Hanya terima POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode tidak diizinkan.');
}

// --- Validasi CSRF token ---
$token = $_POST['token'] ?? '';
if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
    jsonResponse(false, 'Token keamanan tidak valid.');
}

// --- Ambil & validasi input ---
$id      = (int)($_POST['id']      ?? 0);
$status  = trim($_POST['status']   ?? '');
$catatan = trim($_POST['catatan']  ?? '');

if ($id <= 0) {
    jsonResponse(false, 'ID laporan tidak valid.');
}

$validStatuses = ['Pending', 'Diproses', 'Selesai'];
if (!in_array($status, $validStatuses, true)) {
    jsonResponse(false, 'Status tidak valid.');
}

// Batas panjang catatan
if (mb_strlen($catatan) > 1000) {
    $catatan = mb_substr($catatan, 0, 1000);
}

// --- Update ke database menggunakan Prepared Statement ---
$pdo = getDB();

$stmt = $pdo->prepare("
    UPDATE laporan
    SET
        status       = :status,
        catatan_admin = :catatan,
        updated_at   = NOW()
    WHERE id = :id
");

try {
    $stmt->execute([
        ':status'  => $status,
        ':catatan' => $catatan ?: null,
        ':id'      => $id,
    ]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(true, "Status laporan berhasil diubah menjadi \"$status\".");
    } else {
        jsonResponse(false, 'Laporan tidak ditemukan atau tidak ada perubahan.');
    }
} catch (PDOException $e) {
    error_log('Update status error: ' . $e->getMessage());
    jsonResponse(false, 'Terjadi kesalahan server. Coba lagi.');
}
