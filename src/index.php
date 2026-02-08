<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ensure the user is logged in AND the role is set
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$role = (string)($_SESSION['role'] ?? ''); // 101=Manager/Admin, 102=Employee
$message = "";
$messageType = "";

// Find active shift in DB (real-time truth)
$currentShift = null;
$stmt = $conn->prepare("SELECT Shift_ID, Start_Time FROM shift WHERE Employee_ID = ? AND End_Time IS NULL ORDER BY Shift_ID DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['id']);
if ($stmt->execute()) {
    $currentShift = $stmt->get_result()->fetch_assoc();
}
$current_shift_id = $currentShift['Shift_ID'] ?? "";
$current_shift_start = $currentShift['Start_Time'] ?? "";

// Handle POST actions (employee)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Start Shift (only if no active shift)
    if (isset($_POST['start_shift'])) {
        if ($current_shift_id) {
            $message = "You already have an active shift (#$current_shift_id).";
            $messageType = "error";
        } else {
            $start_time = date("Y-m-d H:i:s");
            $stmt = $conn->prepare("INSERT INTO shift (Employee_ID, Start_Time, Sale_Amount) VALUES (?, ?, 0.00)");
            $stmt->bind_param("is", $_SESSION['id'], $start_time);
            if ($stmt->execute()) {
                $current_shift_id = $conn->insert_id;
                $current_shift_start = $start_time;
                $message = "Shift started at $start_time (Shift #$current_shift_id).";
                $messageType = "success";
            } else {
                $message = "Error starting shift: " . $conn->error;
                $messageType = "error";
            }
        }
    }

    // Stop Shift
    if (isset($_POST['stop_shift'])) {
        if (!$current_shift_id) {
            $message = "No active shift found.";
            $messageType = "error";
        } else {
            $end_time = date("Y-m-d H:i:s");
            $stmt = $conn->prepare("UPDATE shift SET End_Time = ? WHERE Shift_ID = ?");
            $stmt->bind_param("si", $end_time, $current_shift_id);
            if ($stmt->execute()) {
                $message = "Shift ended at $end_time (Shift #$current_shift_id).";
                $messageType = "success";
                $current_shift_id = "";
                $current_shift_start = "";
            } else {
                $message = "Error stopping shift: " . $conn->error;
                $messageType = "error";
            }
        }
    }

    // Submit Tip
    if (isset($_POST['submit_tip'])) {
        if (!$current_shift_id) {
            $message = "Start a shift before submitting tips.";
            $messageType = "error";
        } else {
            $tip_amount = (float)($_POST['tip_amount'] ?? 0);
            $sale_amount = (float)($_POST['sale_amount'] ?? 0);
            $is_cash = (int)($_POST['is_cash'] ?? 1);

            $stmt = $conn->prepare("INSERT INTO tip (Shift_ID, Tip_Amount, Is_It_Cash) VALUES (?, ?, ?)");
            $stmt->bind_param("idi", $current_shift_id, $tip_amount, $is_cash);
            if ($stmt->execute()) {
                $upd = $conn->prepare("UPDATE shift SET Sale_Amount = Sale_Amount + ? WHERE Shift_ID = ?");
                $upd->bind_param("di", $sale_amount, $current_shift_id);
                $upd->execute();

                $message = "Tip submitted.";
                $messageType = "success";
            } else {
                $message = "Error submitting tip: " . $conn->error;
                $messageType = "error";
            }
        }
    }
}

require_once "header.php";

