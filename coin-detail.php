<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

// ── Resolve coin from query string ──────────────────────────────────────────
$allAssets = crypto_asset_list();
$assetMap  = [];
foreach ($allAssets as $row) {
    $assetMap[$row[0]] = $row;
}

$ticker = strtoupper(trim($_GET['coin'] ?? ''));
if (!isset($assetMap[$ticker])) {
    header('Location: crypto-assets.php');
    exit;
}

[$ticker, $coinName, $network, $logoUrl] = $assetMap[$ticker];

// ── Load per-asset balance from asset_balances table ────────────────────────
ensure_asset_balances_table();   // auto-creates table if migration not yet run
$existingCrypto = 0;
$existingUsd    = 0;
try {
    $abStmt = db()->prepare('SELECT crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ?');
    $abStmt->execute([$user['id'], $ticker]);
    $assetBal = $abStmt->fetch(PDO::FETCH_ASSOC);
    if ($assetBal) {
        $existingCrypto = (float) $assetBal['crypto_amount'];
        $existingUsd    = (float) $assetBal['demo_usd_amount'];
    }
} catch (PDOException $e) {
    // Table not yet created — show zero balance, form still works once table exists
}

$syncedAt  = date('Y-m-d H:i') . ' UTC';
$pageTitle = $coinName;

/**
 * Full coin list — duplicated here so coin-detail.php is self-contained.
 * Keep in sync with crypto-assets.php.
 */
function crypto_asset_list(): array {
    return [
        ['XRP',   'XRP',               'XRP Ledger',                'https://assets.coingecko.com/coins/images/44/large/xrp-symbol-white-128.png'],
        ['BTC',   'Bitcoin',           'Bitcoin Network',           'https://assets.coingecko.com/coins/images/1/large/bitcoin.png'],
        ['ETH',   'Ethereum',          'ERC-20 / Ethereum',         'https://assets.coingecko.com/coins/images/279/large/ethereum.png'],
        ['USDT',  'Tether USD',        'TRC-20 / Tron',             'https://assets.coingecko.com/coins/images/325/large/Tether.png'],
        ['BNB',   'BNB',               'BEP-20 / BSC',              'https://assets.coingecko.com/coins/images/825/large/bnb-icon2_2x.png'],
        ['USDC',  'USDC',              'ERC-20 / Ethereum',         'https://assets.coingecko.com/coins/images/6319/large/usdc.png'],
        ['SOL',   'Solana',            'Solana Network',            'https://assets.coingecko.com/coins/images/4128/large/solana.png'],
        ['TRX',   'Tron',              'Tron Network',              'https://assets.coingecko.com/coins/images/1094/large/tron-logo.png'],
        ['DOGE',  'Dogecoin',          'Dogecoin Network',          'https://assets.coingecko.com/coins/images/5/large/dogecoin.png'],
        ['LTC',   'Litecoin',          'Litecoin Network',          'https://assets.coingecko.com/coins/images/2/large/litecoin.png'],
        ['XLM',   'Stellar',           'Stellar Network',           'https://assets.coingecko.com/coins/images/100/large/Stellar_symbol_black_RGB.png'],
        ['AVAX',  'Avalanche',         'Avalanche C-Chain',         'https://assets.coingecko.com/coins/images/12559/large/Avalanche_Circle_RedWhite_Trans.png'],
        ['MATIC', 'Polygon',           'Polygon Network',           'https://assets.coingecko.com/coins/images/4713/large/matic-token-icon.png'],
        ['DOT',   'Polkadot',          'Polkadot Network',          'https://assets.coingecko.com/coins/images/12171/large/polkadot.png'],
        ['ADA',   'Cardano',           'Cardano Network',           'https://assets.coingecko.com/coins/images/975/large/cardano.png'],
        ['LINK',  'Chainlink',         'ERC-20 / Ethereum',         'https://assets.coingecko.com/coins/images/877/large/chainlink-new-logo.png'],
        ['UNI',   'Uniswap',           'ERC-20 / Ethereum',         'https://assets.coingecko.com/coins/images/12504/large/uniswap-uni.png'],
        ['ATOM',  'Cosmos',            'Cosmos Network',            'https://assets.coingecko.com/coins/images/1481/large/cosmos_hub.png'],
        ['NEAR',  'NEAR Protocol',     'NEAR Network',              'https://assets.coingecko.com/coins/images/10365/large/near_icon.png'],
        ['ICP',   'Internet Computer', 'ICP Network',               'https://assets.coingecko.com/coins/images/14495/large/Internet_Computer_logo.png'],
        ['VET',   'VeChain',           'VeChain Network',           'https://assets.coingecko.com/coins/images/1167/large/VeChain-Logo-768x768.png'],
        ['FIL',   'Filecoin',          'Filecoin Network',          'https://assets.coingecko.com/coins/images/12817/large/filecoin.png'],
        ['ALGO',  'Algorand',          'Algorand Network',          'https://assets.coingecko.com/coins/images/4380/large/download.png'],
        ['FTM',   'Fantom',            'Fantom Opera',              'https://assets.coingecko.com/coins/images/4001/large/Fantom_round.png'],
        ['XTZ',   'Tezos',             'Tezos Network',             'https://assets.coingecko.com/coins/images/976/large/Tezos-logo.png'],
    ];
}

require __DIR__ . '/includes/dash_header.php';
?>

<!-- ── Back link ── -->
<div style="margin-bottom:20px">
  <a href="crypto-assets.php" class="cd-back-btn">
    <span class="cd-back-arrow">&#8592;</span> Crypto Assets
  </a>
