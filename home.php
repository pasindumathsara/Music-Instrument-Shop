<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = "Shop";
$pageDesc  = "Browse our full collection of guitars, keyboards, drums and more.";

// ── Filters ────────────────────────────────────────────────────────
$search  = trim($_GET['q']   ?? '');
$catId   = (int)($_GET['cat'] ?? 0);
$sort    = $_GET['sort'] ?? 'newest';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// Build WHERE clause
$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $where   .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($catId > 0) {
    $where   .= " AND p.category_id = ?";
    $params[] = $catId;
    $types   .= 'i';
}

// Sort
$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
    default      => 'p.created_at DESC',
};

// Total count
$countSql  = "SELECT COUNT(*) FROM products p $where";
$countStmt = $conn->prepare($countSql);
if ($params) { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalRows / $perPage);

// Products
$sql  = "SELECT p.*, c.name AS cat_name,
                ROUND(AVG(r.rating),1) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN reviews    r ON r.product_id = p.id
         $where
         GROUP BY p.id
         ORDER BY $orderBy
         LIMIT ? OFFSET ?";

$allParams   = $params;
$allParams[] = $perPage;
$allParams[] = $offset;
$allTypes    = $types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$products = $stmt->get_result();

// Categories for filter bar
$cats = $conn->query("SELECT * FROM categories ORDER BY name");

require_once 'includes/header.php';
?>

<!-- Hero -->
<div class="hero home-hero" style="background-image: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('<?php echo BASE_URL; ?>/assets/images/banner.png');">
  <div class="hero-content">
    <h1>Find Your <span>Perfect Instrument</span></h1>
    <p>Explore our curated collection of premium musical instruments for every skill level.</p>
    <form class="search-bar" method="GET" action="<?php echo BASE_URL; ?>/home.php">
      <input type="text" name="q" placeholder="Search guitars, pianos, drums…"
             value="<?php echo sanitize($search); ?>">
      <?php if ($catId): ?>
        <input type="hidden" name="cat" value="<?php echo $catId; ?>">
      <?php endif; ?>
      <button type="submit">Search</button>
    </form>
  </div>
</div>

<!-- Category Filter Bar -->
<div class="filter-bar">
  <div class="filter-inner">
    <a href="<?php echo BASE_URL; ?>/home.php<?php echo $search ? '?q='.urlencode($search) : ''; ?>"
       class="fp <?php echo $catId === 0 ? 'active' : ''; ?>">All</a>
    <?php while ($cat = $cats->fetch_assoc()): ?>
      <?php
        $href = BASE_URL . '/home.php?cat=' . $cat['id'];
        if ($search) $href .= '&q=' . urlencode($search);
      ?>
      <a href="<?php echo $href; ?>"
         class="fp <?php echo $catId === (int)$cat['id'] ? 'active' : ''; ?>">
        <?php echo sanitize($cat['name']); ?>
      </a>
    <?php endwhile; ?>
  </div>
</div>

<!-- Products Section -->
<div class="container section">
  <div class="section-header">
    <h2 class="section-title">
      <?php if ($search): ?>
        Results for "<span><?php echo sanitize($search); ?></span>"
      <?php elseif ($catId): ?>
        <?php
          $catName = $conn->prepare("SELECT name FROM categories WHERE id=?");
          $catName->bind_param("i", $catId);
          $catName->execute();
          $cn = $catName->get_result()->fetch_assoc()['name'] ?? 'Products';
          echo "<span>" . sanitize($cn) . "</span>";
        ?>
      <?php else: ?>
        All <span>Products</span>
      <?php endif; ?>
      <small style="font-size:.9rem;font-weight:400;color:var(--muted);margin-left:8px;">
        (<?php echo $totalRows; ?> items)
      </small>
    </h2>

    <!-- Sort -->
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
      <?php if ($catId):  ?><input type="hidden" name="cat" value="<?php echo $catId; ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q"   value="<?php echo sanitize($search); ?>"><?php endif; ?>
      <select name="sort" class="form-control" style="width:auto;" onchange="this.form.submit()">
        <option value="newest"     <?php echo $sort==='newest'     ? 'selected':'' ?>>Newest</option>
        <option value="price_asc"  <?php echo $sort==='price_asc'  ? 'selected':'' ?>>Price: Low→High</option>
        <option value="price_desc" <?php echo $sort==='price_desc' ? 'selected':'' ?>>Price: High→Low</option>
        <option value="name_asc"   <?php echo $sort==='name_asc'   ? 'selected':'' ?>>Name A–Z</option>
      </select>
    </form>
  </div>

  <?php if ($products->num_rows === 0): ?>
    <div class="empty-state">
      <div class="icon"></div>
      <h3>No products found</h3>
      <p>Try a different search term or browse all categories.</p>
      <a href="<?php echo BASE_URL; ?>/home.php" class="btn btn-primary">Browse All</a>
    </div>
  <?php else: ?>
    <div class="products-grid">
      <?php while ($p = $products->fetch_assoc()): ?>
        <div class="product-card">
          <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $p['id']; ?>">
            <div class="pc-img">
              <?php echo productImageTag($p['image'] ?? null, $p['name']); ?>
              <span class="cat-badge"><?php echo sanitize($p['cat_name'] ?? ''); ?></span>
              <?php if ($p['stock'] > 0 && $p['stock'] <= 5 && ($p['product_type'] ?? 'physical') === 'physical'): ?>
                <span class="stock-low">Only <?php echo $p['stock']; ?> left!</span>
              <?php endif; ?>
              <?php if (($p['product_type'] ?? 'physical') === 'digital'): ?>
                <span style="position:absolute;bottom:8px;left:8px;background:rgba(109,40,217,.9);color:#fff;font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:20px;">&#9660; Digital</span>
              <?php endif; ?>
            </div>
          </a>
          <div class="pc-body">
            <div class="pc-cat"><?php echo sanitize($p['cat_name'] ?? 'Instrument'); ?></div>
            <div class="pc-name">
              <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $p['id']; ?>">
                <?php echo sanitize($p['name']); ?>
              </a>
            </div>
            <div class="pc-rating">
              <?php echo renderStars((float)($p['avg_rating'] ?? 0)); ?>
              <span class="rc">(<?php echo (int)($p['review_count'] ?? 0); ?>)</span>
            </div>
            <div class="pc-footer">
              <span class="pc-price"><?php echo formatPrice($p['price']); ?></span>
              <?php
                $isDigital = ($p['product_type'] ?? 'physical') === 'digital';
                $canBuy    = $isDigital || (int)$p['stock'] > 0;
              ?>
              <?php if ($canBuy): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>/cart.php">
                  <input type="hidden" name="action"     value="add">
                  <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="redirect"   value="home">
                  <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo $isDigital ? '&#8659; Buy' : '&#128722; Add'; ?>
                  </button>
                </form>
              <?php else: ?>
                <span class="badge badge-cancelled">Out of Stock</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;justify-content:center;gap:8px;margin-top:40px;">
        <?php
          $baseUrl = BASE_URL . '/home.php?';
          $qArr = [];
          if ($search) $qArr[] = 'q=' . urlencode($search);
          if ($catId)  $qArr[] = 'cat=' . $catId;
          if ($sort)   $qArr[] = 'sort=' . $sort;
          $base = implode('&', $qArr);
        ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="<?php echo $baseUrl . $base . '&page=' . $i; ?>"
             class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-ghost'; ?> btn-sm">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
