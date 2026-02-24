<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$orderId = (int)($_SESSION['last_order_id'] ?? 0);
if (!$orderId) { header("Location: " . BASE_URL . "/orders.php"); exit(); }
unset($_SESSION['last_order_id']);

// Payment info from session (set by payment.php)
$payLast4    = $_SESSION['payment_last4']     ?? null;
$payCardType = $_SESSION['payment_card_type'] ?? null;
unset($_SESSION['payment_last4'], $_SESSION['payment_card_type']);

// Load order
$uid  = (int)$_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT o.*, u.name AS user_name, u.email
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ?"
);
$stmt->bind_param("ii", $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header("Location: " . BASE_URL . "/orders.php"); exit(); }

// Load items (incl. product type for digital download buttons)
$items = $conn->prepare(
    "SELECT oi.*, p.name AS product_name, p.image, p.product_type, p.digital_file
     FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?"
);
$items->bind_param("i", $orderId);
$items->execute();
$orderItems = $items->get_result();
$isDigitalOrder = true; // will be set false if any physical item found
$orderItemsArr  = [];
while ($row = $orderItems->fetch_assoc()) {
    if (($row['product_type'] ?? 'physical') !== 'digital') $isDigitalOrder = false;
    $orderItemsArr[] = $row;
}

// Load payment record
$pmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
$pmt->bind_param("i", $orderId);
$pmt->execute();
$payment = $pmt->get_result()->fetch_assoc();

$pageTitle = "Order Confirmed";
require_once 'includes/header.php';
?>

