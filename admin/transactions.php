<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';

$txs = db()->query('SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 200')->fetchAll();

$pageTitle = 'Wallet Transactions';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <?php if (!$txs): ?>
    <p style="color:var(--muted)">No wallet transactions yet.</p>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>User</th><th>Type</th><th>Asset</th><th>Amount</th><th>Destination / Note</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($txs as $t): [$label] = tx_label($t['type']); ?>
        <tr>
          <td><strong><?= e($t['full_name']) ?></strong><br><span style="color:var(--muted);font-size:12.5px"><?= e($t['email']) ?></span></td>
          <td><?= e($label) ?><?= $t['counter_asset'] ? ' &rarr; ' . e($t['counter_asset']) : '' ?></td>
          <td><?= e($t['asset']) ?></td>
          <td><strong><?= fmt_money((float) $t['amount_usd']) ?></strong></td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['destination'] ?: ($t['note'] ?: '—')) ?></td>
          <td><?= e(date('M j, Y g:ia', strtotime($t['created_at']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
