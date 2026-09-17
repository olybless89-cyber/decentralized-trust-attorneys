<?php
require_once __DIR__ . '/../auth.php';
require_admin();

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
</div>

<div class="adm-panel">
  <?php if (!$logs): ?>
    <div class="adm-empty">No wallet connections logged yet.</div>
  <?php else: ?>
  <div class="adm-table-wrap">
  <table class="adm-table" id="logsTable">
    <thead><tr><th>User</th><th>Email</th><th>Provider</th><th>Method</th><th>Address</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr data-status="<?= e($l['status']) ?>" data-search="<?= e(strtolower($l['full_name'] . ' ' . $l['email'] . ' ' . $l['address'])) ?>">
        <td><strong><?= e($l['full_name']) ?></strong></td>
        <td><?= e($l['email']) ?></td>
        <td><?= e($l['provider'] ?? '') ?: '&mdash;' ?></td>
        <td><?= e(ucfirst($l['method'])) ?></td>
        <td class="adm-mono" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($l['address']) ?></td>
        <td><?= badge_for_status($l['status']) ?></td>
        <td><?= e(date('n/j/Y, g:i:s A', strtotime($l['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row" style="display:none"><td colspan="7" class="adm-empty">No matching wallet connections.</td></tr>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
