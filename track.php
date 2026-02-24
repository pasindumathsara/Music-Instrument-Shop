<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$uid     = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) { header("Location: " . BASE_URL . "/orders.php"); exit(); }

// Load order (must belong to this user)
$oStmt = $conn->prepare(
    "SELECT o.*, u.name AS customer, u.email
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ?"
);
$oStmt->bind_param("ii", $orderId, $uid);
$oStmt->execute();
$order = $oStmt->get_result()->fetch_assoc();
if (!$order) { header("Location: " . BASE_URL . "/orders.php"); exit(); }

// Load items
$iStmt = $conn->prepare(
    "SELECT oi.*, p.name AS pname, p.image, p.product_type, p.digital_file
     FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?"
);
$iStmt->bind_param("i", $orderId);
$iStmt->execute();
$items = [];
$iRes = $iStmt->get_result();
while ($row = $iRes->fetch_assoc()) {
    $items[] = $row;
}

// Load status history
$hStmt = $conn->prepare(
    "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC"
);
$hStmt->bind_param("i", $orderId);
$hStmt->execute();
$history = [];
$hRes = $hStmt->get_result();
while ($row = $hRes->fetch_assoc()) {
    $history[] = $row;
}

// All digital?
$isAllDigital = !empty($items) && count(array_filter($items, fn($i) => ($i['product_type'] ?? 'physical') !== 'digital')) === 0;

// Status stages and icons
$stages = [
    'pending'    => ['label' => 'Order Placed',    'icon' => '&#128203;', 'color' => '#f59e0b'],
    'paid'       => ['label' => 'Payment Received', 'icon' => '&#10003;',  'color' => '#3b82f6'],
    'processing' => ['label' => 'Processing',       'icon' => '&#9881;',   'color' => '#8b5cf6'],
    'shipped'    => ['label' => 'Shipped',          'icon' => '&#128666;', 'color' => '#ec4899'],
    'delivered'  => ['label' => 'Delivered',        'icon' => '&#127873;', 'color' => '#10b981'],
    'cancelled'  => ['label' => 'Cancelled',        'icon' => '&#10007;',  'color' => '#ef4444'],
];
$stageOrder = ['pending','paid','processing','shipped','delivered'];
$currentStatus = $order['status'];
$currentIdx    = array_search($currentStatus, $stageOrder);

// Which stages have been reached
$reachedStatuses = array_column($history, 'status');

function getStageDate(array $history, string $status): ?string {
    foreach ($history as $h) {
        if ($h['status'] === $status) return $h['created_at'];
    }
    return null;
}

function pmLabel(string $pm): string {
    return match($pm) {
        'card'             => 'Credit / Debit Card',
        'bank_transfer'    => 'Bank Transfer',
        'cash_on_delivery' => 'Cash on Delivery',
        default            => ucfirst(str_replace('_',' ', $pm)),
    };
}

$pageTitle = "Track Order #$orderId";
require_once 'includes/header.php';
?>

