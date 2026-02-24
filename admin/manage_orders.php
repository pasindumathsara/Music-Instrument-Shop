<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle   = "Manage Orders";
$statusFilter = $_GET['status'] ?? '';
$viewId      = (int)($_GET['id']     ?? 0);

// ── UPDATE STATUS ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';
    $note   = trim($_POST['status_note'] ?? '');
    $allowed = ['pending','paid','processing','shipped','delivered','cancelled'];
    
    if (in_array($status, $allowed)) {
        $upd = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $upd->bind_param("si", $status, $oid);
        $upd->execute();

        // Record status history
        $hist = $conn->prepare("INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)");
        $hBy   = "Admin";
        $hNote = $note ?: "Status updated by administrator";
        $hist->bind_param("isss", $oid, $status, $hNote, $hBy);
        $hist->execute();

        setFlash('success', "Order #$oid status updated to '$status'.");
    }
    header("Location: " . BASE_URL . "/admin/manage_orders.php" . ($viewId ? "?id=$oid" : "")); exit();
}

// ── SINGLE ORDER DETAIL ──────────────────────────────────────────
if ($viewId > 0) {
    $oStmt = $conn->prepare(
        "SELECT o.*, u.name AS customer, u.email
         FROM orders o JOIN users u ON u.id=o.user_id
         WHERE o.id=?"
    );
    $oStmt->bind_param("i", $viewId);
    $oStmt->execute();
    $order = $oStmt->get_result()->fetch_assoc();

    if (!$order) { header("Location: " . BASE_URL . "/admin/manage_orders.php"); exit(); }

    $iStmt = $conn->prepare(
        "SELECT oi.*, p.name AS pname FROM order_items oi
         LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?"
    );
    $iStmt->bind_param("i", $viewId);
    $iStmt->execute();
    $items = $iStmt->get_result();

    // Payment record
    $pmtStmt = $conn->prepare("SELECT * FROM payments WHERE order_id=? LIMIT 1");
    $pmtStmt->bind_param("i", $viewId);
    $pmtStmt->execute();
    $payment = $pmtStmt->get_result()->fetch_assoc();

    // Status History
    $hStmt = $conn->prepare("SELECT * FROM order_status_history WHERE order_id=? ORDER BY created_at DESC");
    $hStmt->bind_param("i", $viewId);
    $hStmt->execute();
    $history = $hStmt->get_result();

    require_once 'includes/admin_header.php';
    ?>

    <div class="admin-header-bar">
      <div>
        <h1 class="admin-title">Order #<?php echo $viewId; ?></h1>
        <a href="<?php echo BASE_URL; ?>/admin/manage_orders.php" style="font-size:.83rem;color:var(--muted);">← Back to Orders</a>
      </div>
      <?php echo statusBadge($order['status']); ?>
    </div>

    <?php echo showFlash(); ?>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

      <!-- Items -->
      <div class="card">
        <div class="card-header"><span class="card-title">Order Items</span></div>
        <div class="table-wrap" style="box-shadow:none;">
          <table>
            <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
            <tbody>
              <?php while ($it = $items->fetch_assoc()): ?>
                <tr>
                  <td><?php echo sanitize($it['pname'] ?? 'Deleted Product'); ?></td>
                  <td><?php echo $it['quantity']; ?></td>
                  <td><?php echo formatPrice($it['price']); ?></td>
                  <td style="font-weight:700;"><?php echo formatPrice($it['price']*$it['quantity']); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Side Panel -->
      <div>
        <!-- Update Status -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><span class="card-title">Update Status</span></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="order_id"     value="<?php echo $viewId; ?>">
              <input type="hidden" name="update_status" value="1">
              <select name="status" class="form-control" style="margin-bottom:12px;">
                <?php foreach (['pending','paid','processing','shipped','delivered','cancelled'] as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo $order['status']===$s?'selected':''; ?>>
                    <?php echo ucfirst($s); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <textarea name="status_note" class="form-control" rows="2" placeholder="Internal/Customer Note (optional)" style="margin-bottom:12px;font-size:.8rem;"></textarea>
              <button type="submit" class="btn btn-primary btn-block">Update Order</button>
            </form>
          </div>
        </div>

        <!-- Order Status History -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><span class="card-title">Status History</span></div>
          <div class="card-body" style="font-size:.78rem;padding:0;">
            <?php while ($h = $history->fetch_assoc()): ?>
              <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                  <strong style="color:var(--accent);"><?php echo strtoupper($h['status']); ?></strong>
                  <span style="color:var(--muted);font-size:.7rem;"><?php echo date('M d, H:i', strtotime($h['created_at'])); ?></span>
                </div>
                <div style="color:var(--text);"><?php echo sanitize($h['note']); ?></div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Payment Record -->
        <?php if ($payment): ?>
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><span class="card-title">💳 Payment</span></div>
            <div class="card-body" style="font-size:.83rem;color:var(--muted);line-height:2.2;">
              <div style="display:flex;justify-content:space-between;">
                <span>Card</span>
                <strong><?php echo sanitize($payment['card_type']); ?> •••• <?php echo sanitize($payment['card_last4']); ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;">
                <span>Holder</span>
                <strong><?php echo sanitize($payment['card_holder']); ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;">
                <span>Amount</span>
                <strong style="color:var(--accent);"><?php echo formatPrice($payment['amount']); ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;">
                <span>Date</span>
                <span><?php echo formatDate($payment['created_at']); ?></span>
              </div>
              <div style="margin-top:8px;padding:6px 10px;background:var(--success-bg);border-radius:6px;color:#065f46;font-weight:700;text-align:center;">
                ✅ Payment Successful
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><span class="card-title">💳 Payment</span></div>
            <div class="card-body">
              <div style="background:var(--warning-bg);color:#92400e;padding:10px;border-radius:6px;font-size:.83rem;font-weight:600;text-align:center;">
                ⚠️ No payment recorded yet
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Customer & Shipping -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><span class="card-title">Customer</span></div>
          <div class="card-body" style="font-size:.83rem;color:var(--muted);line-height:2;">
            <strong style="color:var(--text);"><?php echo sanitize($order['customer']); ?></strong><br>
            <?php echo sanitize($order['email']); ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Shipping & Payment</span></div>
          <div class="card-body" style="font-size:.83rem;color:var(--muted);line-height:2;">
            <?php echo sanitize($order['shipping_name'] ?? ''); ?><br>
            <?php echo sanitize($order['shipping_address'] ?? ''); ?><br>
            <?php echo sanitize($order['shipping_city'] ?? ''); ?>, <?php echo sanitize($order['shipping_zip'] ?? ''); ?>
            <hr style="border:none;border-top:1px solid var(--border);margin:10px 0;">
            Shipping: <strong><?php echo $order['shipping_cost']>0 ? formatPrice($order['shipping_cost']) : 'FREE'; ?></strong><br>
            Method: <span class="badge" style="background:var(--bg);color:var(--text);border:1px solid var(--border);"><?php echo strtoupper(str_replace('_',' ',$order['payment_method']??'CARD')); ?></span><br>
            <strong style="color:var(--accent);font-size:1rem;">Total: <?php echo formatPrice($order['total_amount']); ?></strong><br>
            Date: <?php echo formatDate($order['created_at']); ?>
          </div>
        </div>
      </div>
    </div>

    <?php
    require_once 'includes/admin_footer.php';
    exit();
}

// ── ORDER LIST ────────────────────────────────────────────────────
$where = $statusFilter ? "WHERE o.status = '" . $conn->real_escape_string($statusFilter) . "'" : '';
$orders = $conn->query(
    "SELECT o.*, u.name AS customer, COUNT(oi.id) AS items
     FROM orders o
     JOIN users u ON u.id=o.user_id
     LEFT JOIN order_items oi ON oi.order_id=o.id
     $where
     GROUP BY o.id ORDER BY o.created_at DESC"
);

require_once 'includes/admin_header.php';
?>

<div class="admin-header-bar">
  <h1 class="admin-title">📦 Orders</h1>
  <!-- Status filter -->
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php foreach (['','pending','paid','processing','shipped','delivered','cancelled'] as $sf): ?>
      <a href="?<?php echo $sf ? 'status='.$sf : ''; ?>"
         class="fp <?php echo $statusFilter===$sf?'active':''; ?>" style="font-size:.78rem;">
        <?php echo $sf ? ucfirst($sf) : 'All'; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php echo showFlash(); ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php if ($orders->num_rows === 0): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No orders found.</td></tr>
      <?php endif; ?>
      <?php while ($o = $orders->fetch_assoc()): ?>
        <tr>
          <td><strong>#<?php echo $o['id']; ?></strong></td>
          <td><?php echo sanitize($o['customer']); ?></td>
          <td><?php echo $o['items']; ?></td>
          <td style="font-weight:700;color:var(--accent);"><?php echo formatPrice($o['total_amount']); ?></td>
          <td style="font-size:.75rem;font-weight:600;"><?php echo strtoupper(str_replace('_',' ',$o['payment_method']??'CARD')); ?></td>
          <td><?php echo statusBadge($o['status']); ?></td>
          <td style="font-size:.8rem;color:var(--muted);"><?php echo formatDate($o['created_at']); ?></td>
          <td>
            <a href="?id=<?php echo $o['id']; ?>" class="btn btn-ghost btn-sm">Manage →</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
