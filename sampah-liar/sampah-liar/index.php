<?php
// ============================================================
//  index.php - Halaman Utama + Form Laporan
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

$pdo   = getDB();
$error = '';
$sukses = '';

// ============================================================
//  Proses POST: Terima laporan baru
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lapor') {

    // --- CSRF sederhana: token di session ---
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $deskripsi    = trim($_POST['deskripsi'] ?? '');
        $latitude     = $_POST['latitude']  ?? '';
        $longitude    = $_POST['longitude'] ?? '';
        $alamat       = trim($_POST['alamat'] ?? '');
        $nama_pelapor = trim($_POST['nama_pelapor'] ?? 'Anonim');

        // Validasi input
        if (empty($deskripsi)) {
            $error = 'Deskripsi tidak boleh kosong.';
        } elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
            $error = 'Koordinat GPS tidak terdeteksi. Izinkan akses lokasi di browser Anda.';
        } elseif (empty($_FILES['foto']['name'])) {
            $error = 'Foto wajib diunggah.';
        } else {
            // Upload foto
            $namaFoto = uploadFoto($_FILES['foto']);
            if ($namaFoto === false) {
                $error = 'Upload foto gagal. Pastikan format JPG/PNG dan ukuran maksimal 2MB.';
            } else {
                // Simpan ke database
                $userId      = isUserLoggedIn() ? (int)$_SESSION['user_id'] : null;
                $kodeLaporan = generateKodeLaporan($pdo);

                $stmt = $pdo->prepare("
                    INSERT INTO laporan
                        (kode_laporan, user_id, nama_pelapor, deskripsi, foto, latitude, longitude, alamat_manual, status)
                    VALUES
                        (:kode, :uid, :nama, :desk, :foto, :lat, :lng, :alamat, 'Pending')
                ");
                $stmt->execute([
                    ':kode'   => $kodeLaporan,
                    ':uid'    => $userId,
                    ':nama'   => $nama_pelapor ?: 'Anonim',
                    ':desk'   => $deskripsi,
                    ':foto'   => $namaFoto,
                    ':lat'    => (float)$latitude,
                    ':lng'    => (float)$longitude,
                    ':alamat' => $alamat ?: null,
                ]);

                $sukses = $kodeLaporan;
                // Regenerasi CSRF setelah sukses
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
}

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Statistik publik
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending')  AS pending,
        SUM(status = 'Diproses') AS diproses,
        SUM(status = 'Selesai')  AS selesai
    FROM laporan
")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> — Laporkan Sampah Liar</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<style>
:root {
  --emerald:       #059669;
  --emerald-dark:  #047857;
  --emerald-light: #d1fae5;
  --emerald-pale:  #ecfdf5;
  --gray-50:       #f8fafc;
  --gray-100:      #f1f5f9;
  --gray-200:      #e2e8f0;
  --gray-500:      #64748b;
  --gray-700:      #334155;
  --gray-900:      #0f172a;
  --white:         #ffffff;
}

* { box-sizing: border-box; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--gray-50);
  color: var(--gray-700);
  min-height: 100vh;
}

/* ── NAVBAR ── */
.navbar-brand { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.35rem; }
.navbar-brand span { color: var(--emerald); }
.navbar { background: var(--white) !important; border-bottom: 1px solid var(--gray-200); }
.nav-link { font-weight: 500; color: var(--gray-700) !important; }
.nav-link:hover { color: var(--emerald) !important; }

/* ── HERO ── */
.hero-section {
  background: linear-gradient(135deg, #064e3b 0%, #065f46 40%, #047857 100%);
  position: relative; overflow: hidden;
  padding: 5rem 0 4rem;
}
.hero-section::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.hero-section h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--white); }
.hero-section p  { color: rgba(255,255,255,0.85); font-size: 1.1rem; }

/* ── STAT CARDS ── */
.stat-card {
  background: rgba(255,255,255,0.12);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 16px; padding: 1.25rem 1.5rem;
  text-align: center; color: white;
}
.stat-card .stat-num { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 2rem; }
.stat-card .stat-lbl { font-size: .85rem; opacity: .8; }

/* ── SECTION HEADING ── */
.section-label {
  display: inline-block;
  background: var(--emerald-light);
  color: var(--emerald-dark);
  font-weight: 700; font-size: .8rem;
  letter-spacing: .08em; text-transform: uppercase;
  padding: .3rem .9rem; border-radius: 50px;
  margin-bottom: .75rem;
}
.section-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; color: var(--gray-900); }

