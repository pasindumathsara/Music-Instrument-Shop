<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$cart     = $_SESSION['cart'] ?? [];
if (empty($cart)) { header("Location: " . BASE_URL . "/cart.php"); exit(); }

$subtotal  = getCartSubtotal();
$allDigital = isCartAllDigital($conn);   // no shipping for pure-digital orders
$shipping  = $allDigital ? 0.00 : calculateShipping($subtotal, $conn);
$total     = $subtotal + $shipping;


$error = '';

// ── Place Order ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($allDigital) {
        $name = 'Digital Delivery'; $address = 'Digital Delivery'; $city = 'Digital'; $zip = '000000';
    } else {
        $name    = trim($_POST['ship_name']    ?? '');
        $address = trim($_POST['ship_address'] ?? '');
        $city    = trim($_POST['ship_city']    ?? '');
        $zip     = trim($_POST['ship_zip']     ?? '');
    }

    $payMethod = $_POST['payment_method'] ?? 'card';
    if (!in_array($payMethod, ['card','bank_transfer','cash_on_delivery'])) $payMethod = 'card';

    if (!$allDigital && (!$name || !$address || !$city || !$zip)) {
        $error = "Please fill in all shipping fields.";
    } else {
        // RE-CALCULATE SHIPPING (Ensure it's fresh from DB before storage)
        $shipping = $allDigital ? 0.00 : calculateShipping($subtotal, $conn);
        $total    = $subtotal + $shipping;

        $conn->begin_transaction();
        try {
            $userId  = (int)$_SESSION['user_id'];

            // Initial status depends on payment method
            $initStatus = match($payMethod) {
                'card'             => 'pending',
                'bank_transfer'    => 'pending',
                'cash_on_delivery' => 'pending',
                default            => 'pending',
            };

            $ins = $conn->prepare(
                "INSERT INTO orders
                 (user_id, total_amount, shipping_cost, status, shipping_name, shipping_address, shipping_city, shipping_zip, payment_method)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $ins->bind_param("iddssssss", $userId, $total, $shipping, $initStatus, $name, $address, $city, $zip, $payMethod);
            $ins->execute();
            $orderId = $conn->insert_id;

            // Record initial status history
            $hist = $conn->prepare("INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)");
            $histNote = 'Order placed via ' . str_replace('_',' ', $payMethod);
            $histBy   = sanitize($_SESSION['user_name'] ?? 'customer');
            $hist->bind_param("isss", $orderId, $initStatus, $histNote, $histBy);
            $hist->execute();

            // Insert order items & update stock
            foreach ($cart as $item) {
                $pid = (int)$item['id'];
                $qty = (int)$item['quantity'];
                $prc = (float)$item['price'];

                $pcheck = $conn->prepare("SELECT stock, product_type FROM products WHERE id=? FOR UPDATE");
                $pcheck->bind_param("i", $pid);
                $pcheck->execute();
                $prodRow = $pcheck->get_result()->fetch_assoc();
                $isDigitalItem = ($prodRow['product_type'] ?? 'physical') === 'digital';

                if (!$isDigitalItem) {
                    if (!$prodRow || $prodRow['stock'] < $qty) {
                        throw new Exception("Insufficient stock for one or more items.");
                    }
                }

                $itemIns = $conn->prepare(
                    "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)"
                );
                $itemIns->bind_param("iiid", $orderId, $pid, $qty, $prc);
                $itemIns->execute();

                if (!$isDigitalItem) {
                    $upd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id=?");
                    $upd->bind_param("ii", $qty, $pid);
                    $upd->execute();
                }
            }

            $conn->commit();
            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderId;

            // Redirect: card → payment page; others → order confirm directly
            if ($payMethod === 'card') {
                header("Location: " . BASE_URL . "/payment.php?order_id=" . $orderId); exit();
            } else {
                header("Location: " . BASE_URL . "/order_confirm.php?order_id=" . $orderId); exit();
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Order failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "Checkout";
require_once 'includes/header.php';
?>

<div class="container section">
  <h1 class="section-title" style="margin-bottom:32px;">Secure <span>Checkout</span></h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="checkout-grid">

      <!-- Shipping / Digital -->
      <div>
        <?php if ($allDigital): ?>
          <!-- Digital-only order: no shipping needed -->
          <div class="card" style="margin-bottom:24px;border:2px solid #6d28d9;">
            <div class="card-header" style="background:linear-gradient(135deg,#1a0533,#6d28d9);color:#fff;">
              <span class="card-title" style="color:#fff;">Instant Digital Download</span>
            </div>
            <div class="card-body">
              <div style="display:flex;align-items:flex-start;gap:14px;padding:8px 0;">
                <div style="font-size:2.5rem;flex-shrink:0;"></div>
                <div>
                  <strong style="color:var(--text);font-size:.95rem;">No shipping required!</strong><br>
                  <span style="color:var(--muted);font-size:.83rem;line-height:1.7;">
                    Your digital product(s) will be available for immediate download
                    on the order confirmation page after payment is complete.
                  </span>
                </div>
              </div>
              <div style="margin-top:14px;padding:10px 14px;background:#f8fafc;border-radius:8px;font-size:.78rem;color:var(--muted);border:1px solid var(--border);">
                Files will be securely delivered to your account. You can re-download from your Orders page at any time.
              </div>
            </div>
          </div>
        <?php else: ?>
          <!-- Physical: shipping form -->
          <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><span class="card-title"> Shipping Information</span></div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="ship_name" class="form-control"
                       value="<?php echo sanitize($_SESSION['user_name'] ?? ''); ?>"
                       placeholder="John Doe" required>
              </div>
              <div class="form-group">
                <label class="form-label">Street Address</label>
                <input type="text" name="ship_address" class="form-control"
                       placeholder="123 Music Street" required>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">City</label>
                  <input type="text" name="ship_city" class="form-control" placeholder="London" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Postcode</label>
                  <input type="text" name="ship_zip" class="form-control" placeholder="SW1A 1AA" required>
                </div>
              </div>

          </div>
        </div>
        <?php endif; // physical/digital ?>

        <!-- Order Items -->
        <div class="card">

          <div class="card-header"><span class="card-title"> Order Items</span></div>
          <div class="card-body" style="padding:0;">
            <?php foreach ($cart as $item): ?>
              <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border);">
                <div style="width:50px;height:50px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:1.5rem;opacity:.5;flex-shrink:0;">
                  
                </div>
                <div style="flex:1;">
                  <div style="font-weight:600;font-size:.875rem;"><?php echo sanitize($item['name']); ?></div>
                  <div style="font-size:.78rem;color:var(--muted);">Qty: <?php echo $item['quantity']; ?></div>
                </div>
                <div style="font-weight:700;color:var(--accent);">
                  <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Order Summary -->
      <div class="order-sum">
        <div class="card">
          <div class="card-header"><span class="card-title"> Order Summary</span></div>
          <div class="card-body">
            <?php if (isFreeShipping($subtotal)): ?>
              <div class="free-ship"> Free shipping applied!</div>
            <?php endif; ?>
            <div class="sum-row"><span>Subtotal</span><span><?php echo formatPrice($subtotal); ?></span></div>
            <div class="sum-row">
              <span>Shipping</span>
              <span>
                <?php echo $shipping > 0
                    ? formatPrice($shipping)
                    : '<span style="color:var(--success);font-weight:700;">FREE</span>'; ?>
              </span>
            </div>
            <div class="sum-total">
              <span>Total</span><span class="v"><?php echo formatPrice($total); ?></span>
            </div>

            <div style="margin-top:24px;">
              <!-- Payment Method -->
              <div style="margin-bottom:20px;">
                <div style="font-size:.83rem;font-weight:700;margin-bottom:12px;color:var(--text);">Payment Method</div>

                <label id="pm-card" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--accent);border-radius:10px;cursor:pointer;margin-bottom:8px;background:#fff;transition:all .2s;">
                  <input type="radio" name="payment_method" value="card" checked onchange="updatePM()" style="accent-color:var(--accent);width:18px;height:18px;">
                  <div>
                    <div style="font-weight:700;font-size:.9rem;">Credit / Debit Card</div>
                    <div style="font-size:.75rem;color:var(--muted);">Visa, Mastercard, Amex</div>
                  </div>
                  <span style="margin-left:auto;font-size:1.2rem;">&#128179;</span>
                </label>

                <label id="pm-bank" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--border);border-radius:10px;cursor:pointer;margin-bottom:8px;background:#fff;transition:all .2s;">
                  <input type="radio" name="payment_method" value="bank_transfer" onchange="updatePM()" style="accent-color:var(--accent);width:18px;height:18px;">
                  <div>
                    <div style="font-weight:700;font-size:.9rem;">Bank Transfer</div>
                    <div style="font-size:.75rem;color:var(--muted);">Pay via bank / IBAN</div>
                  </div>
                  <span style="margin-left:auto;font-size:1.2rem;">&#127981;</span>
                </label>

                <?php if (!$allDigital): ?>
                <label id="pm-cod" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--border);border-radius:10px;cursor:pointer;margin-bottom:8px;background:#fff;transition:all .2s;">
                  <input type="radio" name="payment_method" value="cash_on_delivery" onchange="updatePM()" style="accent-color:var(--accent);width:18px;height:18px;">
                  <div>
                    <div style="font-weight:700;font-size:.9rem;">Cash on Delivery</div>
                    <div style="font-size:.75rem;color:var(--muted);">Pay when your order arrives</div>
                  </div>
                  <span style="margin-left:auto;font-size:1.2rem;">&#128176;</span>
                </label>
                <?php endif; ?>

                <!-- Bank transfer details panel -->
                <div id="bankDetails" style="display:none;margin-top:10px;padding:14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;font-size:.82rem;line-height:1.8;color:#1e3a5f;">
                  <strong>Bank Transfer Details</strong><br>
                  Bank: <strong>Melody Masters Bank Ltd</strong><br>
                  Account Name: <strong>Melody Masters Ltd</strong><br>
                  Account No: <strong>12345678</strong>&nbsp;&nbsp;Sort Code: <strong>04-00-04</strong><br>
                  IBAN: <strong>GB29 MMBU 0400 0412 3456 78</strong><br>
                  Reference: <strong>Your Order Number (shown after placing)</strong><br>
                  <span style="color:#64748b;">Orders confirm within 1–2 business days after payment received.</span>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="placeBtn">
              Place Order &amp; Pay by Card
            </button>
            <a href="<?php echo BASE_URL; ?>/cart.php"
               class="btn btn-ghost btn-block" style="margin-top:10px;"> Back to Cart</a>
          </div>
        </div>
      </div>
    </div>
</div>

<script>
function updatePM() {
  const val   = document.querySelector('input[name="payment_method"]:checked')?.value ?? 'card';
  const btn   = document.getElementById('placeBtn');
  const bank  = document.getElementById('bankDetails');
  const labels = {
    card:             'Place Order & Pay by Card',
    bank_transfer:    'Place Order – Pay by Bank Transfer',
    cash_on_delivery: 'Place Order – Cash on Delivery'
  };
  if (btn) btn.textContent = labels[val] || labels.card;
  if (bank) bank.style.display = val === 'bank_transfer' ? 'block' : 'none';

  // Highlight selected label
  ['pm-card','pm-bank','pm-cod'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    const radio = el.querySelector('input[type=radio]');
    el.style.borderColor = radio?.checked ? 'var(--accent)' : 'var(--border)';
    el.style.background  = radio?.checked ? '#fff8f8'       : '#fff';
  });
}
// Run once to set initial style
updatePM();
</script>

<?php require_once 'includes/footer.php'; ?>
