<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: " . BASE_URL . "/home.php"); exit(); }

// Load product
$stmt = $conn->prepare(
    "SELECT p.*, c.name AS cat_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) { header("Location: " . BASE_URL . "/home.php"); exit(); }

// Rating
$rating = getProductRating($conn, $id);

// Reviews
$revStmt = $conn->prepare(
    "SELECT r.*, u.name AS user_name
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ?
     ORDER BY r.created_at DESC"
);
$revStmt->bind_param("i", $id);
$revStmt->execute();
$reviews = $revStmt->get_result();

// Handle review submission
$reviewError = $reviewSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    requireLogin();
    $userId = (int)$_SESSION['user_id'];

    if (!hasPurchasedProduct($conn, $userId, $id)) {
        $reviewError = "You can only review products you have purchased.";
    } elseif (hasReviewedProduct($conn, $userId, $id)) {
        $reviewError = "You have already reviewed this product.";
    } else {
        $rating_in  = max(1, min(5, (int)$_POST['rating']));
        $comment_in = trim($_POST['comment'] ?? '');

        $ins = $conn->prepare(
            "INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?)"
        );
        $ins->bind_param("iiis", $id, $userId, $rating_in, $comment_in);
        if ($ins->execute()) {
            setFlash('success', 'Thank you for your review!');
            header("Location: " . BASE_URL . "/product.php?id=$id");
            exit();
        }
        $reviewError = "Could not submit review. Please try again.";
    }
}

// Handle add-to-cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cart'])) {
    // ── LOGIN REQUIRED ────────────────────────────────────────
    if (!isLoggedIn()) {
        $returnUrl = BASE_URL . '/product.php?id=' . $id;
        setFlash('info', 'Please log in to add items to your cart.');
        header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($returnUrl));
        exit();
    }

    $isDigitalProd = ($p['product_type'] ?? 'physical') === 'digital';
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    // Physical: cap at stock; Digital: always qty=1 (unlimited)
    if (!$isDigitalProd) {
        if ($p['stock'] < $qty) { $qty = $p['stock']; }
    } else {
        $qty = 1;
    }

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $pid = (string)$id;
    if (!$isDigitalProd && isset($_SESSION['cart'][$pid])) {
        $_SESSION['cart'][$pid]['quantity'] = min(
            $_SESSION['cart'][$pid]['quantity'] + $qty,
            (int)$p['stock']
        );
    } else {
        $_SESSION['cart'][$pid] = [
            'id'           => $id,
            'name'         => $p['name'],
            'price'        => $p['price'],
            'image'        => $p['image'],
            'quantity'     => $qty,
            'product_type' => $p['product_type'] ?? 'physical',
        ];
    }
    setFlash('success', '"' . $p['name'] . '" added to cart!');
    header("Location: " . BASE_URL . "/product.php?id=$id");
    exit();

}

$pageTitle = $p['name'];
$pageDesc  = substr(strip_tags($p['description'] ?? ''), 0, 155);

// Can user review?
$canReview  = false;
$hasReviewed = false;
if (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];
    $canReview  = hasPurchasedProduct($conn, $uid, $id);
    $hasReviewed = hasReviewedProduct($conn, $uid, $id);
}

require_once 'includes/header.php';
?>

