<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

$pageTitle = 'More';
require __DIR__ . '/includes/dash_header.php';
?>
<div class="panel" style="max-width:480px;margin:0 auto">
  <div class="more-list">
    <a href="swap.php" class="more-item"><span>&#8646;</span> Swap Assets</a>
    <a href="buy.php" class="more-item"><span>&#43;</span> Buy Crypto</a>
    <a href="withdraw.php" class="more-item"><span>&#128176;</span> Withdraw</a>
    <a href="link-wallet.php" class="more-item"><span>&#128279;</span> Link Wallet</a>
    <a href="applications.php" class="more-item"><span>&#128194;</span> My Applications</a>
    <a href="application.php" class="more-item"><span>&#128196;</span> New Application</a>
    <a href="profile.php" class="more-item"><span>&#128100;</span> Profile</a>
    <a href="logout.php" class="more-item" style="color:#b91c1c"><span>&#8630;</span> Log Out</a>
  </div>
</div>
<?php require __DIR__ . '/includes/dash_footer.php'; ?>
