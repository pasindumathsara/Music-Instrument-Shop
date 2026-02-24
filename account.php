<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$uid = (int)$_SESSION['user_id'];

// Load user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle name/email update
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$name || !$email) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // Check email uniqueness (excluding self)
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->bind_param("si", $email, $uid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "That email is already in use.";
        } else {
            $upd = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            $upd->bind_param("ssi", $name, $email, $uid);
            $upd->execute();
            $_SESSION['user_name'] = $name;
            $user['name']  = $name;
            $user['email'] = $email;
            $success = "Profile updated successfully!";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $hash, $uid);
        $upd->execute();
        $success = "Password changed successfully!";
    }
}

// Recent orders
$oStmt = $conn->prepare(
    "SELECT o.id, o.total_amount, o.status, o.created_at, COUNT(oi.id) AS items
     FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id
     WHERE o.user_id=? GROUP BY o.id ORDER BY o.created_at DESC LIMIT 5"
);
$oStmt->bind_param("i", $uid);
$oStmt->execute();
$recentOrders = $oStmt->get_result();

$pageTitle = "My Account";
require_once 'includes/header.php';
?>

<div class="container section">
  <h1 class="section-title" style="margin-bottom:32px;">👤 My <span>Account</span></h1>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo sanitize($success); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start;">

    <!-- Profile Update -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">✏️ Edit Profile</span>
        <span class="badge <?php echo isAdmin() ? 'badge-admin' : 'badge-customer'; ?>">
          <?php echo ucfirst($user['role']); ?>
        </span>
      </div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control"
                   value="<?php echo sanitize($user['name']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control"
                   value="<?php echo sanitize($user['email']); ?>" required>
          </div>
          <div style="font-size:.78rem;color:var(--muted);margin-bottom:14px;">
            Member since: <?php echo formatDate($user['created_at']); ?>
          </div>
          <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Password Change -->
    <div class="card">
      <div class="card-header"><span class="card-title">🔒 Change Password</span></div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control"
                   placeholder="Min. 6 characters" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" name="change_password" class="btn btn-secondary">Change Password</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Recent Orders -->
  <div style="margin-top:32px;">
    <div class="section-header">
      <h2 class="section-title" style="font-size:1.3rem;">Recent <span>Orders</span></h2>
      <a href="<?php echo BASE_URL; ?>/orders.php" class="btn btn-outline btn-sm">View All Orders</a>
    </div>

    <?php if ($recentOrders->num_rows === 0): ?>
      <div class="alert alert-info">You haven't placed any orders yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Order #</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php while ($o = $recentOrders->fetch_assoc()): ?>
              <tr>
                <td><strong>#<?php echo $o['id']; ?></strong></td>
                <td><?php echo formatDate($o['created_at']); ?></td>
                <td><?php echo $o['items']; ?></td>
                <td style="font-weight:700;color:var(--accent);"><?php echo formatPrice($o['total_amount']); ?></td>
                <td><?php echo statusBadge($o['status']); ?></td>
                <td><a href="<?php echo BASE_URL; ?>/orders.php?id=<?php echo $o['id']; ?>"
                       class="btn btn-ghost btn-sm">Details</a></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>