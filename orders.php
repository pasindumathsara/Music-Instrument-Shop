<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$uid    = (int)$_SESSION['user_id'];
$viewId = (int)($_GET['id'] ?? 0);

// ── Single Order Detail ────────────────────────────────────────
if ($viewId > 0) {
    $stmt = $conn->prepare(
        "SELECT o.* FROM orders o WHERE o.id = ? AND o.user_id = ?"
    );
    $stmt->bind_param("ii", $viewId, $uid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) { header("Location: " . BASE_URL . "/orders.php"); exit(); }

    $iStmt = $conn->prepare(
        "SELECT oi.*, p.name AS pname, p.image, p.product_type, p.digital_file
         FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id
         WHERE oi.order_id=?"
    );
    $iStmt->bind_param("i", $viewId);
    $iStmt->execute();
    $itemsResult = $iStmt->get_result();
    $items = [];
    $orderIsAllDigital = true;
    while ($row = $itemsResult->fetch_assoc()) {
        if (($row['product_type'] ?? 'physical') !== 'digital') $orderIsAllDigital = false;
        $items[] = $row;
    }

    $pageTitle = "Order #$viewId";
    require_once 'includes/header.php';
?>
<div class="container section">
  <nav class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>/orders.php">My Orders</a> &rsaquo;
    <span class="active">Order #<?php echo $viewId; ?></span>
  </nav>
  <div class="order-detail-layout">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Order #<?php echo $viewId; ?></span>
        <div style="display:flex;align-items:center;gap:8px;">
          <?php echo statusBadge($order['status']); ?>
          <a href="<?php echo BASE_URL; ?>/track.php?order_id=<?php echo $viewId; ?>"
             class="btn btn-secondary btn-sm">Track Order</a>
          <?php if ($order['status'] === 'pending'): ?>
            <a href="<?php echo BASE_URL; ?>/payment.php?order_id=<?php echo $viewId; ?>"
               class="btn btn-primary btn-sm">Pay Now</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body" style="padding:0;">
        <?php foreach ($items as $it): ?>
          <?php
            $itDigital     = ($it['product_type'] ?? 'physical') === 'digital';
            $canDownload   = $itDigital
                             && !empty($it['digital_file'])
                             && in_array($order['status'], ['paid','processing','shipped','delivered']);
          ?>
          <div class="order-item-detail">
            <div class="item-icon <?php echo $itDigital ? 'digital' : ''; ?>">
              <?php echo $itDigital ? '&#9660;' : '&#127925;'; ?>
            </div>
            <div style="flex:1;">
              <?php if ($it['product_id']): ?>
                <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $it['product_id']; ?>" style="font-weight:600;">
                  <?php echo sanitize($it['pname'] ?? 'Product'); ?>
                </a>
              <?php else: ?>
                <span style="font-weight:600;"><?php echo sanitize($it['pname'] ?? 'Deleted Product'); ?></span>
              <?php endif; ?>
              <div style="display:flex;align-items:center;gap:8px;margin-top:3px;">
                <span style="font-size:.8rem;color:var(--muted);">Qty: <?php echo $it['quantity']; ?> × <?php echo formatPrice($it['price']); ?></span>
                <?php if ($itDigital): ?>
                  <span style="font-size:.68rem;background:#ede9fe;color:#6d28d9;padding:2px 7px;border-radius:10px;font-weight:700;">Digital</span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
              <span style="font-weight:700;color:var(--accent);"><?php echo formatPrice($it['price']*$it['quantity']); ?></span>

              <?php if ($canDownload): ?>
                <a href="<?php echo BASE_URL; ?>/download.php?file=<?php echo urlencode($it['digital_file']); ?>&product_id=<?php echo (int)$it['product_id']; ?>"
                   class="btn btn-primary btn-sm"
                   style="background:#6d28d9;font-size:.72rem;padding:5px 12px;">
                  &#8659; Download
                </a>
              <?php elseif ($itDigital && $order['status'] === 'pending'): ?>
                <span style="font-size:.72rem;color:var(--muted);font-style:italic;">Pay to unlock</span>
              <?php endif; ?>

              <!-- Review link if eligible -->
              <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'pending' && $it['product_id']): ?>
                <?php if (!hasReviewedProduct($conn, $uid, $it['product_id'])): ?>
                  <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $it['product_id']; ?>#review"
                     class="btn btn-outline btn-sm" style="font-size:.72rem;padding:5px 12px;">Review</a>
                <?php else: ?>
                  <span class="badge badge-paid">Reviewed</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <?php if ($orderIsAllDigital): ?>
      <div class="card" style="margin-bottom:16px;border:1.5px solid #6d28d9;">
        <div class="card-header" style="background:linear-gradient(135deg,#1a0533,#6d28d9);">
          <span class="card-title" style="color:#fff;">Digital Delivery</span>
        </div>
        <div class="card-body" style="font-size:.83rem;color:var(--muted);">
          Your digital file(s) are available to download above any time after payment.
        </div>
      </div>
      <?php else: ?>
      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><span class="card-title">Shipping</span></div>
        <div class="card-body" style="font-size:.875rem;color:var(--muted);line-height:2;">
          <strong style="color:var(--text);"><?php echo sanitize($order['shipping_name']); ?></strong><br>
          <?php echo sanitize($order['shipping_address']); ?><br>
          <?php echo sanitize($order['shipping_city']); ?>, <?php echo sanitize($order['shipping_zip']); ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="card">
        <div class="card-header"><span class="card-title"> Summary</span></div>
        <div class="card-body">
          <?php $sub = $order['total_amount'] - $order['shipping_cost']; ?>
          <div class="sum-row"><span>Subtotal</span><span><?php echo formatPrice($sub); ?></span></div>
          <div class="sum-row"><span>Shipping</span><span><?php echo $order['shipping_cost']>0?formatPrice($order['shipping_cost']):'<span style="color:var(--success)">FREE</span>'; ?></span></div>
          <div class="sum-total"><span>Total</span><span class="v"><?php echo formatPrice($order['total_amount']); ?></span></div>
          <div style="margin-top:10px;display:flex;justify-content:space-between;font-size:.78rem;color:var(--muted);">
            <span>Method:</span>
            <strong><?php echo strtoupper(str_replace('_',' ',$order['payment_method']??'CARD')); ?></strong>
          </div>
          <div style="margin-top:4px;font-size:.78rem;color:var(--muted);"><?php echo formatDate($order['created_at']); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
    require_once 'includes/footer.php';
    exit();
}

