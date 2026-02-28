<?php
/**
 * ============================================================
 * admin/reset_password.php
 * Admin utility: reset any user's password + fix plain-text passwords
 * Access: admin@melodymasters.com only (or any logged-in admin)
 * ============================================================
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireManagement();

$pageTitle = "Password Reset Tool";
$success = $error = '';

// ── Auto-fix all plain-text passwords ────────────────────────
$autoFixed = [];
$allUsers  = $conn->query("SELECT id, email, password FROM users");
while ($u = $allUsers->fetch_assoc()) {
    $isHashed = (str_starts_with($u['password'], '$2y$') || str_starts_with($u['password'], '$2a$'));
    if (!$isHashed) {
        // It's plain text — hash it now
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $hash, $u['id']);
        $upd->execute();
        $autoFixed[] = $u['email'];
    }
}

// ── Manual password reset form ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId  = (int)$_POST['user_id'];
    $newPwd  = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm']      ?? '');

    if (!$newPwd || strlen($newPwd) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPwd !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($newPwd, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $hash, $userId);
        $upd->execute();
        $success = "Password has been reset successfully!";
    }
}

// Load all users for dropdown
$users = $conn->query("SELECT id, name, email, role FROM users ORDER BY role DESC, name ASC");

require_once 'includes/admin_header.php';
?>

<div class="admin-header-bar">
  <h1 class="admin-title"> Password Reset Tool</h1>
</div>

<!-- Auto-fix notice -->
<?php if (!empty($autoFixed)): ?>
  <div class="alert alert-warning">
    <strong> Auto-fixed <?php echo count($autoFixed); ?> plain-text password(s):</strong>
    <ul style="margin-top:6px;margin-left:20px;font-size:.83rem;">
      <?php foreach ($autoFixed as $em): ?>
        <li><?php echo sanitize($em); ?> — password hashed (their original password still works)</li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php else: ?>
  <div class="alert alert-success"> All passwords are properly hashed. No issues found.</div>
<?php endif; ?>

<?php if ($success): ?><div class="alert alert-success"><?php echo sanitize($success); ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start;">

  <!-- Manual Reset Form -->
  <div class="card">
    <div class="card-header"><span class="card-title">Reset Any User's Password</span></div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label class="form-label">Select User</label>
          <select name="user_id" class="form-control" required>
            <option value="">— Choose a user —</option>
            <?php
              $users->data_seek(0);
              while ($u = $users->fetch_assoc()):
            ?>
              <option value="<?php echo $u['id']; ?>">
                [<?php echo strtoupper($u['role']); ?>] <?php echo sanitize($u['name']); ?> — <?php echo sanitize($u['email']); ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control"
                 placeholder="Min. 6 characters" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" class="form-control"
                 placeholder="Repeat new password" required>
        </div>
        <button type="submit" name="reset_password" class="btn btn-primary">
           Reset Password
        </button>
      </form>
    </div>
  </div>

  <!-- Users Overview -->
  <div class="card">
    <div class="card-header"><span class="card-title">All Users Password Status</span></div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr><th>Role</th><th>Email</th><th>Hash Status</th></tr>
        </thead>
        <tbody>
          <?php
            $conn->query("SET @row=0");
            $all = $conn->query("SELECT id, name, email, role, LEFT(password,4) AS p FROM users ORDER BY role DESC, email ASC");
            while ($u = $all->fetch_assoc()):
              $ok = ($u['p'] === '$2y$');
          ?>
            <tr>
              <td>
                <?php
                  $badgeCls = 'badge-customer';
                  if ($u['role'] === 'admin') $badgeCls = 'badge-admin';
                  if ($u['role'] === 'staff') $badgeCls = 'badge-staff';
                ?>
                <span class="badge <?php echo $badgeCls; ?>">
                  <?php echo ucfirst($u['role']); ?>
                </span>
              </td>
              <td style="font-size:.83rem;"><?php echo sanitize($u['email']); ?></td>
              <td>
                <?php if ($ok): ?>
                  <span style="color:var(--success);font-weight:600;"> Hashed</span>
                <?php else: ?>
                  <span style="color:var(--danger);font-weight:600;"> Not hashed</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>



<?php require_once 'includes/admin_footer.php'; ?>
