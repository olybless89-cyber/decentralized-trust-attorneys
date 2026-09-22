<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

$stmt = db()->prepare('SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$apps = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
$stmt->execute([$user['id']]);
$txs = $stmt->fetchAll();

// ── Portfolio total = sum of all per-asset USD values in asset_balances ──────
// This starting figure is refreshed client-side with live prices below (see
// the script at the bottom of this page), the same way crypto-assets.php
// does, so the two pages always agree on the current portfolio value.
ensure_asset_balances_table();
$portfolioTotal = 0.0;
$assetCrypto    = [];
try {
    $ptStmt = db()->prepare('SELECT asset_symbol, crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ?');
    $ptStmt->execute([$user['id']]);
    foreach ($ptStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $portfolioTotal += (float) $r['demo_usd_amount'];
        $assetCrypto[$r['asset_symbol']] = (float) $r['crypto_amount'];
    }
} catch (PDOException $e) {
    // Table not yet created — fall back to legacy users.balance
    $portfolioTotal = (float) $user['balance'];
}

$entityLabels = [
    'LLC' => 'Limited Liability Company',
    'CCORP' => 'Corporation (C-Corp)',
    'CLOSE_LLC' => 'Close LLC',
    'CLOSE_CORP' => 'Close Corporation',
];

$pageTitle = 'Overview';
require __DIR__ . '/includes/dash_header.php';
?>
<div id="crypto-ticker" class="ticker-bar"><div class="ticker-item" style="color:#94a3b8">Loading live prices&hellip;</div></div>

<div class="balance-card">
  <div>
    <div class="label">Total Portfolio Value</div>
    <div class="amount" id="dashPortfolioTotal"><?= fmt_money($portfolioTotal) ?></div>
  </div>
  <div class="quick-actions">
    <a href="send.php" class="qa-btn"><span class="qa-icon">&#8593;</span>Send</a>
    <a href="receive.php" class="qa-btn"><span class="qa-icon">&#8595;</span>Receive</a>
    <a href="swap.php" class="qa-btn"><span class="qa-icon">&#8646;</span>Swap</a>
    <a href="buy.php" class="qa-btn"><span class="qa-icon">&#43;</span>Buy</a>
    <a href="withdraw.php" class="qa-btn"><span class="qa-icon">&#128176;</span>Withdraw</a>
  </div>
</div>

<div class="grid grid-2" style="align-items:start">
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0">Recent Activity</h3>
    </div>
    <?php if (!$txs): ?>
      <p style="color:var(--muted);font-size:14px">No wallet activity yet — try Buy or Receive to fund your wallet.</p>
    <?php else: ?>
      <div class="tx-list">
        <?php foreach ($txs as $t): [$label, $tone] = tx_label($t['type']); ?>
          <div class="tx-row">
            <div class="tx-dot tx-<?= $tone ?>"></div>
            <div class="tx-main">
              <div class="tx-title"><?= e($label) ?><?= $t['counter_asset'] ? ' &rarr; ' . e($t['counter_asset']) : '' ?></div>
              <div class="tx-sub"><?= e($t['asset']) ?> &middot; <?= e(date('M j, g:ia', strtotime($t['created_at']))) ?></div>
            </div>
            <div class="tx-amount tx-<?= $tone ?>"><?= $tone === 'down' ? '&minus;' : ($tone === 'up' ? '+' : '') ?><?= fmt_money(abs((float) $t['amount_usd'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0">Your Applications</h3>
      <a href="applications.php" style="font-size:13.5px;font-weight:600;color:var(--navy)">View all &rarr;</a>
    </div>
    <?php if (!$apps): ?>
      <div class="empty-state">
        <h3 style="margin-bottom:8px;font-size:17px">No applications yet</h3>
        <p style="font-size:14px">Start your first business formation application.</p>
        <a href="application.php" class="btn btn-primary btn-sm" style="margin-top:6px">Start Your Formation</a>
      </div>
    <?php else: ?>
      <?php foreach ($apps as $a): ?>
        <div class="review-row">
          <div>
            <div class="v"><?= e($a['business_name']) ?></div>
            <div class="k" style="margin-top:2px"><?= e($entityLabels[$a['entity_type']] ?? $a['entity_type']) ?> &middot; <?= e($a['state']) ?></div>
          </div>
          <?= status_badge($a['status']) ?>
        </div>
      <?php endforeach; ?>
      <a href="application.php" class="btn btn-outline btn-sm btn-block" style="margin-top:16px">+ New Application</a>
    <?php endif; ?>
  </div>
</div>

<script src="assets/js/crypto-ticker.js"></script>
<script>
(function() {
  // Per-asset crypto holdings from PHP — same figures crypto-assets.php uses,
  // so this page's total converges on the same live-priced number.
  var ASSET_CRYPTO = <?= json_encode($assetCrypto) ?>;
  var tickers = Object.keys(ASSET_CRYPTO).filter(function(t){ return ASSET_CRYPTO[t] > 0; });
  if (!tickers.length) return;

  var cgMap = {
    BTC:'bitcoin', ETH:'ethereum', BNB:'binancecoin', SOL:'solana',
    USDT:'tether', XRP:'ripple', TRX:'tron', DOGE:'dogecoin',
    LTC:'litecoin', XLM:'stellar', AVAX:'avalanche-2', MATIC:'matic-network',
    DOT:'polkadot', ADA:'cardano', LINK:'chainlink', UNI:'uniswap',
    ATOM:'cosmos', FTM:'fantom', ALGO:'algorand', NEAR:'near',
    ICP:'internet-computer', VET:'vechain', FIL:'filecoin', XTZ:'tezos',
    USDC:'usd-coin'
  };
  var cgIds = tickers.map(function(t){ return cgMap[t]; }).filter(Boolean).join(',');
  if (!cgIds) return;

  function fmtUsd(v) {
    return '$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  fetch('https://api.coingecko.com/api/v3/simple/price?ids=' + cgIds + '&vs_currencies=usd')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var liveTotal = 0;
      tickers.forEach(function(t) {
        var cgId = cgMap[t];
        if (cgId && data[cgId]) liveTotal += ASSET_CRYPTO[t] * data[cgId].usd;
      });
      var el = document.getElementById('dashPortfolioTotal');
      if (el && liveTotal > 0) el.textContent = fmtUsd(liveTotal);
    })
    .catch(function(){
      // Fallback: total stays as the PHP-rendered value
    });
})();
</script>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
