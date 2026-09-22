<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

$stmt = db()->prepare('SELECT * FROM applications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$apps = $stmt->fetchAll();

$entityLabels = [
    'LLC' => 'Limited Liability Company',
    'CCORP' => 'Corporation (C-Corp)',
    'CLOSE_LLC' => 'Close LLC',
    'CLOSE_CORP' => 'Close Corporation',
];

$pageTitle = 'My Applications';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="dash-header" style="margin-bottom:20px">
  <p style="margin:0;color:var(--muted)">Track the status of every business formation application you've filed.</p>
  <a href="application.php" class="btn btn-gold">+ New Application</a>
</div>

<div class="panel">
  <?php if (!$apps): ?>
    <div class="empty-state">
      <h3 style="margin-bottom:8px">No applications yet</h3>
      <p>Start your first business formation application to see it tracked here.</p>
      <a href="application.php" class="btn btn-primary" style="margin-top:10px">Start Your Formation</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Business Name</th><th>Entity Type</th><th>Jurisdiction</th><th>Status</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php foreach ($apps as $a): ?>
        <tr>
          <td><strong><?= e($a['business_name']) ?></strong></td>
          <td><?= e($entityLabels[$a['entity_type']] ?? $a['entity_type']) ?></td>
          <td><?= e($a['state']) ?></td>
          <td><?= status_badge($a['status']) ?></td>
          <td><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
