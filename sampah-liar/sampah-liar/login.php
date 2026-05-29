<?php
// ============================================================
//  login.php — Login Warga Terregistrasi
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

if (isUserLoggedIn()) redirect(APP_URL);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (!empty($email) && !empty($password)) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, nama, password FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_nama'] = $user['nama'];
            redirect(APP_URL);
        } else {
            $error = 'Email atau password tidak valid.';
            sleep(1);
        }
    } else {
        $error = 'Isi email dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
:root { --emerald:#059669; --emerald-dark:#047857; }
body { font-family:'DM Sans',sans-serif; background:linear-gradient(135deg,#064e3b,#047857); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.card { border-radius:24px; border:none; box-shadow:0 25px 60px rgba(0,0,0,.25); padding:2.5rem; max-width:420px; width:100%; }
.brand-logo { width:56px; height:56px; border-radius:14px; background:var(--emerald); display:flex; align-items:center; justify-content:center; color:white; font-size:1.6rem; margin:0 auto 1rem; }
.form-control { border-color:#e2e8f0; border-radius:10px; }
.form-control:focus { border-color:var(--emerald); box-shadow:0 0 0 3px rgba(5,150,105,.12); }
.input-group-text { background:#f8fafc; border-color:#e2e8f0; border-radius:0 10px 10px 0; cursor:pointer; }
.input-group .form-control { border-radius:10px 0 0 10px; border-right:none; }
.btn-login { background:var(--emerald); color:white; border:none; width:100%; padding:.85rem; border-radius:12px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; }
.btn-login:hover { background:var(--emerald-dark); color:white; }
</style>
</head>
<body>
<div class="card">
  <div class="brand-logo"><i class="bi bi-recycle"></i></div>
  <h4 class="text-center fw-800 mb-1" style="font-family:'Plus Jakarta Sans',sans-serif">Masuk Akun</h4>
  <p class="text-center text-muted mb-4 small"><?= APP_NAME ?></p>
  <form method="POST" novalidate>
    <div class="mb-3">
      <label class="form-label fw-600">Email</label>
      <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username" required>
    </div>
    <div class="mb-4">
      <label class="form-label fw-600">Password</label>
      <div class="input-group">
        <input type="password" name="password" id="pw" class="form-control" autocomplete="current-password" required>
        <span class="input-group-text" onclick="this.previousElementSibling.type=this.previousElementSibling.type==='password'?'text':'password'">
          <i class="bi bi-eye"></i>
        </span>
      </div>
    </div>
    <button type="submit" class="btn-login">Masuk</button>
  </form>
  <div class="text-center mt-3 d-flex justify-content-between">
    <a href="<?= APP_URL ?>" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    <a href="<?= APP_URL ?>/register.php" class="text-success small fw-600">Belum punya akun? Daftar</a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
<?php if ($error): ?>
Swal.fire({ icon:'error', title:'Login Gagal', text:'<?= addslashes(e($error)) ?>', confirmButtonColor:'#059669' });
<?php endif; ?>
</script>
</body>
</html>
