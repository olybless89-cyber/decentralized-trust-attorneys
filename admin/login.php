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
$__base = '../';
$pageTitle = 'Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background:var(--navy-dark)">
<div class="auth-wrap" style="min-height:100vh;background:var(--navy-dark)">
  <div class="auth-card">
    <div class="brand" style="justify-content:center;margin-bottom:8px"><span class="mark">&#9878;</span> Decentralized Trust</div>
    <h2>Admin Panel</h2>
    <p class="auth-sub">Restricted access — staff only</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field"><label>Username</label><input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn btn-primary btn-block" type="submit">Log In</button>
    </form>
    <div class="help-link"><a href="../index.php">&larr; Back to site</a></div>
  </div>
</div>
</body>
</html>
