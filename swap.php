<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
require_once __DIR__ . '/includes/mailer.php';
$user = require_login();
$assets = array_merge(['USD'], wallet_supported_assets());

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $from = $_POST['from_asset'] ?? 'USD';
        $to = $_POST['to_asset'] ?? 'BTC';
        $amount = (float) ($_POST['amount'] ?? 0);
        if (!in_array($from, $assets, true) || !in_array($to, $assets, true) || $from === $to) {
            $errors[] = 'Choose two different assets to swap between.';
        } elseif ($amount <= 0) {
            $errors[] = 'Enter a valid amount.';
        } else {
            // Bug fix: re-fetch live balance with a row lock to avoid stale cache
            // and correctly deduct the full swap amount (not just the fee).
            db()->beginTransaction();
            $liveRow = db()->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $liveRow->execute([$user['id']]);
            $liveBalance = (float) $liveRow->fetch()['balance'];
            if ($amount > $liveBalance) {
                db()->rollBack();
                $errors[] = 'Amount exceeds your available balance.';
            } else {
            $fee = round($amount * 0.005, 2); // 0.5% demo swap fee
            // Deduct the full swap amount from the balance (fee is already included in it).
            $stmt = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$amount, $user['id']]);
            log_transaction($user['id'], 'swap', $from, $amount, $to, null, 'Fee: ' . fmt_money($fee));
            db()->commit();
            send_email($user['email'], $user['full_name'], 'Swap Confirmation',
                '<p>Hi ' . e($user['full_name']) . ',</p><p>You swapped <strong>' . fmt_money($amount) . '</strong> from ' . e($from) . ' to ' . e($to) . ' (fee: ' . fmt_money($fee) . ').</p>');
            flash_set('Swapped ' . fmt_money($amount) . ' from ' . $from . ' to ' . $to . '.');
            header('Location: dashboard.php');
            exit;
            } // end balance check
        }
    }
}

$pageTitle = 'Swap';
require __DIR__ . '/includes/dash_header.php';
// Pre-fill from-asset from coin-detail.php
$preFrom = strtoupper(trim($_GET['from'] ?? 'USD'));
if (!in_array($preFrom, $assets, true)) $preFrom = 'USD';
?>
<div class="panel" style="max-width:480px;margin:0 auto">
  <?php if (!empty($_GET['from']) && $preFrom !== 'USD'): ?>
    <div style="margin-bottom:16px">
      <a href="coin-detail.php?coin=<?= urlencode($preFrom) ?>" class="cd-back-btn">&#8592; <?= e($preFrom) ?></a>
    </div>
  <?php endif; ?>
  <h3 style="margin-bottom:6px;text-align:center">Swap Assets</h3>
  <p style="text-align:center;font-size:14px;margin-bottom:20px">Available balance: <strong style="color:var(--navy)"><?= fmt_money((float) $user['balance']) ?></strong></p>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-row-2">
      <div class="field"><label>From</label>
        <select name="from_asset" id="fromAsset" onchange="updateQuote()">
          <?php foreach ($assets as $a): ?><option value="<?= e($a) ?>" <?= $a === $preFrom ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>To</label>
        <select name="to_asset" id="toAsset" onchange="updateQuote()">
          <?php foreach ($assets as $a): ?><option value="<?= e($a) ?>" <?= $a === 'BTC' ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field"><label>Amount (USD equivalent)</label><input type="number" step="0.01" min="0.01" max="<?= e($user['balance']) ?>" name="amount" id="swapAmount" oninput="updateQuote()" required></div>
    <div id="quotePreview" class="hint" style="margin-bottom:16px">Enter an amount to see the live conversion.</div>
    <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Confirm this swap? A small network fee applies.')">Swap</button>
  </form>
</div>

<script>
const COIN_IDS = { BTC: 'bitcoin', ETH: 'ethereum', USDT: 'tether', BNB: 'binancecoin', SOL: 'solana', XRP: 'ripple' };
let prices = {};
async function loadPrices() {
  try {
    const ids = Object.values(COIN_IDS).join(',');
    const res = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=' + ids + '&vs_currencies=usd');
    prices = await res.json();
  } catch (e) { /* live quote unavailable — form still works */ }
  updateQuote();
}
function priceOf(asset) {
  if (asset === 'USD') return 1;
  const id = COIN_IDS[asset];
  return id && prices[id] ? prices[id].usd : null;
}
function updateQuote() {
  const from = document.getElementById('fromAsset').value;
  const to = document.getElementById('toAsset').value;
  const amount = parseFloat(document.getElementById('swapAmount').value || '0');
  const el = document.getElementById('quotePreview');
  const pFrom = priceOf(from), pTo = priceOf(to);
  if (!amount || !pFrom || !pTo) { el.innerText = 'Enter an amount to see the live conversion.'; return; }
  const fee = amount * 0.005;
  const net = amount - fee;
  const received = net / pTo;
  el.innerText = `≈ ${received.toLocaleString(undefined,{maximumFractionDigits:6})} ${to} (after ${fee.toLocaleString(undefined,{maximumFractionDigits:2})} fee)`;
}
document.addEventListener('DOMContentLoaded', loadPrices);
</script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
