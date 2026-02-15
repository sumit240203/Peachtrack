<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Employees only
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '102') {
    header('Location: index.php');
    exit;
}

require_once "header.php";

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$empId = (int)($_SESSION['id'] ?? 0);

// Shifts with totals (ignore deleted tips only if schema supports it)
$hasIsDeleted = peachtrack_has_column($conn, 'tip', 'Is_Deleted');

$sql = "
SELECT s.Shift_ID,
       s.Start_Time,
       s.End_Time,
       COALESCE(s.Sale_Amount,0) AS sales,
       COALESCE(SUM(t.Tip_Amount),0) AS tips,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS tips_cash,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS tips_elec,
       COUNT(t.Tip_ID) AS tip_count
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID".($hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "")."
WHERE s.Employee_ID = ?
  AND DATE(s.Start_Time) BETWEEN ? AND ?
GROUP BY s.Shift_ID, s.Start_Time, s.End_Time, s.Sale_Amount
ORDER BY s.Start_Time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $empId, $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// KPI range summary
$kpi = ['shifts'=>0,'tips'=>0.0,'sales'=>0.0,'rate'=>0.0];
$kpi['shifts'] = count($rows);
foreach ($rows as $r) {
    $kpi['tips'] += (float)$r['tips'];
    $kpi['sales'] += (float)$r['sales'];
}
$kpi['rate'] = ($kpi['sales'] > 0) ? (($kpi['tips'] / $kpi['sales']) * 100.0) : 0.0;

function fmt_dt($dt) {
    if (!$dt) return '-';
    $t = strtotime($dt);
    if (!$t) return htmlspecialchars($dt);
    return date('M j, Y g:i A', $t);
}

function fmt_duration($start, $end) {
    if (!$start || !$end) return '-';
    $s = strtotime($start);
    $e = strtotime($end);
    if (!$s || !$e || $e < $s) return '-';
    $mins = (int)(($e - $s) / 60);
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return ($h > 0 ? $h.'h ' : '').$m.'m';
}
?>

<div class="grid grid-3">
  <div class="card kpi">
    <div>
      <div class="label">Shifts (range)</div>
      <div class="value"><?php echo (int)$kpi['shifts']; ?></div>
    </div>
    <div class="muted"><?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?></div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Total tips</div>
      <div class="value">$<?php echo htmlspecialchars(number_format($kpi['tips'], 2)); ?></div>
    </div>
    <div class="muted">cash + electronic</div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Tip rate</div>
      <div class="value"><?php echo htmlspecialchars(number_format($kpi['rate'], 2)); ?>%</div>
    </div>
    <div class="muted">tips ÷ sales</div>
  </div>
</div>

<div style="height:14px"></div>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">📈 My Shifts</h2>
      <div class="muted">View your shift history and totals. Deleted tips are excluded.</div>
    </div>
    <div class="no-print" style="display:flex; gap:10px;">
      <button class="btn btn-ghost" onclick="window.print()">🖨️ Print</button>
    </div>
  </div>

  <div style="height:14px"></div>

  <form class="no-print" method="GET" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:12px; align-items:end;">
    <div>
      <label>From</label>
      <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
    </div>
    <div>
      <label>To</label>
      <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Apply</button>
    </div>
  </form>

  <div style="height:12px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>Shift</th>
        <th>Start</th>
        <th>End</th>
        <th>Duration</th>
        <th>Tips</th>
        <th>Sales</th>
        <th>Tip rate</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">No shifts found for this range.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php $rate = ((float)$r['sales'] > 0) ? (((float)$r['tips'] / (float)$r['sales']) * 100.0) : 0.0; ?>
          <tr>
            <td>#<?php echo (int)$r['Shift_ID']; ?></td>
            <td><?php echo fmt_dt($r['Start_Time']); ?></td>
            <td><?php echo fmt_dt($r['End_Time']); ?></td>
            <td><?php echo htmlspecialchars(fmt_duration($r['Start_Time'], $r['End_Time'])); ?></td>
            <td>$<?php echo htmlspecialchars(number_format((float)$r['tips'],2)); ?></td>
            <td>$<?php echo htmlspecialchars(number_format((float)$r['sales'],2)); ?></td>
            <td><?php echo htmlspecialchars(number_format($rate,2)); ?>%</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once "footer.php"; ?>
