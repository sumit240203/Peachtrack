<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: login.php');
    exit;
}

require_once "header.php";

// Filters
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$employee = $_GET['employee'] ?? 'all';

// Employee list
$employees = [];
$res = $conn->query("SELECT Employee_ID, Employee_Name, User_Name FROM employee ORDER BY Employee_Name ASC");
if ($res) $employees = $res->fetch_all(MYSQLI_ASSOC);

$where = " WHERE DATE(s.Start_Time) BETWEEN ? AND ? ";
$params = [$from, $to];
$types = "ss";

// If tip soft-delete schema exists, exclude deleted tips from all reports
$tipJoinCond = peachtrack_has_column($conn, 'tip', 'Is_Deleted') ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "";

if ($employee !== 'all') {
    $where .= " AND e.Employee_ID = ? ";
    $params[] = (int)$employee;
    $types .= "i";
}

// Totals by employee in range
$sqlByEmployee = "
SELECT e.Employee_ID,
       e.Employee_Name,
       e.User_Name,
       COUNT(DISTINCT s.Shift_ID) AS shifts,
       COALESCE(SUM(t.Tip_Amount), 0) AS total_tips,
       COALESCE(SUM(s.Sale_Amount), 0) AS total_sales
FROM employee e
LEFT JOIN shift s ON s.Employee_ID = e.Employee_ID
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
".$where."
GROUP BY e.Employee_ID, e.Employee_Name, e.User_Name
ORDER BY total_tips DESC;
";

$stmt = $conn->prepare($sqlByEmployee);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Chart data: by employee
$chartEmpLabels = [];
$chartEmpTips = [];
$chartEmpSales = [];
foreach ($rows as $r) {
    $chartEmpLabels[] = $r['Employee_Name'];
    $chartEmpTips[] = (float)$r['total_tips'];
    $chartEmpSales[] = (float)$r['total_sales'];
}

// Chart: tips by day (line)
$sqlTipsByDay = "
SELECT DATE(s.Start_Time) AS day,
       COALESCE(SUM(t.Tip_Amount),0) AS tips
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
JOIN employee e ON e.Employee_ID = s.Employee_ID
".$where."
GROUP BY DATE(s.Start_Time)
ORDER BY day ASC;
";
$stmt = $conn->prepare($sqlTipsByDay);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$byDayTips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chartDayLabels = array_map(fn($x)=>$x['day'], $byDayTips);
$chartDayTips = array_map(fn($x)=>(float)$x['tips'], $byDayTips);

// Chart: sales by day (line)
$sqlSalesByDay = "
SELECT DATE(s.Start_Time) AS day,
       COALESCE(SUM(s.Sale_Amount),0) AS sales
FROM shift s
JOIN employee e ON e.Employee_ID = s.Employee_ID
".$where."
GROUP BY DATE(s.Start_Time)
ORDER BY day ASC;
";
$stmt = $conn->prepare($sqlSalesByDay);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$byDaySales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chartDaySales = array_map(fn($x)=>(float)$x['sales'], $byDaySales);

// Chart: cash vs electronic (doughnut)
$sqlMethod = "
SELECT
  COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS cash,
  COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS elec
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
JOIN employee e ON e.Employee_ID = s.Employee_ID
".$where.";
";
$stmt = $conn->prepare($sqlMethod);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$method = $stmt->get_result()->fetch_assoc() ?: ['cash'=>0,'elec'=>0];
$cash = (float)$method['cash'];
$elec = (float)$method['elec'];

// KPI summary (range)
$kpi = [
  'shifts' => 0,
  'tips' => 0.0,
  'sales' => 0.0,
  'rate' => 0.0,
];
$sqlKpi = "
SELECT COUNT(DISTINCT s.Shift_ID) AS shifts,
       COALESCE(SUM(t.Tip_Amount),0) AS tips,
       COALESCE(SUM(s.Sale_Amount),0) AS sales
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
JOIN employee e ON e.Employee_ID = s.Employee_ID
".$where.";
";
$stmt = $conn->prepare($sqlKpi);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc() ?: $kpi;
// Tip rate
$kpi['rate'] = ((float)($kpi['sales'] ?? 0) > 0) ? (((float)($kpi['tips'] ?? 0) / (float)$kpi['sales']) * 100.0) : 0.0;

// Top performer
$topName = '';
$topTips = 0.0;
$topSales = 0.0;
foreach ($rows as $r) {
    if ((float)$r['total_tips'] >= $topTips) {
        $topTips = (float)$r['total_tips'];
        $topName = $r['Employee_Name'];
    }
    if ((float)$r['total_sales'] >= $topSales) {
        $topSales = (float)$r['total_sales'];
    }
}

