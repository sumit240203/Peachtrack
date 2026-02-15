<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Managers/Admins only
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: index.php');
    exit;
}

require_once "header.php";

// Range presets: day | week | month | custom
$range = $_GET['range'] ?? 'week';
$today = date('Y-m-d');

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';

if ($range === 'day') {
    $from = $today;
    $to = $today;
} elseif ($range === 'month') {
    $from = date('Y-m-01');
    $to = $today;
} elseif ($range === 'custom') {
    $from = $from ?: date('Y-m-d', strtotime('-6 days'));
    $to = $to ?: $today;
} else {
    // week default = last 7 days including today
    $range = 'week';
    $from = date('Y-m-d', strtotime('-6 days'));
    $to = $today;
}

$mode = $_GET['mode'] ?? 'unpaid'; // unpaid | paid | all

$hasTipTime = peachtrack_has_column($conn, 'tip', 'Tip_Time');
$hasPayPeriod = peachtrack_has_column($conn, 'tip', 'Pay_Period_ID');
$hasPaidAt = peachtrack_has_column($conn, 'tip', 'Paid_At');

// Choose the best date field for tips
$tipDateExpr = $hasTipTime ? 'DATE(t.Tip_Time)' : 'DATE(s.Start_Time)';

$paidFilter = "";
if ($hasPayPeriod) {
    if ($mode === 'unpaid') $paidFilter = " AND t.Pay_Period_ID IS NULL ";
    elseif ($mode === 'paid') $paidFilter = " AND t.Pay_Period_ID IS NOT NULL ";
}

// Payroll summary by employee
$sql = "
SELECT e.Employee_ID,
       e.Employee_Name,
       e.User_Name,
       COALESCE(SUM(t.Tip_Amount),0) AS total_tips,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS cash_tips,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS elec_tips,
       COALESCE(SUM(t.Sale_Amount),0) AS total_sales,
       COUNT(t.Tip_ID) AS tip_count
FROM employee e
JOIN shift s ON s.Employee_ID = e.Employee_ID
JOIN tip t ON t.Shift_ID = s.Shift_ID
WHERE {$tipDateExpr} BETWEEN ? AND ?
  AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)
  {$paidFilter}
GROUP BY e.Employee_ID, e.Employee_Name, e.User_Name
ORDER BY total_tips DESC;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="peachtrack_payroll_'.$from.'_to_'.$to.'_'.$mode.'.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee', 'Username', 'Period From', 'Period To', 'Total Tips', 'Cash Tips', 'Electronic Tips', 'Total Sales', 'Tip Count', 'Status']);
    foreach ($rows as $r) {
        $status = 'n/a';
        if ($hasPayPeriod) {
            $status = ($mode === 'paid') ? 'paid' : (($mode === 'unpaid') ? 'unpaid' : 'mixed');
        }
        fputcsv($out, [
            $r['Employee_Name'],
            $r['User_Name'],
            $from,
            $to,
            number_format((float)$r['total_tips'], 2, '.', ''),
            number_format((float)$r['cash_tips'], 2, '.', ''),
            number_format((float)$r['elec_tips'], 2, '.', ''),
            number_format((float)$r['total_sales'], 2, '.', ''),
            (int)$r['tip_count'],
            $status
        ]);
    }
    fclose($out);
    exit;
}

$message = '';
$messageType = '';

// Mark period as paid (creates a pay period row + links tips)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    if (!$hasPayPeriod) {
        $message = 'Payroll tracking requires a DB update (add tip.Pay_Period_ID / Paid_At / Paid_By). Run sql/alter_tip_payroll.sql in phpMyAdmin.';
        $messageType = 'error';
    } else {
        $paidBy = (int)($_SESSION['id'] ?? 0);
        $paidAt = date('Y-m-d H:i:s');

        // Create pay period record if table exists; otherwise just stamp tips.
        $payPeriodId = null;
        if ($conn->query("SHOW TABLES LIKE 'tip_pay_period'")?->num_rows) {
            $stmtPP = $conn->prepare("INSERT INTO tip_pay_period (Period_Start, Period_End, Paid_At, Paid_By) VALUES (?,?,?,?)");
            $stmtPP->bind_param('sssi', $from, $to, $paidAt, $paidBy);
            if ($stmtPP->execute()) {
                $payPeriodId = (int)$conn->insert_id;
            }
        }

        $conn->begin_transaction();
        try {
            $sqlUpd = "
UPDATE tip t
JOIN shift s ON s.Shift_ID = t.Shift_ID
SET t.Pay_Period_ID = ?, t.Paid_At = ?, t.Paid_By = ?
WHERE {$tipDateExpr} BETWEEN ? AND ?
  AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)
  AND t.Pay_Period_ID IS NULL;
";
            $stmtUpd = $conn->prepare($sqlUpd);
            $ppid = $payPeriodId; // can be null
            $stmtUpd->bind_param('ssiss', $ppid, $paidAt, $paidBy, $from, $to);
            $stmtUpd->execute();

            $conn->commit();
            $message = 'Marked unpaid tips in this range as PAID.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Error marking paid: '.$e->getMessage();
            $messageType = 'error';
        }
    }
}

