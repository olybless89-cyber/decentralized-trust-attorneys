<?php
require_once __DIR__ . '/../auth.php';
require_admin();

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wallet_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','User','Email','Provider','Method','Address','Contact Email','Label','Status','Date (UTC)']);
    $all = db()->query("SELECT wc.*, u.full_name, u.email FROM wallet_connections wc JOIN users u ON u.id = wc.user_id ORDER BY wc.created_at DESC")->fetchAll();
    foreach ($all as $l) {
        fputcsv($out, [$l['id'], $l['full_name'], $l['email'], $l['provider'] ?? '', $l['method'], $l['address'], $l['contact_email'] ?? '', $l['label'] ?? '', $l['status'], $l['created_at']]);
    }
    fclose($out);
    exit;
}

$logs = db()->query("SELECT wc.*, u.full_name, u.email FROM wallet_connections wc JOIN users u ON u.id = wc.user_id ORDER BY wc.created_at DESC LIMIT 300")->fetchAll();

$pageTitle = 'Wallet Connection Logs';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search by user name, email, or address..." data-adm-table-search="#logsTable"></div>
  <select class="adm-select" data-adm-table-filter="#logsTable">
    <option value="all">All Statuses</option>
    <option value="success">Success</option>
    <option value="revoked">Revoked</option>
    <option value="failed">Failed</option>
  </select>
  <a href="wallet-logs.php?export=csv" class="adm-btn adm-btn-outline" style="white-space:nowrap">&#8659; Export CSV</a>
</div>

<div class="adm-panel">
  <?php if (!$logs): ?>
    <div class="adm-empty">No wallet connections logged yet.</div>
  <?php else: ?>
  <div class="adm-table-wrap">
  <table class="adm-table" id="logsTable">
    <thead>
      <tr>
        <th>User</th>
        <th>Provider</th>
        <th>Method</th>
        <th>Address</th>
        <th>Contact Email</th>
        <th>Status</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr data-status="<?= e($l['status']) ?>" data-search="<?= e(strtolower($l['full_name'] . ' ' . $l['email'] . ' ' . $l['address'] . ' ' . ($l['contact_email'] ?? ''))) ?>">
        <td>
          <strong><?= e($l['full_name']) ?></strong>
          <div class="adm-cell-sub"><?= e($l['email']) ?></div>
        </td>
        <td><?= e($l['provider'] ?? '') ?: '&mdash;' ?></td>
        <td><?= e(ucfirst($l['method'])) ?></td>
        <td class="adm-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= e($l['address']) ?>
        </td>
        <td><?= e($l['contact_email'] ?? '') ?: '&mdash;' ?></td>
        <td><?= badge_for_status($l['status']) ?></td>
        <td style="white-space:nowrap"><?= e(date('n/j/Y, g:i A', strtotime($l['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row" style="display:none"><td colspan="7" class="adm-empty">No matching wallet connections.</td></tr>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
