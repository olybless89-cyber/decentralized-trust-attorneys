<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/wallet.php';
$user = require_login();
$__flash = flash_get();
$current = basename($_SERVER['SCRIPT_NAME']);
function dnavclass($file, $current) { return $file === $current ? 'active' : ''; }
$initials = strtoupper(substr(trim($user['full_name']), 0, 1) . (strpos(trim($user['full_name']), ' ') ? substr(trim($user['full_name']), strpos(trim($user['full_name']), ' ') + 1, 1) : ''));

// ── Sidebar portfolio total = sum of all per-asset balances ──────────────────
ensure_asset_balances_table();
$__sidebarTotal = 0.0;
try {
    $__ptSt = db()->prepare('SELECT COALESCE(SUM(demo_usd_amount), 0) FROM asset_balances WHERE user_id = ?');
    $__ptSt->execute([$user['id']]);
    $__sidebarTotal = (float) $__ptSt->fetchColumn();
} catch (PDOException $e) {
    $__sidebarTotal = (float) $user['balance'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . e(SITE_NAME) : e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/page-loader.css">
</head>
<body<?php
  $__bodyClasses = [];
  if (basename($_SERVER['SCRIPT_NAME']) === 'crypto-assets.php') $__bodyClasses[] = 'ca-page';
  echo $__bodyClasses ? ' class="' . implode(' ', $__bodyClasses) . '"' : '';
?>>

<div class="admin-shell dash-shell">
  <aside class="admin-side dash-side">
    <div class="brand"><span class="mark">&#9878;</span> Decentralized Trust</div>
    <div class="side-user">
      <div class="side-avatar"><?= e($initials ?: 'U') ?></div>
      <div>
        <div class="side-name"><?= e($user['full_name']) ?></div>
        <div class="side-balance"><?= fmt_money($__sidebarTotal) ?></div>
      </div>
    </div>
    <nav>
      <div class="nav-heading">Wallet</div>
      <a href="dashboard.php" class="<?= dnavclass('dashboard.php', $current) ?>">&#128202; Overview</a>
      <a href="send.php" class="<?= dnavclass('send.php', $current) ?>">&#8593; Send</a>
      <a href="receive.php" class="<?= dnavclass('receive.php', $current) ?>">&#8595; Receive</a>
      <a href="swap.php" class="<?= dnavclass('swap.php', $current) ?>">&#8646; Swap</a>
      <a href="buy.php" class="<?= dnavclass('buy.php', $current) ?>">&#43; Buy</a>
      <a href="withdraw.php" class="<?= dnavclass('withdraw.php', $current) ?>">&#128176; Withdraw</a>
      <a href="link-wallet.php" class="<?= dnavclass('link-wallet.php', $current) ?>">&#128279; Link Wallet</a>
      <a href="crypto-assets.php" class="<?= dnavclass('crypto-assets.php', $current) ?>">&#128181; Crypto Assets</a>
      <div class="nav-heading">Business Formation</div>
      <a href="application.php" class="<?= dnavclass('application.php', $current) ?>">&#128196; New Application</a>
      <a href="applications.php" class="<?= dnavclass('applications.php', $current) ?>">&#128194; My Applications</a>
      <div class="nav-heading">Account</div>
      <a href="profile.php" class="<?= dnavclass('profile.php', $current) ?>">&#128100; Profile</a>
      <a href="logout.php" style="margin-top:20px;color:#f87171">&#8630; Log Out</a>
    </nav>
  </aside>

  <nav class="mobile-tabbar">
    <a href="dashboard.php" class="<?= dnavclass('dashboard.php', $current) ?>"><span class="mt-icon">&#127968;</span>Dashboard</a>
    <a href="crypto-assets.php" class="<?= dnavclass('crypto-assets.php', $current) ?>"><span class="mt-icon">&#128181;</span>Crypto Assets</a>
    <a href="link-wallet.php" class="<?= dnavclass('link-wallet.php', $current) ?>"><span class="mt-icon">&#128279;</span>Link Wallet</a>
    <a href="receive.php" class="<?= dnavclass('receive.php', $current) ?>"><span class="mt-icon">&#8595;</span>Receive</a>
    <a href="more.php" class="<?= dnavclass('more.php', $current) ?>"><span class="mt-icon">&#9776;</span>More</a>
  </nav>

  <main class="admin-main">
    <div class="admin-topbar">
      <div><?= isset($pageTitle) ? '<h2 style="margin:0">' . e($pageTitle) . '</h2>' : '' ?></div>
      <div style="font-size:14px;color:var(--muted)">Signed in as <strong style="color:var(--navy)"><?= e($user['full_name']) ?></strong></div>
    </div>
    <?php if ($__flash): ?><div class="alert alert-<?= e($__flash['type']) ?>"><?= e($__flash['msg']) ?></div><?php endif; ?>