<div class="container section">

  <!-- ── Success Banner ── -->
  <div style="text-align:center;padding:56px 20px 40px;max-width:640px;margin:0 auto 48px;">
    <div style="font-size:5rem;margin-bottom:16px;animation:pop .5s ease;"></div>
    <h1 style="font-size:2.2rem;font-weight:800;margin-bottom:12px;color:<?php echo $order['status']==='paid'?'var(--success)':'var(--accent)'; ?>;">
      Order <?php echo $order['status']==='paid' ? 'Confirmed & Paid!' : 'Placed Successfully!'; ?>
    </h1>
    <p style="color:var(--muted);font-size:1.05rem;margin-bottom:8px;">
      Thank you, <strong><?php echo sanitize($order['user_name']); ?></strong>!
      Your order <strong>#<?php echo $orderId; ?></strong> has been successfully placed.
    </p>
    <p style="color:var(--muted);font-size:.9rem;margin-bottom:28px;">
       A confirmation has been sent to <strong><?php echo sanitize($order['email']); ?></strong>
    </p>

    <?php if ($payment): ?>
      <div style="display:inline-flex;align-items:center;gap:10px;background:var(--success-bg);border:1px solid var(--success);border-radius:12px;padding:12px 22px;margin-bottom:24px;">
        <span style="font-size:1.5rem;"></span>
        <div style="text-align:left;">
          <div style="font-weight:700;color:#065f46;font-size:.9rem;">
            <?php echo sanitize($payment['card_type']); ?> ending in <?php echo sanitize($payment['card_last4']); ?>
          </div>
          <div style="font-size:.78rem;color:#065f46;opacity:.8;">
            Payment of <?php echo formatPrice($payment['amount']); ?> · <?php echo formatDate($payment['created_at']); ?>
          </div>
        </div>
        <span style="color:var(--success);font-size:1.3rem;"></span>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
      <a href="<?php echo BASE_URL; ?>/track.php?order_id=<?php echo $orderId; ?>" class="btn btn-primary btn-lg"> Track Order Status</a>
      <a href="<?php echo BASE_URL; ?>/orders.php" class="btn btn-secondary btn-lg"> My Orders</a>
      <a href="<?php echo BASE_URL; ?>/home.php"   class="btn btn-outline btn-lg"> Continue Shopping</a>
    </div>
  </div>

  <!-- ── Order Details ── -->
  <div style="display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;">

    <!-- Items -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"> Order #<?php echo $orderId; ?> Items</span>
        <?php echo statusBadge($order['status']); ?>
      </div>
      <div class="card-body" style="padding:0;">
        <?php foreach ($orderItemsArr as $item): ?>
          <div style="display:flex;align-items:center;gap:14px;padding:16px 24px;border-bottom:1px solid var(--border);">
            <div style="width:54px;height:54px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:1.8rem;opacity:.4;flex-shrink:0;"></div>
            <div style="flex:1;">
              <div style="font-weight:600;"><?php echo sanitize($item['product_name'] ?? 'Product'); ?></div>
              <div style="font-size:.8rem;color:var(--muted);">
                Qty: <?php echo $item['quantity']; ?> × <?php echo formatPrice($item['price']); ?>
              </div>
              <?php if (($item['product_type'] ?? '') === 'digital'): ?>
                <span style="font-size:.7rem;background:#ede9fe;color:#6d28d9;padding:2px 8px;border-radius:10px;font-weight:600;">Digital</span>
              <?php endif; ?>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
              <span style="font-weight:700;color:var(--accent);"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
              <?php if (($item['product_type'] ?? '') === 'digital' && !empty($item['digital_file'])): ?>
                <a href="<?php echo BASE_URL; ?>/download.php?file=<?php echo urlencode($item['digital_file']); ?>&product_id=<?php echo (int)$item['product_id']; ?>"
                   class="btn btn-primary btn-sm"
                   style="font-size:.72rem;padding:5px 12px;">
                  &#8659; Download
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Summary + Shipping -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Payment Record -->
      <?php if ($payment): ?>
      <div class="card">
        <div class="card-header"><span class="card-title"> Payment</span></div>
        <div class="card-body" style="font-size:.875rem;line-height:2.2;color:var(--muted);">
          <div style="display:flex;justify-content:space-between;">
            <span>Method</span>
            <strong><?php echo sanitize($payment['card_type']); ?> •••• <?php echo sanitize($payment['card_last4']); ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span>Cardholder</span>
            <strong><?php echo sanitize($payment['card_holder']); ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span>Status</span>
            <span style="color:var(--success);font-weight:700;"> Paid</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Shipping (only for physical orders) -->
      <?php if (!$isDigitalOrder): ?>
      <div class="card">
        <div class="card-header"><span class="card-title"> Shipping To</span></div>
        <div class="card-body" style="font-size:.875rem;color:var(--muted);line-height:2;">
          <strong style="color:var(--text);"><?php echo sanitize($order['shipping_name']); ?></strong><br>
          <?php echo sanitize($order['shipping_address']); ?><br>
          <?php echo sanitize($order['shipping_city']); ?>, <?php echo sanitize($order['shipping_zip']); ?>
        </div>
      </div>
      <?php else: ?>
      <!-- Digital delivery notice -->
      <div class="card" style="border:1.5px solid #6d28d9;">
        <div class="card-header" style="background:linear-gradient(135deg,#1a0533,#6d28d9);color:#fff;">
          <span class="card-title" style="color:#fff;">Digital Delivery</span>
        </div>
        <div class="card-body" style="font-size:.83rem;color:var(--muted);">
          Your files are ready to download above. You can also re-download from <a href="<?php echo BASE_URL; ?>/orders.php">My Orders</a> at any time.
        </div>
      </div>
      <?php endif; ?>

      <!-- Totals -->
      <div class="card">
        <div class="card-header"><span class="card-title"> Invoice</span></div>
        <div class="card-body">
          <?php $sub = $order['total_amount'] - $order['shipping_cost']; ?>
          <div class="sum-row"><span>Subtotal</span><span><?php echo formatPrice($sub); ?></span></div>
          <div class="sum-row">
            <span>Shipping</span>
            <span><?php echo $order['shipping_cost'] > 0
                ? formatPrice($order['shipping_cost'])
                : '<span style="color:var(--success);">FREE</span>'; ?></span>
          </div>
          <div class="sum-total">
            <span>Total Paid</span>
            <span class="v"><?php echo formatPrice($order['total_amount']); ?></span>
          </div>
          <div style="margin-top:10px;font-size:.75rem;color:var(--muted);">
             <?php echo formatDate($order['created_at']); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes pop {
  0%   { transform:scale(0.5); opacity:0; }
  80%  { transform:scale(1.1); }
  100% { transform:scale(1);   opacity:1; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
