<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';
$req_id = (int)($_GET['request_id'] ?? 0);

if ($req_id <= 0) {
    http_response_code(400);
    echo 'Invalid request id.';
    exit();
}

$where_access = ($user_role === 'admin')
    ? "cr.id = $req_id"
    : "cr.id = $req_id AND cr.student_id = $user_id";

$q = "
    SELECT cr.id, cr.status, cr.semester, cr.school_year, cr.course, cr.created_at,
           u.name, u.student_id, u.year_level
    FROM clearance_requests cr
    JOIN users u ON u.id = cr.student_id
    WHERE $where_access
    LIMIT 1
";
$res = mysqli_query($conn, $q);
if (!$res || mysqli_num_rows($res) !== 1) {
    http_response_code(404);
    echo 'Request not found.';
    exit();
}

$req = mysqli_fetch_assoc($res);
$items = [];
$items_res = mysqli_query(
    $conn,
    "SELECT office, status, reviewed_at
     FROM clearance_items
     WHERE request_id=$req_id
     ORDER BY FIELD(office,'library','mis','toolroom','cashier','tvet_coordinator','tvet_director')"
);
if ($items_res) {
    while ($it = mysqli_fetch_assoc($items_res)) {
        $items[] = $it;
    }
}

$all_approved = true;
$date_cleared = null;
foreach ($items as $it) {
    if (($it['status'] ?? '') !== 'approved') {
        $all_approved = false;
    }
    $rt = $it['reviewed_at'] ?? null;
    if ($rt && ($date_cleared === null || strtotime($rt) > strtotime($date_cleared))) {
        $date_cleared = $rt;
    }
}

if (($req['status'] ?? '') !== 'approved' || !$all_approved) {
    http_response_code(403);
    echo 'Clearance is not fully approved yet.';
    exit();
}

