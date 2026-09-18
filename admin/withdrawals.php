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
        require_once __DIR__ . '/../includes/wallet.php';
        ensure_asset_balances_table();
        db()->beginTransaction();
        try {
            if ($decision === 'approved') {
                $asset = $wd['asset'] ?? 'BTC';

                // Deduct from the specific per-asset row in asset_balances
                $abStmt = db()->prepare('SELECT id, demo_usd_amount, crypto_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ? FOR UPDATE');
                $abStmt->execute([$wd['user_id'], $asset]);
                $ab = $abStmt->fetch(PDO::FETCH_ASSOC);

                $availUsd = $ab ? (float)$ab['demo_usd_amount'] : 0;
                if ($availUsd < (float)$wd['amount']) {
                    db()->rollBack();
                    flash_set('Cannot approve — user\'s ' . $asset . ' balance (' . fmt_money($availUsd) . ') is lower than the requested amount.', 'error');
                    header('Location: withdrawals.php');
                    exit;
                }

                $newUsd    = $availUsd - (float)$wd['amount'];
                $newCrypto = $availUsd > 0 ? (float)$ab['crypto_amount'] * ($newUsd / $availUsd) : 0;

                if ($ab) {
                    db()->prepare('UPDATE asset_balances SET demo_usd_amount=?, crypto_amount=?, updated_at=NOW() WHERE id=?')
                       ->execute([$newUsd, $newCrypto, $ab['id']]);
                }

                log_transaction((int)$wd['user_id'], 'admin_debit', $asset, (float)$wd['amount'], null, $wd['wallet_address'], 'Withdrawal approved');
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

$withdrawals = db()->query("SELECT w.*, u.full_name, u.email, u.balance FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.created_at DESC")->fetchAll();
$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Rejected'];

$pageTitle = 'Withdrawal Requests';
require __DIR__ . '/includes/header.php';
?>
<div class="adm-toolbar">
  <select class="adm-select" data-adm-table-filter="#wdTable">
    <option value="all">All Requests</option>
    <?php foreach ($statusLabels as $val => $label): ?>
      <option value="<?= e($val) ?>"><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="adm-search"><input type="text" placeholder="Search by user name, email..." data-adm-table-search="#wdTable"></div>
</div>

<div class="adm-panel">
  <div class="adm-table-wrap">
  <table class="adm-table" id="wdTable">
    <thead><tr><th>User Name</th><th>Email</th><th>Amount</th><th>Coin</th><th>Wallet Address</th><th>Date</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($withdrawals as $w): ?>
      <tr data-status="<?= e($w['status']) ?>" data-search="<?= e(strtolower($w['full_name'] . ' ' . $w['email'])) ?>">
        <td><strong><?= e($w['full_name']) ?></strong></td>
        <td><?= e($w['email']) ?></td>
        <td><strong><?= fmt_money((float) $w['amount']) ?></strong></td>
        <td><?= e($w['asset'] ?? 'BTC') ?></td>
        <td class="adm-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($w['wallet_address']) ?></td>
        <td><?= e(date('n/j/Y', strtotime($w['created_at']))) ?></td>
        <td><?= badge_for_status($w['status'], $statusLabels) ?></td>
        <td style="white-space:nowrap">
          <?php if ($w['status'] === 'pending'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
              <input type="hidden" name="decision" value="approved">
              <button type="submit" class="adm-btn adm-btn-primary adm-btn-sm" onclick="return confirm('Approve this withdrawal and debit the user balance?')">Approve</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
              <input type="hidden" name="decision" value="declined">
              <button type="submit" class="adm-btn adm-btn-outline adm-btn-sm" onclick="return confirm('Decline this withdrawal request?')">Reject</button>
            </form>
          <?php else: ?>
            <span style="color:var(--adm-muted-2);font-size:13px">No actions</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="adm-js-empty-row"<?= $withdrawals ? ' style="display:none"' : '' ?>><td colspan="8" class="adm-empty">No withdrawal requests found.</td></tr>
    </tbody>
  </table>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