<div class="container section">

  <!-- Breadcrumb -->
  <nav class="breadcrumb">
    <a href="<?php echo BASE_URL; ?>/home.php">Shop</a>
    <?php if ($p['cat_name']): ?>
      &rsaquo; <a href="<?php echo BASE_URL; ?>/home.php?cat=<?php echo $p['category_id']; ?>">
        <?php echo sanitize($p['cat_name']); ?>
      </a>
    <?php endif; ?>
    &rsaquo; <span class="active"><?php echo sanitize($p['name']); ?></span>
  </nav>

  <!-- Product Detail -->
  <div class="pd-grid">
    <!-- Image -->
    <div class="pd-img">
      <?php echo productImageTag($p['image'] ?? null, $p['name']); ?>
    </div>

    <!-- Info -->
    <div>
      <div style="font-size:.75rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
        <?php echo sanitize($p['cat_name'] ?? 'Instrument'); ?>
      </div>
      <h1 class="pd-title"><?php echo sanitize($p['name']); ?></h1>

      <!-- Rating -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
        <?php echo renderStars($rating['avg']); ?>
        <span style="font-weight:600;font-size:.9rem;"><?php echo $rating['avg']; ?></span>
        <span style="color:var(--muted);font-size:.83rem;">(<?php echo $rating['total']; ?> reviews)</span>
      </div>

      <div class="pd-price"><?php echo formatPrice($p['price']); ?></div>
      <p class="pd-desc"><?php echo nl2br(sanitize($p['description'] ?? '')); ?></p>

      <!-- Stock / Availability -->
      <?php $isDigital = ($p['product_type'] ?? 'physical') === 'digital'; ?>
      <div class="stock-row">
        <?php if ($isDigital): ?>
          <span class="dot digital"></span>
          <span class="status-text digital">Digital Download – Always Available</span>
        <?php elseif ((int)$p['stock'] > 10): ?>
          <span class="dot"></span> In Stock (<?php echo $p['stock']; ?> available)
        <?php elseif ((int)$p['stock'] > 0): ?>
          <span class="dot low"></span> Low Stock – only <?php echo $p['stock']; ?> left!
        <?php else: ?>
          <span class="dot out"></span> <span class="status-text out">Out of Stock</span>
        <?php endif; ?>
      </div>

      <!-- Add to Cart / Buy -->
      <?php if ($isDigital || (int)$p['stock'] > 0): ?>
        <form method="POST" class="add-cart-form">
          <input type="hidden" name="add_cart" value="1">
          <?php if (!$isDigital): ?>
          <div class="qty-selector">
            <button type="button" class="qty-btn" onclick="adjQty(-1)">−</button>
            <input id="qtyInput" type="number" name="qty" value="1" min="1"
                   max="<?php echo $p['stock']; ?>" class="qty-in">
            <button type="button" class="qty-btn" onclick="adjQty(1)">+</button>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-lg btn-buy"
            <?php if (!isLoggedIn()): ?>
            onclick="event.preventDefault();
              window.location='<?php echo BASE_URL; ?>/login.php?redirect=<?php echo urlencode(BASE_URL . '/product.php?id=' . $id); ?>';"
            <?php endif; ?>
          ><?php echo $isDigital ? '&#8659; Buy &amp; Download' : '&#128722; Add to Cart'; ?></button>
        </form>
      <?php else: ?>
        <button class="btn btn-ghost btn-lg" disabled>Out of Stock</button>
      <?php endif; ?>

      <?php if ($isDigital): ?>
        <div style="margin-top:20px;padding:14px;background:#ede9fe;border-radius:8px;font-size:.83rem;color:#5b21b6;border:1px solid #c4b5fd;">
          ⚡ <strong>Instant delivery.</strong> Download immediately after payment. Re-download anytime from My Orders.
        </div>
      <?php else: ?>
        <div style="margin-top:20px;padding:14px;background:var(--success-bg);border-radius:8px;font-size:.83rem;color:#065f46;">
          <strong>Free shipping</strong> on orders over £<?php echo number_format(SHIPPING_THRESHOLD,0); ?> · 
          Otherwise flat £<?php echo number_format(SHIPPING_COST,2); ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Reviews Section -->
  <div style="margin-top:64px;">
    <h2 class="section-title" style="margin-bottom:28px;">Customer <span>Reviews</span></h2>

    <!-- Review Form -->
    <?php if (isLoggedIn() && $canReview && !$hasReviewed): ?>
      <div class="card" style="margin-bottom:32px;">
        <div class="card-header"><span class="card-title"> Write a Review</span></div>
        <div class="card-body">
          <?php if ($reviewError): ?>
            <div class="alert alert-danger"><?php echo sanitize($reviewError); ?></div>
          <?php endif; ?>
          <form method="POST">
            <div class="form-group">
              <label class="form-label">Your Rating</label>
              <div id="starPicker" style="display:flex;gap:6px;font-size:2rem;cursor:pointer;">
                <?php for ($s=1;$s<=5;$s++): ?>
                  <span data-val="<?php echo $s; ?>" style="color:#d1d5db;" class="star-pick">★</span>
                <?php endfor; ?>
              </div>
              <input type="hidden" name="rating" id="ratingVal" value="5">
            </div>
            <div class="form-group">
              <label class="form-label">Your Review</label>
              <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience with this product…"></textarea>
            </div>
            <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
          </form>
        </div>
      </div>
    <?php elseif (isLoggedIn() && !$canReview && !$hasReviewed): ?>
      <div class="alert alert-info" style="margin-bottom:28px;">
        Buy this product to unlock the review form.
      </div>
    <?php elseif (!isLoggedIn()): ?>
      <div class="alert alert-info" style="margin-bottom:28px;">
        <a href="<?php echo BASE_URL; ?>/login.php" style="color:var(--info);font-weight:700;">Login</a>
        &nbsp;and purchase this product to write a review.
      </div>
    <?php endif; ?>

    <!-- Reviews List -->
    <?php if ($reviews->num_rows === 0): ?>
      <div class="empty-state" style="padding:40px;">
        <div class="icon" style="font-size:3rem;"></div>
        <p style="color:var(--muted);">No reviews yet. Be the first!</p>
      </div>
    <?php else: ?>
      <?php while ($rev = $reviews->fetch_assoc()): ?>
        <div class="review-card">
          <div class="rh">
            <div>
              <div class="rn"><?php echo sanitize($rev['user_name']); ?></div>
              <?php echo renderStars((float)$rev['rating']); ?>
            </div>
            <span class="rd"><?php echo formatDate($rev['created_at']); ?></span>
          </div>
          <?php if ($rev['comment']): ?>
            <p style="color:var(--muted);font-size:.875rem;line-height:1.7;margin-top:8px;">
              <?php echo nl2br(sanitize($rev['comment'])); ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>

<script>
// Qty adjuster
function adjQty(d) {
  const inp = document.getElementById('qtyInput');
  let v = parseInt(inp.value) + d;
  inp.value = Math.max(1, Math.min(parseInt(inp.max), v));
}
// Star picker
const picks = document.querySelectorAll('.star-pick');
picks.forEach(s => {
  s.addEventListener('click', () => {
    const val = parseInt(s.dataset.val);
    document.getElementById('ratingVal').value = val;
    picks.forEach((p,i) => p.style.color = i < val ? 'var(--warning)' : '#d1d5db');
  });
  s.addEventListener('mouseenter', () => {
    const val = parseInt(s.dataset.val);
    picks.forEach((p,i) => p.style.color = i < val ? 'var(--warning)' : '#d1d5db');
  });
});
// Init stars
if (picks.length) picks.forEach((p,i) => p.style.color = i < 5 ? 'var(--warning)' : '#d1d5db');
</script>

<?php require_once 'includes/footer.php'; ?>
