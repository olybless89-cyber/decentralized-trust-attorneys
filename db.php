<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // ── Step 1: Connect (separate try/catch so errors are accurate) ────
        try {
            // If DB_HOST is 'localhost' on Linux, PDO tries a Unix socket
            // (which doesn't exist inside a Railway container, since MySQL is
            // a separate TCP service). Force TCP by substituting '127.0.0.1'.
            $host = (DB_HOST === 'localhost') ? '127.0.0.1' : DB_HOST;
            $dsn = 'mysql:host=' . $host . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $isSocketError  = (strpos($msg, 'No such file or directory') !== false) || (strpos($msg, 'socket') !== false);
            $isRailwayHint  = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1');
            $details = '';
            if ($isSocketError && $isRailwayHint) {
                $details = '<div style="margin-top:14px;padding:14px 16px;background:#fff7ed;border:1px solid #fdba74;border-radius:8px">'
                    . '<h3 style="margin:0 0 8px;color:#9a3412;font-size:15px">🤔 Railway MySQL plugin not detected</h3>'
                    . '<p style="margin:0;font-size:13px;color:#78350f;line-height:1.6">'
                    . 'The <code>MYSQLHOST</code> environment variable is not set — falling back to <code>localhost</code> tries a Unix socket that doesn&rsquo;t exist in the container.<br><br>'
                    . '<strong>Fix:</strong> in your Railway project:<br>'
                    . '&nbsp;&nbsp;1. Ensure the <strong>MySQL plugin</strong> was added to <em>this same project</em> (not a different one).<br>'
                    . '&nbsp;&nbsp;2. If already added, go to the PHP service &rarr; <strong>Variables</strong> &rarr; confirm <code>MYSQLHOST</code>, <code>MYSQLPORT</code>, <code>MYSQLDATABASE</code>, <code>MYSQLUSER</code>, <code>MYSQLPASSWORD</code> are listed.<br>'
                    . '&nbsp;&nbsp;3. If missing, trigger a <strong>Redeploy</strong> (button on the service) — plugin variables can take one deploy to show up.<br>'
                    . '&nbsp;&nbsp;4. If still missing, go to the MySQL plugin &rarr; <strong>Connect</strong> &rarr; connect it to the PHP service explicitly, then redeploy.'
                    . '</p></div>';
            }
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:680px;margin:80px auto;padding:24px;border:1px solid #eee;border-radius:8px">'
                . '<h2 style="color:#0f172a">Database connection failed</h2>'
                . '<p>Please check your DB credentials in <code>config.php</code> (or your Railway environment variables).</p>'
                . '<p style="color:#888;font-size:13px">' . htmlspecialchars($msg) . '</p>'
                . '<details style="margin-top:10px"><summary style="cursor:pointer;color:#64748b;font-size:12px">Debug info</summary>'
                . '<pre style="font-size:12px;color:#475569;background:#f8fafc;padding:10px;border-radius:6px;overflow:auto">DB_HOST=' . htmlspecialchars(DB_HOST) . "\n"
                . 'DB_PORT=' . htmlspecialchars(DB_PORT) . "\n"
                . 'DB_NAME=' . htmlspecialchars(DB_NAME) . "\n"
                . 'DB_USER=' . htmlspecialchars(DB_USER) . "\n"
                . 'Raw env MYSQLHOST=' . htmlspecialchars(var_export(env_or('MYSQLHOST', '(unset)'), true)) . '</pre></details>'
                . $details . '</div>');
        }

        // ── Step 2: Auto-migrate on first boot (own try/catch — never block login) ──
        try {
            $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
            if (empty($tables)) {
                $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
                $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $stmtErr) {
                        error_log('[db auto-migrate] Statement skipped: ' . $stmtErr->getMessage() . ' | SQL: ' . substr($stmt, 0, 200));
                    }
                }
            }

            // Also ensure v10 columns exist the first time db() is called, so
            // manual migration isn't required after deploy.
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM wallet_connections LIKE 'email'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE wallet_connections ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER label");
                }
                $cols2 = $pdo->query("SHOW COLUMNS FROM wallet_connections LIKE 'image_path'")->fetchAll();
                if (empty($cols2)) {
                    $pdo->exec("ALTER TABLE wallet_connections ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER email");
                }
            } catch (Throwable $e) {
                // Columns may already exist or table may not yet exist — non-fatal
            }
        } catch (Throwable $e) {
            // Auto-migration errors must NEVER break the site or make login
            // look like a "connection failure". Log & fall through.
            error_log('[db auto-migrate] ' . $e->getMessage());
        }
    }
    return $pdo;
}
