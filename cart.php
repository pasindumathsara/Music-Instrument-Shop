<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// ── Cart Actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD
    if ($action === 'add') {
        // ── LOGIN REQUIRED ──────────────────────────────────────
        if (!isLoggedIn()) {
            // Build a sensible "go back to" URL after login
            $pid = (int)($_POST['product_id'] ?? 0);
            $returnUrl = $pid > 0
                ? BASE_URL . '/product.php?id=' . $pid
                : BASE_URL . '/home.php';
            setFlash('info', 'Please log in to add items to your cart.');
            header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($returnUrl));
            exit();
        }

        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));

        // Fetch product — no stock>0 filter so digital products (stock=0/unlimited) are allowed
        $stmt = $conn->prepare("SELECT id,name,price,shipping_cost,stock,image,product_type FROM products WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();

        if ($prod) {
            $isDigital = ($prod['product_type'] ?? 'physical') === 'digital';

            // Physical: enforce stock limit. Digital: always allow qty=1
            if (!$isDigital && (int)$prod['stock'] <= 0) {
                setFlash('danger', 'Sorry, that product is out of stock.');
            } else {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                $key      = (string)$pid;
                $existing = $_SESSION['cart'][$key]['quantity'] ?? 0;
                $newQty   = $isDigital
                    ? 1  // digital: always qty 1
                    : min($existing + $qty, (int)$prod['stock']);
                $_SESSION['cart'][$key] = [
                    'id'           => $prod['id'],
                    'name'         => $prod['name'],
                    'price'        => $prod['price'],
                    'image'        => $prod['image'],
                    'quantity'     => $newQty,
                    'product_type' => $prod['product_type'],
                    'shipping_cost' => $prod['shipping_cost'],
                ];
                setFlash('success', '"' . $prod['name'] . '" added to cart!');
            }
        }


        // Redirect back
        $redirect = $_POST['redirect'] ?? '';
        if ($redirect === 'shop' || $redirect === 'home') {
            header("Location: " . BASE_URL . "/home.php");
        } else {
            header("Location: " . BASE_URL . "/cart.php");
        }
        exit();
    }

    // UPDATE
    if ($action === 'update') {
        foreach ($_POST['quantities'] ?? [] as $key => $qty) {
            $qty = (int)$qty;
            if (isset($_SESSION['cart'][$key])) {
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$key]);
                } else {
                    $_SESSION['cart'][$key]['quantity'] = $qty;
                }
            }
        }
        setFlash('success', 'Cart updated.');
        header("Location: " . BASE_URL . "/cart.php"); exit();
    }

    // REMOVE
    if ($action === 'remove') {
        $key = (string)($_POST['product_id'] ?? '');
        unset($_SESSION['cart'][$key]);
        setFlash('success', 'Item removed from cart.');
        header("Location: " . BASE_URL . "/cart.php"); exit();
    }

    // CLEAR
    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header("Location: " . BASE_URL . "/cart.php"); exit();
    }
}

$pageTitle = "Shopping Cart";
$cart      = $_SESSION['cart'] ?? [];
$subtotal  = getCartSubtotal();
$shipping  = calculateShipping($subtotal, $conn);
$total     = $subtotal + $shipping;

require_once 'includes/header.php';
?>

