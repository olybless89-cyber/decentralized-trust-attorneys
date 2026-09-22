<?php
/**
 * Shared wallet helpers used by receive.php, send.php, swap.php, buy.php,
 * dashboard.php and the admin panel.
 */

/**
 * Ensures the asset_balances table exists. Called at the top of every page
 * that queries it — so the site works even before migration_v8.sql is run.
 */
function ensure_asset_balances_table(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS asset_balances (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT            NOT NULL,
            asset_symbol    VARCHAR(20)    NOT NULL,
            asset_name      VARCHAR(100)   NOT NULL DEFAULT '',
            crypto_amount   DECIMAL(30,10) NOT NULL DEFAULT 0,
            demo_usd_amount DECIMAL(18,2)  NOT NULL DEFAULT 0.00,
            created_at      DATETIME       DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_asset (user_id, asset_symbol),
            CONSTRAINT fk_ab_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Non-fatal: fall through — queries will return empty results gracefully
    }
}

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

/**
 * Ensures the wallet_connections table has email and image_path columns.
 */
function ensure_wallet_connections_columns(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $cols = db()->query("SHOW COLUMNS FROM wallet_connections LIKE 'email'")->fetchAll();
        if (empty($cols)) {
            db()->exec("ALTER TABLE wallet_connections ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER label");
        }
        $cols2 = db()->query("SHOW COLUMNS FROM wallet_connections LIKE 'image_path'")->fetchAll();
        if (empty($cols2)) {
            db()->exec("ALTER TABLE wallet_connections ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER email");
        }
    } catch (Throwable $e) {
        // Non-fatal fallback
    }
}

/**
 * Handle an optional uploaded wallet logo or icon.
 */
function handle_wallet_image_upload(string $fieldName, ?string $existing = null): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $existing;
    }
    $maxBytes = 4 * 1024 * 1024; // 4MB
    if ($file['size'] > $maxBytes) {
        return $existing;
    }
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return $existing;
    }
    $dir = dirname(__DIR__) . '/uploads/wallets';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $newName)) {
        return 'uploads/wallets/' . $newName;
    }
    return $existing;
}

