<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = "Manage Users";
$action    = $_GET['action'] ?? 'list';
$editId    = (int)($_GET['id'] ?? 0);
$error = $success = '';

// ── ADD / EDIT USER ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_user']) || isset($_POST['edit_user']))) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'customer';
    $uid      = (int)($_POST['user_id'] ?? 0);

    if (!$name || !$email) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check email uniqueness
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->bind_param("si", $email, $uid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "That email is already in use.";
        } else {
            if ($uid > 0) {
                // Update
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?");
                    $upd->bind_param("ssssi", $name, $email, $role, $hash, $uid);
                } else {
                    $upd = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                    $upd->bind_param("sssi", $name, $email, $role, $uid);
                }
                $upd->execute();
                setFlash('success', "User updated successfully.");
            } else {
                // Add
                if (!$password) {
                    $error = "Password is required for new users.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $conn->prepare("INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("ssss", $name, $email, $role, $hash);
                    $ins->execute();
                    setFlash('success', "New user added successfully.");
                }
            }
            if (!$error) {
                header("Location: " . BASE_URL . "/admin/manage_users.php");
                exit();
            }
        }
    }
}

// ── CHANGE ROLE (Quick Action) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role_quick'])) {
    $uid  = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $allowedRoles = ['admin', 'staff', 'customer'];
    
    if (!in_array($role, $allowedRoles)) $role = 'customer';

    if ($uid !== (int)$_SESSION['user_id']) { // prevent self-demotion
        $upd = $conn->prepare("UPDATE users SET role=? WHERE id=?");
        $upd->bind_param("si", $role, $uid);
        $upd->execute();
        setFlash('success', "User #$uid role updated to '$role'.");
    } else {
        setFlash('danger', "You cannot change your own role here.");
    }
    header("Location: " . BASE_URL . "/admin/manage_users.php"); exit();
}

// ── DELETE USER ──────────────────────────────────────────────────
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    if ($action !== 'delete') { // Basic safety check
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
}

// ── LOAD FORM DATA ───────────────────────────────────────────────
$editUser = null;
if ($action === 'edit' && $editId > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
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
  <div style="display:flex;gap:12px;align-items:center;">
    <!-- Search -->
    <form method="GET" style="display:flex;gap:8px;">
      <input type="text" name="q" class="form-control" style="width:200px;"
             value="<?php echo sanitize($search); ?>" placeholder="Search users…">
      <button type="submit" class="btn btn-primary btn-sm">Search</button>
      <?php if ($search): ?>
        <a href="?" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
    </form>
    <a href="?action=add" class="btn btn-primary">Add User</a>
  </div>
</div>

<?php echo showFlash(); ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
<?php endif; ?>

<!-- ADD / EDIT FORM -->
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="margin-bottom:28px;">
  <div class="card-header">
    <span class="card-title"><?php echo $action==='edit'?'Edit User':'Add New User'; ?></span>
    <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="btn btn-ghost btn-sm">Cancel</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="user_id" value="<?php echo $editUser['id'] ?? 0; ?>">
      
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" 
                 value="<?php echo sanitize($editUser['name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control"
                 value="<?php echo sanitize($editUser['email'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" class="form-control">
            <option value="customer" <?php echo ($editUser['role']??'')==='customer'?'selected':''; ?>>Customer</option>
            <option value="staff" <?php echo ($editUser['role']??'')==='staff'?'selected':''; ?>>Staff</option>
            <option value="admin" <?php echo ($editUser['role']??'')==='admin'?'selected':''; ?>>Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $action==='edit'?'New Password (leave blank to keep current)':'Password *'; ?></label>
          <input type="password" name="password" class="form-control" <?php echo $action==='add'?'required':''; ?>>
        </div>
      </div>

      <button type="submit" name="<?php echo $action==='edit'?'edit_user':'add_user'; ?>" class="btn btn-primary">
        <?php echo $action==='edit'?'Save Changes':'Create User'; ?>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Role</th>
        <th>Orders</th><th>Joined</th><th>Actions</th>
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
          <!-- Actions -->
          <td>
            <div style="display:flex;gap:6px;">
              <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-ghost btn-sm">Edit</a>
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <a href="?delete=<?php echo $u['id']; ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete this user and all their data?')">Trash</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Stats below -->
<?php
  $adminCount    = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0];
  $staffCount    = $conn->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetch_row()[0];
  $customerCount = $conn->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetch_row()[0];
?>
<div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
  <div style="padding:12px 20px;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:.83rem;">
    <strong><?php echo $customerCount; ?></strong> Customers
  </div>
  <div style="padding:12px 20px;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:.83rem;">
    <strong><?php echo $staffCount; ?></strong> Staff
  </div>
  <div style="padding:12px 20px;background:#fff;border-radius:8px;border:1px solid var(--border);font-size:.83rem;">
    <strong><?php echo $adminCount; ?></strong> Admin<?php echo $adminCount!=1?'s':''; ?>
  </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
