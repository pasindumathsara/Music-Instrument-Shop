<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = "Manage Categories";
$error = $success = '';

// ── DELETE ───────────────────────────────────────────────────────────
$delId = (int)($_GET['delete'] ?? 0);
if ($delId > 0) {
    // Check if any products are using this category
    $chk = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
    $chk->bind_param("i", $delId);
    $chk->execute();
    $inUse = $chk->get_result()->fetch_row()[0];
    if ($inUse > 0) {
        setFlash('danger', "Cannot delete: $inUse product(s) are assigned to this category.");
    } else {
        $conn->prepare("DELETE FROM categories WHERE id=?")->execute() ||
        ($d = $conn->prepare("DELETE FROM categories WHERE id=?")) &&
        $d->bind_param("i", $delId) &&
        $d->execute();
        $del = $conn->prepare("DELETE FROM categories WHERE id=?");
        $del->bind_param("i", $delId);
        $del->execute();
        setFlash('success', 'Category deleted.');
    }
    header("Location: " . BASE_URL . "/admin/manage_categories.php"); exit();
}

// ── ADD / EDIT ────────────────────────────────────────────────────────
$editId  = (int)($_GET['edit'] ?? 0);
$editCat = null;
if ($editId > 0) {
    $ep = $conn->prepare("SELECT * FROM categories WHERE id=?");
    $ep->bind_param("i", $editId);
    $ep->execute();
    $editCat = $ep->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cname = trim($_POST['cat_name'] ?? '');
    $cid   = (int)($_POST['cat_id'] ?? 0);

    if (!$cname) {
        $error = "Category name is required.";
    } else {
        if ($cid > 0) {
            $upd = $conn->prepare("UPDATE categories SET name=? WHERE id=?");
            $upd->bind_param("si", $cname, $cid);
            $upd->execute();
            setFlash('success', 'Category updated.');
        } else {
            $ins = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $ins->bind_param("s", $cname);
            $ins->execute();
            setFlash('success', 'Category "' . htmlspecialchars($cname) . '" added.');
        }
        header("Location: " . BASE_URL . "/admin/manage_categories.php"); exit();
    }
}

// ── LIST ──────────────────────────────────────────────────────────────
$cats = $conn->query(
    "SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.name ASC"
);

require_once 'includes/admin_header.php';
?>

<div class="admin-header-bar">
  <h1 class="admin-title">Manage Categories</h1>
  <a href="<?php echo BASE_URL; ?>/admin/manage_categories.php?edit=0"
     class="btn btn-primary">+ Add Category</a>
</div>

<?php echo showFlash(); ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<?php if ($editCat !== null || isset($_GET['edit'])): ?>
<!-- ADD / EDIT FORM -->
<div class="card" style="margin-bottom:28px;max-width:480px;">
  <div class="card-header">
    <span class="card-title"><?php echo $editCat ? 'Edit Category' : 'Add New Category'; ?></span>
    <a href="<?php echo BASE_URL; ?>/admin/manage_categories.php" class="btn btn-ghost btn-sm">Cancel</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="cat_id" value="<?php echo $editCat['id'] ?? 0; ?>">
      <div class="form-group">
        <label class="form-label">Category Name *</label>
        <input type="text" name="cat_name" class="form-control"
               value="<?php echo sanitize($editCat['name'] ?? ''); ?>"
               placeholder="e.g. Digital Products" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary">
        <?php echo $editCat ? 'Save Changes' : 'Add Category'; ?>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- CATEGORIES TABLE -->
<div class="card">
  <div class="card-header">
    <span class="card-title">All Categories</span>
    <span style="font-size:.8rem;color:var(--muted);"><?php echo $cats->num_rows; ?> total</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Category Name</th>
          <th>Products</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($c = $cats->fetch_assoc()): ?>
        <tr>
          <td style="color:var(--muted);font-size:.8rem;"><?php echo $c['id']; ?></td>
          <td>
            <strong><?php echo sanitize($c['name']); ?></strong>
            <?php if ($c['name'] === 'Digital Products'): ?>
              <span style="margin-left:6px;font-size:.68rem;background:#ede9fe;color:#6d28d9;padding:2px 8px;border-radius:10px;font-weight:700;">Digital</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['product_count'] > 0): ?>
              <a href="<?php echo BASE_URL; ?>/admin/manage_products.php"
                 style="color:var(--accent);font-weight:600;">
                <?php echo $c['product_count']; ?> product<?php echo $c['product_count'] != 1 ? 's' : ''; ?>
              </a>
            <?php else: ?>
              <span style="color:var(--muted);font-size:.83rem;">0 products</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:8px;">
              <a href="?edit=<?php echo $c['id']; ?>" class="btn btn-ghost btn-sm">Edit</a>
              <?php if ($c['product_count'] == 0): ?>
                <a href="?delete=<?php echo $c['id']; ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete category \'<?php echo addslashes($c['name']); ?>\'? This cannot be undone.')">
                  Delete
                </a>
              <?php else: ?>
                <button class="btn btn-ghost btn-sm" disabled title="Has products assigned">Delete</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
