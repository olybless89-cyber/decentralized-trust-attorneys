<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';

// CSV export: stream all transactions as a downloadable file.
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Email', 'Type', 'Asset', 'Amount (USD)', 'Counter Asset', 'Destination', 'Note', 'Status', 'Date (UTC)']);
    $all = db()->query('SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC')->fetchAll();
    foreach ($all as $t) {
        fputcsv($out, [
            $t['id'], $t['full_name'], $t['email'], $t['type'],
            $t['asset'], number_format((float)$t['amount_usd'], 2, '.', ''),
            $t['counter_asset'] ?? '', $t['destination'] ?? '', $t['note'] ?? '',
            $t['status'], $t['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$txs = db()->query('SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 200')->fetchAll();

$pageTitle = 'All Wallet Transactions';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search by user name, email..." data-adm-table-search="#allTxTable"></div>
  <a href="transactions.php?export=csv" class="adm-btn adm-btn-outline" style="white-space:nowrap">&#8659; Export CSV</a>
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
