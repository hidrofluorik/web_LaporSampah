<?php
// ============================================================
//  admin/dashboard.php — Dashboard Admin
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

requireAdmin();   // Guard: harus login sebagai admin

$pdo = getDB();

// ============================================================
//  DATA STATISTIK UNTUK KARTU RINGKASAN
// ============================================================
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending')  AS pending,
        SUM(status = 'Diproses') AS diproses,
        SUM(status = 'Selesai')  AS selesai
    FROM laporan
")->fetch();

// ============================================================
//  DATA CHART 1: Tren Laporan per Bulan (12 bulan terakhir)
// ============================================================
$trendData = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS bulan,
        DATE_FORMAT(created_at, '%b %Y') AS label,
        COUNT(*) AS jumlah
    FROM laporan
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY bulan, label
    ORDER BY bulan ASC
")->fetchAll();

// Isi bulan yang kosong agar grafik selalu 12 titik
$trendMap = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $lbl = date('M Y', strtotime("-$i months"));
    $trendMap[$key] = ['label' => $lbl, 'jumlah' => 0];
}
foreach ($trendData as $row) {
    if (isset($trendMap[$row['bulan']])) {
        $trendMap[$row['bulan']]['jumlah'] = (int)$row['jumlah'];
    }
}
$chartLabels = array_column(array_values($trendMap), 'label');
$chartValues = array_column(array_values($trendMap), 'jumlah');

// ============================================================
//  DATA CHART 2: Distribusi Status (untuk Donut Chart)
// ============================================================
$statusChart = [
    'Pending'  => (int)$stats['pending'],
    'Diproses' => (int)$stats['diproses'],
    'Selesai'  => (int)$stats['selesai'],
];

// ============================================================
//  DAFTAR LAPORAN TERBARU (paginated)
// ============================================================
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['q'] ?? '');
$filter  = $_GET['status'] ?? '';

$where  = '1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (l.kode_laporan LIKE :q OR l.deskripsi LIKE :q2 OR l.nama_pelapor LIKE :q3)';
    $params[':q']  = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
}
if (in_array($filter, ['Pending', 'Diproses', 'Selesai'])) {
    $where .= ' AND l.status = :status';
    $params[':status'] = $filter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM laporan l WHERE $where");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

$params[':limit']  = $perPage;
$params[':offset'] = $offset;

$stmt = $pdo->prepare("
    SELECT
        l.id, l.kode_laporan, l.nama_pelapor, l.deskripsi,
        l.foto, l.latitude, l.longitude, l.status,
        l.created_at, u.nama AS nama_user
    FROM laporan l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE $where
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
foreach ($params as $k => $v) {
    if (!in_array($k, [':limit', ':offset'])) {
        $stmt->bindValue($k, $v);
    }
}
$stmt->execute();
$laporan = $stmt->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= APP_NAME ?> Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=DM+Sans:ital,wght@0,400;0,500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<style>
:root {
  --emerald:#059669; --emerald-dark:#047857; --emerald-light:#d1fae5; --emerald-pale:#ecfdf5;
  --sidebar-bg:#0f2417; --sidebar-w:260px;
  --gray-50:#f8fafc; --gray-100:#f1f5f9; --gray-200:#e2e8f0;
  --gray-500:#64748b; --gray-700:#334155; --gray-900:#0f172a;
}
* { box-sizing:border-box; }
body { font-family:'DM Sans',sans-serif; background:var(--gray-50); color:var(--gray-700); }

/* ── SIDEBAR ── */
.sidebar {
  position:fixed; top:0; left:0; bottom:0;
  width:var(--sidebar-w); background:var(--sidebar-bg);
  display:flex; flex-direction:column; z-index:100;
  transition:transform .3s;
}
.sidebar-brand {
  padding:1.5rem 1.5rem 1rem;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.sidebar-brand .brand-name {
  font-family:'Plus Jakarta Sans',sans-serif; font-weight:800;
  color:white; font-size:1.15rem;
}
.sidebar-brand .brand-sub { font-size:.75rem; color:rgba(255,255,255,.4); }
.sidebar-logo {
  width:36px; height:36px; border-radius:10px;
  background:var(--emerald); display:inline-flex;
  align-items:center; justify-content:center; color:white;
  margin-bottom:.5rem;
}

.nav-section-label {
  font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
  color:rgba(255,255,255,.3); padding:.75rem 1.5rem .3rem;
}
.sidebar-link {
  display:flex; align-items:center; gap:.75rem;
  padding:.65rem 1.5rem; color:rgba(255,255,255,.6);
  text-decoration:none; border-radius:0; font-weight:500;
  transition:all .2s; border-left:3px solid transparent;
  margin:1px 0;
}
.sidebar-link:hover, .sidebar-link.active {
  background:rgba(5,150,105,.2); color:white;
  border-left-color:var(--emerald);
}
.sidebar-link i { font-size:1.1rem; width:20px; text-align:center; }
.sidebar-footer {
  margin-top:auto; padding:1rem 1.5rem;
  border-top:1px solid rgba(255,255,255,.08);
}

/* ── MAIN CONTENT ── */
.main-content { margin-left:var(--sidebar-w); min-height:100vh; display:flex; flex-direction:column; }
.topbar {
  background:white; border-bottom:1px solid var(--gray-200);
  padding:.85rem 2rem; display:flex; align-items:center; justify-content:space-between;
  position:sticky; top:0; z-index:50;
}
.topbar-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:var(--gray-900); font-size:1.15rem; }
.admin-avatar {
  width:36px; height:36px; border-radius:50%;
  background:var(--emerald); color:white;
  display:inline-flex; align-items:center; justify-content:center;
  font-weight:700; font-size:.9rem;
}
.content-area { padding:2rem; flex:1; }

/* ── STAT CARDS ── */
.stat-card {
  background:white; border-radius:16px; border:1px solid var(--gray-200);
  padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.04);
  display:flex; align-items:center; gap:1rem; transition:transform .2s, box-shadow .2s;
}
.stat-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.08); }
.stat-icon {
  width:52px; height:52px; border-radius:14px;
  display:flex; align-items:center; justify-content:center; font-size:1.4rem;
  flex-shrink:0;
}
.stat-num { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:1.8rem; color:var(--gray-900); line-height:1; }
.stat-lbl { font-size:.82rem; color:var(--gray-500); margin-top:.2rem; }

