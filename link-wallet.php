<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $addr = trim($_POST['linked_wallet_address'] ?? '');
        if ($addr === '') {
            $errors[] = 'Enter a wallet address to link.';
        } else {
            $stmt = db()->prepare('UPDATE users SET linked_wallet_address = ? WHERE id = ?');
            $stmt->execute([$addr, $user['id']]);
            log_wallet_connection($user['id'], $addr, 'manual', 'success');
            send_email($user['email'], $user['full_name'], 'Wallet Linked to Your Account',
                '<p>Hi ' . e($user['full_name']) . ',</p><p>A wallet address has been linked to your account for withdrawals:</p><p><code>' . e($addr) . '</code></p><p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            flash_set('Wallet linked. It will be pre-filled on your withdrawal requests.');
            header('Location: link-wallet.php');
            exit;
        }
    }
}

$pageTitle = 'Link Wallet';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:480px;margin:0 auto">
  <h3 style="margin-bottom:6px;text-align:center">Link a Wallet</h3>
  <p style="text-align:center;font-size:14px;margin-bottom:20px">Save a default wallet address so it's pre-filled whenever you request a withdrawal.</p>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if (!empty($user['linked_wallet_address'])): ?>
    <div class="address-box" style="margin-bottom:22px">
      <span><?= e($user['linked_wallet_address']) ?></span>
      <span class="badge" style="color:#15803d;background:#dcfce7">Linked</span>
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label><?= !empty($user['linked_wallet_address']) ? 'Replace Linked Wallet Address' : 'Wallet Address' ?></label>
      <input type="text" name="linked_wallet_address" placeholder="0x... or wallet address" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= !empty($user['linked_wallet_address']) ? 'Update Linked Wallet' : 'Link Wallet' ?></button>
  </form>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
