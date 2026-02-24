<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = "Manage Users";

// ── CHANGE ROLE ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['role'] === 'admin' ? 'admin' : 'customer';
    if ($uid !== (int)$_SESSION['user_id']) { // prevent self-demotion
        $upd = $conn->prepare("UPDATE users SET role=? WHERE id=?");
        $upd->bind_param("si", $role, $uid);
        $upd->execute();
        setFlash('success', "User #$uid role updated to '$role'.");
    } else {
        setFlash('danger', "You cannot change your own role.");
    }
    header("Location: " . BASE_URL . "/admin/manage_users.php"); exit();
}

// ── DELETE USER ──────────────────────────────────────────────────
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $uid = (int)$_GET['delete'];
    if ($uid === (int)$_SESSION['user_id']) {
        setFlash('danger', "You cannot delete your own account.");
    } else {
        $del = $conn->prepare("DELETE FROM users WHERE id=?");
        $del->bind_param("i", $uid);
        $del->execute();
        setFlash('success', "User deleted.");
    }
    header("Location: " . BASE_URL . "/admin/manage_users.php"); exit();
}

// ── SEARCH ────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = '';
$params = [];
if ($search) {
    $like    = '%' . $search . '%';
    $where   = "WHERE u.name LIKE ? OR u.email LIKE ?";
    $params  = [$like, $like];
}

$sql  = "SELECT u.*, COUNT(o.id) AS order_count
         FROM users u
         LEFT JOIN orders o ON o.user_id=u.id
         $where
         GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param("ss", ...$params);
$stmt->execute();
$users = $stmt->get_result();

require_once 'includes/admin_header.php';
?>

<div class="admin-header-bar">
  <h1 class="admin-title">👥 Users</h1>
  <!-- Search -->
  <form method="GET" style="display:flex;gap:8px;">
    <input type="text" name="q" class="form-control" style="width:240px;"
           value="<?php echo sanitize($search); ?>" placeholder="Search by name or email…">
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if ($search): ?>
      <a href="?" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<?php echo showFlash(); ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
        <th>Orders</th><th>Joined</th><th>Change Role</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($users->num_rows === 0): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No users found.</td></tr>
      <?php endif; ?>
      <?php while ($u = $users->fetch_assoc()): ?>
        <tr <?php echo $u['id']==$_SESSION['user_id'] ? 'style="background:#fffbeb;"' : ''; ?>>
          <td style="color:var(--muted);">#<?php echo $u['id']; ?></td>
          <td>
            <strong><?php echo sanitize($u['name']); ?></strong>
            <?php if ($u['id']==$_SESSION['user_id']): ?>
              <span style="font-size:.72rem;color:var(--accent);"> (you)</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted);"><?php echo sanitize($u['email']); ?></td>
          <td><?php echo '<span class="badge badge-'.htmlspecialchars($u['role']).'">'.ucfirst($u['role']).'</span>'; ?></td>
          <td>
            <?php if ($u['order_count'] > 0): ?>
              <a href="<?php echo BASE_URL; ?>/admin/manage_orders.php" style="font-weight:700;">
                <?php echo $u['order_count']; ?>
              </a>
            <?php else: ?>
              <span style="color:var(--muted);">0</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--muted);"><?php echo formatDate($u['created_at']); ?></td>
          <!-- Role Toggle -->
          <td>
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="user_id"     value="<?php echo $u['id']; ?>">
                <input type="hidden" name="change_role"  value="1">
                <select name="role" class="form-control" style="width:auto;padding:5px 10px;font-size:.78rem;"
                        onchange="this.form.submit()"
                        title="Change role (auto-saves)">
                  <option value="customer" <?php echo $u['role']==='customer'?'selected':''; ?>>Customer</option>
                  <option value="admin"    <?php echo $u['role']==='admin'   ?'selected':''; ?>>Admin</option>
                </select>
              </form>
            <?php else: ?>
              <span style="font-size:.78rem;color:var(--muted);">—</span>
            <?php endif; ?>
          </td>
          <!-- Delete -->
          <td>
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <a href="?delete=<?php echo $u['id']; ?>"
                 class="btn btn-danger btn-sm"
                 onclick="return confirm('Delete this user and all their data?')">🗑</a>
            <?php else: ?>
              <span style="font-size:.75rem;color:var(--muted);">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Stats below -->
<?php
  $adminCount    = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0];
  $customerCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetch_row()[0];
?>
<div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
  <div style="padding:12px 20px;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:.83rem;">
    <strong><?php echo $customerCount; ?></strong> Customers
  </div>
  <div style="padding:12px 20px;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:.83rem;">
    <strong><?php echo $adminCount; ?></strong> Admin<?php echo $adminCount!=1?'s':''; ?>
  </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
