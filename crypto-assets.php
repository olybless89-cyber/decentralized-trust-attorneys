<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

/**
 * Full coin list: [ticker, full name, network/chain, logo URL]
 * Order matches reference screenshot: XRP first, then BTC, ETH, USDT, BNB, USDC, SOL, TRX, DOGE, LTC…
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

$assets = crypto_asset_list();

// ── Load all per-asset balances for this user ────────────────────────────────
ensure_asset_balances_table();   // auto-creates table if migration not yet run
$assetBalances  = [];
$portfolioTotal = 0.0;
try {
    $abRows = db()->prepare('SELECT asset_symbol, crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ?');
    $abRows->execute([$user['id']]);
    foreach ($abRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $assetBalances[$r['asset_symbol']] = [
            'crypto' => (float) $r['crypto_amount'],
            'usd'    => (float) $r['demo_usd_amount'],
        ];
    }
    foreach ($assetBalances as $ab) {
        $portfolioTotal += $ab['usd'];
    }
} catch (PDOException $e) {
    // Table may not exist on this host yet — page renders with zero balances
    $assetBalances  = [];
    $portfolioTotal = 0.0;
}

$pageTitle = 'Crypto Assets';
require __DIR__ . '/includes/dash_header.php';
?>

<!-- ── Top action bar ── -->
<div class="ca-action-bar">
  <a href="buy.php" class="ca-action-btn ca-action-buy">
    <span class="ca-action-icon">&#8681;</span> Buy
  </a>
  <a href="receive.php" class="ca-action-btn ca-action-receive">
    <span class="ca-action-icon">&#8600;</span> Receive
  </a>
  <a href="send.php" class="ca-action-btn ca-action-send">
    <span class="ca-action-icon">&#8599;</span> Send
  </a>
  <a href="withdraw.php" class="ca-action-btn ca-action-sell">
    <span class="ca-action-icon">&#8679;</span> Sell
  </a>
</div>

<!-- ── Portfolio total header ── -->
<?php if ($portfolioTotal > 0): ?>
<div class="ca-portfolio-total-bar">
  <span class="ca-ptotal-label">Portfolio Value</span>
  <span class="ca-ptotal-amt" id="caPortfolioTotal"><?= fmt_money($portfolioTotal) ?></span>
</div>
<?php endif; ?>

<!-- ── Asset list ── -->
<div class="ca-asset-list">
  <?php foreach ($assets as [$ticker, $name, $network, $logo]):
    $bal = $assetBalances[$ticker] ?? ['crypto'=>0,'usd'=>0];
    $hasBal = $bal['crypto'] > 0;
  ?>
  <a href="coin-detail.php?coin=<?= urlencode($ticker) ?>" class="ca-asset-row<?= $hasBal ? ' ca-row-has-balance' : '' ?>"
     data-ticker="<?= e($ticker) ?>">
    <!-- Left: icon + name + price -->
    <div class="ca-asset-left">
      <div class="ca-asset-icon">
        <img src="<?= e($logo) ?>" alt="<?= e($ticker) ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <span class="ca-asset-icon-fallback" style="display:none"><?= e(substr($ticker,0,2)) ?></span>
      </div>
      <div class="ca-asset-info">
        <div class="ca-asset-name"><?= e($name) ?></div>
        <div class="ca-asset-sub">
          <span class="ca-price" data-price="<?= e($ticker) ?>">—</span>
          <span class="ca-change ca-change-up" data-change="<?= e($ticker) ?>">—</span>
        </div>
      </div>
    </div>
    <!-- Right: per-asset balance -->
    <div class="ca-asset-right">
      <?php if ($hasBal): ?>
        <div class="ca-asset-coinamt">
          <?= rtrim(rtrim(number_format($bal['crypto'], 10), '0'), '.') ?> <?= e($ticker) ?>
        </div>
        <div class="ca-asset-usd ca-has-bal" data-base-usd="<?= e($bal['usd']) ?>" data-crypto="<?= e($bal['crypto']) ?>" data-ticker="<?= e($ticker) ?>">
          <?= fmt_money($bal['usd']) ?>
        </div>
      <?php else: ?>
        <div class="ca-asset-coinamt"></div>
        <div class="ca-asset-usd"><?= e($ticker) ?></div>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Price + live USD value updates -->
<script>
(function() {
  // Per-asset crypto holdings from PHP (crypto_amount stored per coin)
  var ASSET_CRYPTO = <?= json_encode(array_combine(
      array_column($assets, 0),
      array_map(function($a) use ($assetBalances) {
          return isset($assetBalances[$a[0]]) ? (float)$assetBalances[$a[0]]['crypto'] : 0;
      }, $assets)
  )) ?>;

  function fmtPrice(v) {
    if (v >= 1000) return '$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (v >= 1)    return '$' + v.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    return '$' + v.toFixed(6);
  }
  function fmtUsd(v) {
    return '$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function fmtCrypto(v, ticker) {
    var dp = v > 100 ? 3 : v > 1 ? 4 : 6;
    return parseFloat(v.toFixed(dp)) + ' ' + ticker;
  }

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

  // Collect all tickers that have a cgMap entry
  var tickers = Object.keys(cgMap);
  var cgIds   = tickers.map(function(t){ return cgMap[t]; }).join(',');

  fetch('https://api.coingecko.com/api/v3/simple/price?ids=' + cgIds + '&vs_currencies=usd&include_24hr_change=true')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var livePortfolioTotal = 0;

      document.querySelectorAll('.ca-asset-row').forEach(function(row) {
        var t    = row.dataset.ticker;
        var cgId = cgMap[t];
        if (!cgId || !data[cgId]) return;

        var price  = data[cgId].usd;
        var change = data[cgId].usd_24h_change || 0;

        // Price display
        var priceEl = row.querySelector('[data-price]');
        if (priceEl) priceEl.textContent = fmtPrice(price);

        // % change
        var changeEl = row.querySelector('[data-change]');
        if (changeEl) {
          changeEl.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
          changeEl.classList.toggle('ca-change-up',   change >= 0);
          changeEl.classList.toggle('ca-change-down', change <  0);
        }

        // Per-asset live USD value (crypto_amount * live price)
        var cryptoHeld = ASSET_CRYPTO[t] || 0;
        if (cryptoHeld > 0) {
          var liveUsd = cryptoHeld * price;
          livePortfolioTotal += liveUsd;
          // Update the USD value cell
          var usdEl = row.querySelector('.ca-has-bal');
          if (usdEl) usdEl.textContent = fmtUsd(liveUsd);
        }
      });

      // Update portfolio total with live prices
      var ptEl = document.getElementById('caPortfolioTotal');
      if (ptEl && livePortfolioTotal > 0) ptEl.textContent = fmtUsd(livePortfolioTotal);
    })
    .catch(function(){
      // Fallback: prices stay as PHP-rendered values
    });
})();
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
