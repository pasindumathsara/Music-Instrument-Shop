<?php
// Admin sidebar — included inside admin pages (after requireAdmin())
$adminPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? 'Admin – '.htmlspecialchars($pageTitle).' | Melody Masters' : 'Admin | Melody Masters'; ?></title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar">
  <div class="nav-wrap">
    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="nav-logo">
       Melody<span>Admin</span>
    </a>
    <div class="nav-actions">
      <a href="<?php echo BASE_URL; ?>/home.php" class="btn-nav btn-nav-ghost" style="color:rgba(255,255,255,.7);">← Storefront</a>
      <div class="user-menu">
        <button class="user-btn">
          <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?></span>
          <?php echo sanitize(explode(' ',$_SESSION['user_name'])[0]); ?> ▾
        </button>
        <div class="dropdown">
          <a href="<?php echo BASE_URL; ?>/account.php"> My Account</a>
          <hr>
          <form method="POST" action="<?php echo BASE_URL; ?>/logout.php">
            <button type="submit"> Logout</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
// ── Admin user dropdown: click-toggle ────────────────────────
(function() {
  const menu = document.querySelector('.user-menu');
  const btn  = menu?.querySelector('.user-btn');
  if (!menu || !btn) return;

  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    menu.classList.toggle('open');
  });

  document.addEventListener('click', function(e) {
    if (!menu.contains(e.target)) menu.classList.remove('open');
  });

  menu.querySelectorAll('.dropdown a, .dropdown button[type="submit"]').forEach(el => {
    el.addEventListener('click', () => menu.classList.remove('open'));
  });
})();
</script>

<div class="admin-wrap">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo"> Melody<span>Masters</span></div>
    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php"
       class="<?php echo $adminPage==='dashboard.php'?'active':''; ?>">
      <span class="icon"></span> Dashboard
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/manage_products.php"
       class="<?php echo $adminPage==='manage_products.php'?'active':''; ?>">
      <span class="icon"></span> Products
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/manage_orders.php"
       class="<?php echo $adminPage==='manage_orders.php'?'active':''; ?>">
      <span class="icon"></span> Orders
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/manage_users.php"
       class="<?php echo $adminPage==='manage_users.php'?'active':''; ?>">
      <span class="icon"></span> Users
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/manage_categories.php"
       class="<?php echo $adminPage==='manage_categories.php'?'active':''; ?>">
      <span class="icon"></span> Categories
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/reset_password.php"
       class="<?php echo $adminPage==='reset_password.php'?'active':''; ?>">
      <span class="icon"></span> Password Reset
    </a>
    <hr class="sidebar-sep">
    <a href="<?php echo BASE_URL; ?>/home.php">
      <span class="icon"></span> View Shop
    </a>
  </aside>

  <!-- Main Content -->
  <div class="admin-main">
    <div class="admin-content">
<?php // page content goes here ?>
