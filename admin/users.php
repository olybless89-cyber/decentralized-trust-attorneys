<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$users = db()->query('SELECT u.*, (SELECT COUNT(*) FROM applications a WHERE a.user_id = u.id) AS app_count FROM users u ORDER BY u.created_at DESC')->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search name or email..." data-adm-table-search="#usersTable"></div>
</div>

<div class="adm-panel">
  <div class="adm-table-wrap">
  <table class="adm-table" id="usersTable">
    <thead><tr><th>Name</th><th>Email</th><th>Country</th><th>Balance</th><th>Applications</th><th>Joined</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr data-search="<?= e(strtolower($u['full_name'] . ' ' . $u['email'])) ?>">
        <td><strong><?= e($u['full_name']) ?></strong></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['country'] ?: '—') ?></td>
        <td><strong><?= fmt_money((float) $u['balance']) ?></strong></td>
        <td><?= (int) $u['app_count'] ?></td>
        <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
        <td style="white-space:nowrap">
          <a href="user_edit.php?id=<?= (int) $u['id'] ?>" class="adm-btn adm-btn-outline adm-btn-sm">Profile</a>
          <a href="manage-funds.php?user=<?= (int) $u['id'] ?>" class="adm-btn adm-btn-primary adm-btn-sm">Funds</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row"<?= $users ? ' style="display:none"' : '' ?>><td colspan="7" class="adm-empty">No registered users yet.</td></tr>
    </tbody>
  </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