/* ── CHART CARD ── */
.chart-card {
  background:white; border-radius:16px; border:1px solid var(--gray-200);
  box-shadow:0 2px 8px rgba(0,0,0,.04); padding:1.5rem;
}
.chart-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:var(--gray-900); font-size:1rem; }

/* ── TABLE ── */
.table-card {
  background:white; border-radius:16px; border:1px solid var(--gray-200);
  box-shadow:0 2px 8px rgba(0,0,0,.04); overflow:hidden;
}
.table-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--gray-100); }
.table thead th {
  background:var(--gray-50); font-size:.78rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em; color:var(--gray-500);
  border-bottom:1px solid var(--gray-200); padding:.75rem 1rem;
}
.table tbody td { padding:.85rem 1rem; vertical-align:middle; border-color:var(--gray-100); }
.table tbody tr:hover { background:var(--emerald-pale); }

.kode-badge {
  font-family:monospace; font-size:.82rem; font-weight:700;
  background:var(--gray-100); padding:.2rem .6rem; border-radius:6px;
  color:var(--gray-700);
}
.foto-thumb {
  width:48px; height:48px; border-radius:8px; object-fit:cover;
  border:2px solid var(--gray-200); cursor:pointer;
  transition:transform .2s;
}
.foto-thumb:hover { transform:scale(1.1); }

/* ── FORM & BTN ── */
.form-control, .form-select {
  border-color:var(--gray-200); border-radius:8px;
  font-family:'DM Sans',sans-serif;
}
.form-control:focus, .form-select:focus {
  border-color:var(--emerald); box-shadow:0 0 0 3px rgba(5,150,105,.12);
}
.btn-emerald { background:var(--emerald); color:white; border:none; border-radius:8px; padding:.5rem 1.25rem; font-weight:600; }
.btn-emerald:hover { background:var(--emerald-dark); color:white; }

