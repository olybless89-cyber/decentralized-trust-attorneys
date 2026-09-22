<?php
/**
 * Forgot Password — step 2: user clicks email link, sets a new password.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$token = trim($_GET['token'] ?? '');
$errors = [];
$tokenRow = null;

if ($token !== '') {
    $stmt = db()->prepare(
        'SELECT prt.*, u.full_name, u.email FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch() ?: null;
}

$done = false;
if ($tokenRow && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please request a new reset link.';
    } else {
        $new = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        if (strlen($new) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$hash, $tokenRow['user_id']]);
            // Mark token as used so it cannot be replayed.
            db()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?')
                ->execute([$tokenRow['id']]);
            send_email($tokenRow['email'], $tokenRow['full_name'], 'Your Password Was Changed — ' . SITE_NAME,
                '<p>Hi ' . e($tokenRow['full_name']) . ',</p>'
                . '<p>Your password was successfully changed. If you did not make this change, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            flash_set('Password changed successfully. Please log in with your new password.');
            header('Location: login.php');
            exit;
        }
    }
}

$__base = '';
$pageTitle = 'Set New Password';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Set New Password</h2>

    <?php if (!$token || !$tokenRow): ?>
      <div class="alert alert-error">This reset link is invalid or has expired. Please <a href="forgot-password.php" style="font-weight:700;color:var(--navy)">request a new one</a>.</div>
    <?php else: ?>
      <p class="auth-sub">Choose a new password for <strong><?= e($tokenRow['email']) ?></strong></p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label>New Password</label>
          <input type="password" name="password" placeholder="At least 6 characters" required>
        </div>
        <div class="field">
          <label>Confirm New Password</label>
          <input type="password" name="password_confirm" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Change Password</button>
      </form>
    <?php endif; ?>

    <div class="help-link"><a href="login.php">&larr; Back to Login</a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
