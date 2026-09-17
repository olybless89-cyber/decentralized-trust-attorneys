<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $id = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    // Bug fix: transactions.status ENUM is ('completed','pending','failed') — 'rejected' is not valid.
    // Map admin UI "reject" action to the valid ENUM value 'failed'.
    $statusMap = ['completed' => 'completed', 'rejected' => 'failed'];
    if (isset($statusMap[$decision])) {
        $stmt = db()->prepare("UPDATE transactions SET status = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$statusMap[$decision], $id]);
        flash_set('Transaction marked ' . $decision . '.');
    }
    header('Location: send-transactions.php');
    exit;
}

$dateFrom = $_GET['from'] ?? '';
$where = "t.type = 'send'";
$params = [];
if ($dateFrom !== '') {
    $where .= ' AND DATE(t.created_at) >= ?';
    $params[] = $dateFrom;
}
$stmt = db()->prepare("SELECT t.*, u.full_name, u.email FROM transactions t JOIN users u ON u.id = t.user_id WHERE $where ORDER BY t.created_at DESC LIMIT 300");
$stmt->execute($params);
$txs = $stmt->fetchAll();

$statusLabels = ['completed' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected', 'failed' => 'Rejected'];
$totalCount = count($txs);
$pendingCount = count(array_filter($txs, fn($t) => $t['status'] === 'pending'));

$pageTitle = 'Send Transactions';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-welcome" style="margin-bottom:16px">
  <h1 style="font-size:20px">Send Transactions</h1>
  <p>View and monitor all crypto send requests.</p>
</div>

<div class="adm-grid adm-3col" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
  <div class="adm-card-dark"><div class="k">Total Send Requests</div><div class="v" style="font-size:22px"><?= $totalCount ?></div></div>
  <div class="adm-card-dark"><div class="k">Pending Review</div><div class="v" style="font-size:22px"><?= $pendingCount ?></div></div>
</div>

<div class="adm-toolbar">
  <div class="adm-search"><input type="text" placeholder="Search by user name, email..." data-adm-table-search="#sendTable"></div>
  <form method="get" style="display:flex;gap:8px">
    <input type="date" name="from" value="<?= e($dateFrom) ?>" class="adm-select">
    <button type="submit" class="adm-btn adm-btn-outline">Filter</button>
  </form>
</div>

<div class="adm-panel">
  <?php if (!$txs): ?>
    <div class="adm-empty">No send transactions found.</div>
  <?php else: ?>
  <div class="adm-table-wrap">
  <table class="adm-table" id="sendTable">
    <thead><tr><th>User</th><th>Wallet Address</th><th>Coin</th><th>Amount</th><th>Date</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($txs as $t): ?>
      <tr data-status="<?= e($t['status']) ?>" data-search="<?= e(strtolower($t['full_name'] . ' ' . $t['email'])) ?>">
        <td><strong><?= e($t['full_name']) ?></strong><div class="adm-cell-sub"><?= e($t['email']) ?></div></td>
        <td class="adm-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['destination'] ?: '—') ?></td>
        <td><?= e($t['asset']) ?></td>
        <td><strong><?= fmt_money((float) $t['amount_usd']) ?></strong></td>
        <td><?= e(date('M j, Y H:i', strtotime($t['created_at']))) ?></td>
        <td><?= badge_for_status($t['status'], $statusLabels) ?></td>
        <td style="white-space:nowrap">
          <?php if ($t['status'] === 'pending'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="decision" value="completed">
              <button type="submit" class="adm-btn adm-btn-primary adm-btn-sm">Approve</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="decision" value="rejected">
              <button type="submit" class="adm-btn adm-btn-outline adm-btn-sm">Reject</button>
            </form>
          <?php else: ?>
            <span style="color:var(--adm-muted-2);font-size:13px">No actions</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row" style="display:none"><td colspan="7" class="adm-empty">No matching transactions.</td></tr>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
