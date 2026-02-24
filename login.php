<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Already logged in → go home
if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/home.php"); exit();
}

$error    = '';
// Read redirect from POST (failed login retry) or GET (initial redirect from cart/checkout)
$redirect = trim($_POST['redirect'] ?? $_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (!$email || !$password) {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Set all session data including role
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect
            if ($redirect && strpos($redirect, BASE_URL) === 0) {
                header("Location: $redirect");
            } elseif ($user['role'] === 'admin') {
                header("Location: " . BASE_URL . "/admin/dashboard.php");
            } else {
                header("Location: " . BASE_URL . "/home.php");
            }
            exit();
        } else {
            $error = "Invalid email or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Login – Melody Masters</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
  <style>
    body { background:linear-gradient(135deg,#0f172a 0%,#1a0533 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
    .auth-card {
      width:900px; max-width:100%; min-height:520px; background:#fff;
      border-radius:24px; overflow:hidden; display:flex; box-shadow:0 30px 80px rgba(0,0,0,.5);
    }
    .auth-left {
      width:45%; background:url('<?php echo BASE_URL; ?>/assets/images/bg1.jpg') center/cover no-repeat;
      position:relative; display:flex; flex-direction:column; justify-content:space-between; padding:36px;
    }
    .auth-left::after { content:''; position:absolute; inset:0; background:rgba(0,0,0,.55); }
    .auth-left-content { position:relative; z-index:2; }
    .auth-left h2 { color:#fff; font-size:1.5rem; font-weight:800; margin-bottom:8px; }
    .auth-left p  { color:rgba(255,255,255,.75); font-size:.875rem; line-height:1.6; }
    .auth-brand { position:relative; z-index:2; }
    .auth-brand .logo { color:#fff; font-size:1.2rem; font-weight:800; }
    .auth-brand .logo span { color:var(--accent); }
    .auth-brand small { color:rgba(255,255,255,.6); font-size:.78rem; }
    .auth-right { flex:1; padding:48px 44px; display:flex; flex-direction:column; justify-content:center; }
    .auth-right h1 { font-size:1.8rem; font-weight:800; margin-bottom:4px; }
    .auth-right .sub { color:var(--muted); font-size:.875rem; margin-bottom:28px; }
    @media(max-width:680px) {
      .auth-card { flex-direction:column; }
      .auth-left  { width:100%; min-height:180px; }
      .auth-right { padding:32px 24px; }
    }
  </style>
</head>
<body>

<div class="auth-card">
  <!-- Left Panel -->
  <div class="auth-left">
    <div class="auth-left-content">
      <h2>Find Your Sound 🎵</h2>
      <p>Explore guitars, keyboards, drums, and more from the world's top instrument brands.</p>
    </div>
    <div class="auth-brand">
      <div class="logo">Melody<span>Masters</span></div>
      <small>Trusted by 10,000+ musicians since 2010</small>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="auth-right">
    <div style="font-size:.8rem;color:var(--muted);margin-bottom:20px;">
      <a href="<?php echo BASE_URL; ?>/home.php" style="color:var(--accent);">← Back to Shop</a>
    </div>
    <h1>Welcome Back</h1>
    <p class="sub">Sign in to your Melody Masters account</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?php if ($redirect): ?>
        <input type="hidden" name="redirect" value="<?php echo sanitize($redirect); ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control"
               placeholder="you@example.com"
               value="<?php echo sanitize($_POST['email'] ?? ''); ?>"
               required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div style="position:relative;">
          <input type="password" name="password" id="pwdField" class="form-control"
                 placeholder="Your password" required
                 style="padding-right:52px;">
          <button type="button" onclick="togglePwd()"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;"
                  id="eyeBtn"></button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
        Sign In →
      </button>
    </form>

    <div style="text-align:center;margin-top:24px;font-size:.875rem;color:var(--muted);">
      Don't have an account?
      <a href="<?php echo BASE_URL; ?>/register.php" style="color:var(--accent);font-weight:700;">Create one free</a>
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const f = document.getElementById('pwdField');
  const b = document.getElementById('eyeBtn');
  if (f.type === 'password') { f.type = 'text'; b.textContent = ''; }
  else { f.type = 'password'; b.textContent = '👁'; }
}
</script>
</body>
</html>