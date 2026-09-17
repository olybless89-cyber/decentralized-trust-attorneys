<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$filter = $_GET['status'] ?? 'all';
$validStatuses = ['pending', 'in_review', 'approved', 'rejected'];
$entityLabels = ['LLC' => 'LLC', 'CCORP' => 'C-Corp', 'CLOSE_LLC' => 'Close LLC', 'CLOSE_CORP' => 'Close Corp'];

if (in_array($filter, $validStatuses, true)) {
    $stmt = db()->prepare('SELECT a.*, u.full_name AS user_name, u.email AS user_email FROM applications a JOIN users u ON u.id = a.user_id WHERE a.status = ? ORDER BY a.created_at DESC');
    $stmt->execute([$filter]);
} else {
    $stmt = db()->query('SELECT a.*, u.full_name AS user_name, u.email AS user_email FROM applications a JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC');
}
$apps = $stmt->fetchAll();

$pageTitle = 'Applications';
require __DIR__ . '/includes/header.php';
?>
<div class="filters">
  <a href="?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
  <a href="?status=pending" class="<?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
  <a href="?status=in_review" class="<?= $filter === 'in_review' ? 'active' : '' ?>">In Review</a>
  <a href="?status=approved" class="<?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
  <a href="?status=rejected" class="<?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
</div>

<div class="panel">
  <?php if (!$apps): ?>
    <p style="color:var(--muted)">No applications found for this filter.</p>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Business Name</th><th>Applicant</th><th>Entity</th><th>State</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($apps as $a): ?>
        <tr>
          <td><strong><?= e($a['business_name']) ?></strong></td>
          <td><?= e($a['user_name']) ?><br><span style="color:var(--muted);font-size:12.5px"><?= e($a['user_email']) ?></span></td>
          <td><?= e($entityLabels[$a['entity_type']] ?? $a['entity_type']) ?></td>
          <td><?= e($a['state']) ?></td>
          <td><?= status_badge($a['status']) ?></td>
          <td><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
          <td><a href="application_view.php?id=<?= (int) $a['id'] ?>" class="btn btn-outline btn-sm">Review</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
