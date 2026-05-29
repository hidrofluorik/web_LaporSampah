<?php
// ============================================================
//  admin/login.php — Otentikasi Admin (Solusi Bypass Khusus)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (isAdminLoggedIn()) {
    redirect(APP_URL . '/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; 

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT id, nama, password FROM admin WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $error = 'Email tidak terdaftar.';
        } else {
            // Kita bypass khusus untuk input password yang kamu mau ("minminmin")
            // Atau kalau suatu saat servernya bener, password_verify asli tetap bisa jalan
            if ($password === 'minminmin' || password_verify($password, $admin['password'])) {
                
                session_regenerate_id(true);

                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_nama'] = $admin['nama'];

                setFlash('success', 'Selamat Datang Kembali!');
                redirect(APP_URL . '/admin/dashboard.php');
            } else {
                $error = 'Password yang Anda masukkan salah.';
                sleep(1);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<style>
:root {
  --emerald:#059669; --emerald-dark:#047857;
  --gray-50:#f8fafc; --gray-200:#e2e8f0; --gray-700:#334155; --gray-900:#0f172a;
}
body {
  font-family:'DM Sans',sans-serif;
  background:linear-gradient(135deg,#064e3b 0%,#047857 100%);
  min-height:100vh; display:flex; align-items:center; justify-content:center;
}
.login-card {
  background:white; border-radius:24px; padding:2.5rem; width:100%; max-width:440px;
  box-shadow:0 25px 60px rgba(0,0,0,.25);
}
.brand-logo {
  width:64px; height:64px; border-radius:16px; background:linear-gradient(135deg,#059669,#047857);
  display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:white; margin:0 auto 1.25rem;
}
.login-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; color:var(--gray-900); }
.form-label { font-weight:600; color:var(--gray-700); margin-bottom:.4rem; }
.form-control { border-color:var(--gray-200); border-radius:10px; padding:.7rem 1rem; }
.form-control:focus { border-color:var(--emerald); box-shadow:0 0 0 3px rgba(5,150,105,.15); }
.input-group-text { background:var(--gray-50); border-color:var(--gray-200); border-radius:0 10px 10px 0; cursor:pointer; }
.input-group .form-control { border-radius:10px 0 0 10px; border-right:none; }
.btn-login {
  background:linear-gradient(135deg,var(--emerald),var(--emerald-dark)); color:white; border:none; width:100%;
  padding:.85rem; border-radius:12px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif;
}
</style>
</head>
<body>

<div class="login-card">
  <div class="brand-logo"><i class="bi bi-recycle"></i></div>
  <h3 class="login-title text-center mb-1">Admin Panel</h3>
  <p class="text-center text-muted mb-4" style="font-size:.9rem"><?= APP_NAME ?> — Panel Kontrol</p>

  <form method="POST" id="loginForm" novalidate>
    <div class="mb-3">
      <label class="form-label"><i class="bi bi-envelope me-1"></i>Email Administrator</label>
      <input type="email" name="email" class="form-control" placeholder="admin@domain.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>

    <div class="mb-4">
      <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
      <div class="input-group">
        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
        <span class="input-group-text" onclick="togglePassword()">
          <i class="bi bi-eye" id="eye-icon"></i>
        </span>
      </div>
    </div>

    <button type="submit" class="btn-login" id="btnLogin">
      <i class="bi bi-shield-lock-fill me-2"></i>Masuk ke Panel Admin
    </button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
function togglePassword() {
  const input = document.getElementById('passwordInput');
  const icon  = document.getElementById('eye-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('btnLogin');
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memverifikasi…';
  btn.style.pointerEvents = 'none';
  btn.style.opacity = '0.7';
});

<?php if ($error): ?>
Swal.fire({ icon: 'error', title: 'Login Gagal', text: '<?= addslashes(htmlspecialchars($error)) ?>', confirmButtonColor: '#059669' });
<?php endif; ?>
</script>
</body>
</html>