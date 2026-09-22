<?php
require_once __DIR__ . '/../../auth.php';
$admin = require_admin();
$__flash = flash_get();
$current = basename($_SERVER['SCRIPT_NAME']);
function navclass($files, $current) {
    if (!is_array($files)) $files = [$files];
    return in_array($current, $files, true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | Admin Panel' : 'Admin Panel' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/page-loader.css">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="adm-body adm">
<div class="adm-shell">
  <div class="adm-sidebar-backdrop" data-adm-close-sidebar></div>
  <aside class="adm-sidebar">
    <div class="adm-sidebar-head">
      <div class="adm-brand"><span class="adm-brand-mark">&#9878;</span> Admin Panel</div>
      <button class="adm-sidebar-close" data-adm-close-sidebar aria-label="Close menu">&#10005;</button>
    </div>
    <nav class="adm-nav">
      <a href="dashboard.php" class="<?= navclass(['dashboard.php', 'explorer.php'], $current) ?>"><span class="ic">&#128202;</span> Dashboard</a>
      <a href="applications.php" class="<?= navclass(['applications.php', 'application_view.php'], $current) ?>"><span class="ic">&#128196;</span> Applications</a>
      <a href="users.php" class="<?= navclass(['users.php', 'user_edit.php'], $current) ?>"><span class="ic">&#128101;</span> Users</a>
      <a href="wallet-logs.php" class="<?= navclass('wallet-logs.php', $current) ?>"><span class="ic">&#128279;</span> Wallet Logs</a>
      <a href="manage-funds.php" class="<?= navclass('manage-funds.php', $current) ?>"><span class="ic">&#128181;</span> Manage User Funds</a>
      <a href="withdrawals.php" class="<?= navclass('withdrawals.php', $current) ?>"><span class="ic">&#8593;</span> Withdrawal Requests</a>
      <a href="send-transactions.php" class="<?= navclass('send-transactions.php', $current) ?>"><span class="ic">&#8599;</span> Send Transactions</a>
      <a href="notify-user.php" class="<?= navclass('notify-user.php', $current) ?>"><span class="ic">&#128140;</span> Notify User</a>
      <a href="settings.php" class="<?= navclass('settings.php', $current) ?>"><span class="ic">&#9881;</span> Settings</a>
      <a href="logout.php" class="adm-nav-logout"><span class="ic">&#8630;</span> Log Out</a>
    </nav>
  </aside>

  <main class="adm-main">
    <div class="adm-topbar">
      <div class="adm-topbar-left">
        <button class="adm-hamburger" data-adm-open-sidebar aria-label="Open menu">&#9776;</button>
        <h2><?= isset($pageTitle) ? e($pageTitle) : 'Admin Panel' ?></h2>
      </div>
      <div class="adm-topbar-right">
        <span class="who">Signed in as <strong><?= e($admin['username']) ?></strong></span>
        <a href="logout.php" class="adm-logout-link">Logout</a>
      </div>
    </div>
    <div class="adm-content">
    <?php if ($__flash): ?>
      <span data-adm-flash data-msg="<?= e($__flash['msg']) ?>" data-type="<?= e($__flash['type']) ?>" style="display:none"></span>
      <div class="adm-alert adm-alert-<?= e($__flash['type'] === 'error' ? 'error' : 'success') ?>"><?= e($__flash['msg']) ?></div>
    <?php endif; ?>
