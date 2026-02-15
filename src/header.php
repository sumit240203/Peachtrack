<?php
// PeachTrack App Layout Header (Sidebar + Topbar)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Guard: redirect to login if not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$baseRole = peachtrack_base_role();
$role = peachtrack_effective_role(); // 101=Manager/Admin, 102=Employee
$name = peachtrack_effective_name();

function nav_link($href, $label) {
    $current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
    $active = ($current === basename($href)) ? 'active' : '';
    echo '<a class="'.$active.'" href="'.$href.'">'.$label.'</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeachTrack</title>
  <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: time(); ?>" />
  <link rel="stylesheet" href="dashboard.css?v=<?php echo @filemtime(__DIR__ . '/dashboard.css') ?: time(); ?>" />
</head>
<body class="app">

<div class="app-shell">
  <aside class="sidebar" aria-label="Sidebar">
    <div class="brand">
      <div class="brand-badge">🍑</div>
      <div>
        <h1>PeachTrack</h1>
        <p><?php echo ($role === '101') ? 'Manager' : 'Employee'; ?> Portal</p>
      </div>
    </div>

    <nav class="nav">
      <?php nav_link('index.php', 'Dashboard'); ?>
      <?php if ($role === '102'): ?>
        <?php nav_link('my_shifts.php', '📈 My Shifts'); ?>
      <?php endif; ?>
      <?php if ($baseRole === '101'): ?>
        <?php nav_link('reports.php', 'Reports'); ?>
        <?php nav_link('payroll.php', '💵 Payroll'); ?>
        <?php nav_link('manage_users.php', '👤 Manage Users'); ?>
        <?php nav_link('manage_shifts.php', '🕒 Manage Shifts'); ?>
        <?php nav_link('create_shift.php', '➕ Create Shift'); ?>
        <?php if ($role === '102'): ?>
          <?php nav_link('switch_mode.php?exit=1', '↩ Exit Employee Mode'); ?>
        <?php else: ?>
          <?php nav_link('switch_mode.php', '🔁 Employee Mode'); ?>
        <?php endif; ?>
      <?php endif; ?>
      <?php nav_link('about.php', 'ℹ️ About'); ?>
      <?php nav_link('logout.php', 'Logout'); ?>
    </nav>
  </aside>

  <div class="content">
    <div class="topbar">
      <div class="topbar-card">
        <div style="display:flex; align-items:center; gap:10px;">
          <button class="hamburger no-print" data-toggle-sidebar aria-label="Toggle sidebar">☰</button>
          <div>
            <div style="font-weight:900;">Welcome, <?php echo htmlspecialchars($name); ?></div>
            <div class="muted" style="font-size:12px;">Real-time shift tracking • tips • reports</div>
          </div>
        </div>

        <div class="muted" style="font-size:12px; text-align:right;">
          Role: <strong><?php echo ($role === '101') ? 'Admin/Manager' : 'Employee'; ?></strong>
          <?php if ($baseRole === '101' && $role === '102'): ?>
            <div class="muted" style="font-size:12px;">Admin viewing as Employee</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <main class="main">
