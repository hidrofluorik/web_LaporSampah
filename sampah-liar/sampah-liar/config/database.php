<?php
// ============================================================
//  config/database.php
//  Koneksi Database menggunakan PDO (lebih portabel & aman)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_sampah_liar');
define('DB_USER', 'root');
define('DB_PASS', '');          // Ganti sesuai password MySQL Anda
define('DB_CHARSET', 'utf8mb4');

/**
 * Mengembalikan instance PDO (singleton sederhana).
 * Menggunakan Prepared Statements secara default via PDO::ATTR_EMULATE_PREPARES = false
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,   // gunakan prepared statement asli
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan tampilkan detail error di produksi
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(500);
            die('<h3 style="color:red;font-family:sans-serif;">
                Koneksi database gagal. Pastikan XAMPP/MySQL sudah berjalan.</h3>');
        }
    }

    return $pdo;
}