<style>
/* ── Tracking Timeline ── */
.track-wrap { max-width:860px; margin:0 auto; }
.track-timeline { display:flex; justify-content:space-between; position:relative; margin:40px 0 48px; }
.track-timeline::before {
  content:''; position:absolute; top:28px; left:5%; right:5%;
  height:4px; background:var(--border); border-radius:2px; z-index:0;
}
.track-progress {
  position:absolute; top:28px; left:5%; height:4px;
  background:linear-gradient(90deg,var(--success),var(--accent));
  border-radius:2px; z-index:1; transition:width .6s ease;
}
.track-step { display:flex; flex-direction:column; align-items:center; gap:8px; flex:1; position:relative; z-index:2; }
.track-icon {
  width:56px; height:56px; border-radius:50%; border:3px solid var(--border);
  display:flex; align-items:center; justify-content:center; font-size:1.4rem;
  background:#fff; transition:all .3s; color:#94a3b8;
}
.track-icon.done   { border-color:var(--success); background:var(--success-bg); color:var(--success); }
.track-icon.active { border-color:var(--accent);  background:var(--accent);     color:#fff; box-shadow:0 0 0 4px rgba(225,29,72,.2); }
.track-icon.cancel { border-color:var(--danger);  background:var(--danger-bg);  color:var(--danger); }
.track-label { font-size:.72rem; font-weight:600; text-align:center; color:var(--muted); }
.track-label.done   { color:var(--success); }
.track-label.active { color:var(--accent); }
.track-label.cancel { color:var(--danger); }
.track-date { font-size:.65rem; color:var(--muted); text-align:center; }

/* cancelled ribbon */
.cancelled-ribbon {
  background:var(--danger-bg);border:1.5px solid var(--danger);border-radius:10px;
  padding:14px 20px;font-size:.88rem;color:#991b1b;font-weight:600;margin-bottom:28px;
}
</style>

<div class="container section">
  <div class="track-wrap">

    <!-- Header -->
    <nav style="font-size:.8rem;color:var(--muted);margin-bottom:24px;">
      <a href="<?php echo BASE_URL; ?>/orders.php">My Orders</a> &rsaquo;
      <a href="<?php echo BASE_URL; ?>/orders.php?id=<?php echo $orderId; ?>">Order #<?php echo $orderId; ?></a> &rsaquo;
      <span>Track</span>
    </nav>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:32px;">
      <div>
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:4px;">Order #<?php echo $orderId; ?> Tracking</h1>
        <div style="font-size:.83rem;color:var(--muted);">
          Placed <?php echo formatDate($order['created_at']); ?> &nbsp;·&nbsp;
          <?php echo pmLabel($order['payment_method'] ?? 'card'); ?>
        </div>
      </div>
      <?php echo statusBadge($currentStatus); ?>
    </div>

    <?php if ($currentStatus === 'cancelled'): ?>
    <div class="cancelled-ribbon">&#10007; This order has been cancelled.</div>
    <?php endif; ?>

    <!-- Timeline -->
    <?php
      $progressPct = 0;
      if ($currentStatus !== 'cancelled' && $currentIdx !== false) {
          $progressPct = ($currentIdx / (count($stageOrder) - 1)) * 90;
      }
    ?>
    <div class="track-timeline">
      <div class="track-progress" style="width:<?php echo $progressPct; ?>%;"></div>
      <?php foreach ($stageOrder as $idx => $stage): ?>
        <?php
          $isCurrent  = $stage === $currentStatus;
          $isDone     = $currentStatus !== 'cancelled' && $currentIdx !== false && $idx < $currentIdx;
          $isCancelled= $currentStatus === 'cancelled' && $stage === 'pending';
          $cls = $isCurrent ? 'active' : ($isDone ? 'done' : ($isCancelled ? 'cancel' : ''));
          $stageDate  = getStageDate($history, $stage);
          $info = $stages[$stage];
        ?>
        <div class="track-step">
          <div class="track-icon <?php echo $cls; ?>">
            <?php echo $isDone ? '&#10003;' : $info['icon']; ?>
          </div>
          <div class="track-label <?php echo $cls; ?>"><?php echo $info['label']; ?></div>
          <div class="track-date"><?php echo $stageDate ? date('d M, H:i', strtotime($stageDate)) : ''; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Main Grid -->
    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

      <!-- Left: Items + History -->
      <div>

        <!-- Order Items -->
        <div class="card" style="margin-bottom:20px;">
          <div class="card-header"><span class="card-title">Order Items</span></div>
          <div class="card-body" style="padding:0;">
            <?php foreach ($items as $it): ?>
              <?php $itDig = ($it['product_type'] ?? 'physical') === 'digital'; ?>
              <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border);">
                <div style="width:48px;height:48px;background:<?php echo $itDig ? '#ede9fe' : 'var(--bg)'; ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                  <?php echo $itDig ? '&#9660;' : '&#127925;'; ?>
                </div>
                <div style="flex:1;">
                  <div style="font-weight:600;font-size:.875rem;"><?php echo sanitize($it['pname'] ?? 'Product'); ?></div>
                  <div style="font-size:.75rem;color:var(--muted);">
                    Qty: <?php echo $it['quantity']; ?> &times; <?php echo formatPrice($it['price']); ?>
                    <?php if ($itDig): ?>
                      &nbsp;<span style="background:#ede9fe;color:#6d28d9;padding:1px 7px;border-radius:10px;font-size:.65rem;font-weight:700;">Digital</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="font-weight:700;color:var(--accent);display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                  <?php echo formatPrice($it['price'] * $it['quantity']); ?>
                  <?php if ($itDig && !empty($it['digital_file']) && in_array($order['status'], ['paid','processing','shipped','delivered'])): ?>
                    <a href="<?php echo BASE_URL; ?>/download.php?file=<?php echo urlencode($it['digital_file']); ?>&product_id=<?php echo (int)$it['product_id']; ?>"
                       class="btn btn-primary btn-sm" style="background:#6d28d9;font-size:.7rem;padding:4px 10px;">&#8659; Download</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Status History -->
        <div class="card">
          <div class="card-header"><span class="card-title">Status History</span></div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($history)): ?>
              <div style="padding:16px 20px;color:var(--muted);font-size:.83rem;">No history yet.</div>
            <?php else: ?>
              <?php foreach (array_reverse($history) as $h): ?>
                <?php $si = $stages[$h['status']] ?? ['label'=>$h['status'],'color'=>'#64748b','icon'=>'&#9679;']; ?>
                <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border);">
                  <div style="width:36px;height:36px;border-radius:50%;background:<?php echo $si['color']; ?>22;border:2px solid <?php echo $si['color']; ?>;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;color:<?php echo $si['color']; ?>;">
                    <?php echo $si['icon']; ?>
                  </div>
                  <div style="flex:1;">
                    <div style="font-weight:700;font-size:.83rem;color:<?php echo $si['color']; ?>;">
                      <?php echo $si['label']; ?>
                    </div>
                    <?php if ($h['note']): ?>
                      <div style="font-size:.78rem;color:var(--muted);"><?php echo sanitize($h['note']); ?></div>
                    <?php endif; ?>
                    <div style="font-size:.72rem;color:var(--muted);margin-top:2px;"><?php echo formatDate($h['created_at']); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- Right: Summary -->
      <div>
        <!-- Delivery Info -->
        <?php if ($isAllDigital): ?>
        <div class="card" style="margin-bottom:16px;border:1.5px solid #6d28d9;">
          <div class="card-header" style="background:linear-gradient(135deg,#1a0533,#6d28d9);">
            <span class="card-title" style="color:#fff;">Digital Delivery</span>
          </div>
          <div class="card-body" style="font-size:.83rem;color:var(--muted);">
            Download links are available in Order Items above after payment is confirmed.
          </div>
        </div>
        <?php else: ?>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><span class="card-title">Shipping To</span></div>
          <div class="card-body" style="font-size:.875rem;color:var(--muted);line-height:2;">
            <strong style="color:var(--text);"><?php echo sanitize($order['shipping_name']); ?></strong><br>
            <?php echo sanitize($order['shipping_address']); ?><br>
            <?php echo sanitize($order['shipping_city']); ?>, <?php echo sanitize($order['shipping_zip']); ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><span class="card-title">Payment Summary</span></div>
          <div class="card-body">
            <?php $sub = $order['total_amount'] - $order['shipping_cost']; ?>
            <div class="sum-row"><span>Subtotal</span><span><?php echo formatPrice($sub); ?></span></div>
            <div class="sum-row"><span>Shipping</span><span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : '<span style="color:var(--success)">FREE</span>'; ?></span></div>
            <div class="sum-total"><span>Total</span><span class="v"><?php echo formatPrice($order['total_amount']); ?></span></div>
            <div style="margin-top:12px;padding:10px;background:var(--bg);border-radius:8px;font-size:.78rem;color:var(--muted);">
              Method: <strong><?php echo pmLabel($order['payment_method'] ?? 'card'); ?></strong>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;flex-direction:column;gap:8px;">
          <a href="<?php echo BASE_URL; ?>/orders.php?id=<?php echo $orderId; ?>" class="btn btn-ghost btn-block">View Order Details</a>
          <?php if ($order['status'] === 'pending' && ($order['payment_method'] ?? 'card') === 'card'): ?>
          <a href="<?php echo BASE_URL; ?>/payment.php?order_id=<?php echo $orderId; ?>" class="btn btn-primary btn-block">Pay Now</a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