/* ── RESPONSIVE ── */
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.show { transform:translateX(0); }
  .main-content { margin-left:0; }
}
</style>
</head>
<body>

<!-- ============================================================
     SIDEBAR
     ============================================================ -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo"><i class="bi bi-recycle"></i></div>
    <div class="brand-name"><?= APP_NAME ?></div>
    <div class="brand-sub">Panel Administrasi</div>
  </div>

  <div class="nav-section-label">Menu Utama</div>
  <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-link active">
    <i class="bi bi-speedometer2"></i> Dashboard
  </a>
  <a href="<?= APP_URL ?>/admin/dashboard.php?status=Pending" class="sidebar-link">
    <i class="bi bi-hourglass"></i> Laporan Pending
    <?php if ($stats['pending'] > 0): ?>
      <span class="badge bg-warning text-dark ms-auto"><?= $stats['pending'] ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= APP_URL ?>/admin/dashboard.php?status=Diproses" class="sidebar-link">
    <i class="bi bi-gear"></i> Sedang Diproses
  </a>
  <a href="<?= APP_URL ?>/admin/dashboard.php?status=Selesai" class="sidebar-link">
    <i class="bi bi-check-circle"></i> Selesai
  </a>

  <div class="nav-section-label">Akun</div>
  <a href="<?= APP_URL ?>" class="sidebar-link" target="_blank">
    <i class="bi bi-house"></i> Lihat Situs Publik
  </a>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2 mb-2">
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_nama'], 0, 1)) ?></div>
      <div>
        <div style="color:white;font-weight:600;font-size:.9rem"><?= e($_SESSION['admin_nama']) ?></div>
        <div style="color:rgba(255,255,255,.4);font-size:.75rem">Administrator</div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/admin/logout.php" class="sidebar-link" style="padding:.5rem .75rem;border-radius:8px;margin:0">
      <i class="bi bi-box-arrow-left"></i> Keluar
    </a>
  </div>
