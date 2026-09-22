<?php
/**
 * api/asset-balance.php
 * AJAX endpoint for per-asset balance operations.
 *
 * GET  ?action=get&symbol=BTC          → returns asset balance record for current user
 * GET  ?action=all                     → returns all asset balances for current user
 * POST action=upsert                   → add USD amount to a specific asset (additive)
 * POST action=set (admin only)         → directly set crypto_amount + demo_usd_amount
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/wallet.php';

header('Content-Type: application/json');

// Auto-create asset_balances table if migration not yet run
ensure_asset_balances_table();

function json_out(array $data): void {
    echo json_encode($data);
    exit;
}

function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$user   = require_login();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── GET all balances ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'all') {
    // Admins may pass ?_uid=X to fetch a specific user's balances for the
    // manage-funds preview. Only honour this when the caller is an admin.
    $fetchUserId = $user['id'];
    if (!empty($_GET['_uid']) && !empty($_SESSION['admin_id'])) {
        $fetchUserId = (int) $_GET['_uid'];
    }

    $stmt = db()->prepare('SELECT asset_symbol, asset_name, crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ?');
    $stmt->execute([$fetchUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map  = [];
    foreach ($rows as $r) {
        $map[$r['asset_symbol']] = [
            'crypto_amount'   => (float) $r['crypto_amount'],
            'demo_usd_amount' => (float) $r['demo_usd_amount'],
            'asset_name'      => $r['asset_name'],
        ];
    }
    json_out(['ok' => true, 'balances' => $map]);
}

// ── GET single asset ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get') {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));
    if (!$symbol) json_err('symbol required');
    $stmt = db()->prepare('SELECT crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ?');
    $stmt->execute([$user['id'], $symbol]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    json_out([
        'ok'             => true,
        'symbol'         => $symbol,
        'crypto_amount'  => $row ? (float) $row['crypto_amount']   : 0,
        'demo_usd_amount'=> $row ? (float) $row['demo_usd_amount'] : 0,
    ]);
}

// ── POST upsert: add USD amount to existing balance (additive) ───────────────
if ($method === 'POST' && $action === 'upsert') {
    if (!csrf_check()) json_err('Invalid CSRF token', 403);

    $symbol    = strtoupper(trim($_POST['symbol'] ?? ''));
    $assetName = trim($_POST['asset_name'] ?? $symbol);
    $addUsd    = (float) ($_POST['add_usd']  ?? 0);
    $coinPrice = (float) ($_POST['coin_price'] ?? 0);

    if (!$symbol)         json_err('symbol required');
    if ($addUsd <= 0)     json_err('Amount must be greater than 0');
    if ($coinPrice <= 0)  json_err('coin_price required');

    $addCrypto = $addUsd / $coinPrice;

    $db = db();
    // Fetch existing
    $stmt = $db->prepare('SELECT id, crypto_amount, demo_usd_amount FROM asset_balances WHERE user_id = ? AND asset_symbol = ?');
    $stmt->execute([$user['id'], $symbol]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newCrypto = (float) $existing['crypto_amount']   + $addCrypto;
        $newUsd    = (float) $existing['demo_usd_amount'] + $addUsd;
        $upd = $db->prepare('UPDATE asset_balances SET crypto_amount=?, demo_usd_amount=?, asset_name=?, updated_at=NOW() WHERE id=?');
        $upd->execute([$newCrypto, $newUsd, $assetName, $existing['id']]);
    } else {
        $ins = $db->prepare('INSERT INTO asset_balances (user_id, asset_symbol, asset_name, crypto_amount, demo_usd_amount) VALUES (?,?,?,?,?)');
        $ins->execute([$user['id'], $symbol, $assetName, $addCrypto, $addUsd]);
        $newCrypto = $addCrypto;
        $newUsd    = $addUsd;
    }

    // Log transaction
    log_transaction($user['id'], 'admin_credit', $symbol, $addUsd, null, null, 'Demo balance added');

    json_out([
        'ok'             => true,
        'symbol'         => $symbol,
        'crypto_amount'  => $newCrypto,
        'demo_usd_amount'=> $newUsd,
        'message'        => $assetName . ' balance updated',
    ]);
}

// ── POST set (admin): directly set exact amounts ─────────────────────────────
if ($method === 'POST' && $action === 'set') {
    if (!function_exists('require_admin_session') && !isset($_SESSION['admin_id'])) {
        json_err('Admin access required', 403);
    }
    if (!csrf_check()) json_err('Invalid CSRF token', 403);

    $targetUserId = (int) ($_POST['user_id'] ?? $user['id']);
    $symbol       = strtoupper(trim($_POST['symbol'] ?? ''));
    $assetName    = trim($_POST['asset_name'] ?? $symbol);
    $cryptoAmt    = (float) ($_POST['crypto_amount']   ?? 0);
    $usdAmt       = (float) ($_POST['demo_usd_amount'] ?? 0);

    if (!$symbol) json_err('symbol required');

    $db   = db();
    $stmt = $db->prepare('SELECT id FROM asset_balances WHERE user_id = ? AND asset_symbol = ?');
    $stmt->execute([$targetUserId, $symbol]);
    $row  = $stmt->fetch();

    if ($row) {
        $db->prepare('UPDATE asset_balances SET crypto_amount=?, demo_usd_amount=?, asset_name=?, updated_at=NOW() WHERE id=?')
           ->execute([$cryptoAmt, $usdAmt, $assetName, $row['id']]);
    } else {
        $db->prepare('INSERT INTO asset_balances (user_id, asset_symbol, asset_name, crypto_amount, demo_usd_amount) VALUES (?,?,?,?,?)')
           ->execute([$targetUserId, $symbol, $assetName, $cryptoAmt, $usdAmt]);
    }

    json_out(['ok' => true, 'symbol' => $symbol, 'crypto_amount' => $cryptoAmt, 'demo_usd_amount' => $usdAmt]);
}

json_err('Unknown action or method', 400);
