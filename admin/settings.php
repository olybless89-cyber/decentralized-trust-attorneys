<?php
require_once __DIR__ . '/../auth.php';
$admin = require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } else {
        $stmt = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_BCRYPT), $admin['id']]);
        flash_set('Password updated successfully.');
        header('Location: settings.php');
        exit;
    }
}

$pageTitle = 'Settings';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-grid adm-2col">
  <div class="adm-panel" style="margin-bottom:0">
    <div class="adm-panel-head"><h3>&#128274; Change Admin Password</h3></div>
    <?php foreach ($errors as $err): ?><div class="adm-alert adm-alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="adm-field"><label>Current Password</label><input type="password" name="current_password" required></div>
      <div class="adm-field"><label>New Password</label><input type="password" name="new_password" required minlength="8"></div>
      <div class="adm-field"><label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="8"></div>
      <button type="submit" class="adm-btn adm-btn-primary">Update Password</button>
    </form>
  </div>

  <div>
    <div class="adm-card-dark" style="margin-bottom:16px">
      <h3 style="color:#fff;font-size:15px;margin-bottom:16px">&#128737; Platform Security</h3>
      <div style="margin-bottom:14px"><div class="k">Signed in as</div><div class="v"><?= e($admin['username']) ?></div></div>
      <div style="margin-bottom:14px"><div class="k">Admin since</div><div class="v"><?= e(date('M j, Y', strtotime($admin['created_at']))) ?></div></div>
      <div><div class="k">Network Status</div><div class="v"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px"></span>Operational &amp; Secure</div></div>
    </div>
    <div class="adm-panel" style="margin-bottom:0">
      <h3 style="font-size:15px;margin-bottom:10px">Site Info</h3>
      <div class="adm-detail-grid" style="grid-template-columns:1fr">
        <div><div class="k">Site Name</div><div class="v"><?= e(SITE_NAME) ?></div></div>
        <div><div class="k">Site URL</div><div class="v"><?= e(SITE_URL) ?></div></div>
        <div><div class="k">Support Email</div><div class="v"><?= e(SUPPORT_EMAIL) ?></div></div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
