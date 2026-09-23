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
<?php
$__ogTitle = isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME;
$__ogDesc  = $pageDescription ?? 'Secure your assets, minimize your tax burden, and ensure absolute privacy with the nation\'s premier jurisdiction for corporate formation. Expertly guided, fully compliant.';
// Build the origin from the actual request rather than the SITE_URL config
// constant — SITE_URL has drifted out of sync with the live domain before,
// and a wrong absolute image URL is silently unreachable to link crawlers
// (WhatsApp, etc.) with no visible error, so this avoids that failure mode.
$__scheme  = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
$__host    = $_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST);
$__origin  = $__scheme . '://' . $__host;
$__ogImage = $__origin . '/images/og-image.jpg';
$__ogUrl   = $__origin . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<title><?= e($__ogTitle) ?></title>
<meta name="description" content="<?= e($__ogDesc) ?>">

<!-- Open Graph (Facebook, WhatsApp, LinkedIn, Telegram, etc.) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($__ogTitle) ?>">
<meta property="og:description" content="<?= e($__ogDesc) ?>">
<meta property="og:url" content="<?= e($__ogUrl) ?>">
<meta property="og:image" content="<?= e($__ogImage) ?>">
<meta property="og:image:secure_url" content="<?= e($__ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:alt" content="<?= e(SITE_NAME) ?> — Premier Legal Formation">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($__ogTitle) ?>">
<meta name="twitter:description" content="<?= e($__ogDesc) ?>">
<meta name="twitter:image" content="<?= e($__ogImage) ?>">

<link rel="icon" type="image/jpeg" href="<?= $__base ?? '' ?>images/6e3b39c129edbeceb586c5c6d14a87bb.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $__base ?? '' ?>assets/css/style.css">
<link rel="stylesheet" href="<?= $__base ?? '' ?>assets/css/page-loader.css">
</head>
<body>
<header class="topnav">
  <div class="container">
    <a href="<?= $__base ?? '' ?>index.php" class="brand">
      <span class="mark">&#9878;</span> DecTrust Attorney
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
        <a href="<?= $__base ?? '' ?>application.php" class="btn btn-primary btn-sm" data-loader-label="Loading your application&hellip;">Start Application</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php if ($__flash): ?>
  <div class="container" style="padding-top:20px">
    <div class="alert alert-<?= e($__flash['type']) ?>"><?= e($__flash['msg']) ?></div>
  </div>
<?php endif; ?>
