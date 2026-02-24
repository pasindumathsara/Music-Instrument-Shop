<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$uid     = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) { header("Location: " . BASE_URL . "/orders.php"); exit(); }

// Load order — must belong to this user and be pending
$stmt = $conn->prepare(
    "SELECT o.*, u.name AS user_name, u.email
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ?"
);
$stmt->bind_param("ii", $orderId, $uid);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    setFlash('danger', 'Order not found.');
    header("Location: " . BASE_URL . "/orders.php"); exit();
}
if ($order['status'] !== 'pending') {
    setFlash('info', 'This order has already been processed.');
    header("Location: " . BASE_URL . "/orders.php?id=$orderId"); exit();
}

// Already paid?
$pChk = $conn->prepare("SELECT id FROM payments WHERE order_id=? LIMIT 1");
$pChk->bind_param("i", $orderId);
$pChk->execute();
if ($pChk->get_result()->num_rows > 0) {
    setFlash('info', 'Payment already recorded.'); header("Location: " . BASE_URL . "/orders.php?id=$orderId"); exit();
}

// Load items
$iStmt = $conn->prepare(
    "SELECT oi.*, p.name AS pname
     FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id
     WHERE oi.order_id=?"
);
$iStmt->bind_param("i", $orderId);
$iStmt->execute();
$orderItems = $iStmt->get_result();

$error = '';

// ── Process Payment ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $cardHolder = trim($_POST['card_holder'] ?? '');
    $expiry     = trim($_POST['expiry']      ?? '');
    // Normalize expiry: JS outputs "MM / YY", strip spaces → "MM/YY"
    $expiry = preg_replace('/\s+/', '', $expiry);
    $cvv        = trim($_POST['cvv']         ?? '');

    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19)  { $error = "Enter a valid card number."; }
    elseif (!$cardHolder)                                        { $error = "Enter the cardholder name."; }
    elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)){ $error = "Enter expiry as MM/YY."; }
    else {
        [$mm,$yy] = explode('/', $expiry);
        if (mktime(0,0,0,(int)$mm+1,1,2000+(int)$yy) < time()) { $error = "Your card has expired."; }
    }
    if (!$error && (strlen($cvv) < 3 || strlen($cvv) > 4)) { $error = "Enter a valid CVV (3–4 digits)."; }

    if (!$error) {
        $cardType = 'Unknown';
        if (preg_match('/^4/',$cardNumber))           $cardType = 'Visa';
        elseif (preg_match('/^5[1-5]/',$cardNumber))  $cardType = 'Mastercard';
        elseif (preg_match('/^3[47]/',$cardNumber))   $cardType = 'Amex';
        elseif (preg_match('/^6/',$cardNumber))       $cardType = 'Discover';
        $last4 = substr($cardNumber, -4);

        $conn->begin_transaction();
        try {
            $pIns = $conn->prepare("INSERT INTO payments (order_id,amount,card_last4,card_holder,card_type,status) VALUES (?,?,?,?,?,'success')");
            $pIns->bind_param("idsss", $orderId, $order['total_amount'], $last4, $cardHolder, $cardType);
            $pIns->execute();
            $upd = $conn->prepare("UPDATE orders SET status='paid' WHERE id=?");
            $upd->bind_param("i", $orderId);
            $upd->execute();

            // Record status history
            $hist = $conn->prepare("INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)");
            $hNote = "Payment successful (Card ending in " . $last4 . ")";
            $hBy   = sanitize($_SESSION['user_name'] ?? 'customer');
            $status_paid = 'paid';
            $hist->bind_param("isss", $orderId, $status_paid, $hNote, $hBy);
            $hist->execute();
            $conn->commit();
            $_SESSION['last_order_id']     = $orderId;
            $_SESSION['payment_last4']     = $last4;
            $_SESSION['payment_card_type'] = $cardType;
            setFlash('success', "Payment successful! Order #$orderId is now confirmed.");
            header("Location: " . BASE_URL . "/order_confirm.php"); exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Payment failed. Please try again.";
        }
    }
}