?>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">📊 Reports</h2>
      <div class="muted">Compare employees, filter by date, and export PDF via Print.</div>
    </div>
    <div class="no-print" style="display:flex; gap:10px;">
      <button class="btn btn-ghost" onclick="window.print()">🖨️ Print</button>
    </div>
  </div>

  <div style="height:14px"></div>

  <form class="no-print" method="GET" style="display:grid; grid-template-columns: 1fr 1fr 1.5fr auto; gap:12px; align-items:end;">
    <div>
      <label>From</label>
      <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
    </div>
    <div>
      <label>To</label>
      <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
    </div>
    <div>
      <label>Employee</label>
      <select name="employee">
        <option value="all" <?php echo ($employee==='all')?'selected':''; ?>>All employees (compare)</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?php echo (int)$e['Employee_ID']; ?>" <?php echo ((string)$employee === (string)$e['Employee_ID'])?'selected':''; ?>>
            <?php echo htmlspecialchars($e['Employee_Name'].' ('.$e['User_Name'].')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Apply</button>
    </div>
  </form>
</div>

<div style="height:14px"></div>

<div class="grid grid-4">
  <div class="card kpi">
    <div>
      <div class="label">Total shifts (range)</div>
      <div class="value"><?php echo (int)($kpi['shifts'] ?? 0); ?></div>
    </div>
    <div class="muted"><?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?></div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Total tips (range)</div>
      <div class="value">$<?php echo htmlspecialchars(number_format((float)($kpi['tips'] ?? 0), 2)); ?></div>
    </div>
    <div class="muted">Top: <?php echo htmlspecialchars($topName ?: '—'); ?></div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Total sales (range)</div>
      <div class="value">$<?php echo htmlspecialchars(number_format((float)($kpi['sales'] ?? 0), 2)); ?></div>
    </div>
    <div class="muted">Filtered employee: <?php echo ($employee==='all') ? 'All' : htmlspecialchars((string)$employee); ?></div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Tip rate (range)</div>
      <div class="value"><?php echo htmlspecialchars(number_format((float)($kpi['rate'] ?? 0), 2)); ?>%</div>
    </div>
    <div class="muted">tips ÷ sales</div>
  </div>
</div>

<div style="height:14px"></div>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0;">Tips & Sales by Employee</h3>
    <canvas id="chartEmpBoth" height="120"></canvas>
    <div class="muted" style="margin-top:10px; font-size:12px;">Compare employees. (If you choose 1 employee, it shows a single bar.)</div>
  </div>

  <div class="card">
    <h3 style="margin-top:0;">Cash vs Electronic Tips</h3>
    <canvas id="chartMethod" height="120"></canvas>
    <div class="muted" style="margin-top:10px; font-size:12px;">Doughnut chart by tip method.</div>
  </div>
</div>

<div style="height:14px"></div>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0;">Tips by Day</h3>
    <canvas id="chartDayTips" height="100"></canvas>
  </div>
  <div class="card">
    <h3 style="margin-top:0;">Sales by Day</h3>
    <canvas id="chartDaySales" height="100"></canvas>
  </div>
</div>

<div style="height:14px"></div>

<div class="card">
  <h3 style="margin-top:0;">Totals by Employee</h3>
  <table class="table">
    <thead>
      <tr>
        <th>Employee</th>
        <th>Username</th>
        <th>Shifts</th>
        <th>Total Tips</th>
        <th>Total Sales</th>
        <th>Tip per Shift</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $sh = max(1, (int)$r['shifts']);
          $tps = ((float)$r['total_tips']) / $sh;
        ?>
        <tr>
          <td><?php echo htmlspecialchars($r['Employee_Name']); ?> <span class="muted">(#<?php echo htmlspecialchars($r['Employee_ID']); ?>)</span></td>
          <td><?php echo htmlspecialchars($r['User_Name']); ?></td>
          <td><?php echo htmlspecialchars($r['shifts']); ?></td>
          <td>$<?php echo htmlspecialchars(number_format((float)$r['total_tips'], 2)); ?></td>
          <td>$<?php echo htmlspecialchars(number_format((float)$r['total_sales'], 2)); ?></td>
          <td class="muted">$<?php echo htmlspecialchars(number_format($tps, 2)); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="muted">No data for this range.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="muted" style="margin-top:12px; font-size:12px;">
    Tip: In Chrome, Print → “Save as PDF” to export.
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const empLabels = <?php echo json_encode($chartEmpLabels); ?>;
  const empTips = <?php echo json_encode($chartEmpTips); ?>;
  const empSales = <?php echo json_encode($chartEmpSales); ?>;

  const dayLabels = <?php echo json_encode($chartDayLabels); ?>;
  const dayTips = <?php echo json_encode($chartDayTips); ?>;
  const daySales = <?php echo json_encode($chartDaySales); ?>;

  const cash = <?php echo json_encode($cash); ?>;
  const elec = <?php echo json_encode($elec); ?>;

  const peach = '#ff6b4a';
  const peach2 = '#ff8a72';
  const dark = '#111827';

  new Chart(document.getElementById('chartEmpBoth'), {
    type: 'bar',
    data: {
      labels: empLabels,
      datasets: [
        {
          label: 'Tips ($)',
          data: empTips,
          backgroundColor: 'rgba(255,107,74,.35)',
          borderColor: peach,
          borderWidth: 1,
          borderRadius: 10,
        },
        {
          label: 'Sales ($)',
          data: empSales,
          backgroundColor: 'rgba(17,24,39,.20)',
          borderColor: dark,
          borderWidth: 1,
          borderRadius: 10,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true } }
    }
  });

  new Chart(document.getElementById('chartDayTips'), {
    type: 'line',
    data: {
      labels: dayLabels,
      datasets: [{
        label: 'Tips ($)',
        data: dayTips,
        borderColor: peach,
        backgroundColor: 'rgba(255,107,74,.20)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  new Chart(document.getElementById('chartDaySales'), {
    type: 'line',
    data: {
      labels: dayLabels,
      datasets: [{
        label: 'Sales ($)',
        data: daySales,
        borderColor: dark,
        backgroundColor: 'rgba(17,24,39,.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  new Chart(document.getElementById('chartMethod'), {
    type: 'doughnut',
    data: {
      labels: ['Cash', 'Electronic'],
      datasets: [{
        data: [cash, elec],
        backgroundColor: [peach, dark],
        borderWidth: 0,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      cutout: '65%'
    }
  });
</script>

<?php require_once "footer.php"; ?>