</div>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<div class="main-content">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-outline-secondary d-md-none border-0" onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="bi bi-list fs-5"></i>
      </button>
      <div class="topbar-title">Dashboard</div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted small d-none d-md-inline">
        <i class="bi bi-calendar3 me-1"></i><?= date('d F Y') ?>
      </span>
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_nama'], 0, 1)) ?></div>
    </div>
  </div>

  <div class="content-area">

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-collection"></i></div>
          <div><div class="stat-num"><?= number_format($stats['total']) ?></div><div class="stat-lbl">Total Laporan</div></div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:#fefce8;color:#ca8a04"><i class="bi bi-hourglass-split"></i></div>
          <div><div class="stat-num"><?= number_format($stats['pending']) ?></div><div class="stat-lbl">Pending</div></div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:#eff6ff;color:#2563eb"><i class="bi bi-gear-wide-connected"></i></div>
          <div><div class="stat-num"><?= number_format($stats['diproses']) ?></div><div class="stat-lbl">Diproses</div></div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:var(--emerald-pale);color:var(--emerald)"><i class="bi bi-check-circle-fill"></i></div>
          <div><div class="stat-num"><?= number_format($stats['selesai']) ?></div><div class="stat-lbl">Selesai</div></div>
        </div>
      </div>
    </div>

    <!-- ── CHARTS ── -->
    <div class="row g-3 mb-4">
      <!-- Tren Laporan -->
      <div class="col-lg-8">
        <div class="chart-card h-100">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="chart-title"><i class="bi bi-bar-chart-line me-2 text-success"></i>Tren Pelaporan (12 Bulan Terakhir)</div>
          </div>
          <canvas id="trendChart" height="100"></canvas>
        </div>
      </div>

      <!-- Distribusi Status -->
      <div class="col-lg-4">
        <div class="chart-card h-100">
          <div class="chart-title mb-3"><i class="bi bi-pie-chart me-2 text-success"></i>Distribusi Status</div>
          <canvas id="statusChart" height="220"></canvas>
          <div class="mt-3">
            <?php foreach ($statusChart as $label => $val): ?>
            <?php $pct = $stats['total'] > 0 ? round($val / $stats['total'] * 100) : 0; ?>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <small class="text-muted"><?= e($label) ?></small>
              <span class="fw-bold small"><?= $val ?> <span class="text-muted">(<?= $pct ?>%)</span></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TABEL LAPORAN ── -->
    <div class="table-card">
      <div class="table-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-bold" style="font-family:'Plus Jakarta Sans',sans-serif">
          <i class="bi bi-list-ul me-2 text-success"></i>Daftar Laporan
        </h6>
        <!-- Filter Form -->
        <form method="GET" class="d-flex gap-2 flex-wrap">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari kode/deskripsi…"
            value="<?= e($search) ?>" style="width:200px">
          <select name="status" class="form-select form-select-sm" style="width:140px">
            <option value="">Semua Status</option>
            <?php foreach (['Pending','Diproses','Selesai'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-emerald">Filter</button>
          <?php if ($search || $filter): ?>
          <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Kode Laporan</th>
              <th>Pelapor</th>
              <th>Deskripsi</th>
              <th>Lokasi GPS</th>
              <th>Foto</th>
              <th>Status</th>
              <th>Tanggal</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($laporan)): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada laporan ditemukan.</td></tr>
          <?php else: ?>
          <?php foreach ($laporan as $row): ?>
          <tr>
            <td><span class="kode-badge"><?= e($row['kode_laporan']) ?></span></td>
            <td>
              <div class="fw-600"><?= e($row['nama_user'] ?? $row['nama_pelapor'] ?? 'Anonim') ?></div>
              <?php if ($row['nama_user']): ?>
                <small class="text-success"><i class="bi bi-person-check-fill"></i> Terregistrasi</small>
              <?php else: ?>
                <small class="text-muted"><i class="bi bi-incognito"></i> Anonim</small>
              <?php endif; ?>
            </td>
            <td style="max-width:200px">
              <div class="text-truncate" title="<?= e($row['deskripsi']) ?>" style="max-width:200px">
                <?= e($row['deskripsi']) ?>
              </div>
            </td>
            <td>
              <a href="https://www.google.com/maps?q=<?= urlencode($row['latitude'] . ',' . $row['longitude']) ?>"
                 target="_blank" class="btn btn-sm" style="background:var(--emerald-pale);color:var(--emerald);border:1px solid var(--emerald-light);border-radius:8px;font-size:.78rem;font-weight:600">
                <i class="bi bi-geo-alt-fill me-1"></i>Maps
              </a>
              <div class="text-muted" style="font-size:.72rem;font-family:monospace">
                <?= e(number_format($row['latitude'], 5)) ?>,
                <?= e(number_format($row['longitude'], 5)) ?>
              </div>
            </td>
            <td>
              <img src="<?= APP_URL ?>/uploads/<?= e($row['foto']) ?>"
                   class="foto-thumb"
                   alt="Foto"
                   onclick="lihatFoto('<?= APP_URL ?>/uploads/<?= e($row['foto']) ?>', '<?= e($row['kode_laporan']) ?>')"
                   onerror="this.src='https://via.placeholder.com/48?text=?'">
            </td>
            <td><?= statusBadge($row['status']) ?></td>
            <td>
              <div style="font-size:.82rem"><?= formatTanggal($row['created_at']) ?></div>
            </td>
            <td>
              <button class="btn btn-sm btn-emerald"
                onclick="ubahStatus(<?= $row['id'] ?>, '<?= e($row['status']) ?>', '<?= e($row['kode_laporan']) ?>')">
                <i class="bi bi-pencil-fill me-1"></i>Update
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
      <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
          Menampilkan <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> dari <?= $totalRows ?> laporan
        </small>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($filter) ?>">
                <?= $p ?>
              </a>
            </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /content-area -->
</div><!-- /main-content -->

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
// ============================================================
//  CHART 1: Tren Laporan per Bulan (Bar Chart)
// ============================================================
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Jumlah Laporan',
      data: <?= json_encode($chartValues) ?>,
      backgroundColor: 'rgba(5,150,105,0.15)',
      borderColor: '#059669',
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
      hoverBackgroundColor: 'rgba(5,150,105,0.35)',
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0f2417',
        titleColor: '#6ee7b7',
        bodyColor: '#fff',
        padding: 12,
        callbacks: {
          label: ctx => ` ${ctx.parsed.y} laporan`
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { stepSize: 1, color: '#64748b' },
        grid: { color: '#f1f5f9' }
      },
      x: {
        ticks: { color: '#64748b', font: { size: 11 } },
        grid: { display: false }
      }
    }
  }
});

