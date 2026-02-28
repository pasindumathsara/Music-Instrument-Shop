<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireManagement();

$pageTitle = "Dashboard";

// Stats
$totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
$totalOrders   = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$totalCustomers = $conn->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetch_row()[0];
$totalStaff     = $conn->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetch_row()[0];
$totalRevenue  = $conn->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('cancelled','pending')")->fetch_row()[0] ?? 0;
$pendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0];
$lowStock      = $conn->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5")->fetch_row()[0];

// Recent orders
$recentOrders = $conn->query(
    "SELECT o.id, u.name AS customer, o.total_amount, o.status, o.created_at
     FROM orders o JOIN users u ON u.id=o.user_id
     ORDER BY o.created_at DESC LIMIT 8"
);

// Top products by sales
$topProducts = $conn->query(
    "SELECT p.name, SUM(oi.quantity) AS sold, SUM(oi.quantity * oi.price) AS revenue
     FROM order_items oi JOIN products p ON p.id=oi.product_id
     GROUP BY oi.product_id ORDER BY sold DESC LIMIT 5"
);

require_once 'includes/admin_header.php';
?>

<div class="admin-header-bar">
  <h1 class="admin-title"> Dashboard</h1>
  <span style="font-size:.83rem;color:var(--muted);">
    <?php echo date('l, F j, Y'); ?>
  </span>
</div>

<!-- Flash -->
<?php echo showFlash(); ?>

<!-- Stats Cards -->
<div class="stats-grid">
  <?php if ($_SESSION['user_role'] === 'admin'): ?>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-val"><?php echo formatPrice((float)$totalRevenue); ?></div>
    <div class="stat-lbl">Total Revenue</div>
  </div>
  <?php endif; ?>
  <div class="stat-card green">
    <div class="stat-icon"></div>
    <div class="stat-val"><?php echo $totalOrders; ?></div>
    <div class="stat-lbl">Total Orders
      <?php if ($pendingOrders > 0): ?>
        <span class="badge badge-pending" style="margin-left:4px;"><?php echo $pendingOrders; ?> pending</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon"></div>
    <div class="stat-val"><?php echo $totalProducts; ?></div>
    <div class="stat-lbl">Products
      <?php if ($lowStock > 0): ?>
        <span class="badge badge-pending" style="margin-left:4px;"><?php echo $lowStock; ?> low stock</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($_SESSION['user_role'] === 'admin'): ?>
  <div class="stat-card yellow">
    <div class="stat-icon"></div>
    <div class="stat-val"><?php echo $totalCustomers + $totalStaff; ?></div>
    <div class="stat-lbl">Community 
      <small style="display:block;font-size:10px;margin-top:2px;">
         <?php echo $totalCustomers; ?> Customers · <?php echo $totalStaff; ?> Staff
      </small>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Two column grid -->
<div class="admin-dashboard-grid">

  <!-- Recent Orders -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Orders</span>
      <a href="<?php echo BASE_URL; ?>/admin/manage_orders.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrap" style="box-shadow:none;border-radius:0;">
      <table>
        <thead>
          <tr><th>#</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
          <?php while ($o = $recentOrders->fetch_assoc()): ?>
            <tr>
              <td><strong>#<?php echo $o['id']; ?></strong></td>
              <td><?php echo sanitize($o['customer']); ?></td>
              <td style="font-weight:700;color:var(--accent);"><?php echo formatPrice($o['total_amount']); ?></td>
              <td><?php echo statusBadge($o['status']); ?></td>
              <td style="color:var(--muted);font-size:.8rem;"><?php echo formatDate($o['created_at']); ?></td>
              <td>
                <a href="<?php echo BASE_URL; ?>/admin/manage_orders.php?id=<?php echo $o['id']; ?>"
                   class="btn btn-ghost btn-sm">Manage</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Products -->
  <div class="card">
    <div class="card-header"><span class="card-title"> Top Sellers</span></div>
    <div class="card-body" style="padding:0;">
      <?php $rank=1; while ($tp = $topProducts->fetch_assoc()): ?>
        <div class="top-seller-item">
          <span class="rank-badge"><?php echo $rank++; ?></span>
          <div class="item-info">
            <div class="item-name"><?php echo sanitize($tp['name']); ?></div>
            <div class="item-meta"><?php echo $tp['sold']; ?> sold · <?php echo formatPrice($tp['revenue']); ?></div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<!-- Quick Links -->
<div class="quick-links-grid">
  <a href="<?php echo BASE_URL; ?>/admin/manage_products.php?action=add" class="btn btn-primary">
     Add Product
  </a>
  <a href="<?php echo BASE_URL; ?>/admin/manage_orders.php?status=pending" class="btn btn-secondary">
     Pending Orders (<?php echo $pendingOrders; ?>)
  </a>
  <?php if ($_SESSION['user_role'] === 'admin'): ?>
  <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="btn btn-ghost">
     Manage Users
  </a>
  <?php endif; ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
