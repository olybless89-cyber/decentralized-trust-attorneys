<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

$pageTitle = 'Profile';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:520px;margin:0 auto">
  <div style="text-align:center;margin-bottom:22px">
    <div class="side-avatar" style="width:64px;height:64px;font-size:24px;margin:0 auto 12px"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
    <h3 style="margin-bottom:2px"><?= e($user['full_name']) ?></h3>
    <p style="margin:0;font-size:14px"><?= e($user['email']) ?></p>
  </div>

  <div class="detail-grid">
    <div><div class="k">Phone</div><div class="v"><?= e($user['phone'] ?: '—') ?></div></div>
    <div><div class="k">Country</div><div class="v"><?= e($user['country'] ?: '—') ?></div></div>
    <div><div class="k">State / Region</div><div class="v"><?= e($user['state_region'] ?: '—') ?></div></div>
    <div><div class="k">Balance</div><div class="v"><?= fmt_money((float) $user['balance']) ?></div></div>
    <div><div class="k">Linked Wallet</div><div class="v" style="font-size:13px"><?= $user['linked_wallet_address'] ? e($user['linked_wallet_address']) : '— none —' ?></div></div>
    <div><div class="k">Member Since</div><div class="v"><?= e(date('M j, Y', strtotime($user['created_at']))) ?></div></div>
  </div>

  <div class="grid grid-2" style="margin-top:6px">
    <a href="link-wallet.php" class="btn btn-outline">Manage Linked Wallet</a>
    <a href="application.php" class="btn btn-outline">Update My Details</a>
  </div>
  <a href="logout.php" class="btn btn-primary btn-block" style="margin-top:14px">Log Out</a>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
