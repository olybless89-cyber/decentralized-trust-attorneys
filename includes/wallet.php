<?php
/**
 * Shared wallet helpers used by receive.php, send.php, swap.php, buy.php,
 * dashboard.php and the admin panel.
 */

function wallet_supported_assets(): array {
    return ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'XRP'];
}

/** Full display name for an asset ticker, e.g. BTC -> Bitcoin. */
function asset_label(string $ticker): string {
    $map = ['BTC' => 'Bitcoin', 'ETH' => 'Ethereum', 'USDT' => 'Tether', 'BNB' => 'BNB', 'SOL' => 'Solana', 'XRP' => 'Ripple'];
    return $map[$ticker] ?? $ticker;
}

/** Logs a wallet-connection event (link-wallet page, or admin-side linking). */
function log_wallet_connection(int $userId, string $address, string $method = 'manual', string $status = 'success'): void {
    $stmt = db()->prepare('INSERT INTO wallet_connections (user_id, address, method, status) VALUES (?,?,?,?)');
    $stmt->execute([$userId, $address, $method, $status]);
}

/** Returns the user's demo receive address, generating and saving one on first use. */
function get_or_create_wallet_address(array $user): string {
    if (!empty($user['wallet_address'])) {
        return $user['wallet_address'];
    }
    $addr = '0x' . bin2hex(random_bytes(20));
    $stmt = db()->prepare('UPDATE users SET wallet_address = ? WHERE id = ?');
    $stmt->execute([$addr, $user['id']]);
    return $addr;
}

/** Logs a wallet transaction row. */
function log_transaction(int $userId, string $type, string $asset, float $amountUsd, ?string $counterAsset = null, ?string $destination = null, ?string $note = null): void {
    $stmt = db()->prepare('INSERT INTO transactions (user_id, type, asset, amount_usd, counter_asset, destination, note) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$userId, $type, $asset, $amountUsd, $counterAsset, $destination, $note]);
}

/** Human-readable label + tone class for a transaction type, for the dashboard ledger. */
function tx_label(string $type): array {
    return match ($type) {
        'send' => ['Sent', 'down'],
        'receive' => ['Received', 'up'],
        'swap' => ['Swapped', 'neutral'],
        'buy' => ['Bought', 'up'],
        'admin_credit' => ['Deposit Received', 'up'],
        'admin_debit' => ['Adjustment', 'down'],
        default => [ucfirst($type), 'neutral'],
    };
}
