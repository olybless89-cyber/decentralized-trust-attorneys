<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $asset = $_POST['asset'] ?? 'BTC';
    $newBalance = (float) ($_POST['new_balance'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    if (!in_array($asset, wallet_supported_assets(), true)) $asset = 'BTC';

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        flash_set('Select a valid user.', 'error');
    } else {
        $delta = $newBalance - (float) $target['balance'];
        $stmt = db()->prepare('UPDATE users SET balance = ? WHERE id = ?');
        $stmt->execute([$newBalance, $userId]);
        if (abs($delta) > 0.00001) {
            log_transaction($userId, $delta >= 0 ? 'admin_credit' : 'admin_debit', $asset, abs($delta), null, null, $note ?: null);
            send_email($target['email'], $target['full_name'], $delta >= 0 ? 'Deposit Received' : 'Balance Adjustment',
                '<p>Hi ' . e($target['full_name']) . ',</p><p>Your wallet balance was ' . ($delta >= 0 ? 'credited' : 'debited') . ' by <strong>' . fmt_money(abs($delta)) . '</strong>' . ($note ? ' (' . e($note) . ')' : '') . '.</p>');
        }
        flash_set('User balance successfully updated to ' . fmt_money($newBalance) . '.');
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
      <span class="k">Current Total Balance:</span>
      <span id="balancePillAmt">$0.00</span>
    </div>

    <div class="adm-field">
      <label>Select Coin <span class="req">*</span></label>
      <select name="asset">
        <?php foreach (wallet_supported_assets() as $a): ?><option value="<?= e($a) ?>"><?= e(asset_label($a)) ?> (<?= e($a) ?>)</option><?php endforeach; ?>
      </select>
    </div>

    <div class="adm-field">
      <label>New Total Balance Amount (USD) <span class="req">*</span></label>
      <input type="number" step="0.01" name="new_balance" value="0" required>
      <div class="hint">The difference will be recorded as an addition/subtraction of the selected coin.</div>
    </div>

    <div class="adm-field">
      <label>Reason / Note (Optional)</label>
      <textarea name="note" maxlength="500" placeholder="e.g., Wire transfer received, account credit..."></textarea>
    </div>

    <button type="submit" class="adm-btn adm-btn-primary adm-btn-block" id="fundsSubmit">Update User Balance</button>
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
      return ['id' => (int) $u['id'], 'name' => $u['full_name'], 'email' => $u['email'], 'balance' => (float) $u['balance']];
  }, $users)) ?>;
  var selectedId = <?= (int) $selectedId ?>;
  var search = document.getElementById('userSearch');
  var dropdown = document.getElementById('userDropdown');
  var userIdInput = document.getElementById('userIdInput');
  var pill = document.getElementById('balancePill');
  var pillAmt = document.getElementById('balancePillAmt');

  function fmtMoney(n) { return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function selectUser(u) {
    userIdInput.value = u.id;
    search.value = u.name + ' (' + u.email + ')';
    pill.style.display = 'flex';
    pillAmt.textContent = fmtMoney(u.balance);
    dropdown.style.display = 'none';
  }

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

  search.addEventListener('focus', function () { renderDropdown(filterUsers(search.value)); });
  search.addEventListener('input', function () {
    userIdInput.value = '';
    pill.style.display = 'none';
    renderDropdown(filterUsers(search.value));
  });
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target) && e.target !== search) dropdown.style.display = 'none';
  });

  function filterUsers(q) {
    q = (q || '').toLowerCase();
    return users.filter(function (u) { return !q || (u.name + ' ' + u.email).toLowerCase().indexOf(q) !== -1; });
  }

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
    btn.disabled = true;
    btn.textContent = 'Updating Balance...';
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