/* ── FORM CARD ── */
.form-card {
  background: var(--white);
  border-radius: 20px;
  border: 1px solid var(--gray-200);
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
  padding: 2.5rem;
}
.form-label { font-weight: 600; color: var(--gray-700); margin-bottom: .4rem; }
.form-control, .form-select {
  border-color: var(--gray-200);
  border-radius: 10px;
  padding: .65rem 1rem;
  font-family: 'DM Sans', sans-serif;
  transition: border-color .2s, box-shadow .2s;
}
.form-control:focus, .form-select:focus {
  border-color: var(--emerald);
  box-shadow: 0 0 0 3px rgba(5,150,105,.15);
}

/* ── DRAG & DROP UPLOAD ── */
.upload-zone {
  border: 2px dashed var(--gray-200);
  border-radius: 14px;
  padding: 2.5rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: all .25s ease;
  background: var(--gray-50);
  position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
  border-color: var(--emerald);
  background: var(--emerald-pale);
}
.upload-zone input[type=file] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-icon { font-size: 2.5rem; color: var(--gray-500); margin-bottom: .5rem; display: block; }
.upload-zone.dragover .upload-icon { color: var(--emerald); }
#preview-img {
  max-height: 200px; border-radius: 10px;
  object-fit: cover; display: none;
  width: 100%; margin-top: 1rem;
  border: 2px solid var(--emerald-light);
}