?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">💵 Payroll / Tip Payout</h2>
      <div class="muted">Generate weekly/biweekly totals and export for paycheques.</div>
    </div>
    <div class="no-print" style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn btn-ghost" href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" style="text-decoration:none;">⬇️ Export CSV</a>
    </div>
  </div>

  <div style="height:14px"></div>

  <form class="no-print" method="GET" style="display:grid; grid-template-columns: 1.1fr 1fr 1fr 1fr auto; gap:12px; align-items:end;">
    <div>
      <label>Range</label>
      <select name="range" onchange="this.form.submit()">
        <option value="day" <?php echo ($range==='day')?'selected':''; ?>>Today</option>
        <option value="week" <?php echo ($range==='week')?'selected':''; ?>>Last 7 days</option>
        <option value="month" <?php echo ($range==='month')?'selected':''; ?>>This month</option>
        <option value="custom" <?php echo ($range==='custom')?'selected':''; ?>>Custom</option>
      </select>
    </div>

    <div>
      <label>From</label>
      <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" <?php echo ($range==='custom')?'':'disabled'; ?> />
    </div>

    <div>
      <label>To</label>
      <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" <?php echo ($range==='custom')?'':'disabled'; ?> />
    </div>

    <div>
      <label>Status</label>
      <select name="mode" onchange="this.form.submit()">
        <option value="unpaid" <?php echo ($mode==='unpaid')?'selected':''; ?>>Unpaid only</option>
        <option value="paid" <?php echo ($mode==='paid')?'selected':''; ?>>Paid only</option>
        <option value="all" <?php echo ($mode==='all')?'selected':''; ?>>All</option>
      </select>
    </div>

    <div>
      <button class="btn btn-primary" type="submit" <?php echo ($range==='custom')?'':'disabled'; ?>>Apply</button>
    </div>
  </form>

  <div class="muted" style="margin-top:10px; font-size:12px;">Tip: choose <strong>Custom</strong> for weekly or biweekly payroll ranges.</div>

  <div style="height:14px"></div>

  <table class="table">
    <thead>
      <tr>
        <th>Employee</th>
        <th>Username</th>
        <th>Total Tips</th>
        <th>Cash</th>
        <th>Electronic</th>
        <th>Total Sales</th>
        <th>Tips Count</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">No tip entries found for this range/status.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['Employee_Name']); ?></td>
            <td><?php echo htmlspecialchars($r['User_Name']); ?></td>
            <td>$<?php echo htmlspecialchars(number_format((float)$r['total_tips'],2)); ?></td>
            <td class="muted">$<?php echo htmlspecialchars(number_format((float)$r['cash_tips'],2)); ?></td>
            <td class="muted">$<?php echo htmlspecialchars(number_format((float)$r['elec_tips'],2)); ?></td>
            <td>$<?php echo htmlspecialchars(number_format((float)$r['total_sales'],2)); ?></td>
            <td class="muted"><?php echo (int)$r['tip_count']; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="height:14px"></div>

  <form method="POST" class="no-print">
    <input type="hidden" name="mark_paid" value="1" />
    <button class="btn btn-secondary" type="submit" onclick="return confirm('Mark all unpaid tips in this date range as PAID? This is for payroll.')">
      Mark this range as Paid
    </button>
    <div class="muted" style="margin-top:10px; font-size:12px;">
      This will link tips to a pay period and prevent double-paying next week.
    </div>
  </form>
</div>

<?php require_once "footer.php"; ?>