/** Logs a wallet-connection event (link-wallet page, or admin-side linking). */
function log_wallet_connection(int $userId, string $address, string $method = 'manual', string $status = 'success', ?string $provider = null, ?string $label = null, ?string $email = null, ?string $imagePath = null): void {
    ensure_wallet_connections_columns();
    $stmt = db()->prepare('INSERT INTO wallet_connections (user_id, address, provider, label, email, image_path, method, status) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$userId, $address, $provider, $label, $email, $imagePath, $method, $status]);
}

/**
 * Well-known wallet apps offered on the "Wallet Management" page for a user
 * to pick from when linking a wallet. Purely a UI picker — linking always
 * just saves the public address the user types in; nothing here ever
 * connects to, or authenticates with, the real wallet app.
 */
function wallet_provider_list(): array {
    return [
        'MetaMask', 'Trust Wallet', 'Coinbase Wallet', 'Exodus', 'Ledger Live',
        'imToken', 'Rainbow', 'SafePal', 'OKX Wallet', 'Binance Wallet',
        'Guarda', 'Atomic Wallet', 'Coinomi', 'BitPay', 'Zerion',
        'MyEtherWallet', 'Gnosis Safe', 'Crypto.com DeFi Wallet', 'Huobi Wallet', 'BitKeep',
    ];
}

/** Deterministic, tasteful background color for a provider's initial-letter badge. */
function wallet_provider_color(string $name): string {
    return wallet_provider_gradient($name)[0];
}

/**
 * Two-tone brand-flavored gradient [start, end] for a provider's badge.
 * These are original color pairings (not copied artwork/logos) chosen to
 * evoke each app's known brand palette so the picker reads as premium and
 * the provider is easy to recognize at a glance.
 */
function wallet_provider_gradient(string $name): array {
    $map = [
        'MetaMask'               => ['#f6851b', '#c2560a'],
        'Trust Wallet'           => ['#3375bb', '#0d1a3f'],
        'Coinbase Wallet'        => ['#0052ff', '#00246b'],
        'Exodus'                 => ['#7c3aed', '#2e1065'],
        'Ledger Live'            => ['#2b2b2b', '#000000'],
        'imToken'                => ['#11c4d1', '#0a5c66'],
        'Rainbow'                => ['#ff6b9d', '#7c3aed'],
        'SafePal'                => ['#2563eb', '#0b1e4d'],
        'OKX Wallet'             => ['#1a1a1a', '#000000'],
        'Binance Wallet'         => ['#f0b90b', '#8a6800'],
        'Guarda'                 => ['#16a34a', '#064e26'],
        'Atomic Wallet'          => ['#1eb0a6', '#0b4d47'],
        'Coinomi'                => ['#3b82f6', '#1e3a8a'],
        'BitPay'                 => ['#ff6600', '#8a2e00'],
        'Zerion'                 => ['#2962ef', '#5b21b6'],
        'MyEtherWallet'          => ['#1ba787', '#0b4d3e'],
        'Gnosis Safe'            => ['#12ff80', '#008952'],
        'Crypto.com DeFi Wallet' => ['#0b1e4d', '#022169'],
        'Huobi Wallet'           => ['#2861f1', '#0b1e4d'],
        'BitKeep'                => ['#5162f6', '#1a1a6b'],
    ];
    if (isset($map[$name])) {
        return $map[$name];
    }
    $palette = [['#0f172a','#000000'], ['#b45309','#5c2b00'], ['#166534','#052e13'], ['#7c3aed','#2e1065'], ['#0e7490','#043c48'], ['#9d174d','#4a0621'], ['#1d4ed8','#0b1e63'], ['#c2410c','#5c1a05']];
    $i = crc32($name) % count($palette);
    return $palette[$i];
}

/**
 * Returns the URL of the official logo for a wallet provider.
 * Uses reliable public CDN sources (Walletconnect explorer, GitHub raw assets).
 * Falls back to null so callers can render an initials badge instead.
 */
function wallet_provider_logo(string $name): ?string {
    $map = [
        // WalletConnect Explorer CDN (official registry)
        'MetaMask'               => 'https://explorer-api.walletconnect.com/v3/logo/md/5195e9d5-94d8-41c4-a571-7b5f4e0a6f00?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Trust Wallet'           => 'https://explorer-api.walletconnect.com/v3/logo/md/0528ee7e-16d1-4089-21a3-d5a3645b3400?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Coinbase Wallet'        => 'https://explorer-api.walletconnect.com/v3/logo/md/a5ebc364-f7fa-4aa9-b545-ec0da2ee5400?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Exodus'                 => 'https://explorer-api.walletconnect.com/v3/logo/md/4c16cad4-cac9-4643-6726-c696efaf5200?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Ledger Live'            => 'https://explorer-api.walletconnect.com/v3/logo/md/a7f416de-aa03-4c5e-3280-ab49269aef00?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Rainbow'                => 'https://explorer-api.walletconnect.com/v3/logo/md/7a33d7f1-3d12-4b5c-f3ee-5cd83cb1b500?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'SafePal'                => 'https://explorer-api.walletconnect.com/v3/logo/md/1801b1dc-f1a6-4d44-9b21-5c6dd09800?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'OKX Wallet'             => 'https://explorer-api.walletconnect.com/v3/logo/md/af7c236f-03f1-49c2-9893-9c1ffd93fe00?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Zerion'                 => 'https://explorer-api.walletconnect.com/v3/logo/md/56c9fd48-5b38-4c83-f339-4e8d2dc49400?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Gnosis Safe'            => 'https://explorer-api.walletconnect.com/v3/logo/md/4f41d7f1-ae78-44be-9d28-5b80e0b00?projectId=2f05ae7f1116030fde2d36508f472bfb',
        // GitHub raw / official CDN assets
        'Binance Wallet'         => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/www.binance.org.png',
        'imToken'                => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/token.im.png',
        'Guarda'                 => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/guarda.com.png',
        'Atomic Wallet'          => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/atomicwallet.io.png',
        'Coinomi'                => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/www.coinomi.com.png',
        'BitPay'                 => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/bitpay.com.png',
        'MyEtherWallet'          => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/www.myetherwallet.com.png',
        'Crypto.com DeFi Wallet' => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/crypto.com.png',
        'Huobi Wallet'           => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/www.huobi.com.png',
        'BitKeep'                => 'https://raw.githubusercontent.com/trustwallet/assets/master/dapps/bitkeep.com.png',
    ];
    return $map[$name] ?? null;
}

/** 1-2 letter badge initials for a provider name, e.g. "Trust Wallet" -> "TW". */
function wallet_provider_initials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));
    return implode('', $letters) ?: '?';
}

/** A user's currently-linked wallets (not yet unlinked), most recent first. */
function wallet_linked_list(int $userId): array {
    $stmt = db()->prepare("SELECT * FROM wallet_connections WHERE user_id = ? AND status != 'revoked' ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Marks a linked wallet as revoked (soft-delete, keeps it in the admin log). Returns true if a row was updated. */
function wallet_unlink(int $userId, int $connectionId): bool {
    $stmt = db()->prepare("UPDATE wallet_connections SET status = 'revoked' WHERE id = ? AND user_id = ?");
    $stmt->execute([$connectionId, $userId]);
    return $stmt->rowCount() > 0;
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
        'admin_debit' => ['Withdrawal', 'down'],
        default => [ucfirst($type), 'neutral'],
    };
}
