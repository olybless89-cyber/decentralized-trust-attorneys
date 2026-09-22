<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();

ensure_asset_balances_table();

// Portfolio total from asset_balances (the authoritative per-asset source)
$portfolioTotal = 0.0;
try {
    $ptSt = db()->prepare('SELECT COALESCE(SUM(demo_usd_amount),0) FROM asset_balances WHERE user_id = ?');
    $ptSt->execute([$user['id']]);
    $portfolioTotal = (float) $ptSt->fetchColumn();
} catch (PDOException $e) {
    $portfolioTotal = (float) $user['balance'];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $asset       = strtoupper(trim($_POST['asset'] ?? 'BTC'));
        $destination = trim($_POST['destination'] ?? '');
        $amount      = (float) ($_POST['amount'] ?? 0);
        if ($destination === '') {
            $errors[] = 'Enter a destination wallet address.';
        } elseif ($amount <= 0) {
            $errors[] = 'Enter a valid amount.';
        } else {
            // Deduct from the specific asset_balances row for the chosen coin
            db()->beginTransaction();
            try {
                $abRow = db()->prepare('SELECT id, demo_usd_amount, crypto_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ? FOR UPDATE');
                $abRow->execute([$user['id'], $asset]);
                $ab = $abRow->fetch(PDO::FETCH_ASSOC);
                $availUsd = $ab ? (float)$ab['demo_usd_amount'] : 0;
                if ($amount > $availUsd) {
                    db()->rollBack();
                    $errors[] = 'Amount exceeds your ' . e($asset) . ' balance (' . fmt_money($availUsd) . ').';
                } else {
                    // Proportionally reduce crypto_amount
                    $newUsd    = $availUsd - $amount;
                    $newCrypto = $availUsd > 0 ? (float)$ab['crypto_amount'] * ($newUsd / $availUsd) : 0;
                    db()->prepare('UPDATE asset_balances SET demo_usd_amount=?, crypto_amount=?, updated_at=NOW() WHERE id=?')
                       ->execute([$newUsd, $newCrypto, $ab['id']]);
                    log_transaction($user['id'], 'send', $asset, $amount, null, $destination);
                    db()->commit();
                    send_email($user['email'], $user['full_name'], 'Send Confirmation — ' . $asset,
                        '<p>Hi ' . e($user['full_name']) . ',</p><p>You sent <strong>' . fmt_money($amount) . '</strong> worth of ' . e($asset) . ' to <code>' . e($destination) . '</code>.</p><p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
                    flash_set('Sent ' . fmt_money($amount) . ' worth of ' . $asset . '.');
                    header('Location: dashboard.php');
                    exit;
                }
            } catch (PDOException $ex) {
                db()->rollBack();
                $errors[] = 'Transaction failed. Please try again.';
            }
        }
    }
}

// Pre-fill asset from coin-detail.php
$preAsset = strtoupper(trim($_GET['asset'] ?? ''));
if (!in_array($preAsset, $allCoins, true)) $preAsset = 'BTC';

$pageTitle = 'Send';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:480px;margin:0 auto">
  <?php if (!empty($_GET['asset'])): ?>
    <div style="margin-bottom:16px">
      <a href="coin-detail.php?coin=<?= urlencode($preAsset) ?>" class="cd-back-btn">&#8592; <?= e($preAsset) ?></a>
    </div>
  <?php endif; ?>
  <h3 style="margin-bottom:6px;text-align:center">Send Funds</h3>
  <p style="text-align:center;font-size:14px;margin-bottom:20px">Available balance: <strong style="color:var(--navy)"><?= fmt_money($portfolioTotal) ?></strong></p>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field"><label>Asset</label>
      <select name="asset">
        <?php foreach ($allCoins as $a): ?><option value="<?= e($a) ?>" <?= $a === $preAsset ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Destination Wallet Address</label><input type="text" name="destination" placeholder="0x... or wallet address" required></div>
    <div class="field"><label>Amount (USD equivalent)</label><input type="number" step="0.01" min="0.01" max="<?= e($portfolioTotal) ?>" name="amount" required></div>
    <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Send this amount? This cannot be undone.')">Send</button>
  </form>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
