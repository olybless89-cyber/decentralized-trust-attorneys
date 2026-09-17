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

$assets         = crypto_asset_list();
$portfolioTotal = (float) $user['balance'];
$pageTitle      = 'Crypto Assets';
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

<!-- ── Asset list ── -->
<div class="ca-asset-list">
  <?php foreach ($assets as [$ticker, $name, $network, $logo]): ?>
  <a href="coin-detail.php?coin=<?= urlencode($ticker) ?>" class="ca-asset-row"
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
    <!-- Right: coin amount + usd OR just ticker -->
    <div class="ca-asset-right">
      <div class="ca-asset-coinamt" data-coinamt="<?= e($ticker) ?>"></div>
      <div class="ca-asset-usd"    data-usdval="<?= e($ticker) ?>">
        <?= e($ticker) ?>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Static price data (matches reference screenshot values) -->
<script>
const CA_PRICES = {
  XRP:   { price: 1.29,      change: 0.5,  userAmt: 265.211, userUsd: 342.12 },
  BTC:   { price: 76344.48,  change: 0.7,  userAmt: 0,       userUsd: 0 },
  ETH:   { price: 2433.51,   change: 1.4,  userAmt: 0,       userUsd: 0 },
  USDT:  { price: 0.9992,    change: 0.1,  userAmt: 0,       userUsd: 0 },
  BNB:   { price: 723.76,    change: 2.1,  userAmt: 0,       userUsd: 0 },
  USDC:  { price: 0.9996,    change: 0.1,  userAmt: 0,       userUsd: 0 },
  SOL:   { price: 99.78,     change: 2.9,  userAmt: 0,       userUsd: 0 },
  TRX:   { price: 0.3347,    change: 0.1,  userAmt: 0,       userUsd: 0 },
  DOGE:  { price: 0.0808,    change: 1.4,  userAmt: 0,       userUsd: 0 },
  LTC:   { price: 84.20,     change: 0.8,  userAmt: 0,       userUsd: 0 },
  XLM:   { price: 0.1124,    change: 0.3,  userAmt: 0,       userUsd: 0 },
  AVAX:  { price: 28.54,     change: 1.6,  userAmt: 0,       userUsd: 0 },
  MATIC: { price: 0.4821,    change: 1.1,  userAmt: 0,       userUsd: 0 },
  DOT:   { price: 5.91,      change: 0.9,  userAmt: 0,       userUsd: 0 },
  ADA:   { price: 0.3612,    change: 0.6,  userAmt: 0,       userUsd: 0 },
  LINK:  { price: 11.38,     change: 1.3,  userAmt: 0,       userUsd: 0 },
  UNI:   { price: 6.74,      change: 0.5,  userAmt: 0,       userUsd: 0 },
  ATOM:  { price: 4.57,      change: 1.0,  userAmt: 0,       userUsd: 0 },
  NEAR:  { price: 3.22,      change: 2.0,  userAmt: 0,       userUsd: 0 },
  ICP:   { price: 7.88,      change: 0.4,  userAmt: 0,       userUsd: 0 },
  VET:   { price: 0.0238,    change: 0.7,  userAmt: 0,       userUsd: 0 },
  FIL:   { price: 3.64,      change: 0.9,  userAmt: 0,       userUsd: 0 },
  ALGO:  { price: 0.1589,    change: 0.2,  userAmt: 0,       userUsd: 0 },
  FTM:   { price: 0.6231,    change: 1.5,  userAmt: 0,       userUsd: 0 },
  XTZ:   { price: 0.7012,    change: 0.3,  userAmt: 0,       userUsd: 0 },
};

function fmtPrice(v) {
  if (v >= 1000) return '$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  if (v >= 1)    return '$' + v.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
  return '$' + v.toFixed(4);
}
function fmtUsd(v) {
  return '$' + v.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function fmtAmt(v, ticker) {
  const dp = (v > 100) ? 3 : (v > 1 ? 4 : 6);
  return v.toFixed(dp) + ' ' + ticker;
}

document.querySelectorAll('.ca-asset-row').forEach(row => {
  const t = row.dataset.ticker;
  const d = CA_PRICES[t];
  if (!d) return;

  // Price
  const priceEl = row.querySelector('[data-price]');
  if (priceEl) priceEl.textContent = fmtPrice(d.price);

  // % change
  const changeEl = row.querySelector('[data-change]');
  if (changeEl) {
    const sign = d.change >= 0 ? '+' : '';
    changeEl.textContent = sign + d.change.toFixed(1) + '%';
    changeEl.classList.toggle('ca-change-up',   d.change >= 0);
    changeEl.classList.toggle('ca-change-down', d.change <  0);
  }

  // Right side: coin amount + USD if user holds, else just ticker
  const amtEl = row.querySelector('[data-coinamt]');
  const usdEl = row.querySelector('[data-usdval]');
  if (d.userAmt > 0) {
    if (amtEl) amtEl.textContent = fmtAmt(d.userAmt, t);
    if (usdEl) usdEl.textContent = fmtUsd(d.userUsd);
  } else {
    if (amtEl) amtEl.textContent = '';
    if (usdEl) usdEl.textContent = t;   // just show ticker like reference
  }
});
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
