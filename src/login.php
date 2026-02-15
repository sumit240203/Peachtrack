<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // If Is_Active column exists, block deactivated accounts. Fallback gracefully if DB hasn't been migrated yet.
    $sql = "SELECT e.Employee_ID, e.Employee_Name, e.Type_Code, c.Password
            FROM employee e
            JOIN credential c ON e.Employee_ID = c.Employee_ID
            WHERE e.User_Name = ?
              AND (e.Is_Active IS NULL OR e.Is_Active = 1)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Fallback for older schema (no Is_Active column)
        $sql = "SELECT e.Employee_ID, e.Employee_Name, e.Type_Code, c.Password
                FROM employee e
                JOIN credential c ON e.Employee_ID = c.Employee_ID
                WHERE e.User_Name = ?";
        $stmt = $conn->prepare($sql);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['Password'])) {
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $user['Employee_ID'];
            $_SESSION['name'] = $user['Employee_Name'];
            $_SESSION['role'] = $user['Type_Code'];
            header("Location: index.php");
            exit;
        }
    }
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | PeachTrack</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    :root{
      --primary:#ff6b4a;
      --primary2:#ff8a72;
      --text:#111827;
      --muted:#6b7280;
      --border:rgba(17,24,39,.12);
      --shadow:0 18px 50px rgba(17,24,39,.18);
    }

    body{
      margin:0;
      min-height:100vh;
      display:grid;
      place-items:center;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:var(--text);

      /* Use the RIGHT side of the image (your blur is on the right) */
      background:
        radial-gradient(1000px 700px at 15% 10%, rgba(255,107,74,.18), transparent 58%),
        radial-gradient(900px 600px at 90% 0%, rgba(255,138,114,.14), transparent 60%),
        linear-gradient(135deg, rgba(17,24,39,.28), rgba(17,24,39,.10)),
        url('assets/img/login-bg2.jpg');
      background-repeat:no-repeat;
      background-size: cover;
      background-position: center;
      background-attachment: scroll;
      padding: 28px;
    }

    .wrap{width:100%; max-width: 980px; display:grid; grid-template-columns: 1fr 0.92fr; gap:18px; align-items:stretch;}

    .panel{
      border-radius: 22px;
      overflow:hidden;
      position:relative;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255,255,255,.25);
      min-height: 420px;
    }

    /* left brand panel: subtle, not huge */
    .brand-panel{
      background: rgba(255,255,255,.10);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    .brand-inner{
      height:100%;
      padding: 24px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      color:#fff;
      position:relative;
    }
    .brand-row{display:flex; align-items:center; gap:12px;}
    .badge{width:46px; height:46px; border-radius: 16px; display:grid; place-items:center;
      background: linear-gradient(135deg, var(--primary), var(--primary2));
      box-shadow: 0 14px 35px rgba(0,0,0,.25);
      font-size: 22px;
    }
    .brand-row h1{margin:0; font-size:18px; letter-spacing:.2px;}
    .brand-row p{margin:0; opacity:.85; font-size:12px;}

    .copy h2{margin:0 0 10px 0; font-size:22px;}
    .copy p{margin:0; opacity:.9; line-height:1.5;}

    /* right login card sits on the blurred part of the image */
    .login-card{
      background: rgba(255,255,255,.10);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,.25);
      padding: 22px;
      display:flex;
      flex-direction:column;
      justify-content:center;
      color:#fff;
    }

    .title{margin:0 0 6px 0; font-size: 20px; font-weight: 900; color: #fff;}
    .subtitle{margin:0 0 16px 0; color: rgba(255,255,255,.82); font-size: 13px;}

    label{display:block; font-weight:800; font-size: 13px; margin: 10px 0 6px;}
    input{
      width:100%;
      padding: 12px 12px;
      border-radius: 14px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,.90);
      outline:none;
    }
    input:focus{border-color: rgba(255,107,74,.55); box-shadow: 0 0 0 4px rgba(255,107,74,.15);}

    .btn{
      width:100%;
      margin-top: 14px;
      padding: 12px 14px;
      border-radius: 14px;
      border: 0;
      cursor:pointer;
      font-weight: 900;
      color:#fff;
      background: linear-gradient(135deg, var(--primary), var(--primary2));
      box-shadow: 0 16px 35px rgba(255,107,74,.25);
    }

    .alert{padding:10px 12px; border-radius: 14px; border:1px solid rgba(17,24,39,.12); margin-bottom: 12px;}
    .alert.error{background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.22);}
    .alert.success{background: rgba(16,185,129,.10); border-color: rgba(16,185,129,.22);}

    .help{margin-top: 14px; font-size: 12px; color: rgba(255,255,255,.82); line-height:1.5;}

    @media(max-width: 920px){
      .wrap{grid-template-columns: 1fr;}
      .panel{min-height: 240px;}
    }
  </style>
</head>
<body>

  <div class="wrap">

    <section class="panel brand-panel" aria-label="PeachTrack">
      <div class="brand-inner">
        <div class="brand-row">
          <div class="badge">🍑</div>
          <div>
            <h1>PeachTrack</h1>
            <p>Fuzzy Peach Wax & Beauty Bar</p>
          </div>
        </div>

        <div class="copy">
          <h2>Shifts. Tips. Reports.</h2>
          <p>Fast tracking for employees and clean reporting for managers.</p>
        </div>

        <div style="opacity:.85; font-size:12px;">Secure login • role-based access • live shift view</div>
      </div>
    </section>

    <section class="panel login-card" aria-label="Login">
      <h3 class="title">Sign in</h3>
      <p class="subtitle">Use your PeachTrack account to continue.</p>

      <?php if(isset($_GET['registered'])): ?>
        <div class="alert success">Registration successful! Please login.</div>
      <?php endif; ?>

      <?php if(isset($error)): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="login.php" method="POST">
        <label>Username</label>
        <input type="text" name="username" required placeholder="e.g. mandeep@peach" autocomplete="username" />

        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password" />

        <button type="submit" class="btn">Login</button>

        <div class="help"><strong>New to PeachTrack?</strong><br />Ask your <strong>Manager/Admin</strong> to create your account.</div>
      </form>
    </section>

  </div>

</body>
</html>
