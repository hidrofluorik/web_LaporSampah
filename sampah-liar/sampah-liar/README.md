# 🌿 SampahLiar.ID — Sistem Pelaporan Sampah Liar
**Platform Pelaporan Berbasis Masyarakat | PHP Native + MySQL**

---

## 📁 Struktur Folder

```
sampah-liar/
├── config/
│   ├── database.php        # Koneksi PDO (singleton)
│   └── app.php             # Konstanta, helper functions, session
│
├── admin/
│   ├── login.php           # Otentikasi admin (bcrypt)
│   ├── dashboard.php       # Dashboard + Chart.js + Manajemen Laporan
│   ├── update-status.php   # AJAX handler update status (JSON)
│   └── logout.php          # Destroy session admin
│
├── uploads/
│   └── .htaccess           # ⚠️ Blokir eksekusi PHP di folder ini
│
├── index.php               # Halaman utama + Form Laporan + Geolocation
├── track.php               # Tracking status publik berdasarkan kode
├── login.php               # Login warga terregistrasi
├── register.php            # Registrasi warga baru
├── logout.php              # Destroy session user
└── database.sql            # Skema + seed data admin
```

---

## ⚙️ Instalasi (XAMPP)

### 1. Clone / Copy Folder
Salin folder `sampah-liar` ke:
```
C:\xampp\htdocs\sampah-liar\
```

### 2. Buat Database
- Buka **phpMyAdmin** → http://localhost/phpmyadmin
- Klik **Import** → pilih file `database.sql`
- Klik **Go**

### 3. Konfigurasi Koneksi
Edit `config/database.php`, sesuaikan:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // Ganti jika MySQL Anda pakai password
```

Edit `config/app.php`, sesuaikan URL:
```php
define('APP_URL', 'http://localhost/sampah-liar');
```

### 4. Buat Folder Uploads
Pastikan folder `uploads/` ada dan dapat ditulis:
- Di XAMPP Windows: biasanya sudah bisa ditulis otomatis
- Di Linux/Mac: `chmod 755 uploads/`

### 5. Jalankan
Buka: **http://localhost/sampah-liar**

---

## 🔐 Akun Default Admin

| Field    | Value                    |
|----------|--------------------------|
| Email    | admin@sampah-liar.id     |
| Password | `password`               |

> ⚠️ **WAJIB ganti password setelah instalasi pertama!**
>
> Generate hash baru di PHP:
> ```php
> echo password_hash('PasswordBaru123!', PASSWORD_BCRYPT, ['cost' => 12]);
> ```
> Lalu UPDATE di database:
> ```sql
> UPDATE admin SET password = 'HASH_BARU' WHERE email = 'admin@sampah-liar.id';
> ```

---

## 🛡️ Fitur Keamanan

| Vektor            | Mitigasi                                              |
|-------------------|-------------------------------------------------------|
| SQL Injection     | Semua query menggunakan **PDO Prepared Statements**   |
| XSS               | Output selalu di-escape dengan `htmlspecialchars()`   |
| CSRF              | Token tersimpan di session, divalidasi setiap POST    |
| File Upload       | Validasi MIME type via `finfo`, validasi ekstensi     |
| PHP Upload Exec   | `.htaccess` di folder `uploads/` blokir PHP           |
| Password          | **bcrypt** cost 12 via `password_hash()`              |
| Session Fixation  | `session_regenerate_id(true)` setelah login           |
| Brute Force       | `sleep(1)` delay saat login gagal                     |
| Info Leakage      | Error database di-log, tidak ditampilkan ke user      |

---

## 📊 Fitur Utama

### Warga
- ✅ Lapor anonim (tanpa akun) atau dengan akun
- ✅ GPS Geolocation otomatis via HTML5 API
- ✅ Upload foto drag & drop dengan preview
- ✅ Tracking status berdasarkan kode laporan
- ✅ Notifikasi interaktif via SweetAlert2

### Admin
- ✅ Login aman dengan bcrypt
- ✅ Dashboard statistik + Chart.js (Bar & Donut chart)
- ✅ Kelola laporan: filter, cari, paginasi
- ✅ Update status via AJAX (tanpa reload halaman)
- ✅ Link langsung ke Google Maps dari koordinat GPS
- ✅ Preview foto langsung di dashboard

---

## 🧪 Test Laporan Dummy (SQL)

```sql
-- Tambah laporan dummy untuk testing chart
INSERT INTO laporan (kode_laporan, nama_pelapor, deskripsi, foto, latitude, longitude, status, created_at)
VALUES
  ('LAP-20241101-0001','Budi Santoso','Sampah menumpuk di pinggir jalan','dummy.jpg','-6.9175','107.6191','Selesai','2024-11-15 09:00:00'),
  ('LAP-20241201-0001','Siti Rahayu','Pembuangan liar dekat pasar','dummy.jpg','-6.9200','107.6210','Diproses','2024-12-03 10:00:00'),
  ('LAP-20250101-0001',NULL,'Sampah plastik berserakan','dummy.jpg','-6.9150','107.6170','Pending','2025-01-10 08:00:00');
```

---

## 📦 Dependensi Eksternal (CDN)

| Library       | Versi  | Fungsi                        |
|---------------|--------|-------------------------------|
| Bootstrap     | 5.3.3  | Responsive UI framework       |
| Bootstrap Icons| 1.11.3| Ikon vektor                   |
| Chart.js      | 4.4.3  | Visualisasi data              |
| SweetAlert2   | 11     | Notifikasi modal interaktif   |
| Google Fonts  | —      | Plus Jakarta Sans, DM Sans    |

**Tidak ada dependensi Composer / npm** — murni PHP Native dan CDN.

---

## 📞 Kontak & Pengembangan
Sistem ini dirancang untuk keperluan akademik dan dapat dikembangkan lebih lanjut dengan fitur:
- Notifikasi email (PHPMailer)
- Export laporan ke PDF/Excel
- Peta interaktif Leaflet.js
- API REST untuk mobile app
