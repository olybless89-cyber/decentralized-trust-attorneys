<?php
require_once __DIR__ . '/../../auth.php';
$admin = require_admin();
$__flash = flash_get();
$current = basename($_SERVER['SCRIPT_NAME']);
function navclass($file, $current) { return $file === $current ? 'active' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | Admin' : 'Admin' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-side">
    <div class="brand"><span class="mark">&#9878;</span> Decentralized Trust</div>
    <nav>
      <a href="dashboard.php" class="<?= navclass('dashboard.php', $current) ?>">&#128202; Dashboard</a>
      <a href="applications.php" class="<?= navclass('applications.php', $current) ?>">&#128196; Applications</a>
      <a href="users.php" class="<?= navclass('users.php', $current) ?>">&#128101; Users</a>
      <a href="withdrawals.php" class="<?= navclass('withdrawals.php', $current) ?>">&#128176; Withdrawals</a>
      <a href="transactions.php" class="<?= navclass('transactions.php', $current) ?>">&#128179; Wallet Transactions</a>
      <a href="logout.php" style="margin-top:20px;color:#f87171">&#8630; Log Out</a>
    </nav>
  </aside>
  <main class="admin-main">
    <div class="admin-topbar">
      <div><?= isset($pageTitle) ? '<h2 style="margin:0">' . e($pageTitle) . '</h2>' : '' ?></div>
      <div style="font-size:14px;color:var(--muted)">Signed in as <strong style="color:var(--navy)"><?= e($admin['username']) ?></strong></div>
    </div>
    <?php if ($__flash): ?><div class="alert alert-<?= e($__flash['type']) ?>"><?= e($__flash['msg']) ?></div><?php endif; ?>
