<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$entityLabels = ['LLC' => 'LLC', 'CCORP' => 'C-Corp', 'CLOSE_LLC' => 'Close LLC', 'CLOSE_CORP' => 'Close Corp'];
$statusLabels = ['pending' => 'Pending Review', 'in_review' => 'In Review', 'approved' => 'Approved', 'rejected' => 'Rejected'];

$apps = db()->query('SELECT a.*, u.full_name AS user_name, u.email AS user_email FROM applications a JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC')->fetchAll();

$pageTitle = 'Applications Management';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search business name or applicant..." data-adm-table-search="#appsTable"></div>
  <select class="adm-select" data-adm-table-filter="#appsTable">
    <option value="all">All Statuses</option>
    <?php foreach ($statusLabels as $val => $label): ?>
      <option value="<?= e($val) ?>"><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div class="adm-panel">
  <div class="adm-table-wrap">
  <table class="adm-table" id="appsTable">
    <thead><tr><th>Business Name</th><th>Owner</th><th>Entity</th><th>State</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($apps as $a): ?>
      <tr data-status="<?= e($a['status']) ?>" data-search="<?= e(strtolower($a['business_name'] . ' ' . $a['user_name'] . ' ' . $a['user_email'])) ?>">
        <td><strong><?= e($a['business_name']) ?></strong></td>
        <td><?= e($a['user_name']) ?><div class="adm-cell-sub"><?= e($a['user_email']) ?></div></td>
        <td><?= e($entityLabels[$a['entity_type']] ?? $a['entity_type']) ?></td>
        <td><?= e($a['state']) ?></td>
        <td><?= status_badge($a['status']) ?></td>
        <td><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
        <td><a href="application_view.php?id=<?= (int) $a['id'] ?>" class="adm-btn adm-btn-outline adm-btn-sm">Review</a></td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row"<?= $apps ? ' style="display:none"' : '' ?>><td colspan="7" class="adm-empty">No applications found.</td></tr>
    </tbody>
  </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
