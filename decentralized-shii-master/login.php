<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (current_user()) { header('Location: dashboard.php'); exit; }

$mode = ($_GET['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';
$next = $_GET['next'] ?? 'dashboard.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'signup') {
        $mode = 'signup';
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($full_name === '' || $email === '' || $password === '') {
            $errors[] = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with that email already exists. Please log in instead.';
                $mode = 'login';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash, phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$full_name, $email, $hash, $phone]);
                // Auto-login on signup so the user can proceed immediately
                // (the DB row was just inserted, so SELECT id right after is safe).
                $newId = (int) db()->lastInsertId();
                $_SESSION['user_id'] = $newId;
                try {
                    @send_email($email, $full_name, 'Welcome to ' . SITE_NAME,
                        '<p>Hi ' . e($full_name) . ',</p><p>Your account has been created. You can log in any time at <a href="' . e(SITE_URL) . '/login.php">' . e(SITE_URL) . '/login.php</a> to start a business formation application and manage your account.</p>');
                } catch (\Throwable $mailErr) {
                    // Never let a failed welcome email block signup
                    error_log('[signup] welcome email skipped: ' . $mailErr->getMessage());
                }
                flash_set('Welcome, ' . e($full_name) . '! Your account is ready.');
                header('Location: ' . $next);
                exit;
            }
        }
    } else {
        $mode = 'login';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = db()->prepare('SELECT id, password_hash, full_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = (int) $row['id'];
            flash_set('Welcome back, ' . $row['full_name'] . '.');
            try {
                @send_email($email, $row['full_name'], 'New Login to Your Account',
                    '<p>Hi ' . e($row['full_name']) . ',</p><p>Your account was just signed in to at ' . e(date('M j, Y g:ia')) . ' UTC.</p><p>If this wasn\'t you, contact support immediately at ' . e(SUPPORT_EMAIL) . '.</p>');
            } catch (\Throwable $mailErr) {
                error_log('[login] notification email skipped: ' . $mailErr->getMessage());
            }
            header('Location: ' . $next);
            exit;
        }
        $errors[] = 'Incorrect email or password.';
    }
}

$__base = '';
$pageTitle = $mode === 'signup' ? 'Sign Up' : 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Welcome</h2>
    <p class="auth-sub">Access your application dashboard</p>
    <div class="tabs">
      <a href="login.php?mode=login&next=<?= urlencode($next) ?>" class="<?= $mode === 'login' ? 'active' : '' ?>">Login</a>
      <a href="login.php?mode=signup&next=<?= urlencode($next) ?>" class="<?= $mode === 'signup' ? 'active' : '' ?>">Sign Up</a>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($mode === 'signup'): ?>
      <form method="post" data-loader-label="Submitting your registration&hellip;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="signup">
        <div class="field"><label>Full Name</label><input type="text" name="full_name" placeholder="Jane Doe" required value="<?= e($_POST['full_name'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" placeholder="you@example.com" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label>Phone (optional)</label><input type="text" name="phone" placeholder="(307) 555-0123" value="<?= e($_POST['phone'] ?? '') ?>"></div>
        <div class="field"><label>Password</label><input type="password" name="password" placeholder="At least 6 characters" required></div>
        <button class="btn btn-primary btn-block" type="submit">Create Account</button>
      </form>
    <?php else: ?>
      <form method="post" data-loader-label="Signing you in&hellip;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <div class="field"><label>Email</label><input type="email" name="email" placeholder="you@example.com" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label>Password</label><input type="password" name="password" placeholder="Enter your password" required></div>
        <button class="btn btn-primary btn-block" type="submit">Login</button>
        <div style="text-align:center;margin-top:12px">
          <a href="forgot-password.php" style="font-size:13px;color:var(--navy);font-weight:600">Forgot your password?</a>
        </div>
      </form>
    <?php endif; ?>

    <div class="help-link">Need assistance? <a href="application.php" data-loader-label="Loading your application&hellip;">Start a new business application</a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