$office_map = [
    'library' => 'Library',
    'toolroom' => 'Tool Room Officer (DHT)',
    'mis' => 'MIS Officer (DIT)',
    'cashier' => 'Cashier',
    'tvet_coordinator' => 'TVET Coordinator',
    'tvet_director' => 'TVET Director'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clearance Certificate</title>
  <style>
    :root{--ink:#1e293b;--deep:#162a5f;--line:#9fb3d5;--panel:#f8fbff;--soft:#eaf1fb;--ok:#177337;}
    *{box-sizing:border-box}
    body{
      margin:0;
      padding:18px;
      font-family:Cambria, "Times New Roman", serif;
      color:var(--ink);
      background:
        radial-gradient(circle at 12% 20%, rgba(148,163,184,.16) 0 2px, transparent 3px) 0 0/22px 22px,
        linear-gradient(135deg,#eef3fa,#e6edf8 50%,#edf3fb);
      min-height:100vh;
    }
    .sheet{
      width:min(960px,100%);
      margin:0 auto;
      background:
        linear-gradient(transparent,transparent),
        repeating-linear-gradient(0deg, transparent 0 26px, rgba(148,163,184,.08) 26px 27px),
        repeating-linear-gradient(90deg, transparent 0 38px, rgba(148,163,184,.07) 38px 39px);
      border:2px solid #b8c7df;
      border-radius:10px;
      box-shadow:0 12px 28px rgba(15,23,42,.14);
      padding:8px 8px 12px;
      position:relative;
      overflow:hidden;
    }
    .sheet::before,.sheet::after{
      content:"";
      position:absolute;
      pointer-events:none;
      border:1px solid rgba(100,116,139,.22);
      border-radius:50%;
    }
    .sheet::before{width:220px;height:220px;left:-110px;top:26px}
    .sheet::after{width:220px;height:220px;right:-120px;bottom:12px}
    .top-accent{height:3px;background:#44b7bf;width:43%;margin-bottom:4px;border-radius:2px}
    .logo{
      width:54px;height:54px;margin:-4px auto 2px;border-radius:50%;
      border:1px solid #9aa8bf;background:radial-gradient(circle at 30% 30%,#fff,#dbe3ef);
      color:#4b5563;font-size:11px;font-weight:700;line-height:1.05;
      display:flex;align-items:center;justify-content:center;text-align:center;
      box-shadow:inset 0 0 0 2px #eef2f8;
      font-family:"Plus Jakarta Sans",Arial,sans-serif;
    }
    .title{text-align:center}
    h1{margin:0;color:var(--deep);font-size:52px;line-height:.98;font-weight:700}
    .sub{margin-top:2px;font-size:22px;color:#334155;font-family:Georgia,serif}
    .top-row{margin-top:8px;display:grid;grid-template-columns:2.8fr 1fr;gap:6px}
    .box{
      background:rgba(255,255,255,.82);
      border:1px solid #b6c5dd;
      border-radius:4px;
      padding:7px 10px;
    }
    .box-title{
      font-family:"Plus Jakarta Sans",Arial,sans-serif;
      font-size:12px;
      font-weight:800;
      color:#1f2937;
      letter-spacing:.03em;
      text-transform:uppercase;
      margin-bottom:6px;
    }
    .student-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px 14px}
    .field{display:flex;align-items:center;gap:7px;font-family:"Plus Jakarta Sans",Arial,sans-serif}
    .ico{color:#64748b;font-size:14px;width:14px;text-align:center}
    .value{font-size:14px;font-weight:600;color:#1f2937}
    .status-box{text-align:center;display:flex;flex-direction:column;justify-content:center}
    .cleared{
      display:inline-block;
      margin:0 auto;
      min-width:154px;
      padding:8px 20px 7px;
      border-radius:999px;
      background:linear-gradient(180deg,#2ea44f,#166534);
      color:#fff;
      font-size:35px;
      font-weight:700;
      letter-spacing:.03em;
      line-height:1;
      box-shadow:0 2px 0 #14532d,inset 0 0 0 2px rgba(255,255,255,.35);
    }
    .meta{margin-top:6px;font-family:"Plus Jakarta Sans",Arial,sans-serif;color:#334155}
    .meta .d{font-size:23px;font-weight:700;line-height:1.1}
    .meta .s{font-size:21px;font-weight:700;line-height:1.1;margin-top:2px}
    .table-wrap{margin-top:6px;border:1px solid #92a9ca;border-radius:4px;overflow:hidden}
    table{width:100%;border-collapse:collapse;font-family:"Plus Jakarta Sans",Arial,sans-serif}
    th,td{padding:7px 9px;text-align:left;font-size:13px;border-right:1px solid #b7c7dd}
    th:last-child,td:last-child{border-right:none}
    thead th{
      background:#c9d8f1;
      color:#1f2937;
      font-weight:800;
      border-bottom:1px solid #92a9ca;
    }
    tbody tr:nth-child(odd){background:#f8fbff}
    tbody tr:nth-child(even){background:#e7effb}
    tbody td{border-top:1px solid #cad7ea}
    .ok{display:inline-flex;align-items:center;gap:6px;color:var(--ok);font-weight:700}
    .save-wrap{margin-top:8px;display:flex;justify-content:center}
    .save-btn{
      border:1px solid #224b83;
      border-radius:999px;
      padding:8px 26px;
      font-family:"Plus Jakarta Sans",Arial,sans-serif;
      font-size:12px;
      font-weight:800;
      color:#fff;
      background:linear-gradient(180deg,#2a5da0,#1f4072);
      cursor:pointer;
      box-shadow:0 3px 8px rgba(30,58,138,.25);
    }
    @media (max-width:920px){
      h1{font-size:36px}
      .sub{font-size:18px}
      .top-row{grid-template-columns:1fr}
      .student-grid{grid-template-columns:1fr}
      .cleared{font-size:30px}
      .meta .d,.meta .s{font-size:18px}
    }
    @media print{
      body{padding:0;background:#fff}
      .sheet{width:100%;border:none;box-shadow:none;border-radius:0}
      .save-wrap{display:none}
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="top-accent"></div>
    <div class="logo">Asian<br>College</div>
    <div class="title">
      <h1>Clearance Certificate</h1>
      <div class="sub">Diploma Program Clearance System</div>
    </div>

    <div class="top-row">
      <div class="box">
        <div class="box-title">Student Information</div>
        <div class="student-grid">
          <div class="field"><span class="ico">&#128100;</span><span class="value"><?= htmlspecialchars($req['name']) ?></span></div>
          <div class="field"><span class="ico">&#128179;</span><span class="value"><?= htmlspecialchars($req['student_id'] ?? '-') ?></span></div>
          <div class="field"><span class="ico">&#128218;</span><span class="value"><?= htmlspecialchars($req['course']) ?></span></div>
          <div class="field"><span class="ico">&#128200;</span><span class="value">Year <?= (int)($req['year_level'] ?? 0) ?></span></div>
        </div>
      </div>
      <div class="box status-box">
        <div class="cleared">CLEARED</div>
        <div class="meta">
          <div class="d"><?= $date_cleared ? date('M j, Y', strtotime($date_cleared)) : date('M j, Y') ?></div>
          <div class="s"><?= htmlspecialchars($req['semester']) ?></div>
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Office</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= htmlspecialchars($office_map[$it['office']] ?? $it['office']) ?></td>
            <td><span class="ok">&#9989; Approved</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="save-wrap">
      <button type="button" class="save-btn" onclick="window.print()">SAVE CERTIFICATE</button>
    </div>
  </div>
</body>
</html>

