<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';

$txs = db()->query('SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 200')->fetchAll();

$pageTitle = 'All Wallet Transactions';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search by user name, email..." data-adm-table-search="#allTxTable"></div>
</div>
<div class="adm-panel">
  <?php if (!$txs): ?>
    <div class="adm-empty">No wallet transactions yet.</div>
  <?php else: ?>
    <div class="adm-table-wrap">
    <table class="adm-table" id="allTxTable">
      <thead><tr><th>User</th><th>Type</th><th>Asset</th><th>Amount</th><th>Destination / Note</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($txs as $t): [$label] = tx_label($t['type']); ?>
        <tr data-search="<?= e(strtolower($t['full_name'] . ' ' . $t['email'])) ?>">
          <td><strong><?= e($t['full_name']) ?></strong><div class="adm-cell-sub"><?= e($t['email']) ?></div></td>
          <td><?= e($label) ?><?= $t['counter_asset'] ? ' &rarr; ' . e($t['counter_asset']) : '' ?></td>
          <td><?= e($t['asset']) ?></td>
          <td><strong><?= fmt_money((float) $t['amount_usd']) ?></strong></td>
          <td class="adm-mono" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['destination'] ?: ($t['note'] ?: '—')) ?></td>
          <td><?= e(date('M j, Y g:ia', strtotime($t['created_at']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
