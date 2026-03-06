<?php
// Local DB configuration for PeachTrack
// Default: Homebrew MariaDB/MySQL (free alternative to MAMP)
$host   = getenv('DB_HOST') ?: '127.0.0.1';
$port   = (int)(getenv('DB_PORT') ?: 3306);
$socket = getenv('DB_SOCKET') ?: null; // optional
$db     = getenv('DB_NAME') ?: 'peachtrack';
$user   = getenv('DB_USER') ?: 'peach';
$pass   = getenv('DB_PASS') ?: 'peach123';

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
// --- Session role helpers (supports Admin switching into Employee Mode)
function peachtrack_base_role(): string {
    return (string)($_SESSION['role'] ?? '');
}

function peachtrack_base_employee_id(): int {
    return (int)($_SESSION['id'] ?? 0);
}

function peachtrack_effective_role(): string {
    // If admin is "viewing as employee", treat role as employee for UI/pages that use this.
    $base = peachtrack_base_role();
    if ($base === '101' && isset($_SESSION['view_as']) && $_SESSION['view_as'] === 'employee') {
        return '102';
    }
    return $base;
}

function peachtrack_effective_employee_id(): int {
    $base = peachtrack_base_role();
    if ($base === '101' && isset($_SESSION['view_as']) && $_SESSION['view_as'] === 'employee') {
        return (int)($_SESSION['view_employee_id'] ?? 0);
    }
    return peachtrack_base_employee_id();
}

function peachtrack_effective_name(): string {
    // Keep displaying the admin's own name even in Employee Mode.
    // (Employee Mode should change permissions/views, not identity.)
    return (string)($_SESSION['name'] ?? 'User');
}

function peachtrack_view_employee_name(): string {
    // Only populated when an admin is viewing as an employee.
    return (string)($_SESSION['view_employee_name'] ?? '');
}

function peachtrack_is_admin_base(): bool {
    return peachtrack_base_role() === '101';
}

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