/* ── GPS STATUS ── */
.gps-badge {
  display: inline-flex; align-items: center; gap: .5rem;
  font-size: .85rem; font-weight: 600;
  padding: .4rem 1rem; border-radius: 50px;
}
.gps-badge.detecting { background: #fef3c7; color: #92400e; }
.gps-badge.found     { background: var(--emerald-light); color: var(--emerald-dark); }
.gps-badge.error     { background: #fee2e2; color: #991b1b; }
.gps-pulse {
  width: 10px; height: 10px; border-radius: 50%;
  background: #f59e0b; animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50%       { transform: scale(1.4); opacity: .6; }
}

/* ── BTN PRIMARY ── */
.btn-emerald {
  background: var(--emerald); color: white; border: none;
  padding: .75rem 2rem; border-radius: 12px;
  font-weight: 700; font-size: 1rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  transition: background .2s, transform .1s, box-shadow .2s;
  box-shadow: 0 4px 14px rgba(5,150,105,.35);
}
.btn-emerald:hover { background: var(--emerald-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,.4); }
.btn-emerald:active { transform: translateY(0); }

/* ── TRACK SECTION ── */
.track-card {
  background: var(--white);
  border-radius: 20px; border: 1px solid var(--gray-200);
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
  padding: 2rem;
}
.input-track {
  border-radius: 10px 0 0 10px !important;
  border-right: none !important;
}
.btn-track {
  background: var(--emerald); color: white; border: none;
  border-radius: 0 10px 10px 0; padding: 0 1.5rem;
  font-weight: 600;
}

/* ── HOW IT WORKS ── */
.step-circle {
  width: 52px; height: 52px; border-radius: 50%;
  background: var(--emerald-pale);
  border: 2px solid var(--emerald-light);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; color: var(--emerald);
  margin: 0 auto 1rem;
}

/* ── FOOTER ── */
footer {
  background: var(--gray-900);
  color: rgba(255,255,255,.6);
  padding: 2.5rem 0; font-size: .9rem;
}
footer .brand { font-family: 'Plus Jakarta Sans', sans-serif; color: white; font-weight: 700; }
footer a { color: rgba(255,255,255,.6); text-decoration: none; }
footer a:hover { color: var(--emerald); }
</style>
</head>
<body>

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="<?= APP_URL ?>">
      <i class="bi bi-recycle me-2" style="color:var(--emerald)"></i><?= APP_NAME ?>
      <span style="font-size:.7rem;font-weight:600;background:var(--emerald-light);color:var(--emerald-dark);padding:.1rem .5rem;border-radius:50px;vertical-align:middle;margin-left:.3rem">.ID</span>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <li class="nav-item"><a class="nav-link" href="#lapor"><i class="bi bi-megaphone me-1"></i>Lapor</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/track.php"><i class="bi bi-search me-1"></i>Tracking</a></li>
        <?php if (isUserLoggedIn()): ?>
          <li class="nav-item">
            <span class="nav-link"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['user_nama'] ?? 'User') ?></span>
          </li>
          <li class="nav-item">
            <a class="btn btn-sm btn-outline-danger rounded-pill px-3" href="<?= APP_URL ?>/logout.php">Keluar</a>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/login.php">Masuk</a></li>
          <li class="nav-item">
            <a class="btn btn-sm btn-emerald rounded-pill px-3" href="<?= APP_URL ?>/register.php">Daftar</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="hero-section">
  <div class="container position-relative">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="section-label" style="background:rgba(255,255,255,.15);color:white">
          <i class="bi bi-geo-alt-fill me-1"></i> Platform Warga Digital
        </div>
        <h1 class="display-5 mb-3">Laporkan Sampah Liar<br><span style="color:#6ee7b7">di Sekitar Anda</span></h1>
        <p class="mb-4">Bersama kita jaga kebersihan lingkungan. Kirim laporan dengan foto &amp; lokasi GPS otomatis — tanpa perlu daftar akun.</p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="#lapor" class="btn btn-emerald">
            <i class="bi bi-send-fill me-2"></i>Kirim Laporan
          </a>
          <a href="<?= APP_URL ?>/track.php" class="btn" style="background:rgba(255,255,255,.15);color:white;border:1px solid rgba(255,255,255,.3);border-radius:12px;padding:.75rem 1.5rem;font-weight:600;">
            <i class="bi bi-search me-2"></i>Lacak Status
          </a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="row g-3">
          <div class="col-6"><div class="stat-card"><div class="stat-num"><?= number_format($stats['total']) ?></div><div class="stat-lbl">Total Laporan</div></div></div>
          <div class="col-6"><div class="stat-card"><div class="stat-num"><?= number_format($stats['selesai']) ?></div><div class="stat-lbl">Selesai Ditangani</div></div></div>
          <div class="col-6"><div class="stat-card"><div class="stat-num"><?= number_format($stats['diproses']) ?></div><div class="stat-lbl">Sedang Diproses</div></div></div>
          <div class="col-6"><div class="stat-card"><div class="stat-num"><?= number_format($stats['pending']) ?></div><div class="stat-lbl">Menunggu Tindak</div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CARA KERJA
     ============================================================ -->
<section class="py-5 bg-white border-bottom">
  <div class="container">
    <div class="text-center mb-4">
      <div class="section-label">Panduan</div>
      <h2 class="section-title">Cara Menggunakan Aplikasi</h2>
    </div>
    <div class="row g-4 text-center">
      <div class="col-md-3">
        <div class="step-circle"><i class="bi bi-geo-alt-fill"></i></div>
        <h6 class="fw-700">1. Izinkan Lokasi</h6>
        <p class="text-muted small">Browser akan meminta izin akses GPS. Setujui agar koordinat terisi otomatis.</p>
      </div>
      <div class="col-md-3">
        <div class="step-circle"><i class="bi bi-card-text"></i></div>
        <h6 class="fw-bold">2. Isi Deskripsi</h6>
        <p class="text-muted small">Ceritakan kondisi sampah, volume, dan potensi bahaya yang ditimbulkan.</p>
      </div>
      <div class="col-md-3">
        <div class="step-circle"><i class="bi bi-camera-fill"></i></div>
        <h6 class="fw-bold">3. Upload Foto</h6>
        <p class="text-muted small">Lampirkan foto kondisi terkini. Format JPG/PNG, maksimal 2MB.</p>
      </div>
      <div class="col-md-3">
        <div class="step-circle"><i class="bi bi-check-circle-fill"></i></div>
        <h6 class="fw-bold">4. Kirim & Lacak</h6>
        <p class="text-muted small">Simpan kode laporan untuk memantau perkembangan penanganan.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     FORM LAPORAN
     ============================================================ -->
<section id="lapor" class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="text-center mb-4">
          <div class="section-label">Form Laporan</div>
          <h2 class="section-title">Kirim Laporan Sampah Liar</h2>
          <p class="text-muted">Laporan dapat dikirim tanpa login. Koordinat GPS ditangkap otomatis.</p>
        </div>

        <div class="form-card">

          <!-- GPS Status -->
          <div class="mb-4">
            <span class="gps-badge detecting" id="gps-status">
              <span class="gps-pulse" id="gps-pulse"></span>
              <span id="gps-text">Mendeteksi lokasi GPS…</span>
            </span>
          </div>

          <form method="POST" enctype="multipart/form-data" id="formLaporan" novalidate>
            <input type="hidden" name="action" value="lapor">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="latitude"  id="latitude"  required>
            <input type="hidden" name="longitude" id="longitude" required>

            <!-- Nama Pelapor (jika belum login) -->
            <?php if (!isUserLoggedIn()): ?>
            <div class="mb-4">
              <label class="form-label"><i class="bi bi-person me-1"></i>Nama Pelapor <span class="text-muted fw-normal">(Opsional)</span></label>
              <input type="text" name="nama_pelapor" class="form-control" placeholder="Kosongkan untuk lapor anonim" maxlength="100">
            </div>
            <?php endif; ?>

            <!-- Deskripsi -->
            <div class="mb-4">
              <label class="form-label"><i class="bi bi-card-text me-1"></i>Deskripsi Kondisi Sampah <span class="text-danger">*</span></label>
              <textarea name="deskripsi" class="form-control" rows="4"
                placeholder="Contoh: Terdapat tumpukan sampah rumah tangga setinggi ±1 meter di pinggir jalan, menimbulkan bau tidak sedap dan berpotensi menjadi sarang nyamuk."
                required minlength="20" maxlength="2000"></textarea>
              <div class="form-text">Minimal 20 karakter. Semakin detail semakin cepat ditangani.</div>
            </div>

            <!-- Alamat Manual -->
            <div class="mb-4">
              <label class="form-label"><i class="bi bi-map me-1"></i>Alamat / Patokan Lokasi <span class="text-muted fw-normal">(Opsional)</span></label>
              <input type="text" name="alamat" class="form-control" placeholder="Contoh: Dekat Pasar Tradisional Baru, Jl. Raya No. 12" maxlength="255">
            </div>

            <!-- GPS Info Box -->
            <div class="mb-4 p-3 rounded-3" style="background:var(--emerald-pale);border:1px solid var(--emerald-light)">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-geo-alt-fill text-success"></i>
                <strong style="color:var(--emerald-dark)">Koordinat GPS Terdeteksi</strong>
              </div>
              <div class="row g-2">
                <div class="col-sm-6">
                  <label class="form-label small text-muted mb-1">Latitude</label>
                  <input type="text" class="form-control form-control-sm" id="lat-display" readonly placeholder="Mendeteksi…">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small text-muted mb-1">Longitude</label>
                  <input type="text" class="form-control form-control-sm" id="lng-display" readonly placeholder="Mendeteksi…">
                </div>
              </div>
              <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Koordinat diisi otomatis oleh browser. Pastikan GPS perangkat aktif.</small>
            </div>

            <!-- Upload Foto (Drag & Drop) -->
            <div class="mb-4">
              <label class="form-label"><i class="bi bi-camera me-1"></i>Foto Kondisi Sampah <span class="text-danger">*</span></label>
              <div class="upload-zone" id="uploadZone">
                <input type="file" name="foto" id="fotoInput" accept="image/jpeg,image/png" required>
                <i class="bi bi-cloud-arrow-up upload-icon"></i>
                <p class="mb-0 fw-600" id="upload-text">Drag &amp; drop foto di sini</p>
                <p class="text-muted small mb-0">atau klik untuk memilih file</p>
                <p class="text-muted small">JPG / PNG · Maks. 2MB</p>
                <img id="preview-img" src="" alt="Preview">
              </div>
            </div>

            <!-- Submit -->
            <div class="d-grid">
              <button type="submit" class="btn-emerald" id="btnSubmit">
                <i class="bi bi-send-fill me-2"></i>Kirim Laporan Sekarang
              </button>
            </div>
          </form>
        </div><!-- /form-card -->
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer>
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6 mb-3 mb-md-0">
        <div class="brand mb-1"><i class="bi bi-recycle me-2" style="color:var(--emerald)"></i><?= APP_NAME ?></div>
        <small>Platform pelaporan sampah liar berbasis masyarakat.</small>
      </div>
      <div class="col-md-6 text-md-end">
        <a href="<?= APP_URL ?>/track.php" class="me-3">Lacak Laporan</a>
        <a href="<?= APP_URL ?>/admin/login.php">Admin Panel</a>
      </div>
    </div>
  </div>
</footer>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// ============================================================
//  1. GEOLOCATION API — tangkap koordinat GPS otomatis
// ============================================================
const latInput   = document.getElementById('latitude');
const lngInput   = document.getElementById('longitude');
const latDisplay = document.getElementById('lat-display');
const lngDisplay = document.getElementById('lng-display');
const gpsStatus  = document.getElementById('gps-status');
const gpsPulse   = document.getElementById('gps-pulse');
const gpsText    = document.getElementById('gps-text');

function setGpsStatus(state, msg) {
  gpsStatus.className = 'gps-badge ' + state;
  gpsText.textContent  = msg;
  gpsPulse.style.display = (state === 'detecting') ? 'block' : 'none';
}

if ('geolocation' in navigator) {
  setGpsStatus('detecting', 'Mendeteksi lokasi GPS…');

  navigator.geolocation.getCurrentPosition(
    function(pos) {
      const lat = pos.coords.latitude.toFixed(7);
      const lng = pos.coords.longitude.toFixed(7);

      latInput.value   = lat;
      lngInput.value   = lng;
      latDisplay.value = lat;
      lngDisplay.value = lng;

      setGpsStatus('found', '✓ Lokasi berhasil terdeteksi');
    },
    function(err) {
      setGpsStatus('error', '✗ Lokasi tidak terdeteksi — izinkan akses GPS');
      latDisplay.placeholder = 'Tidak terdeteksi';
      lngDisplay.placeholder = 'Tidak terdeteksi';
    },
    { enableHighAccuracy: true, timeout: 15000 }
  );
} else {
  setGpsStatus('error', 'Browser tidak mendukung Geolocation');
}

// ============================================================
//  2. DRAG & DROP + IMAGE PREVIEW
// ============================================================
const zone      = document.getElementById('uploadZone');
const fotoInput = document.getElementById('fotoInput');
const previewImg = document.getElementById('preview-img');
const uploadText = document.getElementById('upload-text');

function showPreview(file) {
  if (!file || !file.type.startsWith('image/')) return;
  const reader = new FileReader();
  reader.onload = e => {
    previewImg.src = e.target.result;
    previewImg.style.display = 'block';
    uploadText.textContent = '✓ ' + file.name;
  };
  reader.readAsDataURL(file);
}

fotoInput.addEventListener('change', () => showPreview(fotoInput.files[0]));

zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    fotoInput.files = dt.files;
    showPreview(file);
  }
});

