<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Require login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once "header.php";
?>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">ℹ️ About PeachTrack</h2>
      <div class="muted">Shift, tips, and reporting dashboard</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="grid" style="gap:12px;">
    <div class="card" style="margin:0;">
      <h3 style="margin-top:0;">Team</h3>
      <p class="muted" style="margin:0;">
        Team name: <strong>Cyber Dominion</strong><br />
        Program: <strong>Computer Information Technology</strong><br />
        Institution: <strong>Lethbridge College</strong><br />
        Year: <strong>2026</strong>
      </p>
      <div style="height:10px"></div>
      <div class="muted" style="font-size:12px; font-weight:800; letter-spacing:.06em; text-transform:uppercase;">Team members</div>
      <ol class="muted" style="margin:8px 0 0; padding-left:18px;">
        <li>Sumit Niveriya</li>
        <li>Mandeep Kaur</li>
        <li>Eliser Roluna</li>
        <li>Fidelis Fabowale</li>
        <li>Felix Ernest Eshun</li>
      </ol>
    </div>

    <div class="card" style="margin:0;">
      <h3 style="margin-top:0;">Purpose</h3>
      <p class="muted" style="margin:0;">
        PeachTrack is a role-based web app for a salon/beauty bar workflow.
        Employees can start/end shifts and log tips, while managers monitor active shifts and generate reports.
      </p>
    </div>

    <div class="card" style="margin:0;">
      <h3 style="margin-top:0;">Tech</h3>
      <ul class="muted" style="margin:0; padding-left:18px;">
        <li>PHP + MySQL</li>
        <li>MAMP (local development)</li>
        <li>Chart.js (reports)</li>
      </ul>
      <div style="height:12px"></div>
      <hr style="border:none; border-top:1px solid rgba(17,24,39,.10); margin:12px 0;" />
      <div class="muted" style="font-size:12px;">
        © 2026 <strong>Cyber Dominion</strong>. All rights reserved. PeachTrack is an academic project created for
        <strong>Computer Information Technology</strong>, Lethbridge College.
      </div>
    </div>
  </div>
</div>

<?php require_once "footer.php"; ?>
