<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { flash_set('User not found.', 'error'); header('Location: users.php'); exit; }

$stmt = db()->prepare('SELECT COUNT(*) c FROM applications WHERE user_id = ?');
$stmt->execute([$id]);
$appCount = (int) $stmt->fetch()['c'];

$pageTitle = 'User Profile';
require __DIR__ . '/includes/header.php';
?>
<div style="margin-bottom:16px"><a href="users.php" style="color:var(--adm-muted);font-size:14px">&larr; Back to Users</a></div>

<div class="adm-panel">
  <div class="adm-panel-head">
    <h3><?= e($u['full_name']) ?></h3>
    <a href="manage-funds.php?user=<?= (int) $u['id'] ?>" class="adm-btn adm-btn-primary adm-btn-sm">Manage Funds</a>
  </div>
  <div class="adm-detail-grid">
    <div><div class="k">Email</div><div class="v"><?= e($u['email']) ?></div></div>
    <div><div class="k">Phone</div><div class="v"><?= e($u['phone'] ?: '—') ?></div></div>
    <div><div class="k">Street Address</div><div class="v"><?= e($u['street_address'] ?: '—') ?></div></div>
    <div><div class="k">City</div><div class="v"><?= e($u['city'] ?: '—') ?></div></div>
    <div><div class="k">Country</div><div class="v"><?= e($u['country'] ?: '—') ?></div></div>
    <div><div class="k">State / Region</div><div class="v"><?= e($u['state_region'] ?: '—') ?></div></div>
    <div><div class="k">SSN (last 4)</div><div class="v"><?= $u['ssn_last4'] ? '••• ' . e($u['ssn_last4']) : '—' ?></div></div>
    <div><div class="k">Applications Filed</div><div class="v"><?= $appCount ?></div></div>
    <div><div class="k">Current Balance</div><div class="v"><?= fmt_money((float) $u['balance']) ?></div></div>
    <div><div class="k">Linked Wallet</div><div class="v adm-mono" style="font-size:13px"><?= e($u['linked_wallet_address'] ?: '—') ?></div></div>
    <div><div class="k">ID Document</div><div class="v">
      <?php if ($u['id_document_path']): ?>
        <a href="../<?= e($u['id_document_path']) ?>" target="_blank" class="adm-btn adm-btn-outline adm-btn-sm">View Document</a>
      <?php else: ?> Not uploaded <?php endif; ?>
    </div></div>
    <div><div class="k">Joined</div><div class="v"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></div></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
