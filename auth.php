<?php
require_once __DIR__ . '/db.php';

function current_user(bool $fresh = false): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    // Bug fix: allow callers to bypass the static cache to get a live DB value
    // (needed after any balance update within the same request).
    if ($cache !== null && !$fresh) return $cache;
    $stmt = db()->prepare('SELECT id, full_name, email, phone, street_address, city, country, state_region, ssn_last4, id_document_path, balance, wallet_address, linked_wallet_address, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

/**
 * Handle an optional uploaded identity document. Returns the stored relative
 * path (to save in the DB) or null if no file was uploaded / on error.
 * $existing lets a re-submission keep the previously uploaded file.
 */
function handle_id_upload(string $fieldName, ?string $existing = null): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $existing;
    }
    $maxBytes = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxBytes) {
        return $existing;
    }
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return $existing;
    }
    $dir = __DIR__ . '/uploads/ids';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $newName)) {
        return 'uploads/ids/' . $newName;
    }
    return $existing;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php'));
        exit;
    }
    return $u;
}

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = db()->prepare('SELECT id, username, created_at FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

function require_admin(): array {
    $a = current_admin();
    if (!$a) {
        header('Location: login.php');
        exit;
    }
    return $a;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function flash_set(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function status_badge(string $status): string {
    $map = [
        'pending'   => ['Pending Review', '#b45309', '#fef3c7'],
        'in_review' => ['In Review', '#1d4ed8', '#dbeafe'],
        'approved'  => ['Approved', '#15803d', '#dcfce7'],
        'rejected'  => ['Rejected', '#b91c1c', '#fee2e2'],
    ];
    [$label, $fg, $bg] = $map[$status] ?? [ucfirst($status), '#334155', '#e2e8f0'];
    return '<span class="badge" style="color:' . $fg . ';background:' . $bg . '">' . $label . '</span>';
}

function wd_status_badge(string $status): string {
    $map = [
        'pending'  => ['Pending', '#b45309', '#fef3c7'],
        'approved' => ['Approved', '#15803d', '#dcfce7'],
        'declined' => ['Declined', '#b91c1c', '#fee2e2'],
    ];
    [$label, $fg, $bg] = $map[$status] ?? [ucfirst($status), '#334155', '#e2e8f0'];
    return '<span class="badge" style="color:' . $fg . ';background:' . $bg . '">' . $label . '</span>';
}

function fmt_money(float $n): string {
    return '$' . number_format($n, 2);
}

/**
 * Generic status pill for the redesigned admin panel — maps a raw status
 * value to a human label and a color, using the shared .adm-badge classes.
 */
function badge_for_status(string $status, array $labels = []): string {
    $tone = [
        'pending' => 'amber', 'in_review' => 'blue',
        'approved' => 'green', 'declined' => 'red', 'rejected' => 'red',
        'success' => 'green', 'completed' => 'green', 'failed' => 'red',
    ][$status] ?? 'gray';
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="adm-badge adm-badge-' . $tone . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}