// Employee recent tips
$recentTips = [];
if ($role === '102') {
    $stmt = $conn->prepare(
        "SELECT t.Tip_ID, t.Shift_ID, t.Tip_Amount, s.Sale_Amount, t.Is_It_Cash
         FROM tip t
         JOIN shift s ON s.Shift_ID = t.Shift_ID
         WHERE s.Employee_ID = ?
         ORDER BY t.Tip_ID DESC
         LIMIT 10"
    );
    $stmt->bind_param("i", $_SESSION['id']);
    if ($stmt->execute()) {
        $recentTips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Admin KPIs
$kpiActive = 0;
$kpiTipsToday = 0.0;
$kpiSalesToday = 0.0;
if ($role === '101') {
    $res = $conn->query("SELECT COUNT(*) AS c FROM shift WHERE End_Time IS NULL");
    if ($res) $kpiActive = (int)($res->fetch_assoc()['c'] ?? 0);

    // Use MySQL CURDATE() (timezone set in db_config.php) to avoid UTC vs local date mismatch
    $res = $conn->query("SELECT COALESCE(SUM(t.Tip_Amount),0) AS tips
                         FROM tip t JOIN shift s ON s.Shift_ID=t.Shift_ID
                         WHERE DATE(s.Start_Time)=CURDATE()");
    if ($res) $kpiTipsToday = (float)($res->fetch_assoc()['tips'] ?? 0);

    $res = $conn->query("SELECT COALESCE(SUM(Sale_Amount),0) AS sales
                         FROM shift
                         WHERE DATE(Start_Time)=CURDATE()");
    if ($res) $kpiSalesToday = (float)($res->fetch_assoc()['sales'] ?? 0);
}
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($role === '101'): ?>

  <div class="grid grid-3">
    <div class="card kpi">
      <div>
        <div class="label">Active shifts (live)</div>
        <div class="value" data-kpi-active-shifts><?php echo (int)$kpiActive; ?></div>
      </div>
      <div class="muted">updates every 5s</div>
    </div>

    <div class="card kpi">
      <div>
        <div class="label">Tips today</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($kpiTipsToday, 2)); ?></div>
      </div>
      <div class="muted">based on shift start date</div>
    </div>

    <div class="card kpi">
      <div>
        <div class="label">Sales today</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($kpiSalesToday, 2)); ?></div>
      </div>
      <div class="muted">sum of shifts</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
      <div>
        <h2 style="margin:0;">🛠️ Admin Dashboard</h2>
        <div class="muted">Active shifts appear here automatically when employees start a shift.</div>
      </div>
      <div class="no-print" style="display:flex; gap:10px;">
        <a class="btn btn-primary" href="reports.php" style="text-decoration:none;">Open Reports</a>
      </div>
    </div>

    <div style="height:12px"></div>

    <table class="table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Username</th>
          <th>Start time</th>
          <th>Duration</th>
          <th>Shift</th>
        </tr>
      </thead>
      <tbody data-active-shifts-body>
        <tr><td colspan="5" class="muted">Loading…</td></tr>
      </tbody>
    </table>
  </div>

<?php else: ?>

  <div class="grid grid-2">
    <div class="card">
      <h3 style="margin-top:0;">Shift</h3>
      <p class="muted" style="margin-top:0;">
        Status:
        <?php if ($current_shift_id): ?>
          <strong>Active</strong> (Shift #<?php echo htmlspecialchars($current_shift_id); ?>)
          <br />
          Started: <strong><?php echo htmlspecialchars($current_shift_start); ?></strong>
          <br />
          Duration: <strong><span data-start-iso="<?php echo htmlspecialchars(date('c', strtotime($current_shift_start ?: 'now'))); ?>">00:00:00</span></strong>
        <?php else: ?>
          <strong>Not started</strong>
        <?php endif; ?>
      </p>

      <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if (!$current_shift_id): ?>
          <button class="btn btn-primary" type="submit" name="start_shift" value="1">▶ Start Shift</button>
        <?php else: ?>
          <button class="btn btn-secondary" type="submit" name="stop_shift" value="1">■ End Shift</button>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-top:0;">Log Tip</h3>
      <form method="POST">
        <label>Tip Amount ($)</label>
        <input type="number" step="0.01" name="tip_amount" required />

        <label>Total Sales ($)</label>
        <input type="number" step="0.01" name="sale_amount" required />

        <label>Payment Type</label>
        <select name="is_cash">
          <option value="1">Cash</option>
          <option value="0">Electronic (Card)</option>
        </select>

        <div style="margin-top:12px;">
          <button class="btn btn-primary" type="submit" name="submit_tip" value="1">Submit Tip</button>
        </div>
      </form>
      <div class="muted" style="margin-top:10px; font-size:12px;">Tip logging is tied to your active shift in the database.</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <h3 style="margin-top:0;">Recent Tips</h3>
    <?php if (empty($recentTips)): ?>
      <p class="muted">No tips recorded yet.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Tip ID</th>
            <th>Shift ID</th>
            <th>Tip Amount</th>
            <th>Sales Amount</th>
            <th>Method</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTips as $t): ?>
            <tr>
              <td><?php echo htmlspecialchars($t['Tip_ID']); ?></td>
              <td><?php echo htmlspecialchars($t['Shift_ID']); ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)$t['Tip_Amount'], 2)); ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)$t['Sale_Amount'], 2)); ?></td>
              <td><?php echo ((int)$t['Is_It_Cash'] === 1) ? 'Cash' : 'Electronic'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once "footer.php"; ?>