// ============================================================
//  CHART 2: Distribusi Status (Donut Chart)
// ============================================================
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
  type: 'doughnut',
  data: {
    labels: ['Pending', 'Diproses', 'Selesai'],
    datasets: [{
      data: [
        <?= $statusChart['Pending'] ?>,
        <?= $statusChart['Diproses'] ?>,
        <?= $statusChart['Selesai'] ?>
      ],
      backgroundColor: ['#fbbf24', '#3b82f6', '#059669'],
      borderColor:     ['#f59e0b', '#2563eb', '#047857'],
      borderWidth: 2,
      hoverOffset: 8
    }]
  },
  options: {
    responsive: true,
    cutout: '70%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: { padding: 16, font: { size: 12 }, color: '#334155' }
      },
      tooltip: {
        backgroundColor: '#0f2417',
        titleColor: '#6ee7b7',
        bodyColor: '#fff',
        padding: 12,
      }
    }
  }
});

// ============================================================
//  AKSI: Lihat Foto
// ============================================================
function lihatFoto(url, kode) {
  Swal.fire({
    title: kode,
    imageUrl: url,
    imageAlt: 'Foto Laporan',
    imageWidth: '100%',
    showCloseButton: true,
    confirmButtonText: 'Tutup',
    confirmButtonColor: '#059669',
  });
}

// ============================================================
//  AKSI: Ubah Status Laporan
// ============================================================
function ubahStatus(id, statusSaat, kode) {
  Swal.fire({
    title: 'Update Status Laporan',
    html: `
      <p class="text-muted mb-3">Kode: <strong>${kode}</strong></p>
      <div class="mb-3 text-start">
        <label class="form-label fw-bold">Status Baru</label>
        <select class="form-select" id="newStatus">
          <option value="Pending"  ${statusSaat==='Pending'  ? 'selected':''}>⏳ Pending</option>
          <option value="Diproses" ${statusSaat==='Diproses' ? 'selected':''}>⚙️ Diproses</option>
          <option value="Selesai"  ${statusSaat==='Selesai'  ? 'selected':''}>✅ Selesai</option>
        </select>
      </div>
      <div class="text-start">
        <label class="form-label fw-bold">Catatan Petugas <span class="text-muted fw-normal">(Opsional)</span></label>
        <textarea class="form-control" id="catatanAdmin" rows="3" placeholder="Tindakan yang telah diambil…"></textarea>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: '<i class="bi bi-save me-1"></i>Simpan',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#059669',
    cancelButtonColor: '#64748b',
    preConfirm: () => {
      return {
        id:       id,
        status:   document.getElementById('newStatus').value,
        catatan:  document.getElementById('catatanAdmin').value,
      };
    }
  }).then(result => {
    if (result.isConfirmed) {
      // Kirim via fetch ke update-status.php
      fetch('<?= APP_URL ?>/admin/update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          id:      result.value.id,
          status:  result.value.status,
          catatan: result.value.catatan,
          token:   '<?= e($_SESSION['csrf_token'] ?? '') ?>'
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          Swal.fire({ icon:'success', title:'Status Diperbarui!', text: data.message, confirmButtonColor:'#059669', timer:2000, showConfirmButton:false })
          .then(() => location.reload());
        } else {
          Swal.fire({ icon:'error', title:'Gagal', text: data.message, confirmButtonColor:'#059669' });
        }
      })
      .catch(() => Swal.fire({ icon:'error', title:'Koneksi Error', confirmButtonColor:'#059669' }));
    }
  });
}

// Flash dari PHP
<?php if ($flash): ?>
Swal.fire({
  icon: '<?= e($flash['type']) ?>',
  title: '<?= addslashes(e($flash['msg'])) ?>',
  confirmButtonColor: '#059669',
  timer: 3000
});
<?php endif; ?>
</script>
</body>
</html>
