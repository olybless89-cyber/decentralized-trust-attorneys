<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/mailer.php';

ensure_asset_balances_table();

/**
 * Full coin list for admin — all 25 coins the platform supports.
 * Mirrors crypto-assets.php so admin can set any coin's balance.
 */
function admin_all_assets(): array {
    return [
        'XRP'  => 'XRP',           'BTC'  => 'Bitcoin',        'ETH'  => 'Ethereum',
        'USDT' => 'Tether USD',    'BNB'  => 'BNB',            'USDC' => 'USDC',
        'SOL'  => 'Solana',        'TRX'  => 'Tron',           'DOGE' => 'Dogecoin',
        'LTC'  => 'Litecoin',      'XLM'  => 'Stellar',        'AVAX' => 'Avalanche',
        'MATIC'=> 'Polygon',       'DOT'  => 'Polkadot',       'ADA'  => 'Cardano',
        'LINK' => 'Chainlink',     'UNI'  => 'Uniswap',        'ATOM' => 'Cosmos',
        'NEAR' => 'NEAR Protocol', 'ICP'  => 'Internet Computer',
        'VET'  => 'VeChain',       'FIL'  => 'Filecoin',       'ALGO' => 'Algorand',
        'FTM'  => 'Fantom',        'XTZ'  => 'Tezos',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $userId    = (int) ($_POST['user_id'] ?? 0);
    $asset     = strtoupper(trim($_POST['asset'] ?? 'BTC'));
    $mode      = $_POST['mode'] ?? 'add';   // 'add' or 'set'
    $usdAmount = (float) ($_POST['usd_amount']   ?? 0);
    $coinPrice = (float) ($_POST['coin_price']   ?? 0);
    $note      = trim($_POST['note'] ?? '');

    $allAdminAssets = admin_all_assets();
    if (!isset($allAdminAssets[$asset])) $asset = 'BTC';

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        flash_set('Select a valid user.', 'error');
    } elseif ($usdAmount <= 0) {
        flash_set('USD amount must be greater than 0.', 'error');
    } elseif ($coinPrice <= 0) {
        flash_set('Please enter the current coin price.', 'error');
    } else {
        $db = db();
        // Fetch existing per-asset balance
        $existStmt = $db->prepare('SELECT id, crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ?');
        $existStmt->execute([$userId, $asset]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

        $addCrypto = $usdAmount / $coinPrice;

        if ($mode === 'set') {
            // Replace entire balance for this asset
            $newCrypto = $addCrypto;
            $newUsd    = $usdAmount;
        } else {
            // Add on top of existing
            $newCrypto = ($existing ? (float)$existing['crypto_amount']   : 0) + $addCrypto;
            $newUsd    = ($existing ? (float)$existing['demo_usd_amount'] : 0) + $usdAmount;
        }

        if ($existing) {
            $db->prepare('UPDATE asset_balances SET crypto_amount=?, demo_usd_amount=?, updated_at=NOW() WHERE id=?')
               ->execute([$newCrypto, $newUsd, $existing['id']]);
        } else {
            $db->prepare('INSERT INTO asset_balances (user_id, asset_symbol, asset_name, crypto_amount, demo_usd_amount) VALUES (?,?,?,?,?)')
               ->execute([$userId, $asset, asset_label($asset), $newCrypto, $newUsd]);
        }

        log_transaction($userId, 'admin_credit', $asset, $usdAmount, null, null, $note ?: 'Admin balance set');
        send_email($target['email'], $target['full_name'], 'Balance Update',
            '<p>Hi ' . e($target['full_name']) . ',</p><p>Your <strong>' . e($asset) . '</strong> balance was updated' .
            ($note ? ' (' . e($note) . ')' : '') . '.</p>');

        flash_set($asset . ' balance set: ' . number_format($newCrypto, 8) . ' ' . $asset . ' (' . fmt_money($newUsd) . ')');
    }
    header('Location: manage-funds.php?user=' . $userId);
    exit;
}

$users = db()->query("SELECT id, full_name, email, balance FROM users ORDER BY full_name ASC")->fetchAll();
$selectedId = (int) ($_GET['user'] ?? 0);

$history = db()->query("SELECT t.*, u.full_name FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.type IN ('admin_credit','admin_debit') ORDER BY t.created_at DESC LIMIT 25")->fetchAll();

$pageTitle = 'Manage User Funds';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-panel">
  <div class="adm-panel-head">
    <div>
      <h3>&#128181; Set User Wallet Balance</h3>
      <div class="sub">Update the master USD balance for a user and record the coin.</div>
    </div>
  </div>

  <form method="post" id="fundsForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="user_id" id="userIdInput" value="<?= $selectedId ?: '' ?>">

    <div class="adm-field" style="position:relative">
      <label>Select Target User <span class="req">*</span></label>
      <input type="text" id="userSearch" placeholder="Search and select a user..." autocomplete="off" required>
      <div id="userDropdown" style="display:none;position:absolute;z-index:30;left:0;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid var(--adm-line);border-radius:10px;box-shadow:var(--adm-shadow);max-height:260px;overflow-y:auto"></div>
    </div>

    <div id="balancePill" class="adm-balance-pill" style="display:none">
      <span class="k">Current Asset Balances:</span>
      <span id="balancePillAmt" style="font-size:13px">—</span>
    </div>

    <div class="adm-field">
      <label>Select Coin <span class="req">*</span></label>
      <select name="asset" id="assetSelect">
        <?php foreach (admin_all_assets() as $sym => $lbl): ?>
          <option value="<?= e($sym) ?>"><?= e($lbl) ?> (<?= e($sym) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="currentAssetBal" style="display:none;margin-bottom:12px;padding:10px 14px;background:#f0fdf4;border-radius:8px;font-size:13px;color:#15803d">
      Current balance for selected coin: <strong id="currentAssetBalText">—</strong>
    </div>

    <div class="adm-field">
      <label>Current Coin Price (USD) <span class="req">*</span></label>
      <input type="number" step="0.000001" name="coin_price" id="coinPriceInput" placeholder="e.g. 95000 for BTC" required>
      <div class="hint">Used to calculate the crypto amount: crypto = USD ÷ price.</div>
    </div>

    <div class="adm-field">
      <label>USD Amount to Add <span class="req">*</span></label>
      <input type="number" step="0.01" name="usd_amount" id="usdAmountInput" placeholder="e.g. 5000" required>
      <div id="cryptoPreviewAdmin" style="display:none;margin-top:6px;font-size:13px;color:#6366f1">≈ <span id="cryptoPreviewAmt">0</span> <span id="cryptoPreviewSymbol">BTC</span></div>
    </div>

    <div class="adm-field">
      <label>Mode</label>
      <select name="mode">
        <option value="add">Add to existing balance</option>
        <option value="set">Replace (set exact balance)</option>
      </select>
      <div class="hint">"Add" stacks on top of current balance. "Replace" sets it directly.</div>
    </div>

    <div class="adm-field">
      <label>Reason / Note (Optional)</label>
      <textarea name="note" maxlength="500" placeholder="e.g., Wire transfer received, account credit..."></textarea>
    </div>

    <button type="submit" class="adm-btn adm-btn-primary adm-btn-block" id="fundsSubmit">Update Asset Balance</button>
  </form>
</div>

<div class="adm-panel" style="margin-bottom:0">
  <div class="adm-panel-head">
    <div><h3>&#9889; Fund History Records</h3><div class="sub">Recent records of coin balance adjustments.</div></div>
  </div>
  <?php if (!$history): ?>
    <div class="adm-empty">No balance adjustments recorded yet.</div>
  <?php else: ?>
    <div class="adm-table-wrap">
    <table class="adm-table">
      <thead><tr><th>Date</th><th>Target User</th><th>Coin</th><th>Amount</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): $isCredit = $h['type'] === 'admin_credit'; ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($h['created_at']))) ?> <span class="adm-cell-sub">UTC</span></td>
          <td><?= e($h['full_name']) ?></td>
          <td><?= e($h['asset']) ?></td>
          <td class="<?= $isCredit ? 'adm-amt-pos' : 'adm-amt-neg' ?>"><?= $isCredit ? '+' : '-' ?><?= fmt_money((float) $h['amount_usd']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var users = <?= json_encode(array_map(function ($u) {
      return ['id' => (int) $u['id'], 'name' => $u['full_name'], 'email' => $u['email']];
  }, $users)) ?>;
  var selectedId  = <?= (int) $selectedId ?>;
  var search      = document.getElementById('userSearch');
  var dropdown    = document.getElementById('userDropdown');
  var userIdInput = document.getElementById('userIdInput');
  var pill        = document.getElementById('balancePill');
  var pillAmt     = document.getElementById('balancePillAmt');
  var assetSelect = document.getElementById('assetSelect');
  var curAssetDiv = document.getElementById('currentAssetBal');
  var curAssetTxt = document.getElementById('currentAssetBalText');
  var coinPriceIn = document.getElementById('coinPriceInput');
  var usdAmtIn    = document.getElementById('usdAmountInput');
  var preview     = document.getElementById('cryptoPreviewAdmin');
  var previewAmt  = document.getElementById('cryptoPreviewAmt');
  var previewSym  = document.getElementById('cryptoPreviewSymbol');

  var userAssetBalances = {}; // { userId: { BTC: {crypto, usd}, ... } }

  function fmtMoney(n) { return '$' + n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

  function loadUserAssetBalances(userId) {
    fetch('../api/asset-balance.php?action=all', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    // Note: admin fetches for self; to fetch per-user we use POST set route.
    // For display, show balances from the hidden GET for the selected user via URL trick.
    .catch(function(){});

    // Direct PHP-side: re-render pill using existing data
    // We pass asset balances per user from the server
  }

  function showAssetBalance(userId, symbol) {
    var key = userId + '_' + symbol;
    if (window._adminAssetBals && window._adminAssetBals[key] !== undefined) {
      var b = window._adminAssetBals[key];
      curAssetDiv.style.display = 'block';
      curAssetTxt.textContent   = b.crypto + ' ' + symbol + ' (' + fmtMoney(b.usd) + ')';
    } else {
      curAssetDiv.style.display = 'none';
    }
  }

  function selectUser(u) {
    userIdInput.value = u.id;
    search.value      = u.name + ' (' + u.email + ')';
    pill.style.display = 'flex';
    // Load asset balances for this user via AJAX (admin endpoint)
    fetch('../api/asset-balance.php?action=all&_uid=' + u.id + '&csrf=<?= e(csrf_token()) ?>')
      .then(function(r){ return r.json(); })
      .then(function(res) {
        if (!res.ok) return;
        var summary = [];
        for (var sym in res.balances) {
          var b = res.balances[sym];
          window._adminAssetBals = window._adminAssetBals || {};
          window._adminAssetBals[u.id + '_' + sym] = b;
          if (b.crypto_amount > 0) summary.push(sym + ': ' + parseFloat(b.crypto_amount.toFixed(8)));
        }
        pillAmt.textContent = summary.length ? summary.join(' | ') : 'No asset balances yet';
        showAssetBalance(u.id, assetSelect.value);
      })
      .catch(function(){ pillAmt.textContent = '—'; });
    dropdown.style.display = 'none';
  }

  assetSelect.addEventListener('change', function() {
    var uid = parseInt(userIdInput.value);
    if (uid) showAssetBalance(uid, assetSelect.value);
    previewSym.textContent = assetSelect.value;
    updatePreview();
  });

  function updatePreview() {
    var price = parseFloat(coinPriceIn.value);
    var usd   = parseFloat(usdAmtIn.value);
    if (price > 0 && usd > 0) {
      previewAmt.textContent = parseFloat((usd / price).toFixed(8));
      preview.style.display  = 'block';
    } else {
      preview.style.display  = 'none';
    }
  }

  coinPriceIn.addEventListener('input', updatePreview);
  usdAmtIn.addEventListener('input', updatePreview);

  function renderDropdown(list) {
    if (!list.length) { dropdown.style.display = 'none'; return; }
    dropdown.innerHTML = '';
    list.slice(0, 30).forEach(function (u) {
      var row = document.createElement('div');
      row.textContent = u.name + ' (' + u.email + ')';
      row.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #f1f1f3';
      row.addEventListener('mouseenter', function () { row.style.background = '#fafafb'; });
      row.addEventListener('mouseleave', function () { row.style.background = ''; });
      row.addEventListener('click', function () { selectUser(u); });
      dropdown.appendChild(row);
    });
    dropdown.style.display = 'block';
  }

  function filterUsers(q) {
    q = (q || '').toLowerCase();
    return users.filter(function (u) { return !q || (u.name + ' ' + u.email).toLowerCase().indexOf(q) !== -1; });
  }

  search.addEventListener('focus', function () { renderDropdown(filterUsers(search.value)); });
  search.addEventListener('input', function () {
    userIdInput.value     = '';
    pill.style.display    = 'none';
    curAssetDiv.style.display = 'none';
    renderDropdown(filterUsers(search.value));
  });
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target) && e.target !== search) dropdown.style.display = 'none';
  });

  if (selectedId) {
    var found = users.filter(function (u) { return u.id === selectedId; })[0];
    if (found) selectUser(found);
  }

  document.getElementById('fundsForm').addEventListener('submit', function (e) {
    if (!userIdInput.value) {
      e.preventDefault();
      admToast('Select a target user first.', 'error');
      return;
    }
    var btn = document.getElementById('fundsSubmit');
    btn.disabled    = true;
    btn.textContent = 'Updating Balance...';
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
