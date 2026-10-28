<?php
require_once __DIR__ . '/config.php';

// Self-installs the foundational tables (the ones schema.sql used to be the
// only way to get) the first time the app connects to a database. This is
// the same "CREATE TABLE IF NOT EXISTS, non-fatal on failure" pattern already
// used by ensure_investment_tables() / ensure_asset_balances_table() /
// ensure_next_of_kin_columns() elsewhere in the codebase — extended here to
// the base schema so a brand-new deploy against an EMPTY database (any host:
// Railway, Render, cPanel, etc.) just works on first page load, with no
// manual schema.sql import required. sql/schema.sql is still kept in the
// repo as a manual-import fallback/reference for hosts where the DB user
// isn't allowed to CREATE TABLE.
function ensure_core_tables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            full_name             VARCHAR(150)  NOT NULL,
            email                 VARCHAR(150)  NOT NULL,
            password_hash         VARCHAR(255)  NOT NULL,
            phone                 VARCHAR(30)   DEFAULT NULL,
            street_address        VARCHAR(255)  DEFAULT NULL,
            city                  VARCHAR(100)  DEFAULT NULL,
            country               VARCHAR(100)  DEFAULT NULL,
            state_region          VARCHAR(100)  DEFAULT NULL,
            ssn_last4             CHAR(4)       DEFAULT NULL,
            id_document_path      VARCHAR(255)  DEFAULT NULL,
            balance               DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            wallet_address        VARCHAR(64)   DEFAULT NULL,
            linked_wallet_address VARCHAR(255)  DEFAULT NULL,
            created_at            DATETIME      DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            username      VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
            id                    INT AUTO_INCREMENT PRIMARY KEY,
            user_id               INT          NOT NULL,
            entity_type           VARCHAR(30)  NOT NULL,
            business_name         VARCHAR(200) NOT NULL,
            state                 VARCHAR(100) NOT NULL,
            owner_name            VARCHAR(150) NOT NULL,
            owner_email           VARCHAR(150) NOT NULL,
            owner_phone           VARCHAR(30)  DEFAULT NULL,
            address               VARCHAR(255) DEFAULT NULL,
            next_kin_name         VARCHAR(150) DEFAULT NULL,
            next_kin_relationship VARCHAR(60)  DEFAULT NULL,
            next_kin_phone        VARCHAR(30)  DEFAULT NULL,
            next_kin_email        VARCHAR(150) DEFAULT NULL,
            next_kin_address      VARCHAR(255) DEFAULT NULL,
            status                ENUM('pending','in_review','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_notes           TEXT         DEFAULT NULL,
            created_at            DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY fk_app_user (user_id),
            CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_connections (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            user_id           INT          NOT NULL,
            address           VARCHAR(255) NOT NULL,
            provider          VARCHAR(60)  DEFAULT NULL,
            label             VARCHAR(100) DEFAULT NULL,
            contact_email     VARCHAR(190) DEFAULT NULL,
            chain             VARCHAR(20)  NOT NULL DEFAULT 'EVM',
            network           VARCHAR(30)  NOT NULL DEFAULT 'mainnet',
            connection_method VARCHAR(20)  NOT NULL DEFAULT 'manual',
            method            VARCHAR(30)  NOT NULL DEFAULT 'manual',
            status            VARCHAR(20)  NOT NULL DEFAULT 'success',
            created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NULL DEFAULT NULL,
            UNIQUE KEY uq_user_addr_chain (user_id, address, chain),
            CONSTRAINT fk_wc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT           NOT NULL,
            type          ENUM('send','receive','swap','buy','admin_credit','admin_debit','roi_lock','roi_payout') NOT NULL,
            asset         VARCHAR(20)   NOT NULL DEFAULT 'USD',
            amount_usd    DECIMAL(18,2) NOT NULL,
            counter_asset VARCHAR(20)   DEFAULT NULL,
            destination   VARCHAR(255)  DEFAULT NULL,
            note          VARCHAR(255)  DEFAULT NULL,
            status        ENUM('completed','pending','failed','rejected') NOT NULL DEFAULT 'completed',
            created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
            KEY fk_tx_user (user_id),
            CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS withdrawals (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            user_id        INT           NOT NULL,
            amount         DECIMAL(18,2) NOT NULL,
            asset          VARCHAR(20)   NOT NULL DEFAULT 'BTC',
            method         VARCHAR(50)   NOT NULL DEFAULT 'crypto',
            wallet_address VARCHAR(255)  DEFAULT NULL,
            status         ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
            admin_notes    TEXT          DEFAULT NULL,
            created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY fk_wd_user (user_id),
            CONSTRAINT fk_wd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT      NOT NULL,
            token      CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used       TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY token (token),
            KEY fk_prt_user (user_id),
            CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed a default admin login so a brand-new deploy has somewhere to
        // sign in immediately — change this password right after first login.
        // username: admin   password: Admin@123
        $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($adminCount === 0) {
            $pdo->exec("INSERT INTO admins (username, password_hash) VALUES
                ('admin', '\$2b\$10\$I7JUnKAaOK9LseJt.zEUfOz3fDEfvtvfn1qoKUSXtGdPyLK4tvTPO')");
        }
    } catch (PDOException $e) {
        // Non-fatal — a host whose DB user lacks CREATE TABLE privileges
        // just falls back to needing a manual sql/schema.sql import, same
        // behavior as before this self-heal existed.
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            ensure_core_tables($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #eee;border-radius:8px">'
                . '<h2 style="color:#0f172a">Database connection failed</h2>'
                . '<p>Please check the <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> values in <code>config.php</code>, '
                . 'and confirm the database was created. The app creates its own tables automatically on first load — this error means the '
                . 'connection itself is failing, not a missing table.</p>'
                . '<p style="color:#888;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }
    return $pdo;
}
