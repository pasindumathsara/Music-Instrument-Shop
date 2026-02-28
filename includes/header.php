<?php
// header.php – shared navbar (include AFTER includes/db.php & functions.php)
$cartCount = getCartCount();
$flash     = getFlash();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – Melody Masters' : 'Melody Masters – Music Instrument Shop'; ?></title>
  <meta name="description" content="<?php echo isset($pageDesc) ? htmlspecialchars($pageDesc) : 'Shop the finest musical instruments at Melody Masters. Guitars, pianos, drums and more.'; ?>">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-wrap">
    <!-- Logo -->
    <a href="<?php echo BASE_URL; ?>/home.php" class="nav-logo">
       Melody<span>Masters</span>
    </a>

    <!-- Desktop links -->
    <div class="nav-links" id="navLinks">
      
      <?php if (isLoggedIn()): ?>
        
        <?php if (isAdmin()): ?>
          
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="nav-actions">
      <?php if (isLoggedIn()): ?>
        <!-- Cart -->
        <a href="<?php echo BASE_URL; ?>/cart.php" class="cart-btn">
        🛒 Cart
          <?php if ($cartCount > 0): ?>
            <span class="cart-badge"><?php echo $cartCount; ?></span>
          <?php endif; ?>
        </a>

        <!-- User dropdown -->
        <div class="user-menu">
          <button class="user-btn">
            <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></span>
            <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?> ▾
          </button>
          <div class="dropdown">
            <a href="<?php echo BASE_URL; ?>/account.php"> My Account</a>
            <a href="<?php echo BASE_URL; ?>/orders.php"> My Orders</a>
            <?php if (isAdmin()): ?>
              <hr>
              <a href="<?php echo BASE_URL; ?>/admin/dashboard.php"> Admin Panel</a>
            <?php endif; ?>
            <hr>
            <form method="POST" action="<?php echo BASE_URL; ?>/logout.php">
              <button type="submit"> Logout</button>
            </form>
          </div>
        </div>

      <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/login.php"    class="btn-nav btn-nav-ghost">Login</a>
        <a href="<?php echo BASE_URL; ?>/register.php" class="btn-nav btn-nav-primary">Sign Up</a>
      <?php endif; ?>

      <button class="hamburger" id="hamburger" aria-label="Open menu">☰</button>
    </div>
  </div>
</nav>

<!-- Flash Toast Notification -->
<?php if ($flash): ?>
<?php
  $icons = ['success'=>'✅', 'danger'=>'❌', 'info'=>'ℹ️', 'warning'=>'⚠️'];
  $icon  = $icons[$flash['type']] ?? 'ℹ️';
?>
<style>
.flash-toast {
  position: fixed;
  top: 72px;          /* just below navbar */
  right: 20px;
  z-index: 99999;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 14px;
  font-size: .88rem;
  font-weight: 600;
  max-width: 380px;
  min-width: 260px;
  box-shadow: 0 12px 40px rgba(0,0,0,.35);
  animation: ft-in .35s cubic-bezier(.21,1.02,.73,1) forwards;
  pointer-events: auto;
  line-height: 1.4;
}
@keyframes ft-in {
  from { transform: translateX(calc(100% + 20px)); opacity:0; }
  to   { transform: translateX(0);                 opacity:1; }
}
@keyframes ft-out {
  from { transform: translateX(0);                 opacity:1; }
  to   { transform: translateX(calc(100% + 20px)); opacity:0; }
}
.flash-toast.removing { animation: ft-out .35s ease forwards; }
.flash-success { background: rgba(5,150,105,.97);  color:#fff; border-left:4px solid #34d399; }
.flash-danger  { background: rgba(220,38,38,.97);  color:#fff; border-left:4px solid #fca5a5; }
.flash-info    { background: rgba(37,99,235,.97);  color:#fff; border-left:4px solid #93c5fd; }
.flash-warning { background: rgba(217,119,6,.97);  color:#fff; border-left:4px solid #fcd34d; }
.flash-icon { font-size:1.15rem; flex-shrink:0; }
.flash-msg  { flex:1; }
.flash-close {
  background:none; border:none; cursor:pointer;
  color:rgba(255,255,255,.7); font-size:1.3rem; line-height:1;
  padding:0 0 0 4px; flex-shrink:0; transition:color .15s;
}
.flash-close:hover { color:#fff; }
</style>
<div class="flash-toast flash-<?php echo htmlspecialchars($flash['type']); ?>" id="flashToast">
  <span class="flash-icon"><?php echo $icon; ?></span>
  <span class="flash-msg"><?php echo htmlspecialchars($flash['message']); ?></span>
  <button class="flash-close" onclick="dismissToast()" aria-label="Close">×</button>
</div>
<script>
function dismissToast() {
  const t = document.getElementById('flashToast');
  if (!t) return;
  t.classList.add('removing');
  setTimeout(() => t.remove(), 350);
}
// Auto-dismiss after 4.5 s
setTimeout(dismissToast, 4500);
</script>
<?php endif; ?>

<main>
<script>
// ── User dropdown: click to open, click-outside to close ──────
(function() {
  const menu = document.querySelector('.user-menu');
  const btn  = menu?.querySelector('.user-btn');
  if (!menu || !btn) return;

  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    menu.classList.toggle('open');
  });

  // Close when clicking anywhere outside
  document.addEventListener('click', function(e) {
    if (!menu.contains(e.target)) {
      menu.classList.remove('open');
    }
  });

  // Close when a dropdown link is clicked
  menu.querySelectorAll('.dropdown a, .dropdown button[type="submit"]').forEach(el => {
    el.addEventListener('click', () => menu.classList.remove('open'));
  });
})();

// ── Hamburger: mobile nav toggle ──────────────────────────────
(function() {
  const ham   = document.getElementById('hamburger');
  const links = document.getElementById('navLinks');
  if (!ham || !links) return;
  ham.addEventListener('click', function() {
    links.classList.toggle('show');
    ham.textContent = links.classList.contains('show') ? '✕' : '☰';
  });
})();
</script>