$pageTitle = "Payment";
require_once 'includes/header.php';
?>

<style>
/* ── Payment page layout ────────────────────────────────────── */
.pay-page {
  background: #f8fafc;
  min-height: 100vh;
  padding: 40px 0 60px;
}
.pay-container {
  max-width: 960px;
  margin: 0 auto;
  padding: 0 20px;
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 28px;
  align-items: start;
}

/* ── Left: Payment form ─────────────────────────────────────── */
.pay-card {
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 4px 32px rgba(0,0,0,.09);
  overflow: hidden;
}
.pay-card-header {
  padding: 20px 28px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.pay-card-header h2 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
}
.ssl-badge {
  display: flex;
  align-items: center;
  gap: 5px;
  background: #ecfdf5;
  color: #059669;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 700;
}
.pay-card-body { padding: 28px; }

/* ── Card type selector ─────────────────────────────────────── */
.card-types {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
}
.card-type-btn {
  flex: 1;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  padding: 8px 6px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  transition: border-color .2s, box-shadow .2s;
  background: #fff;
  font-size: .65rem;
  font-weight: 700;
  color: #64748b;
}
.card-type-btn.active {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(225,29,72,.1);
  color: var(--accent);
}
.card-type-logo {
  font-size: 1.4rem;
  line-height: 1;
}

