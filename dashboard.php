<?php
// BACKEND
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

$user_id    = (int)$_SESSION['user_id'];
$user_name  = $_SESSION['user_name'] ?? '';
$user_role  = $_SESSION['user_role'] ?? '';
$year_level = (int)($_SESSION['year_level'] ?? 0);
$course = $_SESSION['course'] ?? 'DIT';
$course = strtoupper(trim((string)$course));
$first_name = explode(' ', trim($user_name))[0] ?: 'User';
$initials   = strtoupper(substr($user_name, 0, 1) . (strpos($user_name, ' ') ? substr($user_name, strpos($user_name, ' ') + 1, 1) : ''));

if ($user_role === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$office_labels = [
    'library'          => ['Library', 'Mr. Ramel Nudo'],
    'toolroom'         => ['Tool Room Officer (DHT)', 'Mr. Limuel Panday'],
    'cashier'          => ['Cashier', 'Ms. Glee Mae Soriano'],
    'mis'              => ['MIS Officer (DIT)', 'Mr. Christian B. Solis'],
    'tvet_coordinator' => ['TVET Coordinator', 'Ms. Reyna F. Villadares'],
    'tvet_director'    => ['TVET Director', 'Ms. Melody C. Prado']
];

$role_labels = [
    'student'          => 'Student',
    'library'          => 'Library',
    'toolroom'         => 'Tool Room Officer (DHT)',
    'cashier'          => 'Cashier',
    'mis'              => 'MIS Officer (DIT)',
    'tvet_coordinator' => 'TVET Coordinator',
    'tvet_director'    => 'TVET Director'
];

$staff_roles = ['library','toolroom','cashier','mis','tvet_coordinator','tvet_director'];
$is_dht_course = ($course === 'DHT');
$middle_office = $is_dht_course ? 'toolroom' : 'mis';
$sequential_offices = ['library', $middle_office, 'tvet_coordinator', 'tvet_director'];
if ($year_level === 3) {
    $sequential_offices = ['library', $middle_office, 'cashier', 'tvet_coordinator', 'tvet_director'];
}

$student_program_short = ($course === 'DHT') ? 'DHT' : 'DIT';
$student_course_full = ($course === 'DHT')
    ? 'Diploma in Hospitality Technology'
    : 'Diploma in Information Technology';

function get_flow_offices_by_student($student_course, $student_year_level) {
    $c = strtoupper(trim((string)$student_course));
    $middle = ($c === 'DHT') ? 'toolroom' : 'mis';
    if ((int)$student_year_level === 3) {
        return ['library', $middle, 'cashier', 'tvet_coordinator', 'tvet_director'];
    }
    return ['library', $middle, 'tvet_coordinator', 'tvet_director'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request' && $user_role === 'student') {
    $semester = trim($_POST['semester'] ?? '2nd Semester');
    $school_year = trim($_POST['school_year'] ?? '2025-2026');
    $course = $student_course_full;

    if ($semester === '' || $school_year === '') {
        header('Location: dashboard.php?view=dashboard&msg=' . urlencode('Please fill semester and school year.'));
        exit();
    }

    $semester_e = mysqli_real_escape_string($conn, $semester);
    $sy_e = mysqli_real_escape_string($conn, $school_year);
    $course_e = mysqli_real_escape_string($conn, $course);

    $exists_q = mysqli_query($conn, "SELECT id FROM clearance_requests WHERE student_id=$user_id LIMIT 1");
    if ($exists_q && mysqli_num_rows($exists_q) > 0) {
        header('Location: dashboard.php?view=dashboard&msg=' . urlencode('You already submitted your clearance request.'));
        exit();
    }

    mysqli_query($conn, "INSERT INTO clearance_requests (student_id, status, semester, school_year, course) VALUES ($user_id, 'pending', '$semester_e', '$sy_e', '$course_e')");
    $new_req_id = (int)mysqli_insert_id($conn);

    foreach ($sequential_offices as $i => $ofc) {
        $ofc_e = mysqli_real_escape_string($conn, $ofc);
        $initial_status = ($i === 0) ? 'pending' : 'not_started';
        mysqli_query($conn, "INSERT INTO clearance_items (request_id, office, status, remarks) VALUES ($new_req_id, '$ofc_e', '$initial_status', NULL)");
    }

    header('Location: dashboard.php?view=dashboard&msg=' . urlencode('Clearance request submitted successfully.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit_office' && $user_role === 'student') {
    $req_id = (int)($_POST['req_id'] ?? 0);
    $office_raw = trim($_POST['office'] ?? '');
    $office = mysqli_real_escape_string($conn, $office_raw);

    if ($req_id <= 0 || $office_raw === '') {
        header('Location: dashboard.php?view=clearance&msg=' . urlencode('Invalid resubmit request.'));
        exit();
    }

    $item_res = mysqli_query($conn, "
        SELECT ci.id, ci.status
        FROM clearance_items ci
        JOIN clearance_requests cr ON cr.id = ci.request_id
        WHERE ci.request_id=$req_id AND ci.office='$office' AND cr.student_id=$user_id
        LIMIT 1
    ");

    if (!$item_res || mysqli_num_rows($item_res) !== 1) {
        header('Location: dashboard.php?view=clearance&msg=' . urlencode('Office item not found for this request.'));
        exit();
    }

    $item = mysqli_fetch_assoc($item_res);
    if (($item['status'] ?? '') !== 'rejected') {
        header('Location: dashboard.php?view=clearance&msg=' . urlencode('Only rejected offices can be resubmitted.'));
        exit();
    }

    mysqli_query($conn, "UPDATE clearance_items SET status='pending', remarks=NULL, reviewed_by=NULL, reviewed_at=NULL WHERE request_id=$req_id AND office='$office' AND status='rejected'");
    mysqli_query($conn, "UPDATE clearance_requests SET status='pending' WHERE id=$req_id AND student_id=$user_id");

    $office_name = $office_labels[$office_raw][0] ?? $office_raw;
    header('Location: dashboard.php?view=clearance&msg=' . urlencode("Resubmitted to $office_name successfully."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        header('Location: dashboard.php?view=settings&msg=' . urlencode('Please fill in all password fields.'));
        exit();
    }

    if ($new_password !== $confirm_password) {
        header('Location: dashboard.php?view=settings&msg=' . urlencode('New password and confirm password do not match.'));
        exit();
    }

    $user_res = mysqli_query($conn, "SELECT password FROM users WHERE id=$user_id LIMIT 1");
    if (!$user_res || mysqli_num_rows($user_res) !== 1) {
        header('Location: dashboard.php?view=settings&msg=' . urlencode('Unable to validate current password.'));
        exit();
    }

    $user_row = mysqli_fetch_assoc($user_res);
    $stored_hash = (string)($user_row['password'] ?? '');
    if (!password_verify($current_password, $stored_hash)) {
        header('Location: dashboard.php?view=settings&msg=' . urlencode('Current password is incorrect.'));
        exit();
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $new_hash_e = mysqli_real_escape_string($conn, $new_hash);
    mysqli_query($conn, "UPDATE users SET password='$new_hash_e' WHERE id=$user_id LIMIT 1");

    if (mysqli_affected_rows($conn) >= 0) {
        header('Location: dashboard.php?view=settings&msg=' . urlencode('Password updated successfully.'));
        exit();
    }

    header('Location: dashboard.php?view=settings&msg=' . urlencode('Failed to update password.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($user_role, $staff_roles, true)) {
    $action     = $_POST['action'];
    $req_id     = (int)($_POST['req_id'] ?? 0);
    $remarks_raw = trim($_POST['remarks'] ?? '');
    $remarks    = mysqli_real_escape_string($conn, $remarks_raw);
    $office     = mysqli_real_escape_string($conn, $user_role);
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    if ($req_id > 0) {
        if ($action === 'reject' && $remarks_raw === '') {
            header('Location: dashboard.php?msg=' . urlencode('Remarks are required when rejecting a request.'));
            exit();
        }

        $current_item_res = mysqli_query($conn, "SELECT status FROM clearance_items WHERE request_id=$req_id AND office='$office' LIMIT 1");
        $can_review = false;
        if ($current_item_res && mysqli_num_rows($current_item_res) === 1) {
            $current_item = mysqli_fetch_assoc($current_item_res);
            $can_review = (($current_item['status'] ?? '') === 'pending');
        }

        if (!$can_review) {
            header('Location: dashboard.php?msg=' . urlencode('You can only process requests that are pending for your office.'));
            exit();
        }

        mysqli_query($conn, "UPDATE clearance_items SET status='$new_status', remarks=" . ($remarks_raw === '' ? "NULL" : "'$remarks'") . ", reviewed_by=$user_id, reviewed_at=NOW() WHERE request_id=$req_id AND office='$office'");

        if ($new_status === 'approved') {
            $flow_meta_res = mysqli_query($conn, "
                SELECT u.course, u.year_level
                FROM clearance_requests cr
                JOIN users u ON u.id = cr.student_id
                WHERE cr.id = $req_id
                LIMIT 1
            ");
            $flow_course = 'DIT';
            $flow_year = 1;
            if ($flow_meta_res && mysqli_num_rows($flow_meta_res) === 1) {
                $flow_meta = mysqli_fetch_assoc($flow_meta_res);
                $flow_course = strtoupper(trim((string)($flow_meta['course'] ?? 'DIT')));
                $flow_year = (int)($flow_meta['year_level'] ?? 1);
            }
            $req_offices = get_flow_offices_by_student($flow_course, $flow_year);
            $current_index = array_search($user_role, $req_offices, true);
            if ($current_index !== false && isset($req_offices[$current_index + 1])) {
                $next_office = mysqli_real_escape_string($conn, $req_offices[$current_index + 1]);
                mysqli_query($conn, "UPDATE clearance_items SET status='pending' WHERE request_id=$req_id AND office='$next_office' AND status='not_started'");
            }

            $remaining_res = mysqli_query($conn, "SELECT COUNT(*) c FROM clearance_items WHERE request_id=$req_id AND status IN ('pending','not_started','rejected')");
            if ($remaining_res) {
                $remaining = (int)(mysqli_fetch_assoc($remaining_res)['c'] ?? 0);
                if ($remaining === 0) {
                    mysqli_query($conn, "UPDATE clearance_requests SET status='approved' WHERE id=$req_id");
                }
            }
        }

        $student_res = mysqli_query($conn, "SELECT student_id FROM clearance_requests WHERE id=$req_id LIMIT 1");
        if ($student_res && mysqli_num_rows($student_res) === 1) {
            $student_row = mysqli_fetch_assoc($student_res);
            $sid = (int)$student_row['student_id'];
            $oname = $office_labels[$user_role][0] ?? $user_role;
            $msg = ($new_status === 'approved')
                ? "$oname approved your clearance request."
                : "$oname rejected your clearance request. Reason: $remarks";
            $msg_e = mysqli_real_escape_string($conn, $msg);
            $type = ($new_status === 'approved') ? 'success' : 'error';
            mysqli_query($conn, "INSERT INTO notifications (user_id, message, type) VALUES ($sid, '$msg_e', '$type')");
        }
    }

    header('Location: dashboard.php?msg=' . urlencode('Clearance ' . $new_status . ' successfully.'));
    exit();
}

$notifications = [];
$unread_count = 0;
$notifs_res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 8");
if ($notifs_res) {
    while ($n = mysqli_fetch_assoc($notifs_res)) {
        $notifications[] = $n;
        if ((int)$n['is_read'] === 0) {
            $unread_count++;
        }
    }
}

$success_msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$allowed_student_views = ['dashboard','clearance','notifications','profile','settings'];
$allowed_staff_views = ['dashboard','requests','profile','settings'];
$view = $_GET['view'] ?? 'dashboard';
if ($user_role === 'student' && !in_array($view, $allowed_student_views, true)) $view = 'dashboard';
if (in_array($user_role, $staff_roles, true) && !in_array($view, $allowed_staff_views, true)) $view = 'dashboard';

$stats = ['total'=>0,'approved'=>0,'pending'=>0,'rejected'=>0,'not_started'=>0];
$progress_pct = 0;
$clearance_items = [];
$clearance_req = null;
$all_students = [];
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$can_generate_clearance = false;
$selected_semester = trim($_GET['semester'] ?? '2nd Semester');
$selected_school_year = trim($_GET['school_year'] ?? '2025-2026');

if ($user_role === 'student') {
    $stats['total'] = ($year_level === 3) ? 5 : 4;
    $sem_e = mysqli_real_escape_string($conn, $selected_semester);
    $sy_e = mysqli_real_escape_string($conn, $selected_school_year);
    $req_res = mysqli_query($conn, "SELECT * FROM clearance_requests WHERE student_id=$user_id ORDER BY created_at DESC LIMIT 1");
    if ($req_res && mysqli_num_rows($req_res) > 0) {
        $clearance_req = mysqli_fetch_assoc($req_res);
        $req_id = (int)$clearance_req['id'];
        $ir = mysqli_query($conn, "SELECT * FROM clearance_items WHERE request_id=$req_id ORDER BY FIELD(office,'library','mis','toolroom','cashier','tvet_coordinator','tvet_director')");
        while ($item = mysqli_fetch_assoc($ir)) {
            if ($item['office'] === 'cashier' && $year_level !== 3) {
                continue;
            }
            $clearance_items[] = $item;
            if ($item['status'] === 'approved') $stats['approved']++;
            elseif ($item['status'] === 'pending') $stats['pending']++;
            elseif ($item['status'] === 'rejected') $stats['rejected']++;
            else $stats['not_started']++;
        }
        $progress_pct = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0;
        $can_generate_clearance = (($clearance_req['status'] ?? '') === 'approved' && $stats['approved'] === $stats['total']);
    }
}

if (in_array($user_role, $staff_roles, true)) {
    $year_filter = ($user_role === 'cashier') ? "AND u.year_level = 3" : "";
    $course_filter = "";
    if ($user_role === 'toolroom') {
        $course_filter = "AND u.course = 'DHT'";
    } elseif ($user_role === 'mis') {
        $course_filter = "AND u.course = 'DIT'";
    }
    $stu_sql = "
        SELECT u.id, u.name, u.student_id AS sid, u.email, u.year_level,
               cr.id AS req_id, cr.course, cr.semester, cr.school_year,
               ci.status AS office_status, ci.remarks
        FROM users u
        LEFT JOIN clearance_requests cr ON cr.student_id = u.id
        LEFT JOIN clearance_items ci ON ci.request_id = cr.id AND ci.office = '$user_role'
        WHERE u.role = 'student' $course_filter $year_filter
        ORDER BY u.name ASC
    ";
    $stu_res = mysqli_query($conn, $stu_sql);
    if ($stu_res) {
        while ($s = mysqli_fetch_assoc($stu_res)) {
            $all_students[] = $s;
            $ost = $s['office_status'] ?? 'not_started';
            if ($ost === 'pending' || $ost === 'not_started') $pending_count++;
            elseif ($ost === 'approved') $approved_count++;
            elseif ($ost === 'rejected') $rejected_count++;
        }
    }
    $stats['total'] = count($all_students);
    $stats['approved'] = $approved_count;
    $stats['pending'] = $pending_count;
    $stats['rejected'] = $rejected_count;
}
?>
<!-- FRONTEND -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
<div class="top">
  <div class="brand">
    <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none';">
    <div>
      <h1>Diploma Program Clearance System</h1>
      <small>Diploma Program TVET</small>
    </div>
  </div>
  <div class="top-right">
    <div class="user-pill">
      <div class="av"><?= htmlspecialchars($initials ?: 'U') ?></div>
      <div>
        <div class="nm"><?= htmlspecialchars($user_name) ?></div>
        <div class="rl"><?= htmlspecialchars($role_labels[$user_role] ?? 'User') ?> - Diploma Program</div>
      </div>
    </div>
    <a class="logout" href="logout.php">Logout</a>
  </div>
</div>

<div class="layout">
  <aside class="side">
    <div class="sect">MAIN MENU</div>
    <a class="item <?= $view==='dashboard'?'active':'' ?>" href="dashboard.php?view=dashboard"><span class="ic">&#127968;</span> Dashboard</a>
    <a class="item <?= ($view==='clearance'||$view==='requests')?'active':'' ?>" href="dashboard.php?view=<?= $user_role==='student'?'clearance':'requests' ?>"><span class="ic">&#128203;</span> <?= $user_role === 'student' ? 'My Clearance' : 'Requests' ?></a>
    <?php if ($user_role === 'student'): ?>
    <a class="item <?= $view==='notifications'?'active':'' ?>" href="dashboard.php?view=notifications"><span class="ic">&#128276;</span> Notifications<?= $unread_count > 0 ? ' (' . $unread_count . ')' : '' ?></a>
    <?php endif; ?>
    <div class="sect">ACCOUNT</div>
    <a class="item <?= $view==='profile'?'active':'' ?>" href="dashboard.php?view=profile"><span class="ic">&#128100;</span> My Profile</a>
    <a class="item <?= $view==='settings'?'active':'' ?>" href="dashboard.php?view=settings"><span class="ic">&#9881;&#65039;</span> Settings</a>
  </aside>

  <main class="main">
    <?php if ($success_msg): ?><div class="notice"><?= $success_msg ?></div><?php endif; ?>

    <?php if ($user_role === 'student'): ?>
      <?php if ($view === 'dashboard'): ?>
      <div class="hero">
        <div>
          <h2>Welcome back, <?= htmlspecialchars($first_name) ?>! &#128075;</h2>
          <p>Here is your clearance overview Â· Academic Year <?= htmlspecialchars($selected_school_year) ?></p>
        </div>
        <div class="progress-box">
          <div class="num"><?= $progress_pct ?>%</div>
          <div class="lbl">Clearance Progress</div>
        </div>
      </div>

      <?php if ($year_level === 3): ?>
        <div class="warn" style="margin-top:10px;">Cashier is included for 3rd-year students only.</div>
      <?php endif; ?>
      <?php if (!$clearance_req): ?>
      <div class="panel" style="margin-top:14px;">
        <div class="ph">Submit New Clearance Request</div>
        <div class="ni">
          <form method="POST" style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;">
            <input type="hidden" name="action" value="submit_request">
            <div class="fld" style="margin:0;">
              <label>Semester</label>
              <select name="semester">
                <option value="1st Semester" <?= $selected_semester === '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                <option value="2nd Semester" <?= $selected_semester === '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
              </select>
            </div>
            <div class="fld" style="margin:0;">
              <label>School Year</label>
              <input type="text" name="school_year" value="<?= htmlspecialchars($selected_school_year) ?>" placeholder="2025-2026">
            </div>
            <button type="submit" class="save-btn" style="margin:0;">Submit Request</button>
          </form>
        </div>
        <div class="ph">Offices Included In Your Request</div>
        <div class="ni">Library</div>
        <div class="ni"><?= htmlspecialchars($office_labels[$middle_office][0] ?? ucfirst($middle_office)) ?></div>
        <?php if ($year_level === 3): ?><div class="ni">Cashier</div><?php endif; ?>
        <div class="ni">TVET Coordinator</div>
        <div class="ni">TVET Director</div>
      </div>
      <?php else: ?>
      <div class="cards">
        <div class="card c1"><div class="kico">&#128203;</div><div class="n"><?= $stats['total'] ?></div><div class="t">Total Offices</div></div>
        <div class="card c2"><div class="kico">&#9989;</div><div class="n"><?= $stats['approved'] ?></div><div class="t">Approved</div></div>
        <div class="card c3"><div class="kico">&#9203;</div><div class="n"><?= $stats['pending'] ?></div><div class="t">Pending</div></div>
        <div class="card c4"><div class="kico">&#10060;</div><div class="n"><?= $stats['rejected'] ?></div><div class="t">Rejected</div></div>
      </div>

      <div class="panel" style="margin-bottom:14px;">
        <div class="ph">
          <div class="ph-row">
            <span>Clearance Status per Office</span>
            <?php if ($can_generate_clearance && !empty($clearance_req['id'])): ?>
              <a class="clearance-download-btn" href="download_clearance.php?request_id=<?= (int)$clearance_req['id'] ?>" target="_blank"><span>Download</span><span>Clearance</span></a>
            <?php endif; ?>
          </div>
        </div>
        <div class="table-container">
          <table class="tbl">
            <thead><tr><th>OFFICE</th><th>STATUS</th><th>REMARKS</th></tr></thead>
            <tbody>
            <?php if (!empty($clearance_items)): foreach ($clearance_items as $ci):
              $office = $office_labels[$ci['office']][0] ?? $ci['office']; ?>
              <tr>
                <td><?= htmlspecialchars($office) ?></td>
                <td><span class="badge <?= htmlspecialchars($ci['status']) ?>"><?= ucfirst(str_replace('_', ' ', $ci['status'])) ?></span></td>
                <td>
                  <?= htmlspecialchars($ci['remarks'] ?? '-') ?>
                  <?php if (($ci['status'] ?? '') === 'rejected' && !empty($clearance_req['id'])): ?>
                    <form method="POST" style="margin-top:8px;">
                      <input type="hidden" name="action" value="resubmit_office">
                      <input type="hidden" name="req_id" value="<?= (int)$clearance_req['id'] ?>">
                      <input type="hidden" name="office" value="<?= htmlspecialchars($ci['office']) ?>">
                      <button type="submit" class="btn approve">Resubmit to <?= htmlspecialchars($office) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="3" class="muted" style="text-align:center;">No clearance request found.Please contact your coordinator.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php endif; ?>
      <?php elseif ($view === 'clearance'): ?>
      <div class="hero"><div><h2>&#128203; My Clearance</h2><p>Track your office clearance status</p></div></div>
      <div class="panel" style="margin-top:14px;">
        <div class="ph">Clearance Status per Office</div>
        <div class="table-container">
          <table class="tbl">
            <thead><tr><th>OFFICE</th><th>STATUS</th><th>REMARKS</th></tr></thead>
            <tbody>
            <?php if (!empty($clearance_items)): foreach ($clearance_items as $ci): $office = $office_labels[$ci['office']][0] ?? $ci['office']; ?>
              <tr>
                <td><?= htmlspecialchars($office) ?></td>
                <td><span class="badge <?= htmlspecialchars($ci['status']) ?>"><?= ucfirst(str_replace('_', ' ', $ci['status'])) ?></span></td>
                <td>
                  <?= htmlspecialchars($ci['remarks'] ?? '-') ?>
                  <?php if (($ci['status'] ?? '') === 'rejected' && !empty($clearance_req['id'])): ?>
                    <form method="POST" style="margin-top:8px;">
                      <input type="hidden" name="action" value="resubmit_office">
                      <input type="hidden" name="req_id" value="<?= (int)$clearance_req['id'] ?>">
                      <input type="hidden" name="office" value="<?= htmlspecialchars($ci['office']) ?>">
                      <button type="submit" class="btn approve">Resubmit to <?= htmlspecialchars($office) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="3" class="muted" style="text-align:center;">No clearance request found. Please contact your coordinator.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
          <?php if ($can_generate_clearance && !empty($clearance_req['id'])): ?>
            <a class="clearance-download-btn download-clearance" href="download_clearance.php?request_id=<?= (int)$clearance_req['id'] ?>" target="_blank"><span>Download</span><span>Clearance</span></a>
          <?php endif; ?>
        </div>
      </div>
      <?php elseif ($view === 'notifications'): ?>
      <div class="hero"><div><h2>&#128276; Notifications</h2><p>All your clearance updates and alerts</p></div><div class="progress-box"><div class="num"><?= (int)$unread_count ?></div><div class="lbl">Unread</div></div></div>
      <div class="panel" style="margin-top:14px;">
        <div class="ph"><div class="ph-row"><span>All Notifications</span><span class="ph-note"><?= (int)$unread_count ?> unread</span></div></div>
        <?php if (!empty($notifications)): foreach ($notifications as $n): ?>
          <div class="notif-row"><div class="notif-icon">&#10003;</div><div><div><strong><?= htmlspecialchars($n['message']) ?></strong></div><div class="muted"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div></div></div>
        <?php endforeach; else: ?><div class="ni muted" style="text-align:center;">No notifications yet.</div><?php endif; ?>
      </div>
      <?php elseif ($view === 'profile'): ?>
      <div class="hero"><div><h2>&#128100; My Profile</h2><p>Your personal information</p></div></div>
      <div class="profile-card" style="margin-top:14px;">
        <div style="text-align:center">
          <div class="profile-avatar"><?= htmlspecialchars($initials ?: 'U') ?></div>
          <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
          <div class="profile-sub">Student Â· Diploma Program <?= htmlspecialchars($student_program_short) ?></div>
          <div class="profile-badge">ID: <?= htmlspecialchars($_SESSION['student_id'] ?? '-') ?></div>
        </div>
        <div class="profile-lines">
          <div>&#128231; <?= htmlspecialchars($_SESSION['user_email'] ?? '-') ?></div>
          <div>&#127979; Asian College Dumaguete</div>
          <div>&#128197; 2nd Semester Â· SY <?= htmlspecialchars($clearance_req['school_year'] ?? '2025-2026') ?></div>
          <div>&#128218; <?= htmlspecialchars($student_course_full) ?></div>
          <div>&#127891; Year Level <?= (int)$year_level > 0 ? (int)$year_level : '-' ?></div>
        </div>
      </div>
      <?php elseif ($view === 'settings'): ?>
      <div class="hero"><div><h2>&#9881;&#65039; Settings</h2><p>Manage your account preferences</p></div></div>
      <div class="settings-box" style="margin-top:14px;">
        <div class="ph" style="padding:0 0 12px 0;border-bottom:1px solid #e5e7eb;">Change Password</div>
        <form method="POST" action="dashboard.php?view=settings">
          <input type="hidden" name="action" value="change_password">
          <div class="fld"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password"></div>
          <div class="fld"><label>New Password</label><input type="password" name="new_password" placeholder="Enter new password"></div>
          <div class="fld"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Re-enter new password"></div>
          <button type="submit" class="save-btn">Save Changes</button>
        </form>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <?php if ($view === 'dashboard'): ?>
      <div class="hero" id="dashboard-section">
        <div>
          <h2><?= htmlspecialchars($role_labels[$user_role] ?? ucfirst($user_role)) ?></h2>
          <p>Welcome back, <?= htmlspecialchars($first_name) ?>. Manage student clearance requests below</p>
        </div>
        <div class="progress-box">
          <div class="num"><?= $pending_count ?></div>
          <div class="lbl">Pending</div>
        </div>
      </div>

      <div class="cards">
        <a class="card-link" href="dashboard.php?view=requests"><div class="card c1"><div class="kico">&#128101;</div><div class="n"><?= $stats['total'] ?></div><div class="t">Total of Students</div></div></a>
        <a class="card-link" href="dashboard.php?view=requests"><div class="card c2"><div class="kico">&#9989;</div><div class="n"><?= $stats['approved'] ?></div><div class="t">Approved</div></div></a>
        <a class="card-link" href="dashboard.php?view=requests"><div class="card c3"><div class="kico">&#9203;</div><div class="n"><?= $stats['pending'] ?></div><div class="t">Pending</div></div></a>
        <a class="card-link" href="dashboard.php?view=requests"><div class="card c4"><div class="kico">&#10060;</div><div class="n"><?= $stats['rejected'] ?></div><div class="t">Rejected</div></div></a>
      </div>

      <div class="two" id="clearance-section">
        <div class="panel">
          <div class="ph">Student Clearance Request</div>
          <table class="tbl">
            <thead><tr><th>STUDENT</th><th>STUDENT ID</th><th>SEMESTER</th><th>SCHOOL YEAR</th><th>MY OFFICE STATUS</th><th>REMARKS</th><th>ACTION</th></tr></thead>
            <tbody>
            <?php if (!empty($all_students)): foreach ($all_students as $s):
              $ost = $s['office_status'] ?? 'not_started'; ?>
              <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong><div class="muted"><?= htmlspecialchars($s['email']) ?></div></td>
                <td><?= htmlspecialchars($s['sid'] ?? '-') ?></td>
                <td><?= htmlspecialchars($s['semester'] ?? '-') ?></td>
                <td><?= htmlspecialchars($s['school_year'] ?? '-') ?></td>
                <td><span class="badge <?= htmlspecialchars($ost) ?>"><?= ucfirst(str_replace('_', ' ', $ost)) ?></span></td>
                <td><?= htmlspecialchars($s['remarks'] ?? '-') ?></td>
                <td>
                  <?php if (!empty($s['req_id'])): ?>
                  <form method="POST" style="display:flex;flex-direction:column;gap:6px;">
                    <input type="hidden" name="req_id" value="<?= (int)$s['req_id'] ?>">
                    <input type="text" name="remarks" placeholder="Remarks (required for reject)" value="" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;font-size:12px;">
                    <?php if ($ost !== 'approved'): ?><button class="btn approve" type="submit" name="action" value="approve">APPROVE</button><?php endif; ?>
                    <?php if ($ost !== 'rejected'): ?><button class="btn reject" type="submit" name="action" value="reject">REJECT</button><?php endif; ?>
                  </form>
                  <?php else: ?>
                    <span class="muted">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7" class="muted" style="text-align:center;">No students found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="panel" id="notifications-section">
          <div class="ph">Office Summary</div>
          <div class="stack">
            <div class="muted" style="padding:10px;background:#f3f4f6;border-radius:10px;">
              <strong><?= htmlspecialchars($role_labels[$user_role] ?? ucfirst($user_role)) ?></strong><br>
              <?= htmlspecialchars($office_labels[$user_role][1] ?? 'Asian College Dumaguete') ?>
            </div>
            <div class="sum s1">APPROVED: <?= $approved_count ?></div>
            <div class="sum s2">PENDING: <?= $pending_count ?></div>
            <div class="sum s3">REJECTED: <?= $rejected_count ?></div>
          </div>
        </div>
      </div>
      <?php elseif ($view === 'requests'): ?>
      <div class="hero">
        <div>
          <h2>&#128203; Requests</h2>
          <p>Review and process student requests for your office</p>
        </div>
      </div>
      <div class="two" style="margin-top:14px;">
        <div class="panel">
          <div class="ph">Student Clearance Request</div>
          <table class="tbl">
            <thead><tr><th>STUDENT</th><th>STUDENT ID</th><th>SEMESTER</th><th>SCHOOL YEAR</th><th>MY OFFICE STATUS</th><th>REMARKS</th><th>ACTION</th></tr></thead>
            <tbody>
            <?php if (!empty($all_students)): foreach ($all_students as $s):
              $ost = $s['office_status'] ?? 'not_started'; ?>
              <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong><div class="muted"><?= htmlspecialchars($s['email']) ?></div></td>
                <td><?= htmlspecialchars($s['sid'] ?? '-') ?></td>
                <td><?= htmlspecialchars($s['semester'] ?? '-') ?></td>
                <td><?= htmlspecialchars($s['school_year'] ?? '-') ?></td>
                <td><span class="badge <?= htmlspecialchars($ost) ?>"><?= ucfirst(str_replace('_', ' ', $ost)) ?></span></td>
                <td><?= htmlspecialchars($s['remarks'] ?? '-') ?></td>
                <td>
                  <?php if (!empty($s['req_id'])): ?>
                  <form method="POST" style="display:flex;flex-direction:column;gap:6px;">
                    <input type="hidden" name="req_id" value="<?= (int)$s['req_id'] ?>">
                    <input type="text" name="remarks" placeholder="Remarks (required for reject)" value="" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;font-size:12px;">
                    <?php if ($ost !== 'approved'): ?><button class="btn approve" type="submit" name="action" value="approve">APPROVE</button><?php endif; ?>
                    <?php if ($ost !== 'rejected'): ?><button class="btn reject" type="submit" name="action" value="reject">REJECT</button><?php endif; ?>
                  </form>
                  <?php else: ?><span class="muted">-</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7" class="muted" style="text-align:center;">No students found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="panel">
          <div class="ph">Office Summary</div>
          <div class="stack">
            <div class="muted" style="padding:10px;background:#f3f4f6;border-radius:10px;">
              <strong><?= htmlspecialchars($role_labels[$user_role] ?? ucfirst($user_role)) ?></strong><br>
              <?= htmlspecialchars($office_labels[$user_role][1] ?? 'Asian College Dumaguete') ?>
            </div>
            <div class="sum s1">APPROVED: <?= $approved_count ?></div>
            <div class="sum s2">PENDING: <?= $pending_count ?></div>
            <div class="sum s3">REJECTED: <?= $rejected_count ?></div>
          </div>
        </div>
      </div>
      <?php elseif ($view === 'profile'): ?>
      <div class="hero"><div><h2>&#128100; My Profile</h2><p>Your personal information</p></div></div>
      <div class="profile-card" style="margin-top:14px;">
        <div style="text-align:center">
          <div class="profile-avatar"><?= htmlspecialchars($initials ?: 'U') ?></div>
          <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
          <div class="profile-sub"><?= htmlspecialchars($role_labels[$user_role] ?? 'User') ?> Â· Diploma Program</div>
        </div>
        <div class="profile-lines">
          <div>&#128231; <?= htmlspecialchars($_SESSION['user_email'] ?? '-') ?></div>
          <div>&#127979; Asian College Dumaguete</div>
          <div>&#128188; <?= htmlspecialchars($role_labels[$user_role] ?? 'User') ?></div>
        </div>
      </div>
      <?php elseif ($view === 'settings'): ?>
      <div class="hero"><div><h2>&#9881;&#65039; Settings</h2><p>Manage your account preferences</p></div></div>
      <div class="settings-box" style="margin-top:14px;">
        <div class="ph" style="padding:0 0 12px 0;border-bottom:1px solid #e5e7eb;">Change Password</div>
        <form method="POST" action="dashboard.php?view=settings">
          <input type="hidden" name="action" value="change_password">
          <div class="fld"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password"></div>
          <div class="fld"><label>New Password</label><input type="password" name="new_password" placeholder="Enter new password"></div>
          <div class="fld"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Re-enter new password"></div>
          <button type="submit" class="save-btn">Save Changes</button>
        </form>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
</body>
</html>

