<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $asset = $_POST['asset'] ?? 'BTC';
        $destination = trim($_POST['destination'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        if (!in_array($asset, wallet_supported_assets(), true)) {
            $errors[] = 'Select a valid asset.';
        } elseif ($destination === '') {
            $errors[] = 'Enter a destination wallet address.';
        } elseif ($amount <= 0) {
            $errors[] = 'Enter a valid amount.';
        } elseif ($amount > (float) $user['balance']) {
            $errors[] = 'Amount exceeds your available balance.';
        } else {
            db()->beginTransaction();
            $stmt = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$amount, $user['id']]);
            log_transaction($user['id'], 'send', $asset, $amount, null, $destination);
            db()->commit();
            send_email($user['email'], $user['full_name'], 'Send Confirmation — ' . $asset,
                '<p>Hi ' . e($user['full_name']) . ',</p><p>You sent <strong>' . fmt_money($amount) . '</strong> worth of ' . e($asset) . ' to <code>' . e($destination) . '</code>.</p><p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            flash_set('Sent ' . fmt_money($amount) . ' worth of ' . $asset . '.');
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Send';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:480px;margin:0 auto">
  <h3 style="margin-bottom:6px;text-align:center">Send Funds</h3>
  <p style="text-align:center;font-size:14px;margin-bottom:20px">Available balance: <strong style="color:var(--navy)"><?= fmt_money((float) $user['balance']) ?></strong></p>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field"><label>Asset</label>
      <select name="asset">
        <?php foreach (wallet_supported_assets() as $a): ?><option value="<?= e($a) ?>"><?= e($a) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Destination Wallet Address</label><input type="text" name="destination" placeholder="0x... or wallet address" required></div>
    <div class="field"><label>Amount (USD equivalent)</label><input type="number" step="0.01" min="0.01" max="<?= e($user['balance']) ?>" name="amount" required></div>
    <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Send this amount? This cannot be undone.')">Send</button>
  </form>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