/* ── Input field styling ────────────────────────────────────── */
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.pay-label {
  display: block;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: #64748b;
  margin-bottom: 6px;
}
.pay-input {
  width: 100%;
  padding: 12px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: .95rem;
  font-family: inherit;
  transition: border-color .2s, box-shadow .2s;
  background: #f8fafc;
  box-sizing: border-box;
  outline: none;
}
.pay-input:focus {
  border-color: var(--primary);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(30,41,59,.08);
}
.pay-input.card-num {
  letter-spacing: 3px;
  font-family: 'Courier New', monospace;
  font-size: 1.05rem;
  font-weight: 600;
}
.pay-input.valid     { border-color: #10b981; }
.pay-input.invalid   { border-color: #ef4444; }

/* ── Mini card display ──────────────────────────────────────── */
.mini-card {
  background: linear-gradient(135deg, var(--primary) 0%, #e11d48 100%);
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  box-shadow: 0 8px 24px rgba(30,41,59,.25);
  position: relative;
  overflow: hidden;
}
.mini-card::after {
  content: '';
  position: absolute;
  width: 140px; height: 140px;
  border-radius: 50%;
  background: rgba(255,255,255,.06);
  top: -40px; right: -20px;
}
.mini-card-num {
  font-family: 'Courier New', monospace;
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: 3px;
  color: #fff;
}
.mini-card-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}
.mini-card-label { font-size: .6rem; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: 1px; }
.mini-card-val   { font-size: .82rem; color: #fff; font-weight: 600; margin-top: 2px; }
.mini-card-type  { font-size: .75rem; font-weight: 800; color: rgba(255,255,255,.9); }
.chip {
  width: 34px; height: 26px;
  border-radius: 5px;
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

/* ── Pay button ─────────────────────────────────────────────── */
.pay-btn {
  width: 100%;
  padding: 15px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--primary) 0%, #e11d48 100%);
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  margin-top: 22px;
  transition: opacity .2s, transform .15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.pay-btn:hover   { opacity: .92; transform: translateY(-1px); }
.pay-btn:active  { transform: translateY(0); }
.pay-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* ── Right: Order summary ───────────────────────────────────── */
.summary-card {
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 4px 32px rgba(0,0,0,.09);
  overflow: hidden;
  position: sticky;
  top: 90px;
}
.summary-header {
  padding: 18px 22px;
  border-bottom: 1px solid #f1f5f9;
  font-size: .95rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 8px;
}
.summary-items { padding: 0; }
.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 22px;
  border-bottom: 1px solid #f1f5f9;
  font-size: .85rem;
}
.summary-item-name {
  color: var(--text);
  font-weight: 500;
  flex: 1;
}
.summary-item-qty {
  font-size: .75rem;
  color: var(--muted);
  background: #f1f5f9;
  border-radius: 4px;
  padding: 1px 6px;
  margin-left: 4px;
}
.summary-item-price {
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
}
.summary-calc {
  padding: 14px 22px;
  border-bottom: 1px solid #f1f5f9;
}
.sum-line {
  display: flex;
  justify-content: space-between;
  font-size: .83rem;
  color: var(--muted);
  padding: 4px 0;
}
.sum-line.ship { color: #059669; font-weight: 600; }
.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
}
.summary-total-label { font-weight: 700; color: var(--text); }
.summary-total-amount {
  font-size: 1.5rem;
  font-weight: 900;
  color: var(--accent);
}
.summary-shipping-to {
  padding: 12px 22px 18px;
  font-size: .78rem;
  color: var(--muted);
  border-top: 1px solid #f1f5f9;
}
.summary-security {
  padding: 14px 22px;
  background: #f8fafc;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  border-top: 1px solid #f1f5f9;
}
.sec-pill {
  font-size: .7rem;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 20px;
  padding: 3px 10px;
  font-weight: 600;
}

@media(max-width: 768px) {
  .pay-container { grid-template-columns: 1fr; }
  .summary-card  { position: relative; top: 0; }
  .field-row     { grid-template-columns: 1fr; }
}
</style>

<div class="pay-page">
  <!-- Breadcrumb -->
  <div style="max-width:960px;margin:0 auto;padding:0 20px 20px;">
    <nav style="font-size:.8rem;color:var(--muted);">
      <a href="<?php echo BASE_URL; ?>/orders.php" style="color:var(--muted);">My Orders</a>
      &rsaquo; <a href="<?php echo BASE_URL; ?>/orders.php?id=<?php echo $orderId; ?>" style="color:var(--muted);">Order #<?php echo $orderId; ?></a>
      &rsaquo; <span>Payment</span>
    </nav>
  </div>

  <?php if ($error): ?>
    <div style="max-width:960px;margin:0 auto 16px;padding:0 20px;">
      <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
    </div>
  <?php endif; ?>

  <div class="pay-container">

    <!-- ── LEFT: Payment Form ─────────────────────────────────── -->
    <div class="pay-card">
      <div class="pay-card-header">
        <h2>Payment Details</h2>
        <div class="ssl-badge">
          <svg width="11" height="13" viewBox="0 0 11 13" fill="none"><rect x="2" y="5" width="7" height="8" rx="1.5" fill="#059669" opacity=".25"/><rect x="2" y="5" width="7" height="8" rx="1.5" stroke="#059669" stroke-width="1.2"/><path d="M3.5 5V3.5a2 2 0 014 0V5" stroke="#059669" stroke-width="1.2" stroke-linecap="round"/><circle cx="5.5" cy="9" r="1" fill="#059669"/></svg>
          SSL Secured
        </div>
      </div>

      <div class="pay-card-body">
        <!-- Mini Card Preview -->
        <div class="mini-card" id="miniCard">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div class="chip"></div>
            <div class="mini-card-type" id="mcType">CARD</div>
          </div>
          <div class="mini-card-num" id="mcNum">•••• •••• •••• ••••</div>
          <div class="mini-card-row">
            <div>
              <div class="mini-card-label">Card Holder</div>
              <div class="mini-card-val" id="mcHolder"><?php echo strtoupper(sanitize($order['user_name'] ?? 'FULL NAME')); ?></div>
            </div>
            <div style="text-align:right;">
              <div class="mini-card-label">Expires</div>
              <div class="mini-card-val" id="mcExpiry">MM/YY</div>
            </div>
          </div>
        </div>

        <!-- Card Type Pills -->
        <div class="card-types" id="cardTypePills">
          <div class="card-type-btn" data-type="Visa">
            <div class="card-type-logo" style="color:#1a1f71;font-weight:900;font-family:serif;font-size:1rem;">VISA</div>
            <span>Visa</span>
          </div>
          <div class="card-type-btn" data-type="Mastercard">
            <div class="card-type-logo">
              <svg width="28" height="20" viewBox="0 0 28 20"><circle cx="10" cy="10" r="9" fill="#eb001b"/><circle cx="18" cy="10" r="9" fill="#f79e1b"/><path d="M14 4.5a9 9 0 010 11A9 9 0 0114 4.5z" fill="#ff5f00"/></svg>
            </div>
            <span>Mastercard</span>
          </div>
          <div class="card-type-btn" data-type="Amex">
            <div class="card-type-logo" style="color:#2557d6;font-weight:900;font-size:.75rem;">AMEX</div>
            <span>Amex</span>
          </div>
          <div class="card-type-btn" data-type="Discover">
            <div class="card-type-logo" style="color:#ff6b00;font-weight:900;font-size:.75rem;">DISC</div>
            <span>Discover</span>
          </div>
        </div>

        <!-- Form -->
        <form method="POST" id="payForm" novalidate>

          <!-- Card Number -->
          <div class="form-group">
            <label class="pay-label" for="cardNumInput">Card Number</label>
            <input type="text" id="cardNumInput" name="card_number"
                   class="pay-input card-num"
                   placeholder="0000  0000  0000  0000"
                   maxlength="19"
                   autocomplete="cc-number"
                   inputmode="numeric" required>
          </div>

          <!-- Holder -->
          <div class="form-group">
            <label class="pay-label" for="holderInput">Cardholder Name</label>
            <input type="text" id="holderInput" name="card_holder"
                   class="pay-input"
                   placeholder="Name as on card"
                   value="<?php echo sanitize($order['user_name'] ?? ''); ?>"
                   autocomplete="cc-name" required>
          </div>

          <!-- Expiry + CVV -->
          <div class="field-row">
            <div class="form-group">
              <label class="pay-label" for="expiryInput">Expiry Date</label>
              <input type="text" id="expiryInput" name="expiry"
                     class="pay-input"
                     placeholder="MM / YY"
                     maxlength="7"
                     autocomplete="cc-exp"
                     inputmode="numeric" required>
            </div>
            <div class="form-group">
              <label class="pay-label" for="cvvInput">CVV / CVC</label>
              <input type="password" id="cvvInput" name="cvv"
                     class="pay-input"
                     placeholder="•••"
                     maxlength="4"
                     autocomplete="cc-csc"
                     inputmode="numeric" required>
            </div>
          </div>

          <!-- Pay Button -->
          <button type="submit" class="pay-btn" id="payBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Pay <?php echo formatPrice($order['total_amount']); ?> Now
          </button>

          <div style="text-align:center;margin-top:14px;font-size:.72rem;color:var(--muted);">
            This is a simulated payment. No real charge is made.
          </div>
        </form>
      </div>
    </div>

    <!-- ── RIGHT: Order Summary ───────────────────────────────── -->
    <div class="summary-card">
      <div class="summary-header">
        Order Summary
        <span style="margin-left:auto;font-size:.75rem;font-weight:400;color:var(--muted);">#<?php echo $orderId; ?></span>
      </div>

      <!-- Items -->
      <div class="summary-items">
        <?php
          $orderItems->data_seek(0);
          while ($it = $orderItems->fetch_assoc()):
        ?>
          <div class="summary-item">
            <span class="summary-item-name">
              <?php echo sanitize($it['pname'] ?? 'Product'); ?>
              <span class="summary-item-qty">×<?php echo $it['quantity']; ?></span>
            </span>
            <span class="summary-item-price"><?php echo formatPrice($it['price'] * $it['quantity']); ?></span>
          </div>
        <?php endwhile; ?>
      </div>

      <!-- Calc -->
      <?php $sub = $order['total_amount'] - $order['shipping_cost']; ?>
      <div class="summary-calc">
        <div class="sum-line">
          <span>Subtotal</span>
          <span><?php echo formatPrice($sub); ?></span>
        </div>
        <div class="sum-line <?php echo $order['shipping_cost'] == 0 ? 'ship' : ''; ?>">
          <span>Shipping</span>
          <span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'FREE'; ?></span>
        </div>
      </div>

      <!-- Total -->
      <div class="summary-total">
        <span class="summary-total-label">Total</span>
        <span class="summary-total-amount"><?php echo formatPrice($order['total_amount']); ?></span>
      </div>

      <!-- Shipping To -->
      <div class="summary-shipping-to">
        <strong>Shipping to:</strong><br>
        <?php echo sanitize($order['shipping_name']); ?><br>
        <?php echo sanitize($order['shipping_address']); ?><br>
        <?php echo sanitize($order['shipping_city']); ?>, <?php echo sanitize($order['shipping_zip']); ?>
      </div>

      <!-- Security -->
      <div class="summary-security">
        <span class="sec-pill">SSL Secured</span>
        <span class="sec-pill">256-bit Encryption</span>
        <span class="sec-pill">Safe Checkout</span>
      </div>
    </div>

  </div><!-- /pay-container -->
</div><!-- /pay-page -->

<script>
// ── Mini card live update ─────────────────────────────────────
const mcNum    = document.getElementById('mcNum');
const mcHolder = document.getElementById('mcHolder');
const mcExpiry = document.getElementById('mcExpiry');
const mcType   = document.getElementById('mcType');
const miniCard = document.getElementById('miniCard');

// Card type detection
const cardTypes = {
  Visa:       { pattern: /^4/,        label: 'VISA',       gradient: 'linear-gradient(135deg,#1a1f71,#2557d6)' },
  Mastercard: { pattern: /^5[1-5]/,   label: 'MASTERCARD', gradient: 'linear-gradient(135deg,#1a1a1a,#eb001b)' },
  Amex:       { pattern: /^3[47]/,    label: 'AMEX',       gradient: 'linear-gradient(135deg,#00175a,#2557d6)' },
  Discover:   { pattern: /^6/,        label: 'DISCOVER',   gradient: 'linear-gradient(135deg,#231f20,#ff6b00)' },
};

function detectType(num) {
  const n = num.replace(/\D/g,'');
  for (const [name,info] of Object.entries(cardTypes)) {
    if (info.pattern.test(n)) return { name, ...info };
  }
  return null;
}

function updateCardPills(type) {
  document.querySelectorAll('.card-type-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.type === type);
  });
}

// Card number
document.getElementById('cardNumInput').addEventListener('input', function() {
  const digits = this.value.replace(/\D/g,'').slice(0,16);
  const spaced = digits.replace(/(.{4})/g,'$1 ').trim();
  this.value   = spaced;

  let display = '';
  for (let i=0; i<16; i++) {
    if (i>0 && i%4===0) display += ' ';
    display += digits[i] || '•';
  }
  mcNum.textContent = display;

  const type = detectType(this.value);
  if (type) {
    mcType.textContent        = type.label;
    miniCard.style.background = type.gradient;
    updateCardPills(type.name);
  } else {
    mcType.textContent        = 'CARD';
    miniCard.style.background = 'linear-gradient(135deg,var(--primary),#e11d48)';
    updateCardPills(null);
  }
});

// Holder
document.getElementById('holderInput').addEventListener('input', function() {
  mcHolder.textContent = this.value.toUpperCase() || 'FULL NAME';
});

// Expiry
document.getElementById('expiryInput').addEventListener('input', function() {
  let v = this.value.replace(/\D/g,'');
  if (v.length >= 2) v = v.slice(0,2) + ' / ' + v.slice(2,4);
  this.value       = v;
  const clean = v.replace(/\s/g,'').replace('/','\/');
  mcExpiry.textContent = clean || 'MM/YY';
});

// CVV tooltip
document.getElementById('cvvInput').addEventListener('focus', function() {
  miniCard.style.opacity = '.6';
});
document.getElementById('cvvInput').addEventListener('blur', function() {
  miniCard.style.opacity = '1';
});

// Submit loading state
document.getElementById('payForm').addEventListener('submit', function() {
  const btn = document.getElementById('payBtn');
  btn.disabled = true;
  btn.textContent = 'Processing…';
  btn.style.opacity = '.7';
});
</script>

<?php require_once 'includes/footer.php'; ?>
