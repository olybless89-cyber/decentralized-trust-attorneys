<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$counts = [
    ['label' => 'Users', 'icon' => '&#128101;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'], 'href' => 'users.php'],
    ['label' => 'Applications', 'icon' => '&#128196;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM applications')->fetch()['c'], 'href' => 'applications.php'],
    ['label' => 'Withdrawals', 'icon' => '&#128176;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM withdrawals')->fetch()['c'], 'href' => 'withdrawals.php'],
    ['label' => 'Wallet Transactions', 'icon' => '&#128179;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM transactions')->fetch()['c'], 'href' => 'transactions.php'],
];

$pageTitle = 'Data Explorer';
require __DIR__ . '/includes/header.php';
?>
<div class="filters" style="margin-bottom:20px">
  <a href="dashboard.php">Overview</a>
  <a href="explorer.php" class="active">Data Explorer</a>
</div>

<div class="grid grid-2">
  <?php foreach ($counts as $c): ?>
    <a href="<?= e($c['href']) ?>" class="card" style="display:block">
      <div class="icon-badge" style="margin-bottom:12px"><?= $c['icon'] ?></div>
      <div style="font-family:'Playfair Display',serif;font-size:30px;color:var(--navy)"><?= $c['count'] ?></div>
      <div style="color:var(--muted);font-size:14px">Total <?= e($c['label']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
