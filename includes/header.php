<?php
require_once __DIR__ . '/../auth.php';
$__user = current_user();
$__flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . SITE_NAME : SITE_NAME ?></title>
<link rel="icon" type="image/jpeg" href="<?= $__base ?? '' ?>images/6e3b39c129edbeceb586c5c6d14a87bb.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $__base ?? '' ?>assets/css/style.css">
</head>
<body>
<header class="topnav">
  <div class="container">
    <a href="<?= $__base ?? '' ?>index.php" class="brand">
      <span class="mark">&#9878;</span> Decentralized Trust
    </a>
    <nav class="nav-links">
      <a href="<?= $__base ?? '' ?>index.php">Home</a>
      <a href="<?= $__base ?? '' ?>index.php#packages">Packages</a>
      <a href="<?= $__base ?? '' ?>index.php#faq">FAQ</a>
      <a href="<?= $__base ?? '' ?>index.php#contact">Contact</a>
    </nav>
    <div class="nav-actions">
      <?php if ($__user): ?>
        <a href="<?= $__base ?? '' ?>dashboard.php" class="btn btn-outline btn-sm">Dashboard</a>
        <a href="<?= $__base ?? '' ?>logout.php" class="btn btn-primary btn-sm">Log out</a>
      <?php else: ?>
        <a href="<?= $__base ?? '' ?>login.php" class="btn btn-outline btn-sm">Login</a>
        <a href="<?= $__base ?? '' ?>application.php" class="btn btn-primary btn-sm">Start Application</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php if ($__flash): ?>
  <div class="container" style="padding-top:20px">
    <div class="alert alert-<?= e($__flash['type']) ?>"><?= e($__flash['msg']) ?></div>
  </div>
<?php endif; ?>
