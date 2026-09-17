<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$users = db()->query('SELECT u.*, (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) AS app_count FROM users u ORDER BY u.created_at DESC')->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <?php if (!$users): ?>
    <p style="color:var(--muted)">No registered users yet.</p>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Country</th><th>Balance</th><th>Applications</th><th>Joined</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['full_name']) ?></strong></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['country'] ?: '—') ?></td>
          <td><strong><?= fmt_money((float) $u['balance']) ?></strong></td>
          <td><?= (int) $u['app_count'] ?></td>
          <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td><a href="user_edit.php?id=<?= (int) $u['id'] ?>" class="btn btn-outline btn-sm">Edit Balance</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
