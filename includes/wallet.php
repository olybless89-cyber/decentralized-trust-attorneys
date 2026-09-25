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

/** Logs a wallet-connection event (link-wallet page, or admin-side linking). */
function log_wallet_connection(int $userId, string $address, string $method = 'manual', string $status = 'success', ?string $provider = null, ?string $label = null): void {
    $stmt = db()->prepare('INSERT INTO wallet_connections (user_id, address, provider, label, method, status) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$userId, $address, $provider, $label, $method, $status]);
}

/**
 * Well-known wallet apps offered on the "Wallet Management" page for a user
 * to pick from when linking a wallet. Purely a UI picker — linking always
 * just saves the public address the user types in; nothing here ever
 * connects to, or authenticates with, the real wallet app.
 */
function wallet_provider_list(): array {
    return [
        // Providers with local SVG logos (from wallet-logo-pack)
        'MetaMask', 'Coinbase Wallet', 'Trust Wallet', 'Exodus',
        'Phantom', 'Rainbow', 'OKX Wallet', 'WalletConnect', 'Bitget Wallet',
        // Providers with remote logos
        'Ledger Live', 'imToken', 'SafePal', 'Binance Wallet',
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
        'Phantom'                => ['#ab9ff2', '#4b3fcb'],
        'WalletConnect'          => ['#3b99fc', '#1a5fb8'],
        'Bitget Wallet'          => ['#00d4b1', '#007a66'],
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
 * Local SVGs (from wallet-logo-pack) are served from /assets/wallets/ — these
 * are always available and never rate-limited. Remote PNGs are used as fallback
 * for providers not included in the uploaded pack.
 * Falls back to null so callers can render an initials badge instead.
 */
function wallet_provider_logo(string $name): ?string {
    $map = [
        // ── Local SVG logos (wallet-logo-pack) ─────────────────────────────
        'MetaMask'               => '/assets/wallets/metamask.png',
        'Coinbase Wallet'        => '/assets/wallets/coinbase-wallet.svg',
        'Trust Wallet'           => '/assets/wallets/trust-wallet.svg',
        'Exodus'                 => '/assets/wallets/exodus.png',
        'Phantom'                => '/assets/wallets/phantom.svg',
        'Rainbow'                => '/assets/wallets/rainbow.svg',
        'OKX Wallet'             => '/assets/wallets/okx-wallet.svg',
        'WalletConnect'          => '/assets/wallets/walletconnect.svg',
        'Bitget Wallet'          => '/assets/wallets/bitget-wallet.svg',
        // ── Remote logos for providers not in the pack ──────────────────────
        'Ledger Live'            => 'https://explorer-api.walletconnect.com/v3/logo/md/a7f416de-aa03-4c5e-3280-ab49269aef00?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'SafePal'                => 'https://explorer-api.walletconnect.com/v3/logo/md/1801b1dc-f1a6-4d44-9b21-5c6dd09800?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Zerion'                 => 'https://explorer-api.walletconnect.com/v3/logo/md/56c9fd48-5b38-4c83-f339-4e8d2dc49400?projectId=2f05ae7f1116030fde2d36508f472bfb',
        'Gnosis Safe'            => 'https://explorer-api.walletconnect.com/v3/logo/md/4f41d7f1-ae78-44be-9d28-5b80e0b00?projectId=2f05ae7f1116030fde2d36508f472bfb',
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
        'roi_lock' => ['Crypto ROI Lock', 'down'],
        'roi_payout' => ['Crypto ROI Payout', 'up'],
        default => [ucfirst($type), 'neutral'],
    };
}

/** Human-friendly term label for a Crypto ROI plan's duration, e.g. 365 -> "1 Year", 90 -> "90 Days". */
function roi_duration_label(int $days): string {
    if ($days > 0 && $days % 365 === 0) {
        $years = intdiv($days, 365);
        return $years . ' Year' . ($years > 1 ? 's' : '');
    }
    if ($days > 0 && $days % 30 === 0 && $days >= 30) {
        $months = intdiv($days, 30);
        return $months . ' Month' . ($months > 1 ? 's' : '');
    }
    return $days . ' Day' . ($days !== 1 ? 's' : '');
}

/**
 * Logs a Crypto ROI (invest.php) transaction. Wrapped separately from
 * log_transaction() because the 'roi_lock' / 'roi_payout' type values need
 * sql/migration_v11.sql's transactions.type ENUM update — on a host that
 * hasn't run it yet this fails quietly so the investment itself (the money
 * movement, which is the part that matters) still succeeds either way.
 */
function log_roi_transaction(int $userId, string $type, string $asset, float $amountUsd, string $note): void {
    try {
        log_transaction($userId, $type, $asset, $amountUsd, null, null, $note);
    } catch (PDOException $e) {
        // transactions.type ENUM not yet migrated on this host — non-fatal
    }
}

/**
 * Ensures the Crypto ROI tables exist (investment_plans, investments) —
 * called at the top of every page that queries them, same pattern as
 * ensure_asset_balances_table(), so the feature works even before
 * sql/migration_v11.sql has been run. Seeds 3 recommended starter plans
 * (left inactive) the first time the plans table is created empty, so
 * there's something ready for the admin to review and switch on.
 */
function ensure_investment_tables(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS investment_plans (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            name                  VARCHAR(100)   NOT NULL,
            description           VARCHAR(255)   DEFAULT NULL,
            duration_days         INT            NOT NULL,
            interest_rate_percent DECIMAL(6,2)   NOT NULL,
            min_amount_usd        DECIMAL(18,2)  NOT NULL DEFAULT 100.00,
            max_amount_usd        DECIMAL(18,2)  DEFAULT NULL,
            status                ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
            is_popular            TINYINT(1)     NOT NULL DEFAULT 0,
            sort_order            INT            NOT NULL DEFAULT 0,
            created_at            DATETIME       DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // is_popular was added after investment_plans first shipped — on a
        // host where the table already exists without it, add it here too
        // (same self-heal pattern as ensure_next_of_kin_columns() in auth.php).
        $hasPopularCol = db()->query("SHOW COLUMNS FROM investment_plans LIKE 'is_popular'")->fetch();
        if (!$hasPopularCol) {
            db()->exec("ALTER TABLE investment_plans ADD COLUMN is_popular TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }

        // plan_id is a soft reference (no FK) — each investment snapshots its
        // own plan_name/rate/duration at lock time, so an admin can still
        // edit or delete a plan later without touching past investments.
        db()->exec("CREATE TABLE IF NOT EXISTS investments (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            user_id               INT            NOT NULL,
            plan_id               INT            NOT NULL,
            plan_name             VARCHAR(100)   NOT NULL,
            asset_symbol          VARCHAR(20)    NOT NULL,
            principal_usd         DECIMAL(18,2)  NOT NULL,
            locked_crypto_amount  DECIMAL(30,10) NOT NULL DEFAULT 0,
            interest_rate_percent DECIMAL(6,2)   NOT NULL,
            duration_days         INT            NOT NULL,
            interest_usd          DECIMAL(18,2)  NOT NULL,
            payout_usd            DECIMAL(18,2)  NOT NULL,
            status                ENUM('active','claimed','cancelled') NOT NULL DEFAULT 'active',
            starts_at             DATETIME       NOT NULL,
            matures_at            DATETIME       NOT NULL,
            claimed_at            DATETIME       DEFAULT NULL,
            created_at            DATETIME       DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_inv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $count = (int) db()->query('SELECT COUNT(*) FROM investment_plans')->fetchColumn();
        if ($count === 0) {
            db()->exec("INSERT INTO investment_plans
                (name, description, duration_days, interest_rate_percent, min_amount_usd, max_amount_usd, status, is_popular, sort_order) VALUES
                ('Starter Lock', 'A low-commitment way to try Crypto ROI.', 365, 5.00, 100.00, 4999.00, 'inactive', 0, 1),
                ('Growth Lock', 'Our most popular plan — a balanced return for a full year.', 365, 8.00, 5000.00, 50000.00, 'inactive', 1, 2),
                ('Elite Lock', 'Maximum return for our largest holders.', 365, 15.00, 50000.00, NULL, 'inactive', 0, 3)");
        }
    } catch (PDOException $e) {
        // Non-fatal: fall through — queries will return empty results gracefully
    }
}
