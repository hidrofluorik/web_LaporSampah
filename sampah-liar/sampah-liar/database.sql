-- ============================================================
--  SISTEM PELAPORAN SAMPAH LIAR
--  Database Schema - MySQL
--  Dibuat untuk XAMPP / localhost
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_sampah_liar
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_sampah_liar;

-- ------------------------------------------------------------
-- Tabel: users (Warga Terregistrasi)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nama        VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,           -- bcrypt hash
    no_hp       VARCHAR(20)     NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nama        VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,           -- bcrypt hash
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: laporan
-- user_id NULL berarti laporan dari Anonim
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS laporan (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    kode_laporan    VARCHAR(20)         NOT NULL UNIQUE,   -- e.g. LAP-20240101-0001
    user_id         INT UNSIGNED        NULL,              -- FK ke users, NULL = anonim
    nama_pelapor    VARCHAR(100)        NULL,              -- untuk anonim
    deskripsi       TEXT                NOT NULL,
    foto            VARCHAR(255)        NOT NULL,          -- nama file di folder uploads/
    latitude        DECIMAL(10, 8)      NOT NULL,
    longitude       DECIMAL(11, 8)      NOT NULL,
    alamat_manual   VARCHAR(255)        NULL,              -- alamat teks opsional
    status          ENUM(
                        'Pending',
                        'Diproses',
                        'Selesai'
                    )                   NOT NULL DEFAULT 'Pending',
    catatan_admin   TEXT                NULL,              -- catatan saat update status
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_laporan_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX idx_kode_laporan (kode_laporan),
    INDEX idx_status       (status),
    INDEX idx_created_at   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Data Seed: Akun Admin Default
-- Password: Admin@123  (hash bcrypt cost 12)
-- GANTI PASSWORD INI SETELAH INSTALASI!
-- ------------------------------------------------------------
INSERT INTO admin (nama, email, password) VALUES
(
    'Administrator',
    'admin@sampah-liar.id',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);

-- Catatan: hash di atas adalah hash bcrypt dari string "password"
-- Untuk produksi, generate hash baru dengan:
-- password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12])
