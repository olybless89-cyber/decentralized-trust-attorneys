<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/wallet.php';
$user = require_login();

/**
 * Full coin list shown in the CryptoRepublic-style portfolio view.
 * Each entry: [ticker, full name, network/chain label, logo URL]
 * Logos: CoinGecko CDN (stable, widely used) + fallback colour set.
 */
function crypto_asset_list(): array {
    return [
        ['BTC',   'Bitcoin',           'Bitcoin Network',           'https://assets.coingecko.com/coins/images/1/large/bitcoin.png'],
        ['ETH',   'Ethereum',          'ERC-20 / Ethereum',         'https://assets.coingecko.com/coins/images/279/large/ethereum.png'],
        ['BNB',   'BNB',               'BEP-20 / BSC',              'https://assets.coingecko.com/coins/images/825/large/bnb-icon2_2x.png'],
        ['SOL',   'Solana',            'Solana Network',            'https://assets.coingecko.com/coins/images/4128/large/solana.png'],
        ['USDT',  'Tether',            'TRC-20 / Tron',             'https://assets.coingecko.com/coins/images/325/large/Tether.png'],
        ['XRP',   'Ripple',            'XRP Ledger',                'https://assets.coingecko.com/coins/images/44/large/xrp-symbol-white-128.png'],
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
        ['FTM',   'Fantom',            'Fantom Opera',              'https://assets.coingecko.com/coins/images/4001/large/Fantom_round.png'],
        ['ONE',   'Harmony',           'Harmony Network',           'https://assets.coingecko.com/coins/images/4344/large/Y88JAze.png'],
        ['ALGO',  'Algorand',          'Algorand Network',          'https://assets.coingecko.com/coins/images/4380/large/download.png'],
        ['NEAR',  'NEAR Protocol',     'NEAR Network',              'https://assets.coingecko.com/coins/images/10365/large/near_icon.png'],
        ['ICP',   'Internet Computer', 'ICP Network',               'https://assets.coingecko.com/coins/images/14495/large/Internet_Computer_logo.png'],
        ['VET',   'VeChain',           'VeChain Network',           'https://assets.coingecko.com/coins/images/1167/large/VeChain-Logo-768x768.png'],
        ['FIL',   'Filecoin',          'Filecoin Network',          'https://assets.coingecko.com/coins/images/12817/large/filecoin.png'],
        ['XTZ',   'Tezos',             'Tezos Network',             'https://assets.coingecko.com/coins/images/976/large/Tezos-logo.png'],
    ];
}

/** Per-user balance for a given asset (from transactions, or 0). */
function asset_balance_for_user(int $userId, string $ticker): float {
    // In this platform the single USD-denominated balance covers all assets.
    // Show the platform balance only on BTC row; all others display $0 to match
    // the CryptoRepublic reference flow (real multi-asset balances can be added
    // once per-asset tracking is implemented).
    return 0.00;
}

$assets      = crypto_asset_list();
$syncedAt    = date('Y-m-d H:i') . ' UTC';   // "last synced" timestamp
$portfolioTotal = (float) $user['balance'];

$pageTitle = 'Crypto Assets';
require __DIR__ . '/includes/dash_header.php';
?>

<!-- ── Portfolio header ── -->
<div class="ca-portfolio-header">
  <div class="ca-portfolio-title">
    <span>Portfolio of</span>
    <button class="ca-portfolio-name-btn" onclick="void(0)">
      <?= e($user['full_name']) ?> <span class="ca-chevron">&#x2304;</span>
    </button>
  </div>
  <div class="ca-portfolio-total"><?= fmt_money($portfolioTotal) ?></div>
  <div class="ca-portfolio-sub">Total Balance &nbsp;&bull;&nbsp; <?= $syncedAt ?></div>
</div>

<!-- ── Asset list ── -->
<div class="ca-asset-list">
  <?php foreach ($assets as [$ticker, $name, $network, $logo]): ?>
  <a href="coin-detail.php?coin=<?= urlencode($ticker) ?>" class="ca-asset-row">
    <!-- Left: icon + name -->
    <div class="ca-asset-left">
      <div class="ca-asset-icon">
        <img src="<?= e($logo) ?>" alt="<?= e($ticker) ?>"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <span class="ca-asset-icon-fallback" style="display:none"><?= e(substr($ticker,0,2)) ?></span>
      </div>
      <div class="ca-asset-info">
        <div class="ca-asset-name"><?= e($name) ?></div>
        <div class="ca-asset-sub">Last synced: <?= e($syncedAt) ?></div>
      </div>
    </div>
    <!-- Right: balance + chevron -->
    <div class="ca-asset-right">
      <div class="ca-asset-balance"><?= fmt_money(asset_balance_for_user($user['id'], $ticker)) ?></div>
      <div class="ca-asset-ticker"><?= e($name) ?></div>
    </div>
    <span class="ca-row-chevron">&#8250;</span>
  </a>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
