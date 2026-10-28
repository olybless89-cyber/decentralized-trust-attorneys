<?php
// TEMPORARY diagnostic page — delete this file once the database connection
// issue is resolved. It only prints environment variable NAMES and
// connection diagnostics, with the password masked, but it's still not
// something that should stay on a public site long-term.
header('Content-Type: text/plain');

function show(string $name): string {
    $v = getenv($name);
    if ($v === false) return "$name = (not set)\n";
    if ($name === 'MYSQLPASSWORD') {
        return "$name = (set, length " . strlen($v) . ")\n";
    }
    return "$name = " . var_export($v, true) . "\n";
}

echo "=== Environment variables as seen by THIS running process ===\n";
echo show('MYSQLHOST');
echo show('MYSQLPORT');
echo show('MYSQLDATABASE');
echo show('MYSQLUSER');
echo show('MYSQLPASSWORD');

echo "\n=== DNS resolution test ===\n";
$host = getenv('MYSQLHOST') ?: 'localhost';
echo "Trying to resolve: $host\n";
if (filter_var($host, FILTER_VALIDATE_IP)) {
    echo "$host is already a literal IP address (no DNS lookup needed)\n";
} else {
    $ip = gethostbyname($host);
    if ($ip === $host) {
        echo "FAILED to resolve $host (gethostbyname returned the hostname unchanged, meaning lookup failed)\n";
    } else {
        echo "Resolved $host -> $ip\n";
    }
}

echo "\n=== Raw TCP connect test (bypassing PDO) ===\n";
$port = (int) (getenv('MYSQLPORT') ?: 3306);
$start = microtime(true);
$errno = 0;
$errstr = '';
$fp = @fsockopen($host, $port, $errno, $errstr, 5);
$elapsed = round((microtime(true) - $start) * 1000);
if ($fp) {
    echo "Connected to $host:$port in {$elapsed}ms\n";
    fclose($fp);
} else {
    echo "FAILED to connect to $host:$port after {$elapsed}ms -- errno=$errno errstr=$errstr\n";
}

echo "\n=== PHP info ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
