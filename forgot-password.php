<?php
/**
 * Forgot Password — step 1: user enters their email, we send a reset link.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $stmt = db()->prepare('SELECT id, full_name FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Invalidate any existing unused tokens for this user.
                db()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0')
                    ->execute([$user['id']]);

                $token = bin2hex(random_bytes(32)); // 64-char hex token
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                db()->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?,?,?)')
                    ->execute([$user['id'], $token, $expires]);

                $link = rtrim(SITE_URL, '/') . '/reset-password.php?token=' . urlencode($token);
                send_email($email, $user['full_name'], 'Reset Your Password — ' . SITE_NAME,
                    '<p>Hi ' . e($user['full_name']) . ',</p>'
                    . '<p>We received a request to reset your password. Click the button below to choose a new one. This link expires in 1 hour.</p>'
                    . '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#0f172a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">Reset My Password</a></p>'
                    . '<p style="font-size:13px;color:#64748b">If you didn\'t request this, you can safely ignore this email — your password has not changed.</p>');
            }
            // Always show success to prevent user enumeration.
            $success = true;
        }
    }
}

$__base = '';
$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Reset Password</h2>
    <p class="auth-sub">Enter your account email and we'll send you a reset link.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">If an account with that email exists, a reset link has been sent. Check your inbox (and spam folder).</div>
      <div style="text-align:center;margin-top:16px"><a href="login.php" style="color:var(--navy);font-weight:600">&larr; Back to Login</a></div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="you@example.com" required value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <button class="btn btn-primary btn-block" type="submit">Send Reset Link</button>
      </form>
      <div class="help-link"><a href="login.php">&larr; Back to Login</a></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
