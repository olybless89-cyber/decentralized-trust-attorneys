<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

$address = get_or_create_wallet_address($user);
$asset = $_GET['asset'] ?? 'BTC';
if (!in_array($asset, wallet_supported_assets(), true)) $asset = 'BTC';

$pageTitle = 'Receive';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:520px;margin:0 auto">
  <h3 style="margin-bottom:6px;text-align:center">Receive Funds</h3>
  <p style="text-align:center;font-size:14px;margin-bottom:24px">Share this address to receive crypto into your wallet.</p>

  <div class="field" style="max-width:260px;margin:0 auto 20px">
    <label>Asset</label>
    <select onchange="location.href='receive.php?asset='+this.value">
      <?php foreach (wallet_supported_assets() as $a): ?>
        <option value="<?= e($a) ?>" <?= $asset === $a ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="qr-wrap">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($address) ?>" alt="Wallet address QR code" width="220" height="220">
  </div>

  <div class="address-box">
    <span id="walletAddr"><?= e($address) ?></span>
    <button type="button" class="btn btn-outline btn-sm" onclick="copyAddr()">Copy</button>
  </div>
  <p class="hint" style="text-align:center;margin-top:14px">Only send <?= e($asset) ?> to this address. Deposits are credited to your balance once confirmed by our team.</p>
</div>

<script>
function copyAddr() {
  const text = document.getElementById('walletAddr').innerText;
  navigator.clipboard.writeText(text).then(() => {
    const btn = event.target;
    const old = btn.innerText;
    btn.innerText = 'Copied!';
    setTimeout(() => btn.innerText = old, 1500);
  });
}
</script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
