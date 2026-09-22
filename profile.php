<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

$errors = [];
$editMode = isset($_GET['edit']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $full_name    = trim($_POST['full_name'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $street_address = trim($_POST['street_address'] ?? '');
        $city         = trim($_POST['city'] ?? '');

        if ($full_name === '') {
            $errors[] = 'Full name is required.';
        }

        // Password change is optional — only apply if user filled in the fields.
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        $currentPass = $_POST['current_password'] ?? '';
        $changePassword = $newPass !== '';

        if ($changePassword) {
            $row = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $row->execute([$user['id']]);
            $stored = $row->fetch()['password_hash'];
            if (!password_verify($currentPass, $stored)) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($newPass) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirmPass) {
                $errors[] = 'New passwords do not match.';
            }
        }

        if (!$errors) {
            if ($changePassword) {
                db()->prepare('UPDATE users SET full_name=?, phone=?, street_address=?, city=?, password_hash=? WHERE id=?')
                    ->execute([$full_name, $phone, $street_address, $city, password_hash($newPass, PASSWORD_BCRYPT), $user['id']]);
            } else {
                db()->prepare('UPDATE users SET full_name=?, phone=?, street_address=?, city=? WHERE id=?')
                    ->execute([$full_name, $phone, $street_address, $city, $user['id']]);
            }
            flash_set('Profile updated successfully.');
            header('Location: profile.php');
            exit;
        }
        $editMode = true; // keep form open on validation error
    }
}

// Refresh user data so the view always shows the latest values.
$user = current_user(true);

$pageTitle = 'Profile';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:520px;margin:0 auto">
  <div style="text-align:center;margin-bottom:22px">
    <div class="side-avatar" style="width:64px;height:64px;font-size:24px;margin:0 auto 12px"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
    <h3 style="margin-bottom:2px"><?= e($user['full_name']) ?></h3>
    <p style="margin:0;font-size:14px"><?= e($user['email']) ?></p>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if (!$editMode): ?>
    <!-- Read-only view -->
    <div class="detail-grid">
      <div><div class="k">Phone</div><div class="v"><?= e($user['phone'] ?: '—') ?></div></div>
      <div><div class="k">Street Address</div><div class="v"><?= e($user['street_address'] ?: '—') ?></div></div>
      <div><div class="k">City</div><div class="v"><?= e($user['city'] ?: '—') ?></div></div>
      <div><div class="k">Country</div><div class="v"><?= e($user['country'] ?: '—') ?></div></div>
      <div><div class="k">State / Region</div><div class="v"><?= e($user['state_region'] ?: '—') ?></div></div>
      <div><div class="k">Balance</div><div class="v"><?= fmt_money((float) $user['balance']) ?></div></div>
      <div><div class="k">Linked Wallet</div><div class="v" style="font-size:13px"><?= $user['linked_wallet_address'] ? e($user['linked_wallet_address']) : '— none —' ?></div></div>
      <div><div class="k">Member Since</div><div class="v"><?= e(date('M j, Y', strtotime($user['created_at']))) ?></div></div>
    </div>
    <div class="grid grid-2" style="margin-top:14px">
      <a href="profile.php?edit=1" class="btn btn-outline">&#9998; Edit Profile</a>
      <a href="link-wallet.php" class="btn btn-outline">&#128279; Linked Wallet</a>
    </div>
    <a href="logout.php" class="btn btn-primary btn-block" style="margin-top:12px">Log Out</a>

  <?php else: ?>
    <!-- Editable form -->
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field"><label>Full Name *</label>
        <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>">
      </div>
      <div class="field"><label>Phone</label>
        <input type="text" name="phone" placeholder="(555) 123-4567" value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>">
      </div>
      <div class="field"><label>Street Address</label>
        <input type="text" name="street_address" placeholder="123 Main St" value="<?= e($_POST['street_address'] ?? $user['street_address'] ?? '') ?>">
      </div>
      <div class="field"><label>City</label>
        <input type="text" name="city" placeholder="Cheyenne" value="<?= e($_POST['city'] ?? $user['city'] ?? '') ?>">
      </div>

      <hr class="section-divider">
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Leave the password fields blank to keep your current password.</p>
      <div class="field"><label>Current Password</label>
        <input type="password" name="current_password" placeholder="Required only when changing password">
      </div>
      <div class="field"><label>New Password</label>
        <input type="password" name="new_password" placeholder="At least 6 characters">
      </div>
      <div class="field"><label>Confirm New Password</label>
        <input type="password" name="confirm_password">
      </div>

      <div class="grid grid-2" style="margin-top:6px">
        <a href="profile.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
