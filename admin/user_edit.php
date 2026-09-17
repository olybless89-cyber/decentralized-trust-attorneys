<?php
require_once __DIR__ . '/../auth.php';
require_admin();
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/mailer.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { flash_set('User not found.', 'error'); header('Location: users.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'set_balance') {
        $newBalance = (float) ($_POST['balance'] ?? 0);
        $stmt = db()->prepare('UPDATE users SET balance = ? WHERE id = ?');
        $stmt->execute([$newBalance, $id]);
        flash_set('Balance updated to ' . fmt_money($newBalance) . '.');
    } elseif ($action === 'adjust_balance') {
        $delta = (float) ($_POST['delta'] ?? 0);
        $stmt = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([$delta, $id]);
        log_transaction($id, $delta >= 0 ? 'admin_credit' : 'admin_debit', 'USD', abs($delta), null, null, 'Adjusted by admin');
        send_email($u['email'], $u['full_name'], $delta >= 0 ? 'Deposit Received' : 'Balance Adjustment',
            '<p>Hi ' . e($u['full_name']) . ',</p><p>Your wallet balance was ' . ($delta >= 0 ? 'credited' : 'debited') . ' by <strong>' . fmt_money(abs($delta)) . '</strong>.</p>');
        flash_set(($delta >= 0 ? 'Credited ' : 'Debited ') . fmt_money(abs($delta)) . '.');
    }
    header('Location: user_edit.php?id=' . $id);
    exit;
}

$stmt = db()->prepare('SELECT COUNT(*) c FROM applications WHERE user_id = ?');
$stmt->execute([$id]);
$appCount = (int) $stmt->fetch()['c'];

$pageTitle = 'Edit User';
require __DIR__ . '/includes/header.php';
?>
<div style="margin-bottom:16px"><a href="users.php" style="color:var(--muted);font-size:14px">&larr; Back to Users</a></div>

<div class="panel" style="margin-bottom:24px">
  <h3 style="margin-bottom:16px"><?= e($u['full_name']) ?></h3>
  <div class="detail-grid">
    <div><div class="k">Email</div><div class="v"><?= e($u['email']) ?></div></div>
    <div><div class="k">Phone</div><div class="v"><?= e($u['phone'] ?: '—') ?></div></div>
    <div><div class="k">Street Address</div><div class="v"><?= e($u['street_address'] ?: '—') ?></div></div>
    <div><div class="k">City</div><div class="v"><?= e($u['city'] ?: '—') ?></div></div>
    <div><div class="k">Country</div><div class="v"><?= e($u['country'] ?: '—') ?></div></div>
    <div><div class="k">State / Region</div><div class="v"><?= e($u['state_region'] ?: '—') ?></div></div>
    <div><div class="k">SSN (last 4)</div><div class="v"><?= $u['ssn_last4'] ? '••• ' . e($u['ssn_last4']) : '—' ?></div></div>
    <div><div class="k">Applications Filed</div><div class="v"><?= $appCount ?></div></div>
    <div><div class="k">ID Document</div><div class="v">
      <?php if ($u['id_document_path']): ?>
        <a href="../<?= e($u['id_document_path']) ?>" target="_blank" class="btn btn-outline btn-sm">View Document</a>
      <?php else: ?> Not uploaded <?php endif; ?>
    </div></div>
    <div><div class="k">Joined</div><div class="v"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></div></div>
  </div>
</div>

<div class="panel">
  <h3 style="margin-bottom:16px">Account Balance</h3>
  <div class="balance-card" style="margin-bottom:22px">
    <div><div class="label">Current Balance</div><div class="amount"><?= fmt_money((float) $u['balance']) ?></div></div>
  </div>

  <div class="grid grid-2">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form_action" value="set_balance">
      <div class="field"><label>Set Exact Balance (USD)</label><input type="number" step="0.01" name="balance" value="<?= e($u['balance']) ?>" required></div>
      <button type="submit" class="btn btn-primary">Save Balance</button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form_action" value="adjust_balance">
      <div class="field"><label>Credit / Debit Amount (use &minus; to debit)</label><input type="number" step="0.01" name="delta" placeholder="e.g. 500 or -200" required></div>
      <button type="submit" class="btn btn-outline">Apply Adjustment</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
