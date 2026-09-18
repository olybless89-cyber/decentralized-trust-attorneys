<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

ensure_asset_balances_table();

// Available balance = sum of all per-asset USD values (authoritative source)
$portfolioTotal = 0.0;
try {
    $ptSt = db()->prepare('SELECT COALESCE(SUM(demo_usd_amount),0) FROM asset_balances WHERE user_id = ?');
    $ptSt->execute([$user['id']]);
    $portfolioTotal = (float) $ptSt->fetchColumn();
} catch (PDOException $e) {
    $portfolioTotal = (float) $user['balance'];
}

// All 25 supported coins (mirrors crypto-assets.php)
$allCoins = ['XRP','BTC','ETH','USDT','BNB','USDC','SOL','TRX','DOGE','LTC','XLM',
             'AVAX','MATIC','DOT','ADA','LINK','UNI','ATOM','NEAR','ICP','VET','FIL',
             'ALGO','FTM','XTZ'];

$wdErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $wdErrors[] = 'Your session expired, please try again.';
    } else {
        $amount = (float) ($_POST['amount'] ?? 0);
        $asset  = strtoupper(trim($_POST['asset'] ?? 'BTC'));
        $wallet = trim($_POST['wallet_address'] ?? '');
        $method = trim($_POST['method'] ?? 'crypto');
        if (!in_array($asset, $allCoins, true)) $asset = 'BTC';

        // Get the specific coin's available balance
        $coinBal = 0.0;
        try {
            $cbSt = db()->prepare('SELECT COALESCE(demo_usd_amount,0) FROM asset_balances WHERE user_id = ? AND asset_symbol = ?');
            $cbSt->execute([$user['id'], $asset]);
            $coinBal = (float) $cbSt->fetchColumn();
        } catch (PDOException $e) {}

        if ($amount <= 0) {
            $wdErrors[] = 'Enter a valid withdrawal amount.';
        } elseif ($amount > $portfolioTotal) {
            $wdErrors[] = 'Withdrawal amount exceeds your available balance.';
        } elseif ($wallet === '') {
            $wdErrors[] = 'Please provide a destination wallet address or payout detail.';
        } else {
            $stmt = db()->prepare('INSERT INTO withdrawals (user_id, amount, asset, method, wallet_address) VALUES (?,?,?,?,?)');
            $stmt->execute([$user['id'], $amount, $asset, $method, $wallet]);
            send_email($user['email'], $user['full_name'], 'Withdrawal Request Received',
                '<p>Hi ' . e($user['full_name']) . ',</p><p>We\'ve received your withdrawal request for <strong>' . fmt_money($amount) . '</strong> via ' . e(ucfirst($method)) . '.</p><p>Our team will review it and you\'ll get another email once it\'s approved or declined.</p>');
            flash_set('Withdrawal request submitted and processing.');
            header('Location: withdraw.php');
            exit;
        }
    }
}

$stmt = db()->prepare('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$withdrawals = $stmt->fetchAll();

$pageTitle = 'Withdraw';
require __DIR__ . '/includes/dash_header.php';
// Pre-fill asset from coin-detail.php
$preAsset = strtoupper(trim($_GET['asset'] ?? 'BTC'));
if (!in_array($preAsset, $allCoins, true)) $preAsset = 'BTC';
?>
<div class="grid grid-2" style="align-items:start">
  <div class="panel">
    <?php if (!empty($_GET['asset'])): ?>
      <div style="margin-bottom:16px">
        <a href="coin-detail.php?coin=<?= urlencode($preAsset) ?>" class="cd-back-btn">&#8592; <?= e($preAsset) ?></a>
      </div>
    <?php endif; ?>
    <h3 style="margin-bottom:16px">Request a Withdrawal</h3>
    <p style="font-size:14px;margin-bottom:20px">Available balance: <strong style="color:var(--navy)"><?= fmt_money($portfolioTotal) ?></strong></p>
    <?php foreach ($wdErrors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field"><label>Amount (USD)</label><input type="number" step="0.01" min="0.01" max="<?= e($portfolioTotal) ?>" name="amount" required></div>
      <div class="field"><label>Coin</label>
        <select name="asset">
          <?php foreach ($allCoins as $a): ?><option value="<?= e($a) ?>" <?= $a === $preAsset ? 'selected' : '' ?>><?= e($a) ?> (<?= e($a) ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Method</label>
        <select name="method">
          <option value="crypto">Crypto Wallet</option>
          <option value="bank">Bank Transfer</option>
        </select>
      </div>
      <div class="field"><label>Wallet Address / Payout Details</label><input type="text" name="wallet_address" placeholder="e.g. 0x... or bank details" value="<?= e($user['linked_wallet_address'] ?? '') ?>" required></div>
      <?php if (empty($user['linked_wallet_address'])): ?>
        <p class="hint" style="margin-top:-8px;margin-bottom:16px"><a href="link-wallet.php">Link a wallet</a> to have this pre-filled next time.</p>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-block">Submit Request</button>
    </form>
  </div>

  <div class="panel">
    <h3 style="margin-bottom:16px">Withdrawal History</h3>
    <?php if (!$withdrawals): ?>
      <div class="empty-state"><p style="margin:0">No withdrawal requests yet.</p></div>
    <?php else: ?>
      <?php foreach ($withdrawals as $w): ?>
        <div class="review-row">
          <div>
            <div class="v"><?= fmt_money((float) $w['amount']) ?> <span style="font-weight:500;color:var(--muted)"><?= e($w['asset'] ?? 'BTC') ?></span></div>
            <div class="k" style="margin-top:2px"><?= e(ucfirst($w['method'])) ?> &middot; <?= e(date('M j, Y', strtotime($w['created_at']))) ?></div>
          </div>
          <?= wd_status_badge($w['status']) ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
