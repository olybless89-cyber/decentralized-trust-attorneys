/**
 * Live crypto price ticker — fetches directly from CoinGecko's public API
 * in the visitor's browser (no server-side API key needed), refreshing
 * every 30 seconds. Renders into any element with id="crypto-ticker".
 */
(function () {
  const COINS = [
    { id: 'bitcoin', symbol: 'BTC' },
    { id: 'ethereum', symbol: 'ETH' },
    { id: 'tether', symbol: 'USDT' },
    { id: 'binancecoin', symbol: 'BNB' },
    { id: 'solana', symbol: 'SOL' },
    { id: 'ripple', symbol: 'XRP' },
  ];
  const API = 'https://api.coingecko.com/api/v3/simple/price?ids=' +
    COINS.map(c => c.id).join(',') + '&vs_currencies=usd&include_24hr_change=true';

  function fmtPrice(n) {
    if (n >= 1) return '$' + n.toLocaleString(undefined, { maximumFractionDigits: 2 });
    return '$' + n.toLocaleString(undefined, { maximumFractionDigits: 4 });
  }

  async function refresh() {
    const el = document.getElementById('crypto-ticker');
    if (!el) return;
    try {
      const res = await fetch(API);
      if (!res.ok) throw new Error('bad response');
      const data = await res.json();
      el.innerHTML = COINS.map(c => {
        const d = data[c.id];
        if (!d) return '';
        const change = d.usd_24h_change || 0;
        const up = change >= 0;
        return `<div class="ticker-item">
          <span class="ticker-sym">${c.symbol}</span>
          <span class="ticker-price">${fmtPrice(d.usd)}</span>
          <span class="ticker-change ${up ? 'up' : 'down'}">${up ? '▲' : '▼'} ${Math.abs(change).toFixed(2)}%</span>
        </div>`;
      }).join('');
    } catch (e) {
      el.innerHTML = '<div class="ticker-item" style="color:var(--muted)">Live prices unavailable right now.</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    refresh();
    setInterval(refresh, 30000);
  });
})();
