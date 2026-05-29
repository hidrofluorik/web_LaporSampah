<?php
// ============================================================
//  track.php — Halaman Tracking Status Laporan Publik
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

$pdo     = getDB();
$laporan = null;
$notFound = false;

// Ambil kode dari GET (dari hero link) atau POST (dari form search)
$kode = trim(
    $_GET['kode'] ?? $_POST['kode'] ?? ''
);
$kode = strtoupper($kode);

if ($kode !== '') {
    // Prepared statement — aman dari SQL Injection
    $stmt = $pdo->prepare("
        SELECT
            l.kode_laporan,
            l.nama_pelapor,
            l.deskripsi,
            l.foto,
            l.latitude,
            l.longitude,
            l.alamat_manual,
            l.status,
            l.catatan_admin,
            l.created_at,
            l.updated_at,
            u.nama AS nama_user
        FROM laporan l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.kode_laporan = :kode
        LIMIT 1
    ");
    $stmt->execute([':kode' => $kode]);
    $laporan = $stmt->fetch();

    if (!$laporan) {
        $notFound = true;
    }
}

// Urutkan tahapan timeline
$steps = [
    ['label' => 'Laporan Masuk',    'status' => 'Pending',  'icon' => 'bi-inbox'],
    ['label' => 'Sedang Diproses',  'status' => 'Diproses', 'icon' => 'bi-gear'],
    ['label' => 'Selesai Ditangani','status' => 'Selesai',  'icon' => 'bi-check-circle'],
];
$currentStep = 0;
if ($laporan) {
    $currentStep = match($laporan['status']) {
        'Pending'  => 0,
        'Diproses' => 1,
        'Selesai'  => 2,
        default    => 0,
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lacak Laporan — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --emerald: #059669; --emerald-dark: #047857;
  --emerald-light: #d1fae5; --emerald-pale: #ecfdf5;
  --gray-50: #f8fafc; --gray-100: #f1f5f9; --gray-200: #e2e8f0;
  --gray-500: #64748b; --gray-700: #334155; --gray-900: #0f172a;
}
body { font-family:'DM Sans',sans-serif; background:var(--gray-50); }
.navbar-brand { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; }
.page-header {
  background:linear-gradient(135deg,#064e3b,#047857);
  padding:3rem 0; color:white;
}
.page-header h1 { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; }
.search-card, .result-card {
  background:white; border-radius:20px;
  border:1px solid var(--gray-200);
  box-shadow:0 4px 24px rgba(0,0,0,.07);
}

/* ── TIMELINE ── */
.timeline-wrap { display:flex; align-items:flex-start; justify-content:space-between; position:relative; }
.timeline-wrap::before {
  content:''; position:absolute; top:22px; left:10%; right:10%;
  height:3px; background:var(--gray-200); z-index:0;
}
.tl-step { flex:1; text-align:center; position:relative; z-index:1; }
.tl-circle {
  width:46px; height:46px; border-radius:50%; margin:0 auto 10px;
  border:3px solid var(--gray-200);
  background:white; display:flex; align-items:center; justify-content:center;
  font-size:1.2rem; color:var(--gray-500); transition:all .4s;
}
.tl-step.done .tl-circle    { background:var(--emerald); border-color:var(--emerald); color:white; }
.tl-step.active .tl-circle  { background:white; border-color:var(--emerald); color:var(--emerald); box-shadow:0 0 0 4px var(--emerald-light); }
.tl-step.done .tl-label, .tl-step.active .tl-label { color:var(--emerald-dark); font-weight:700; }
.tl-label { font-size:.82rem; color:var(--gray-500); }
/* Progress bar fill */
.timeline-progress {
  position:absolute; top:22px; left:10%;
  height:3px; background:var(--emerald); z-index:0;
  transition:width .6s ease;
}

.info-row { display:flex; gap:.75rem; align-items:flex-start; padding:.75rem 0; border-bottom:1px solid var(--gray-100); }
.info-row:last-child { border-bottom:none; }
.info-icon { width:36px; height:36px; border-radius:8px; background:var(--emerald-pale); display:flex; align-items:center; justify-content:center; color:var(--emerald); flex-shrink:0; }
.info-label { font-size:.78rem; color:var(--gray-500); margin-bottom:.1rem; }
.info-val { font-weight:600; color:var(--gray-900); }

.foto-preview { border-radius:14px; overflow:hidden; border:2px solid var(--emerald-light); }
.foto-preview img { width:100%; height:200px; object-fit:cover; display:block; }

.btn-emerald { background:var(--emerald); color:white; border:none; border-radius:10px; padding:.7rem 1.5rem; font-weight:700; }
.badge-pending  { background:#fef3c7; color:#92400e; }
.badge-diproses { background:#dbeafe; color:#1e40af; }
.badge-selesai  { background:var(--emerald-light); color:var(--emerald-dark); }
</style>
</head>
<body>

<nav class="navbar navbar-light bg-white border-bottom shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="<?= APP_URL ?>">
      <i class="bi bi-recycle me-2" style="color:var(--emerald)"></i><?= APP_NAME ?>
    </a>
    <a href="<?= APP_URL ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
      <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
  </div>
</nav>

<div class="page-header">
  <div class="container text-center">
    <h1 class="mb-2"><i class="bi bi-search me-2"></i>Lacak Status Laporan</h1>
    <p class="opacity-75 mb-0">Masukkan kode laporan yang Anda terima setelah mengirim laporan</p>
  </div>
</div>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <!-- Form Pencarian -->
      <div class="search-card p-4 mb-4">
        <form method="GET" action="" class="d-flex gap-2">
          <input type="text" name="kode" class="form-control form-control-lg"
            placeholder="Contoh: LAP-20241201-0001"
            value="<?= e($kode) ?>"
            style="border-radius:10px;font-family:monospace;font-weight:600;letter-spacing:.05em">
          <button type="submit" class="btn btn-emerald px-4 fs-6">
            <i class="bi bi-search"></i>
          </button>
        </form>
      </div>

      <?php if ($notFound): ?>
      <!-- Tidak ditemukan -->
      <div class="result-card p-5 text-center">
        <i class="bi bi-file-earmark-x" style="font-size:3rem;color:var(--gray-200)"></i>
        <h5 class="mt-3 fw-bold">Laporan Tidak Ditemukan</h5>
        <p class="text-muted">Kode <code><?= e($kode) ?></code> tidak ada dalam sistem.<br>Periksa kembali kode yang Anda masukkan.</p>
        <a href="<?= APP_URL ?>" class="btn btn-emerald mt-2">Buat Laporan Baru</a>
      </div>

      <?php elseif ($laporan): ?>
      <!-- Hasil Tracking -->
      <div class="result-card p-4 mb-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold mb-1" style="font-family:'Plus Jakarta Sans',sans-serif">
              <?= e($laporan['kode_laporan']) ?>
            </h5>
            <small class="text-muted">Dikirim: <?= formatTanggal($laporan['created_at']) ?></small>
          </div>
          <?php
            $cls = [
              'Pending'  => 'badge-pending',
              'Diproses' => 'badge-diproses',
              'Selesai'  => 'badge-selesai',
            ][$laporan['status']] ?? '';
          ?>
          <span class="badge <?= $cls ?> px-3 py-2 fs-6 rounded-pill"><?= e($laporan['status']) ?></span>
        </div>

        <!-- Timeline Progress -->
        <div class="mb-5">
          <div class="timeline-wrap">
            <!-- Progress bar fill -->
            <div class="timeline-progress" id="tl-fill"></div>

            <?php foreach ($steps as $i => $step):
              $cls = '';
              if ($i < $currentStep)       $cls = 'done';
              elseif ($i === $currentStep) $cls = 'active';
            ?>
            <div class="tl-step <?= $cls ?>">
              <div class="tl-circle">
                <?php if ($i < $currentStep): ?>
                  <i class="bi bi-check-lg"></i>
                <?php else: ?>
                  <i class="bi <?= $step['icon'] ?>"></i>
                <?php endif; ?>
              </div>
              <div class="tl-label"><?= $step['label'] ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Info Detail -->
        <div class="row g-4">
          <div class="col-md-7">
            <h6 class="fw-bold mb-3 text-uppercase" style="font-size:.8rem;letter-spacing:.08em;color:var(--gray-500)">Detail Laporan</h6>

            <div class="info-row">
              <div class="info-icon"><i class="bi bi-person"></i></div>
              <div>
                <div class="info-label">Pelapor</div>
                <div class="info-val"><?= e($laporan['nama_user'] ?? $laporan['nama_pelapor'] ?? 'Anonim') ?></div>
              </div>
            </div>

            <div class="info-row">
              <div class="info-icon"><i class="bi bi-card-text"></i></div>
              <div>
                <div class="info-label">Deskripsi</div>
                <div class="info-val" style="font-weight:400;color:var(--gray-700)"><?= nl2br(e($laporan['deskripsi'])) ?></div>
              </div>
            </div>

            <?php if ($laporan['alamat_manual']): ?>
            <div class="info-row">
              <div class="info-icon"><i class="bi bi-map"></i></div>
              <div>
                <div class="info-label">Alamat</div>
                <div class="info-val"><?= e($laporan['alamat_manual']) ?></div>
              </div>
            </div>
            <?php endif; ?>

            <div class="info-row">
              <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
              <div>
                <div class="info-label">Koordinat GPS</div>
                <div class="info-val">
                  <?= e($laporan['latitude']) ?>, <?= e($laporan['longitude']) ?>
                  <br>
                  <a href="https://www.google.com/maps?q=<?= urlencode($laporan['latitude'] . ',' . $laporan['longitude']) ?>"
                     target="_blank" class="text-success small fw-600">
                    <i class="bi bi-map-fill me-1"></i>Buka di Google Maps
                  </a>
                </div>
              </div>
            </div>

            <?php if ($laporan['catatan_admin']): ?>
            <div class="info-row">
              <div class="info-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-chat-left-text"></i></div>
              <div>
                <div class="info-label">Catatan Petugas</div>
                <div class="info-val"><?= nl2br(e($laporan['catatan_admin'])) ?></div>
              </div>
            </div>
            <?php endif; ?>

            <div class="info-row">
              <div class="info-icon"><i class="bi bi-clock-history"></i></div>
              <div>
                <div class="info-label">Terakhir Diperbarui</div>
                <div class="info-val"><?= formatTanggal($laporan['updated_at']) ?></div>
              </div>
            </div>
          </div>

          <!-- Foto -->
          <div class="col-md-5">
            <h6 class="fw-bold mb-3 text-uppercase" style="font-size:.8rem;letter-spacing:.08em;color:var(--gray-500)">Foto Laporan</h6>
            <div class="foto-preview">
              <img src="<?= APP_URL ?>/uploads/<?= e($laporan['foto']) ?>" alt="Foto Laporan"
                   onerror="this.src='https://via.placeholder.com/400x200?text=Foto+Tidak+Tersedia'">
            </div>
          </div>
        </div>
      </div>

      <?php elseif ($kode === ''): ?>
      <!-- State awal -->
      <div class="result-card p-5 text-center">
        <i class="bi bi-search" style="font-size:3rem;color:var(--gray-200)"></i>
        <h5 class="mt-3 fw-bold">Masukkan Kode Laporan</h5>
        <p class="text-muted">Kode laporan diberikan setelah Anda berhasil mengirim laporan.<br>Format: <code>LAP-YYYYMMDD-XXXX</code></p>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<footer class="py-3 text-center border-top">
  <small class="text-muted">&copy; <?= date('Y') ?> <?= APP_NAME ?>. Platform Pelaporan Sampah Liar.</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animasi timeline fill bar
<?php if ($laporan): ?>
const fillWidths = ['5%', '50%', '95%'];
const fill = document.getElementById('tl-fill');
if (fill) {
  setTimeout(() => { fill.style.width = fillWidths[<?= $currentStep ?>]; }, 200);
}
<?php endif; ?>
</script>
</body>
</html>
