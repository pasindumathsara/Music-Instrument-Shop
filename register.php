<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Already logged in
if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/home.php"); exit();
}

$error = '';
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';
    $confirm  = $_POST['confirm']        ?? '';

    // Validate
    if (!$name || !$email || !$password) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email exists
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "That email is already registered. <a href='" . BASE_URL . "/login.php' style='color:var(--accent)'>Login instead?</a>";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = "customer";

            $ins = $conn->prepare(
                "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
            );
            $ins->bind_param("ssss", $name, $email, $hash, $role);

            if ($ins->execute()) {
                // Auto-login after register
                $newId = $conn->insert_id;
                $_SESSION['user_id']   = $newId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = 'customer';
                setFlash('success', "Welcome to Melody Masters, $name! 🎵");
                header("Location: " . BASE_URL . "/home.php"); exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Create Account – Melody Masters</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
  <style>
    body { background:linear-gradient(135deg,#0f172a 0%,#1a0533 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
    .auth-card {
      width:920px; max-width:100%; min-height:560px; background:#fff;
      border-radius:24px; overflow:hidden; display:flex; box-shadow:0 30px 80px rgba(0,0,0,.5);
    }
    .auth-left {
      width:40%; background:url('<?php echo BASE_URL; ?>/assets/images/bg2.jpg') center/cover no-repeat;
      position:relative; display:flex; flex-direction:column; justify-content:space-between; padding:36px;
    }
    .auth-left::after { content:''; position:absolute; inset:0; background:rgba(15,23,42,.65); }
    .auth-left-content { position:relative; z-index:2; }
    .auth-left h2 { color:#fff; font-size:1.4rem; font-weight:800; margin-bottom:8px; }
    .auth-left p  { color:rgba(255,255,255,.75); font-size:.85rem; line-height:1.7; }
    .auth-perks { position:relative; z-index:2; display:flex; flex-direction:column; gap:10px; }
    .perk { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,.85); font-size:.83rem; }
    .perk span { font-size:1.2rem; }
    .auth-right { flex:1; padding:44px; display:flex; flex-direction:column; justify-content:center; }
    .auth-right h1 { font-size:1.7rem; font-weight:800; margin-bottom:4px; }
    .auth-right .sub { color:var(--muted); font-size:.875rem; margin-bottom:24px; }
    .pwd-strength { height:4px; border-radius:4px; margin-top:6px; transition:all .3s; background:#e2e8f0; }
    @media(max-width:680px){
      .auth-card { flex-direction:column; }
      .auth-left { width:100%; min-height:160px; }
      .auth-right { padding:28px 20px; }
    }
  </style>
</head>
<body>

<div class="auth-card">
  <!-- Left Panel -->
  <div class="auth-left">
    <div class="auth-left-content">
      <div style="color:#fff;font-size:1.1rem;font-weight:800;margin-bottom:12px;">
        🎵 Melody<span style="color:var(--accent);">Masters</span>
      </div>
      <h2>Join the Community</h2>
      <p>Thousands of musicians trust Melody Masters for their gear.</p>
    </div>
    <div class="auth-perks">
      <div class="perk"><span>🎸</span> Browse 100+ instruments</div>
      <div class="perk"><span>🚚</span> Free shipping over $100</div>
      <div class="perk"><span>⭐</span> Leave reviews after purchase</div>
      <div class="perk"><span>📦</span> Track all your orders</div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="auth-right">
    <div style="font-size:.8rem;color:var(--muted);margin-bottom:18px;">
      <a href="<?php echo BASE_URL; ?>/home.php" style="color:var(--accent);">← Back to Shop</a>
    </div>
    <h1>Create Account</h1>
    <p class="sub">It's free and takes less than a minute</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo $error; /* may contain HTML link */ ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control"
               placeholder="John Doe"
               value="<?php echo sanitize($name); ?>" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control"
               placeholder="you@example.com"
               value="<?php echo sanitize($email); ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" id="pwdInput" class="form-control"
                 placeholder="Min. 6 characters" required
                 oninput="checkStrength(this.value)">
          <div class="pwd-strength" id="strengthBar"></div>
          <div id="strengthLabel" style="font-size:.72rem;color:var(--muted);margin-top:3px;"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" id="confirmInput" class="form-control"
                 placeholder="Repeat password" required
                 oninput="checkMatch()">
          <div id="matchLabel" style="font-size:.72rem;margin-top:3px;"></div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:4px;">
        Create My Account →
      </button>
    </form>

    <div style="text-align:center;margin-top:20px;font-size:.875rem;color:var(--muted);">
      Already have an account?
      <a href="<?php echo BASE_URL; ?>/login.php" style="color:var(--accent);font-weight:700;">Sign in</a>
    </div>
  </div>
</div>

<script>
function checkStrength(val) {
  const bar = document.getElementById('strengthBar');
  const lbl = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 6) score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const colors = ['#ef4444','#f59e0b','#f59e0b','#10b981','#10b981'];
  const labels = ['Too short','Weak','Fair','Strong','Very Strong'];
  bar.style.background = colors[Math.max(0,score-1)] || '#e2e8f0';
  bar.style.width = (score * 20) + '%';
  bar.style.maxWidth = '100%';
  lbl.textContent = val.length ? labels[Math.max(0,score-1)] : '';
  lbl.style.color = colors[Math.max(0,score-1)] || '#94a3b8';
}
function checkMatch() {
  const p = document.getElementById('pwdInput').value;
  const c = document.getElementById('confirmInput').value;
  const lbl = document.getElementById('matchLabel');
  if (!c) { lbl.textContent = ''; return; }
  lbl.textContent = p === c ? '✓ Passwords match' : '✗ Passwords do not match';
  lbl.style.color  = p === c ? 'var(--success)' : 'var(--danger)';
}
</script>
</body>
</html>