<?php
require_once __DIR__ . '/config.php';

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
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #eee;border-radius:8px">'
                . '<h2 style="color:#0f172a">Database connection failed</h2>'
                . '<p>Please check the <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> values in <code>config.php</code>, '
                . 'and confirm the database was created and <code>sql/schema.sql</code> has been imported via phpMyAdmin.</p>'
                . '<p style="color:#888;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }
    return $pdo;
}
