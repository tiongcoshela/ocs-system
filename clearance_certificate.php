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
           u.name, u.email, u.student_id, u.year_level
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
$items_res = mysqli_query($conn, "SELECT office, status, remarks, reviewed_at FROM clearance_items WHERE request_id=$req_id ORDER BY FIELD(office,'library','mis','toolroom','cashier','tvet_coordinator','tvet_director')");
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
    body { font-family: Arial, sans-serif; background:#f8fafc; color:#0f172a; margin:0; padding:24px; }
    .sheet { max-width:900px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; }
    h1 { margin:0 0 6px 0; color:#1e3a8a; }
    .sub { color:#475569; margin-bottom:18px; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 18px; margin-bottom:16px; }
    .k { font-size:12px; color:#64748b; }
    .v { font-weight:700; }
    table { width:100%; border-collapse:collapse; margin-top:8px; }
    th, td { border:1px solid #e2e8f0; padding:10px; text-align:left; font-size:14px; }
    th { background:#eff6ff; color:#1e3a8a; }
    .ok { color:#166534; font-weight:700; }
    .print { margin-top:16px; }
    @media print { .print { display:none; } body { padding:0; background:#fff; } .sheet { border:none; } }
  </style>
</head>
<body>
  <div class="sheet">
    <h1>Clearance Certificate</h1>
    <div class="sub">Diploma Program Clearance System</div>
    <div class="grid">
      <div><div class="k">Student Name</div><div class="v"><?= htmlspecialchars($req['name']) ?></div></div>
      <div><div class="k">Student ID</div><div class="v"><?= htmlspecialchars($req['student_id'] ?? '-') ?></div></div>
      <div><div class="k">Course</div><div class="v"><?= htmlspecialchars($req['course']) ?></div></div>
      <div><div class="k">Year Level</div><div class="v"><?= (int)($req['year_level'] ?? 0) ?></div></div>
      <div><div class="k">Semester</div><div class="v"><?= htmlspecialchars($req['semester']) ?></div></div>
      <div><div class="k">School Year</div><div class="v"><?= htmlspecialchars($req['school_year']) ?></div></div>
      <div><div class="k">Clearance Status</div><div class="v ok">Cleared</div></div>
      <div><div class="k">Date Cleared</div><div class="v"><?= $date_cleared ? date('F j, Y', strtotime($date_cleared)) : date('F j, Y') ?></div></div>
    </div>

    <table>
      <thead><tr><th>Office</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($office_map[$it['office']] ?? $it['office']) ?></td>
          <td><?= ucfirst(str_replace('_', ' ', htmlspecialchars($it['status']))) ?></td>
          <td><?= htmlspecialchars($it['remarks'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <button class="print" onclick="window.print()">Print / Save as PDF</button>
  </div>
</body>
</html>


