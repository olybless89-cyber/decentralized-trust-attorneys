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
        // Live payment processing is not connected yet. Buying crypto is
        // disabled until a real payment provider is wired in — no balance
        // is ever debited or credited from this form in the meantime.
        $errors[] = 'Buying crypto isn\'t available right now — live payment processing hasn\'t been connected yet. Please check back soon.';
    }
}

$pageTitle = 'Buy';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:480px;margin:0 auto">
  <h3 style="margin-bottom:20px;text-align:center">Buy Crypto</h3>
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
    <div class="field"><label>Amount (USD)</label><input type="number" step="0.01" min="1" name="amount" placeholder="e.g. 250" required></div>
    <div class="field"><label>Payment Method</label>
      <select name="payment_method" id="payMethod" onchange="document.getElementById('cardFields').style.display = this.value === 'card' ? '' : 'none'">
        <option value="card">Debit / Credit Card</option>
        <option value="bank">Bank Transfer</option>
      </select>
    </div>
    <div id="cardFields">
      <div class="form-row-2">
        <div class="field"><label>Card Number</label><input type="text" placeholder="4242 4242 4242 4242" maxlength="19"></div>
        <div class="field"><label>Expiry</label><input type="text" placeholder="MM/YY" maxlength="5"></div>
      </div>
    </div>
    <button type="submit" class="btn btn-gold btn-block">Buy Now</button>
  </form>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
