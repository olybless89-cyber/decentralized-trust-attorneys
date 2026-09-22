<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$counts = [
    ['label' => 'Users', 'icon' => '&#128101;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'], 'href' => 'users.php'],
    ['label' => 'Applications', 'icon' => '&#128196;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM applications')->fetch()['c'], 'href' => 'applications.php'],
    ['label' => 'Withdrawal Requests', 'icon' => '&#128181;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM withdrawals')->fetch()['c'], 'href' => 'withdrawals.php'],
    ['label' => 'Wallet Transactions', 'icon' => '&#128179;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM transactions')->fetch()['c'], 'href' => 'send-transactions.php'],
    ['label' => 'Wallet Connections', 'icon' => '&#128279;', 'count' => (int) db()->query('SELECT COUNT(*) c FROM wallet_connections')->fetch()['c'], 'href' => 'wallet-logs.php'],
];

$pageTitle = 'Data Explorer';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-tabs" style="margin-bottom:20px">
  <a href="dashboard.php">&#128202; Overview</a>
  <a href="explorer.php" class="active">&#128269; Data Explorer</a>
</div>

<div class="adm-grid adm-3col">
  <?php foreach ($counts as $c): ?>
    <a href="<?= e($c['href']) ?>" class="adm-kpi" style="display:block">
      <div class="icon" style="width:36px;height:36px;border-radius:10px;background:var(--adm-red-light);color:var(--adm-red);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:14px"><?= $c['icon'] ?></div>
      <div class="num"><?= $c['count'] ?></div>
      <div class="label" style="text-transform:none;letter-spacing:0;font-weight:600;margin-top:4px">Total <?= e($c['label']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
