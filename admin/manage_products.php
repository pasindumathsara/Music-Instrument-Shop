<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireManagement();

$pageTitle = "Manage Products";
$action    = $_GET['action'] ?? 'list';
$editId    = (int)($_GET['id']     ?? 0);
$error = $success = '';

// ── DELETE ──────────────────────────────────────────────────────
if ($action === 'delete' && $editId > 0) {
    // Remove image file
    $row = $conn->prepare("SELECT image FROM products WHERE id=?");
    $row->bind_param("i", $editId);
    $row->execute();
    $img = $row->get_result()->fetch_assoc()['image'] ?? null;
    if ($img && file_exists(UPLOAD_DIR . $img)) unlink(UPLOAD_DIR . $img);

    $del = $conn->prepare("DELETE FROM products WHERE id=?");
    $del->bind_param("i", $editId);
    $del->execute();
    setFlash('success', 'Product deleted.');
    header("Location: " . BASE_URL . "/admin/manage_products.php"); exit();
}

// ── SAVE (ADD / EDIT) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']        ?? '');
    $catId    = (int)($_POST['category_id'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $price    = (float)($_POST['price']    ?? 0);
    $shipping = (float)($_POST['shipping_cost'] ?? 0);
    $stock    = (int)($_POST['stock']      ?? 0);
    $pid      = (int)($_POST['product_id'] ?? 0);
    $ptype    = ($_POST['product_type'] ?? 'physical') === 'digital' ? 'digital' : 'physical';
    if ($ptype === 'digital') $shipping = 0;

    if (!$name || $price <= 0) {
        $error = "Name and a valid price are required.";
    } else {
        // Image upload
        $imageName = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $upload = uploadProductImage($_FILES['image']);
            if ($upload['ok']) {
                if ($imageName && file_exists(UPLOAD_DIR . $imageName)) unlink(UPLOAD_DIR . $imageName);
                $imageName = $upload['filename'];
            } else { $error = $upload['error']; }
        }

        // Digital file upload
        $digitalFile     = $_POST['existing_digital_file'] ?? null;
        $digitalOrigName = '';
        $digitalFileSize = 0;
        $digitalMime     = '';
        $digitalExt      = '';

        if (!empty($_FILES['digital_file']['name'])) {
            $dDir = UPLOAD_DIR . 'digital/';
            if (!is_dir($dDir)) mkdir($dDir, 0755, true);
            $dExt  = strtolower(pathinfo($_FILES['digital_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','mp3','mp4','zip','epub','wav','flac'];
            if (!in_array($dExt, $allowed)) {
                $error = "Digital file must be PDF, MP3, MP4, ZIP, EPUB, WAV or FLAC.";
            } elseif ($_FILES['digital_file']['size'] > 50 * 1024 * 1024) {
                $error = "Digital file must be under 50 MB.";
            } else {
                $dName = uniqid('digi_') . '.' . $dExt;
                if (move_uploaded_file($_FILES['digital_file']['tmp_name'], $dDir . $dName)) {
                    if ($digitalFile && file_exists($dDir . $digitalFile)) unlink($dDir . $digitalFile);
                    $digitalFile     = $dName;
                    $digitalOrigName = $_FILES['digital_file']['name'];
                    $digitalFileSize = (int)$_FILES['digital_file']['size'];
                    $digitalMime     = mime_content_type($dDir . $dName) ?: $dExt;
                    $digitalExt      = $dExt;
                } else { $error = "Failed to upload digital file."; }
            }
        }

        // For physical products, clear digital file
        if ($ptype === 'physical') { $digitalFile = null; }

        if (!$error) {
            if ($pid > 0) {
                $upd = $conn->prepare(
                    "UPDATE products SET category_id=?,name=?,description=?,price=?,shipping_cost=?,stock=?,image=?,product_type=?,digital_file=? WHERE id=?"
                );
                $upd->bind_param("issddisssi", $catId, $name, $desc, $price, $shipping, $stock, $imageName, $ptype, $digitalFile, $pid);
                $upd->execute();
                $savedPid = $pid;
                setFlash('success', 'Product updated successfully!');
            } else {
                $ins = $conn->prepare(
                    "INSERT INTO products (category_id,name,description,price,shipping_cost,stock,image,product_type,digital_file) VALUES (?,?,?,?,?,?,?,?,?)"
                );
                $ins->bind_param("issddisss", $catId, $name, $desc, $price, $shipping, $stock, $imageName, $ptype, $digitalFile);
                $ins->execute();
                $savedPid = $conn->insert_id;
                setFlash('success', 'Product added successfully!');
            }

            // ── Sync digital_products metadata table ─────────────────
            if ($ptype === 'digital' && $digitalFile) {
                if ($digitalOrigName) {
                    // New file uploaded — full upsert with metadata
                    $dp = $conn->prepare(
                        "INSERT INTO digital_products (product_id,file_name,original_name,file_size,file_type,file_ext)
                         VALUES (?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE
                           file_name=VALUES(file_name), original_name=VALUES(original_name),
                           file_size=VALUES(file_size), file_type=VALUES(file_type), file_ext=VALUES(file_ext)"
                    );
                    $dp->bind_param("issiis", $savedPid, $digitalFile, $digitalOrigName, $digitalFileSize, $digitalMime, $digitalExt);
                    $dp->execute();
                } else {
                    // No new file — insert stub row if none exists
                    $ext = strtolower(pathinfo($digitalFile, PATHINFO_EXTENSION));
                    $dp2 = $conn->prepare(
                        "INSERT IGNORE INTO digital_products (product_id,file_name,original_name,file_ext) VALUES (?,?,?,?)"
                    );
                    $dp2->bind_param("isss", $savedPid, $digitalFile, $digitalFile, $ext);
                    $dp2->execute();
                }
            } elseif ($ptype === 'physical') {
                // Switched to physical — remove metadata row
                $ddel = $conn->prepare("DELETE FROM digital_products WHERE product_id=?");
                $ddel->bind_param("i", $savedPid);
                $ddel->execute();
            }

            header("Location: " . BASE_URL . "/admin/manage_products.php"); exit();
        }
    }
}

// ── LOAD FORM DATA (add or edit) ─────────────────────────────────
$editProduct = null;
if (($action === 'edit' || $action === 'add') && $editId > 0) {
    $ep = $conn->prepare("SELECT * FROM products WHERE id=?");
    $ep->bind_param("i", $editId);
    $ep->execute();
    $editProduct = $ep->get_result()->fetch_assoc();
}

// Categories
$cats = $conn->query("SELECT * FROM categories ORDER BY name");

// ── LIST ──────────────────────────────────────────────────────────
$products = $conn->query(
    "SELECT p.*, c.name AS cat_name FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     ORDER BY p.created_at DESC"
);

require_once 'includes/admin_header.php';
?>

<div class="admin-header-bar">
  <h1 class="admin-title"> Products</h1>
  <a href="<?php echo BASE_URL; ?>/admin/manage_products.php?action=add"
     class="btn btn-primary"> Add Product</a>
</div>

<?php echo showFlash(); ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<!-- ADD / EDIT FORM -->
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="margin-bottom:28px;">
  <div class="card-header">
    <span class="card-title"><?php echo $action==='edit'?' Edit Product':' Add New Product'; ?></span>
    <a href="<?php echo BASE_URL; ?>/admin/manage_products.php" class="btn btn-ghost btn-sm"> Cancel</a>
  </div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="product_id"    value="<?php echo $editProduct['id'] ?? 0; ?>">
      <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($editProduct['image'] ?? ''); ?>">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" class="form-control"
                 value="<?php echo sanitize($editProduct['name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="0">— Uncategorised —</option>
            <?php
              $cats->data_seek(0);
              while ($c = $cats->fetch_assoc()):
                $sel = ($editProduct['category_id'] ?? 0) == $c['id'] ? 'selected' : '';
            ?>
              <option value="<?php echo $c['id']; ?>" <?php echo $sel; ?>>
                <?php echo sanitize($c['name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4"><?php echo sanitize($editProduct['description'] ?? ''); ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Product Type *</label>
        <div style="display:flex;gap:20px;">
          <?php $isDigital = ($editProduct['product_type'] ?? 'physical') === 'digital'; ?>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="product_type" value="physical" <?php echo !$isDigital ? 'checked' : ''; ?> onchange="toggleDigital(this.value)">
            <span>Physical Product <small style="color:var(--muted);">(requires shipping)</small></span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="product_type" value="digital" <?php echo $isDigital ? 'checked' : ''; ?> onchange="toggleDigital(this.value)">
            <span>Digital Product <small style="color:var(--muted);">(downloadable file)</small></span>
          </label>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Price (GBP £) *</label>
          <input type="number" name="price" class="form-control" step="0.01" min="0"
                 value="<?php echo $editProduct['price'] ?? ''; ?>" required>
        </div>
        <div class="form-group" id="shippingField" style="<?php echo $isDigital ? 'display:none;' : ''; ?>">
          <label class="form-label">Shipping Cost (£)</label>
          <input type="number" name="shipping_cost" class="form-control" step="0.01" min="0"
                 value="<?php echo $editProduct['shipping_cost'] ?? 0; ?>">
          <small style="color:var(--muted);font-size:.7rem;">Per unit shipping fee</small>
        </div>
        <div class="form-group" id="stockField">
          <label class="form-label">Stock Quantity</label>
          <input type="number" name="stock" class="form-control" min="0"
                 value="<?php echo $editProduct['stock'] ?? 0; ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Product Image</label>
        <?php if (!empty($editProduct['image']) && file_exists(UPLOAD_DIR . $editProduct['image'])): ?>
          <div style="margin-bottom:10px;">
            <img src="<?php echo UPLOAD_URL . htmlspecialchars($editProduct['image']); ?>"
                 alt="Current image" style="height:80px;border-radius:8px;object-fit:cover;">
            <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">Current image (upload a new one to replace)</div>
          </div>
        <?php endif; ?>
        <input type="file" name="image" class="form-control" accept="image/*">
        <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">JPG, PNG, WEBP or GIF · Max 5 MB</div>
      </div>

      <!-- Digital file upload (hidden for physical) -->
      <div class="form-group" id="digitalField" style="<?php echo $isDigital ? '' : 'display:none;'; ?>">
        <label class="form-label">Digital File <span style="color:var(--danger);">*</span></label>
        <input type="hidden" name="existing_digital_file" value="<?php echo htmlspecialchars($editProduct['digital_file'] ?? ''); ?>">
        <?php if (!empty($editProduct['digital_file'])): ?>
          <div style="margin-bottom:8px;padding:10px 14px;background:var(--bg);border-radius:8px;font-size:.83rem;display:flex;align-items:center;gap:8px;">
            <span style="color:var(--success);">&#10003;</span>
            Current file: <strong><?php echo htmlspecialchars($editProduct['digital_file']); ?></strong>
            <a href="<?php echo BASE_URL; ?>/download.php?admin=1&file=<?php echo urlencode($editProduct['digital_file']); ?>" style="margin-left:auto;color:var(--accent);font-size:.75rem;">Preview</a>
          </div>
        <?php endif; ?>
        <input type="file" name="digital_file" class="form-control" accept=".pdf,.mp3,.mp4,.zip,.epub,.wav,.flac">
        <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">PDF, MP3, MP4, ZIP, EPUB, WAV, FLAC · Max 50 MB</div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg">
        <?php echo $action==='edit' ? ' Save Changes' : ' Add Product'; ?>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function toggleDigital(type) {
  document.getElementById('digitalField').style.display = type === 'digital' ? '' : 'none';
  document.getElementById('shippingField').style.display = type === 'digital' ? 'none' : '';
}
</script>

<!-- PRODUCTS TABLE -->
<div class="card">
  <div class="card-header">
    <span class="card-title">All Products (<?php echo $products->num_rows; ?>)</span>
  </div>
  <div class="table-wrap" style="box-shadow:none;border-radius:0;">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Image</th><th>Name</th><th>Category</th>
          <th>Price</th><th>Ship Fee</th><th>Stock</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($p = $products->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--muted);">#<?php echo $p['id']; ?></td>
            <td>
              <?php if ($p['image'] && file_exists(UPLOAD_DIR . $p['image'])): ?>
                <img src="<?php echo UPLOAD_URL . htmlspecialchars($p['image']); ?>"
                     style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
              <?php else: ?>
                <span style="font-size:1.8rem;">🎵</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $p['id']; ?>"
                 target="_blank" style="font-weight:600;"><?php echo sanitize($p['name']); ?></a>
            </td>
            <td><?php echo sanitize($p['cat_name'] ?? '—'); ?></td>
            <td style="font-weight:700;color:var(--accent);"><?php echo formatPrice($p['price']); ?></td>
            <td style="font-size:.8rem;color:var(--muted);"><?php echo $p['shipping_cost'] > 0 ? formatPrice($p['shipping_cost']) : 'FREE'; ?></td>
            <td>
              <?php if ($p['stock'] == 0): ?>
                <span class="badge badge-cancelled">Out of Stock</span>
              <?php elseif ($p['stock'] <= 5): ?>
                <span class="badge badge-pending"><?php echo $p['stock']; ?> left</span>
              <?php else: ?>
                <span class="badge badge-paid"><?php echo $p['stock']; ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-ghost btn-sm"> Edit</a>
                <a href="?action=delete&id=<?php echo $p['id']; ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this product? This cannot be undone.')"> Del</a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
