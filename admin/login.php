<?php
require_once __DIR__ . '/../auth.php';

if (current_admin()) { header('Location: dashboard.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['admin_id'] = (int) $row['id'];
            header('Location: dashboard.php');
            exit;
        }
        $errors[] = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/page-loader.css">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="adm-body adm">
<div class="adm-login-wrap">
  <div class="adm-login-card">
    <div class="adm-brand"><span class="adm-brand-mark">&#9878;</span> Admin Panel</div>
    <h2>Welcome back</h2>
    <p class="sub">Restricted access &mdash; staff only</p>
    <?php foreach ($errors as $err): ?><div class="adm-alert adm-alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="adm-field"><label>Username</label><input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>"></div>
      <div class="adm-field"><label>Password</label><input type="password" name="password" required></div>
      <button class="adm-btn adm-btn-primary adm-btn-block" type="submit">Log In</button>
    </form>
    <a href="../index.php" class="back">&larr; Back to site</a>
  </div>
</div>
<script src="../assets/js/page-loader.js"></script>
</body>
</html>
