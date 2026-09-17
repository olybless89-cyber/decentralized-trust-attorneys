<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $id = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $stmt = db()->prepare('SELECT w.*, u.full_name, u.email FROM withdrawals w JOIN users u ON u.id = w.user_id WHERE w.id = ?');
    $stmt->execute([$id]);
    $wd = $stmt->fetch();

    if ($wd && $wd['status'] === 'pending' && in_array($decision, ['approved', 'declined'], true)) {
        db()->beginTransaction();
        try {
            if ($decision === 'approved') {
                // Verify sufficient balance at approval time, then debit it.
                $stmt = db()->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
                $stmt->execute([$wd['user_id']]);
                $bal = (float) $stmt->fetch()['balance'];
                if ($bal < (float) $wd['amount']) {
                    db()->rollBack();
                    flash_set('Cannot approve — user balance is lower than the requested amount.', 'error');
                    header('Location: withdrawals.php');
                    exit;
                }
                $stmt = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$wd['amount'], $wd['user_id']]);
            }
            $stmt = db()->prepare('UPDATE withdrawals SET status = ? WHERE id = ?');
            $stmt->execute([$decision, $id]);
            db()->commit();
            flash_set('Withdrawal ' . $decision . '.');
            $subject = $decision === 'approved' ? 'Withdrawal Approved' : 'Withdrawal Declined';
            $body = $decision === 'approved'
                ? '<p>Hi ' . e($wd['full_name']) . ',</p><p>Your withdrawal request for <strong>' . fmt_money((float) $wd['amount']) . '</strong> has been approved and processed.</p>'
                : '<p>Hi ' . e($wd['full_name']) . ',</p><p>Your withdrawal request for <strong>' . fmt_money((float) $wd['amount']) . '</strong> was declined. Contact support if you have questions.</p>';
            send_email($wd['email'], $wd['full_name'], $subject, $body);
        } catch (Exception $e) {
            db()->rollBack();
            flash_set('Something went wrong processing this request.', 'error');
        }
    }
    header('Location: withdrawals.php');
    exit;
}

$filter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'declined'];
if (in_array($filter, $validStatuses, true)) {
    $stmt = db()->prepare('SELECT w.*, u.full_name, u.email, u.balance FROM withdrawals w JOIN users u ON u.id = w.user_id WHERE w.status = ? ORDER BY w.created_at DESC');
    $stmt->execute([$filter]);
} else {
    $stmt = db()->query('SELECT w.*, u.full_name, u.email, u.balance FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.created_at DESC');
}
$withdrawals = $stmt->fetchAll();

$pageTitle = 'Withdrawals';
require __DIR__ . '/includes/header.php';
?>
<div class="filters">
  <a href="?status=pending" class="<?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
  <a href="?status=approved" class="<?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
  <a href="?status=declined" class="<?= $filter === 'declined' ? 'active' : '' ?>">Declined</a>
  <a href="?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
</div>

<div class="panel">
  <?php if (!$withdrawals): ?>
    <p style="color:var(--muted)">No withdrawal requests found for this filter.</p>
  <?php else: ?>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>User</th><th>Amount</th><th>Method</th><th>Destination</th><th>User Balance</th><th>Status</th><th>Requested</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td><strong><?= e($w['full_name']) ?></strong><br><span style="color:var(--muted);font-size:12.5px"><?= e($w['email']) ?></span></td>
          <td><strong><?= fmt_money((float) $w['amount']) ?></strong></td>
          <td><?= e(ucfirst($w['method'])) ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($w['wallet_address']) ?></td>
          <td><?= fmt_money((float) $w['balance']) ?></td>
          <td><?= wd_status_badge($w['status']) ?></td>
          <td><?= e(date('M j, Y', strtotime($w['created_at']))) ?></td>
          <td>
            <?php if ($w['status'] === 'pending'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                <input type="hidden" name="decision" value="approved">
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Approve this withdrawal and debit the user balance?')">Approve</button>
              </form>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                <input type="hidden" name="decision" value="declined">
                <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Decline this withdrawal request?')">Decline</button>
              </form>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
