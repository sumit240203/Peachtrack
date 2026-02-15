<?php
// MAMP MySQL database configuration for PeachTrack
// Note: In this MAMP setup, MySQL is reachable via Unix socket.
$host   = getenv('DB_HOST') ?: 'localhost';
$port   = (int)(getenv('DB_PORT') ?: 8889);
$socket = getenv('DB_SOCKET') ?: '/Applications/MAMP/tmp/mysql/mysql.sock';
$db     = getenv('DB_NAME') ?: 'peachtrack';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: 'root';

$conn = new mysqli($host, $user, $pass, $db, $port, $socket);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Use local timezone for date-based dashboards/reports
// (Feb in Alberta is MST = UTC-07:00; adjust if you want DST handling)
$conn->query("SET time_zone = '-07:00'");
// Also set PHP timezone for consistent date() output
@date_default_timezone_set('America/Edmonton');

// ---- Schema helpers (for graceful fallback when DB migrations haven't been run yet)
function peachtrack_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table.'.'.$column);
    if (array_key_exists($key, $cache)) return $cache[$key];

    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow['db'] ?? '';
    if (!$dbName) {
        $cache[$key] = false;
        return false;
    }

    $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $cache[$key] = ($res && $res->num_rows > 0);
    return $cache[$key];
}