// ── Order List ────────────────────────────────────────────────
$orders = $conn->prepare(
    "SELECT o.*, COUNT(oi.id) AS item_count
     FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id
     WHERE o.user_id=?
     GROUP BY o.id ORDER BY o.created_at DESC"
);
$orders->bind_param("i", $uid);
$orders->execute();
$orderRows = $orders->get_result();

$pageTitle = "My Orders";
require_once 'includes/header.php';
?>

<div class="container section">
  <h1 class="section-title" style="margin-bottom:28px;"> My <span>Orders</span></h1>

  <?php if ($orderRows->num_rows === 0): ?>
    <div class="empty-state">
      <div class="icon"></div>
      <h3>No orders yet</h3>
      <p>Start shopping to see your orders here.</p>
      <a href="<?php echo BASE_URL; ?>/home.php" class="btn btn-primary btn-lg">Shop Now</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Items</th>
            <th>Total</th>
            <th>Method</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($o = $orderRows->fetch_assoc()): ?>
            <tr>
              <td><strong>#<?php echo $o['id']; ?></strong></td>
              <td><?php echo formatDate($o['created_at']); ?></td>
              <td><?php echo $o['item_count']; ?> item<?php echo $o['item_count']!=1?'s':''; ?></td>
              <td style="font-weight:700;color:var(--accent);"><?php echo formatPrice($o['total_amount']); ?></td>
              <td style="font-size:.75rem;font-weight:600;"><?php echo strtoupper(str_replace('_',' ',$o['payment_method']??'CARD')); ?></td>
              <td><?php echo statusBadge($o['status']); ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="<?php echo BASE_URL; ?>/orders.php?id=<?php echo $o['id']; ?>"
                     class="btn btn-ghost btn-sm">Details</a>
                  <a href="<?php echo BASE_URL; ?>/track.php?order_id=<?php echo $o['id']; ?>"
                     class="btn btn-secondary btn-sm">Track</a>
                  <?php if ($o['status'] === 'pending'): ?>
                    <a href="<?php echo BASE_URL; ?>/payment.php?order_id=<?php echo $o['id']; ?>"
                       class="btn btn-primary btn-sm"> Pay</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
