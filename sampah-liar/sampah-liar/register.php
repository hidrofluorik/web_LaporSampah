<?php
// ============================================================
//  register.php — Registrasi Warga Terregistrasi
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

if (isUserLoggedIn()) redirect(APP_URL);

$error  = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $konfirm  = $_POST['konfirmasi']    ?? '';
    $no_hp    = trim($_POST['no_hp']    ?? '');

    if (empty($nama) || empty($email) || empty($password)) {
        $error = 'Semua field bertanda * wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $konfirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $pdo = getDB();

        // Cek duplikat email
        $cek = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $cek->execute([':email' => $email]);
        if ($cek->fetch()) {
            $error = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, no_hp) VALUES (:nama, :email, :pass, :hp)");
            $stmt->execute([':nama' => $nama, ':email' => $email, ':pass' => $hash, ':hp' => $no_hp ?: null]);
            $sukses = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
:root { --emerald:#059669; --emerald-dark:#047857; }
body { font-family:'DM Sans',sans-serif; background:linear-gradient(135deg,#064e3b,#047857); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
.reg-card { background:white; border-radius:24px; padding:2.5rem; width:100%; max-width:480px; box-shadow:0 25px 60px rgba(0,0,0,.25); }
.brand-logo { width:56px; height:56px; border-radius:14px; background:var(--emerald); display:flex; align-items:center; justify-content:center; color:white; font-size:1.6rem; margin:0 auto 1rem; }
.form-label { font-weight:600; color:#334155; }
.form-control { border-color:#e2e8f0; border-radius:10px; }
.form-control:focus { border-color:var(--emerald); box-shadow:0 0 0 3px rgba(5,150,105,.12); }
.btn-reg { background:var(--emerald); color:white; border:none; width:100%; padding:.85rem; border-radius:12px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; }
.btn-reg:hover { background:var(--emerald-dark); color:white; }
</style>
</head>
<body>
<div class="reg-card">
  <div class="brand-logo"><i class="bi bi-recycle"></i></div>
  <h4 class="text-center fw-800 mb-1" style="font-family:'Plus Jakarta Sans',sans-serif">Daftar Akun Warga</h4>
  <p class="text-center text-muted mb-4 small">Registrasi agar laporan Anda terhubung ke akun</p>

  <form method="POST" novalidate>
    <div class="mb-3">
      <label class="form-label">Nama Lengkap *</label>
      <input type="text" name="nama" class="form-control" value="<?= e($_POST['nama'] ?? '') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email *</label>
      <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">No. HP</label>
      <input type="tel" name="no_hp" class="form-control" value="<?= e($_POST['no_hp'] ?? '') ?>" placeholder="Opsional">
    </div>
    <div class="mb-3">
      <label class="form-label">Password * <small class="text-muted">(min. 8 karakter)</small></label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-4">
      <label class="form-label">Konfirmasi Password *</label>
      <input type="password" name="konfirmasi" class="form-control" required>
    </div>
    <button type="submit" class="btn-reg">Buat Akun</button>
  </form>
  <div class="text-center mt-3">
    <a href="<?= APP_URL ?>/login.php" class="text-success small fw-600">Sudah punya akun? Masuk</a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
<?php if ($sukses): ?>
Swal.fire({ icon:'success', title:'Akun Berhasil Dibuat!', text:'Silakan login dengan akun Anda.', confirmButtonColor:'#059669' })
.then(() => window.location.href = '<?= APP_URL ?>/login.php');
<?php elseif ($error): ?>
Swal.fire({ icon:'error', title:'Gagal Mendaftar', text:'<?= addslashes(e($error)) ?>', confirmButtonColor:'#059669' });
<?php endif; ?>
</script>
</body>
</html>