<div class="container section">
  <h1 class="section-title" style="margin-bottom:32px;"> Shopping <span>Cart</span></h1>

  <?php if (empty($cart)): ?>
    <div class="empty-state">
      <div class="icon"></div>
      <h3>Your cart is empty</h3>
      <p>Browse our collection and add some instruments!</p>
      <a href="<?php echo BASE_URL; ?>/home.php" class="btn btn-primary btn-lg">Shop Now</a>
    </div>
  <?php else: ?>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <div class="cart-layout">

        <!-- Cart Items -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Items (<?php echo getCartCount(); ?>)</span>
            <button type="button" onclick="confirmClear()" class="btn btn-ghost btn-sm"> Clear Cart</button>
          </div>
          <div class="card-body">
            <?php foreach ($cart as $key => $item): ?>
              <?php
                $imgSrc = ($item['image'] && file_exists(UPLOAD_DIR . $item['image']))
                    ? UPLOAD_URL . htmlspecialchars($item['image'])
                    : null;
                $lineTotal = (float)$item['price'] * (int)$item['quantity'];
              ?>
              <div class="cart-item">
                <!-- Image -->
                <?php if ($imgSrc): ?>
                  <img src="<?php echo $imgSrc; ?>" alt="<?php echo sanitize($item['name']); ?>" class="cart-img">
                <?php else: ?>
                  <div class="cart-img" style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;opacity:.3;">🎵</div>
                <?php endif; ?>

                <!-- Info -->
                <div class="cart-info" style="flex:1;">
                  <div class="cart-name">
                    <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $item['id']; ?>">
                      <?php echo sanitize($item['name']); ?>
                    </a>
                  </div>
                  <div class="cart-price"><?php echo formatPrice($item['price']); ?> each</div>
                </div>

                <!-- Qty -->
                <div class="qty-wrap" style="margin:0 16px;">
                  <button type="button" class="qty-btn" onclick="adjItem('<?php echo $key; ?>',-1)">−</button>
                  <input type="number" name="quantities[<?php echo htmlspecialchars($key); ?>]"
                         id="qty_<?php echo $key; ?>"
                         value="<?php echo $item['quantity']; ?>" min="0" max="99" class="qty-in">
                  <button type="button" class="qty-btn" onclick="adjItem('<?php echo $key; ?>',1)">+</button>
                </div>

                <!-- Line total -->
                <div style="font-weight:700;color:var(--accent);min-width:80px;text-align:right;">
                  <?php echo formatPrice($lineTotal); ?>
                </div>

                <!-- Remove -->
                <button type="button" onclick="removeItem('<?php echo $key; ?>')"
                        style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.3rem;margin-left:12px;"
                        title="Remove">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
          <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:12px;">
            <button type="submit" class="btn btn-ghost">↻ Update Cart</button>
            <a href="<?php echo BASE_URL; ?>/home.php" class="btn btn-outline">← Continue Shopping</a>
          </div>
        </div>

        <!-- Order Summary -->
        <div class="order-sum">
          <div class="card">
            <div class="card-header"><span class="card-title">Order Summary</span></div>
            <div class="card-body">
              <?php if (isFreeShipping($subtotal)): ?>
                <div class="free-ship"> You qualify for FREE shipping!</div>
              <?php else: ?>
                <div style="background:var(--info-bg);color:#1e40af;padding:10px 14px;border-radius:8px;font-size:.78rem;font-weight:600;margin-bottom:14px;">
                  Add <?php echo formatPrice(SHIPPING_THRESHOLD - $subtotal); ?> more for free shipping!
                </div>
              <?php endif; ?>

              <div class="sum-row"><span>Subtotal</span><span><?php echo formatPrice($subtotal); ?></span></div>
              <div class="sum-row">
                <span>Shipping</span>
                <span><?php echo $shipping > 0 ? formatPrice($shipping) : '<span style="color:var(--success);font-weight:700;">FREE</span>'; ?></span>
              </div>
              <div class="sum-total">
                <span>Total</span>
                <span class="v"><?php echo formatPrice($total); ?></span>
              </div>
              <div style="margin-top:20px;">
                <?php if (isLoggedIn()): ?>
                  <a href="<?php echo BASE_URL; ?>/checkout.php" class="btn btn-primary btn-block btn-lg">
                    Proceed to Checkout →
                  </a>
                <?php else: ?>
                  <a href="<?php echo BASE_URL; ?>/login.php?redirect=<?php echo urlencode(BASE_URL . '/checkout.php'); ?>"
                     class="btn btn-primary btn-block btn-lg">Login to Checkout</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>

    <!-- Hidden remove form -->
    <form id="removeForm" method="POST" style="display:none;">
      <input type="hidden" name="action" value="remove">
      <input type="hidden" name="product_id" id="removeId">
    </form>
    <form id="clearForm" method="POST" style="display:none;">
      <input type="hidden" name="action" value="clear">
    </form>
  <?php endif; ?>
</div>

<script>
function adjItem(key, d) {
  const inp = document.getElementById('qty_' + key);
  inp.value = Math.max(0, parseInt(inp.value) + d);
}
function removeItem(key) {
  document.getElementById('removeId').value = key;
  document.getElementById('removeForm').submit();
}
function confirmClear() {
  if (confirm('Clear all items from cart?')) document.getElementById('clearForm').submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>