</div>

<!-- ── Coin hero card ── -->
<div class="cd-hero">
  <div class="cd-hero-left">
    <div class="cd-coin-icon">
      <img src="<?= e($logoUrl) ?>" alt="<?= e($ticker) ?>"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <span class="cd-coin-icon-fallback" style="display:none"><?= e(substr($ticker,0,2)) ?></span>
    </div>
    <div>
      <div class="cd-coin-name"><?= e($coinName) ?></div>
      <div class="cd-coin-ticker"><?= e($ticker) ?> &middot; <?= e($network) ?></div>
    </div>
  </div>
  <div class="cd-hero-right">
    <div class="cd-live-price" id="cdLivePrice">—</div>
    <div class="cd-price-change" id="cdPriceChange">&nbsp;</div>
  </div>
</div>

<!-- ── Per-asset balance card ── -->
<div class="cd-balance-card" id="cdBalanceCard">
  <div class="cd-balance-label">Your <?= e($ticker) ?> Balance</div>
  <div class="cd-balance-amount" id="cdCryptoDisplay">
    <?= $existingCrypto > 0
        ? rtrim(rtrim(number_format($existingCrypto, 10), '0'), '.') . ' ' . e($ticker)
        : '0 ' . e($ticker) ?>
  </div>
  <div class="cd-balance-sub">
    USD Value: <span id="cdUsdValue">
      <?= $existingCrypto > 0 ? '<span style="color:var(--muted);font-size:13px">Calculating live price…</span>' : '$0.00' ?>
    </span>
    &nbsp;&bull;&nbsp; Last synced: <?= e($syncedAt) ?>
  </div>
</div>

<!-- ── 4 Action buttons ── -->
<div class="cd-actions">
  <a href="send.php?asset=<?= urlencode($ticker) ?>" class="cd-action-btn">
    <span class="cd-action-icon cd-send">&#8593;</span>
    <span>Send</span>
  </a>
  <a href="receive.php?asset=<?= urlencode($ticker) ?>" class="cd-action-btn">
    <span class="cd-action-icon cd-receive">&#8595;</span>
    <span>Receive</span>
  </a>
  <a href="swap.php?from=<?= urlencode($ticker) ?>" class="cd-action-btn">
    <span class="cd-action-icon cd-swap">&#8646;</span>
    <span>Swap</span>
  </a>
  <a href="withdraw.php?asset=<?= urlencode($ticker) ?>" class="cd-action-btn">
    <span class="cd-action-icon cd-withdraw">&#8659;</span>
    <span>Withdraw</span>
  </a>
</div>

<!-- ── Recent transactions for this coin ── -->
<?php
$txStmt = db()->prepare("SELECT * FROM transactions WHERE user_id = ? AND asset = ? ORDER BY created_at DESC LIMIT 20");
$txStmt->execute([$user['id'], $ticker]);
$coinTxs = $txStmt->fetchAll();
?>
<div class="panel" style="margin-top:24px">
  <h3 style="margin-bottom:16px"><?= e($coinName) ?> Transactions</h3>
  <?php if (!$coinTxs): ?>
    <div class="empty-state">
      <p style="margin:0;font-size:14px">No <?= e($ticker) ?> transactions yet.<br>Use Send, Receive or Swap to get started.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($coinTxs as $t): [$label, $tone] = tx_label($t['type']); ?>
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

<script>
(function() {
  var TICKER     = <?= json_encode($ticker) ?>;
  var existingCrypto = <?= json_encode($existingCrypto) ?>;
  var livePrice  = 0;

  // CoinGecko id map
  var cgMap = {
    BTC:'bitcoin', ETH:'ethereum', BNB:'binancecoin', SOL:'solana',
    USDT:'tether', XRP:'ripple', TRX:'tron', DOGE:'dogecoin',
    LTC:'litecoin', XLM:'stellar', AVAX:'avalanche-2', MATIC:'matic-network',
    DOT:'polkadot', ADA:'cardano', LINK:'chainlink', UNI:'uniswap',
    ATOM:'cosmos', FTM:'fantom', ALGO:'algorand', NEAR:'near',
    ICP:'internet-computer', VET:'vechain', FIL:'filecoin', XTZ:'tezos',
    USDC:'usd-coin'
  };

  function fmtUsd(n) {
    return '$' + n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  // ── Fetch live price ────────────────────────────────────────────────────────
  var cgId = cgMap[TICKER];
  if (cgId) {
    fetch('https://api.coingecko.com/api/v3/simple/price?ids=' + cgId + '&vs_currencies=usd&include_24hr_change=true')
      .then(function(r){ return r.json(); })
      .then(function(data) {
        var info = data[cgId];
        if (!info) return;
        livePrice = info.usd;
        var change = info.usd_24h_change;

        document.getElementById('cdLivePrice').textContent =
          '$' + livePrice.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits: livePrice < 1 ? 6 : 2});

        var chEl = document.getElementById('cdPriceChange');
        chEl.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '% (24h)';
        chEl.className   = 'cd-price-change ' + (change >= 0 ? 'up' : 'down');

        // Update live USD value of existing crypto balance
        if (existingCrypto > 0 && livePrice > 0) {
          var liveUsd = existingCrypto * livePrice;
          document.getElementById('cdUsdValue').textContent = fmtUsd(liveUsd);
        }
      })
      .catch(function(){});
  }
})();
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