// ============================================================
//  3. VALIDASI FORM & SUBMIT
// ============================================================
document.getElementById('formLaporan').addEventListener('submit', function(e) {
  const lat = latInput.value;
  const lng = lngInput.value;

  if (!lat || !lng) {
    e.preventDefault();
    Swal.fire({
      icon: 'warning', title: 'Lokasi Belum Terdeteksi',
      text: 'Izinkan akses lokasi GPS di browser Anda, lalu coba kembali.',
      confirmButtonColor: '#059669'
    });
    return;
  }

  const foto = fotoInput.files[0];
  if (!foto) {
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Foto Wajib Dilampirkan', confirmButtonColor: '#059669' });
    return;
  }
  if (foto.size > 2 * 1024 * 1024) {
    e.preventDefault();
    Swal.fire({ icon: 'error', title: 'Foto Terlalu Besar', text: 'Maksimal 2MB.', confirmButtonColor: '#059669' });
    return;
  }

  // Loading state
  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim…';
});

// ============================================================
//  4. SWEET ALERT dari PHP
// ============================================================
<?php if ($sukses): ?>
Swal.fire({
  icon: 'success',
  title: 'Laporan Berhasil Dikirim!',
  html: `Kode laporan Anda:<br>
    <span style="font-family:monospace;font-size:1.5rem;font-weight:800;color:#059669"><?= e($sukses) ?></span><br><br>
    <small class="text-muted">Simpan kode ini untuk melacak status laporan Anda.</small>`,
  confirmButtonText: 'Lacak Status',
  cancelButtonText: 'Tutup',
  showCancelButton: true,
  confirmButtonColor: '#059669',
}).then(res => {
  if (res.isConfirmed) {
    window.location.href = '<?= APP_URL ?>/track.php?kode=<?= urlencode($sukses) ?>';
  }
});
<?php elseif ($error): ?>
Swal.fire({
  icon: 'error',
  title: 'Gagal Mengirim Laporan',
  text: '<?= addslashes(e($error)) ?>',
  confirmButtonColor: '#059669'
});
<?php endif; ?>
</script>
</body>
</html>
