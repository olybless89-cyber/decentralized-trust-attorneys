<?php
require_once __DIR__ . '/../auth.php';
$admin = require_admin(); // Bug fix: capture return value so $admin is available in the view.
require_once __DIR__ . '/../includes/wallet.php';

$totalUsers = (int) db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$totalTx = (int) db()->query('SELECT COUNT(*) c FROM transactions')->fetch()['c'];
$activeAssets = (int) db()->query('SELECT COUNT(*) c FROM users WHERE wallet_address IS NOT NULL')->fetch()['c'];
$totalApps = (int) db()->query('SELECT COUNT(*) c FROM applications')->fetch()['c'];
$pendingWithdrawals = (int) db()->query("SELECT COUNT(*) c FROM withdrawals WHERE status='pending'")->fetch()['c'];

// "Live sessions" — users with wallet activity in the last 15 minutes, as a
// lightweight stand-in for real-time presence tracking.
$liveSessions = (int) db()->query("SELECT COUNT(DISTINCT user_id) c FROM transactions WHERE created_at >= (NOW() - INTERVAL 15 MINUTE)")->fetch()['c'];

$recentTx = db()->query('SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT 8')->fetchAll();
$lastBackup = date('n/j/Y');

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-welcome">
  <h1>Welcome, <?= e($admin['username']) ?></h1>
  <p>Here's your comprehensive system overview for today.</p>
</div>

<div class="adm-tabs">
  <a href="dashboard.php" class="active">&#128202; Overview</a>
  <a href="explorer.php">&#128269; Data Explorer</a>
</div>

<div class="adm-grid adm-kpis">
  <div class="adm-kpi">
    <div class="top"><span class="label">Total Users</span><span class="icon">&#128101;</span></div>
    <div class="num"><?= $totalUsers ?></div>
  </div>
  <div class="adm-kpi">
    <div class="top"><span class="label">Transactions</span><span class="icon">&#128200;</span></div>
    <div class="num"><?= $totalTx ?></div>
  </div>
  <div class="adm-kpi">
    <div class="top"><span class="label">Active Assets</span><span class="icon">&#128179;</span></div>
    <div class="num"><?= $activeAssets ?></div>
  </div>
  <div class="adm-kpi">
    <div class="top"><span class="label">Live Sessions</span><span class="icon">&#128172;</span></div>
    <div class="adm-live"><span class="num"><?= $liveSessions ?></span><span class="dot"></span></div>
  </div>
</div>

<div class="adm-grid adm-2col">
  <div class="adm-panel" style="margin-bottom:0">
    <div class="adm-panel-head">
      <div><h3>Recent Network Activity</h3><div class="sub">Latest wallet transactions across all users</div></div>
      <a href="send-transactions.php" class="viewall">View All Log &rarr;</a>
    </div>
    <?php if (!$recentTx): ?>
      <div class="adm-empty">No transactions yet.</div>
    <?php else: ?>
      <div class="adm-table-wrap">
      <table class="adm-table">
        <thead><tr><th>Transaction ID</th><th>Type</th><th>User Ref</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentTx as $t): [$label] = tx_label($t['type']); $isOut = in_array($t['type'], ['send', 'admin_debit'], true); ?>
          <tr>
            <td class="adm-mono">TX<?= str_pad((string) $t['id'], 4, '0', STR_PAD_LEFT) ?></td>
            <td><?= e($label) ?></td>
            <td><?= e($t['full_name']) ?><div class="adm-cell-sub"><?= e($t['email']) ?></div></td>
            <td class="<?= $isOut ? 'adm-amt-neg' : 'adm-amt-pos' ?>"><?= $isOut ? '-' : '+' ?><?= fmt_money((float) $t['amount_usd']) ?></td>
            <td><?= e(date('M j, Y', strtotime($t['created_at']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="adm-card-dark" style="margin-bottom:16px">
      <h3 style="color:#fff;font-size:15px;margin-bottom:16px">&#128737; System Controls</h3>
      <div style="margin-bottom:14px">
        <div class="k">Network Status</div>
        <div class="v"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px"></span>Operational &amp; Secure</div>
      </div>
      <div style="margin-bottom:18px">
        <div class="k">Last Backup</div>
        <div class="v"><?= e($lastBackup) ?></div>
      </div>
      <a href="settings.php" class="adm-btn adm-btn-primary adm-btn-block">Configure Settings</a>
    </div>

    <div class="adm-panel" style="margin-bottom:0">
      <h3 style="font-size:15px;margin-bottom:6px">Operations Shortcuts</h3>
      <a href="users.php" class="adm-shortcut" style="display:flex">
        <span class="icon">&#128101;</span>
        <span class="txt"><div class="t">Manage Users</div><div class="s">View and edit accounts</div></span>
        <span class="arrow">&rarr;</span>
      </a>
      <a href="manage-funds.php" class="adm-shortcut" style="display:flex">
        <span class="icon">&#128181;</span>
        <span class="txt"><div class="t">Manage Wallets</div><div class="s">Adjust user balances</div></span>
        <span class="arrow">&rarr;</span>
      </a>
      <a href="settings.php" class="adm-shortcut" style="display:flex">
        <span class="icon">&#128274;</span>
        <span class="txt"><div class="t">Platform Security</div><div class="s">Admin account settings</div></span>
        <span class="arrow">&rarr;</span>
      </a>
    </div>
  </div>
</div>

<?php if ($pendingWithdrawals > 0): ?>
  <div class="adm-alert adm-alert-info">You have <?= $pendingWithdrawals ?> withdrawal request<?= $pendingWithdrawals === 1 ? '' : 's' ?> awaiting review. <a href="withdrawals.php" style="font-weight:700;text-decoration:underline">Review now &rarr;</a></div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
