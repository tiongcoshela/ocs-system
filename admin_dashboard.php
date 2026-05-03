<?php
// BACKEND
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'db.php';

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Admin';
$first_name = explode(' ', trim($user_name))[0];
$initials   = strtoupper(substr($user_name, 0, 1) . (strpos($user_name, ' ') ? substr($user_name, strpos($user_name, ' ') + 1, 1) : ''));
$allowed_views = ['dashboard','users','clearances','clearance_details','reports','offices','notifications','settings'];
$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, $allowed_views, true)) {
    $view = 'dashboard';
}

$office_map = [
    'library' => 'Library',
    'toolroom' => 'Tool Room Officer (DHT)',
    'mis' => 'MIS Officer (DIT)',
    'cashier' => 'Cashier',
    'tvet_coordinator' => 'TVET Coordinator',
    'tvet_director' => 'TVET Director'
];

if (!isset($_SESSION['office_active']) || !is_array($_SESSION['office_active'])) {
    $_SESSION['office_active'] = [
        'library' => 1,
        'toolroom' => 1,
        'mis' => 1,
        'cashier' => 1,
        'tvet_coordinator' => 1,
        'tvet_director' => 1
    ];
}

if (!isset($_SESSION['system_settings']) || !is_array($_SESSION['system_settings'])) {
    $_SESSION['system_settings'] = [
    'system_name' => 'Diploma Program Clearance System',
        'school_year' => '2025-2026',
        'period_start' => date('Y-m-01'),
        'period_end' => date('Y-m-t')
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';
    $return_view = $_GET['view'] ?? 'dashboard';
    if (!in_array($return_view, $allowed_views, true)) {
        $return_view = 'dashboard';
    }

    if ($action === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $password = $_POST['password'] ?? '';
        $student_id = trim($_POST['student_id'] ?? '');
        $year_level = (int)($_POST['year_level'] ?? 0);
        $course = strtoupper(trim($_POST['course'] ?? 'DIT'));

        if ($name !== '' && $email !== '' && $password !== '') {
            $valid_roles = ['student','admin','library','toolroom','cashier','mis','tvet_coordinator','tvet_director'];
            if (!in_array($role, $valid_roles, true)) {
                $role = 'student';
            }

            $name_e = mysqli_real_escape_string($conn, $name);
            $email_e = mysqli_real_escape_string($conn, $email);
            $role_e = mysqli_real_escape_string($conn, $role);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $pass_e = mysqli_real_escape_string($conn, $password_hash);
            $course = in_array($course, ['DIT','DHT'], true) ? $course : 'DIT';
            $course_e = mysqli_real_escape_string($conn, $course);

            $sid_sql = ($role === 'student' && $student_id !== '') ? "'" . mysqli_real_escape_string($conn, $student_id) . "'" : "NULL";
            $year_sql = ($role === 'student' && $year_level >= 1 && $year_level <= 3) ? $year_level : "NULL";
            $course_sql = ($role === 'student') ? "'$course_e'" : "'DIT'";

            mysqli_query($conn, "INSERT INTO users (name, email, password, role, student_id, year_level, course) VALUES ('$name_e', '$email_e', '$pass_e', '$role_e', $sid_sql, $year_sql, $course_sql)");
        }

        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('User added successfully.'));
        exit();
    }

    if ($action === 'edit_user') {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        $name = trim($_POST['edit_name'] ?? '');
        $role = trim($_POST['edit_role'] ?? 'student');
        $edit_course = strtoupper(trim($_POST['edit_course'] ?? 'DIT'));

        if ($edit_id > 0 && $name !== '') {
            $valid_roles = ['student','admin','library','toolroom','cashier','mis','tvet_coordinator','tvet_director'];
            if (!in_array($role, $valid_roles, true)) {
                $role = 'student';
            }
            $name_e = mysqli_real_escape_string($conn, $name);
            $role_e = mysqli_real_escape_string($conn, $role);
            $edit_course = in_array($edit_course, ['DIT','DHT'], true) ? $edit_course : 'DIT';
            $edit_course_e = mysqli_real_escape_string($conn, $edit_course);
            $course_sql = ($role === 'student') ? "'$edit_course_e'" : "'DIT'";
            mysqli_query($conn, "UPDATE users SET name='$name_e', role='$role_e', course=$course_sql WHERE id=$edit_id");
        }

        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('User updated successfully.'));
        exit();
    }

    if ($action === 'delete_user') {
        $delete_id = (int)($_POST['delete_id'] ?? 0);
        if ($delete_id > 0 && $delete_id !== $user_id) {
            mysqli_query($conn, "DELETE FROM users WHERE id=$delete_id");
        }
        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('User deleted successfully.'));
        exit();
    }

    if ($action === 'toggle_office') {
        $office = $_POST['office'] ?? '';
        if (isset($_SESSION['office_active'][$office])) {
            $_SESSION['office_active'][$office] = $_SESSION['office_active'][$office] ? 0 : 1;
        }
        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('Office status updated.'));
        exit();
    }

    if ($action === 'override_status') {
        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('Override is disabled for admin.'));
        exit();
    }

    if ($action === 'send_notification') {
        $message = trim($_POST['message'] ?? '');
        $target = $_POST['target'] ?? 'students';
        $type = $_POST['type'] ?? 'info';

        if ($message !== '') {
            $msg_e = mysqli_real_escape_string($conn, $message);
            $type_e = mysqli_real_escape_string($conn, in_array($type, ['success','warning','error','info'], true) ? $type : 'info');

            if ($target === 'all') {
                $res = mysqli_query($conn, "SELECT id FROM users");
            } else {
                $res = mysqli_query($conn, "SELECT id FROM users WHERE role='student'");
            }

            if ($res) {
                while ($u = mysqli_fetch_assoc($res)) {
                    $uid = (int)$u['id'];
                    mysqli_query($conn, "INSERT INTO notifications (user_id, message, type) VALUES ($uid, '$msg_e', '$type_e')");
                }
            }
        }

        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('Notification sent.'));
        exit();
    }

    if ($action === 'save_settings') {
        $_SESSION['system_settings']['system_name'] = trim($_POST['system_name'] ?? $_SESSION['system_settings']['system_name']);
        $_SESSION['system_settings']['school_year'] = trim($_POST['school_year'] ?? $_SESSION['system_settings']['school_year']);
        $_SESSION['system_settings']['period_start'] = trim($_POST['period_start'] ?? $_SESSION['system_settings']['period_start']);
        $_SESSION['system_settings']['period_end'] = trim($_POST['period_end'] ?? $_SESSION['system_settings']['period_end']);

        header('Location: admin_dashboard.php?view=' . urlencode($return_view) . '&msg=' . urlencode('Settings saved.'));
        exit();
    }
}

$total_students = 0;
$approved_requests = 0;
$pending_requests = 0;
$rejected_requests = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role='student'");
if ($r) { $total_students = (int)(mysqli_fetch_assoc($r)['c'] ?? 0); }

$r = mysqli_query($conn, "SELECT status, COUNT(*) c FROM clearance_requests GROUP BY status");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        if ($row['status'] === 'approved') $approved_requests = (int)$row['c'];
        if ($row['status'] === 'pending') $pending_requests = (int)$row['c'];
        if ($row['status'] === 'rejected') $rejected_requests = (int)$row['c'];
    }
}

$users = [];
$u_res = mysqli_query($conn, "SELECT id, name, email, role, student_id, year_level, course, created_at FROM users ORDER BY created_at DESC");
if ($u_res) {
    while ($u = mysqli_fetch_assoc($u_res)) { $users[] = $u; }
}

$requests = [];
$q = "
    SELECT cr.id, u.name, u.email, u.student_id, u.year_level, cr.course, cr.semester, cr.school_year, cr.status, cr.created_at
    FROM clearance_requests cr
    JOIN users u ON u.id = cr.student_id
    ORDER BY cr.created_at DESC
";
$req_res = mysqli_query($conn, $q);
if ($req_res) {
    while ($x = mysqli_fetch_assoc($req_res)) { $requests[] = $x; }
}

$selected_request = null;
$selected_items = [];
$selected_all_approved = false;
if ($view === 'clearance_details') {
    $detail_id = (int)($_GET['id'] ?? 0);
    if ($detail_id > 0) {
        $detail_q = "
            SELECT cr.id, cr.status, cr.semester, cr.school_year, cr.course, cr.created_at,
                   u.name, u.email, u.student_id, u.year_level
            FROM clearance_requests cr
            JOIN users u ON u.id = cr.student_id
            WHERE cr.id = $detail_id
            LIMIT 1
        ";
        $detail_res = mysqli_query($conn, $detail_q);
        if ($detail_res && mysqli_num_rows($detail_res) === 1) {
            $selected_request = mysqli_fetch_assoc($detail_res);
            $selected_all_approved = true;

            $items_res = mysqli_query($conn, "SELECT office, status, remarks FROM clearance_items WHERE request_id = $detail_id ORDER BY FIELD(office,'library','mis','toolroom','cashier','tvet_coordinator','tvet_director')");
            if ($items_res) {
                while ($it = mysqli_fetch_assoc($items_res)) {
                    $selected_items[] = $it;
                    if (($it['status'] ?? '') !== 'approved') {
                        $selected_all_approved = false;
                    }
                }
            }
        }
    }
}

$recent_notifs = [];
$n_res = mysqli_query($conn, "SELECT n.message, n.type, n.created_at, u.name FROM notifications n LEFT JOIN users u ON u.id = n.user_id ORDER BY n.created_at DESC LIMIT 8");
if ($n_res) {
    while ($n = mysqli_fetch_assoc($n_res)) { $recent_notifs[] = $n; }
}

$success_msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$settings = $_SESSION['system_settings'];
$total_requests = $approved_requests + $pending_requests + $rejected_requests;
$clearance_progress = ($total_requests > 0) ? round(($approved_requests / $total_requests) * 100) : 0;
?>
<!-- FRONTEND -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="top">
  <div class="brand"><div class="logo"><img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'; this.parentNode.textContent='AC'; this.parentNode.style.fontWeight='800'; this.parentNode.style.color='#1e3a8a';"></div><div><div style="font-weight:800"><?= htmlspecialchars($settings['system_name']) ?></div><div style="font-size:12px;color:#bfdbfe">Diploma Program TVET</div></div></div>
  <div style="display:flex;align-items:center;gap:10px"><div style="background:rgba(255,255,255,.15);padding:8px 12px;border-radius:999px;font-size:13px"><?= htmlspecialchars($initials) ?> <?= htmlspecialchars($first_name) ?></div><a href="logout.php" class="logout-btn">Logout</a></div>
</div>
<div class="wrap">
  <aside class="side">
    <div style="display:flex;align-items:center;gap:10px;color:#0f172a;padding:8px 10px;margin-bottom:10px">
      <div style="font-size:24px">&#9745;</div>
      <div>
        <div style="font-weight:800;line-height:1.1">Online Clearance System</div>
      </div>
    </div>
    <nav class="menu">
      <a class="<?= $view === 'dashboard' ? 'active' : '' ?>" href="admin_dashboard.php?view=dashboard">Dashboard</a>
      <a class="<?= $view === 'users' ? 'active' : '' ?>" href="admin_dashboard.php?view=users">Users</a>
      <a class="<?= $view === 'clearances' ? 'active' : '' ?>" href="admin_dashboard.php?view=clearances">Clearances</a>
      <a class="<?= $view === 'reports' ? 'active' : '' ?>" href="admin_dashboard.php?view=reports">Reports</a>
      <a class="<?= $view === 'offices' ? 'active' : '' ?>" href="admin_dashboard.php?view=offices">Offices</a>
      <a class="<?= $view === 'notifications' ? 'active' : '' ?>" href="admin_dashboard.php?view=notifications">Notifications</a>
      <a class="<?= $view === 'settings' ? 'active' : '' ?>" href="admin_dashboard.php?view=settings">Settings</a>
    </nav>
  </aside>

  <main class="main">
    <?php if ($success_msg): ?><div class="ok"><?= $success_msg ?></div><?php endif; ?>

    <?php if ($view === 'dashboard'): ?>
    <section class="hero">
      <div>
        <h1>Admin Dashboard</h1>
        <p>Manage the entire clearance system and monitor student progress.</p>
      </div>
    </section>

    <div class="kpis">
      <div class="kpi k1"><div class="l">Total Students</div><div class="n"><?= $total_students ?></div></div>
      <div class="kpi k3"><div class="l">Approved</div><div class="n"><?= $approved_requests ?></div></div>
      <div class="kpi k2"><div class="l">Pending</div><div class="n"><?= $pending_requests ?></div></div>
      <div class="kpi k4"><div class="l">Rejected</div><div class="n"><?= $rejected_requests ?></div></div>
    </div>

    <div class="dash-grid">
      <div class="card">
        <div class="ch">All Clearance Requests</div>
        <div class="pad">
          <table>
            <thead><tr><th>Student Name</th><th>Program/Course</th><th>Overall Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (!empty($requests)): foreach ($requests as $r): ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong><div class="small"><?= htmlspecialchars($r['email']) ?></div></td>
                <td><?= htmlspecialchars($r['course']) ?></td>
                <td><span class="badge <?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                <td><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
                <td><a class="btn b2" href="admin_dashboard.php?view=clearance_details&id=<?= (int)$r['id'] ?>">View Details</a></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="small">No clearance requests yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="ch">System Overview</div>
        <div class="pad">
          <div class="report-grid">
            <div class="report-box"><div class="k">Cleared Students</div><div class="v"><?= $approved_requests ?></div></div>
            <div class="report-box"><div class="k">Ongoing</div><div class="v"><?= $pending_requests ?></div></div>
            <div class="report-box"><div class="k">Issues</div><div class="v"><?= $rejected_requests ?></div></div>
          </div>
          <a class="btn b1" href="admin_dashboard.php?view=reports" style="width:100%;margin-top:14px;text-align:center">Generate Report</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'clearance_details'): ?>
    <div class="card" style="margin-bottom:14px">
      <div class="ch">Clearance Request Details</div>
      <div class="pad">
        <div style="margin-bottom:10px"><a class="btn b2" href="admin_dashboard.php?view=dashboard">Back to Dashboard</a></div>
        <?php if ($selected_request): ?>
        <?php if (($selected_request['status'] ?? '') === 'approved' && $selected_all_approved): ?>
          <div style="margin-bottom:10px;"><a class="btn b1" href="clearance_certificate.php?request_id=<?= (int)$selected_request['id'] ?>" target="_blank">Generate Clearance</a></div>
        <?php endif; ?>
        <div class="report-grid" style="margin-bottom:12px">
          <div class="report-box"><div class="k">Student Name</div><div class="v" style="font-size:16px"><?= htmlspecialchars($selected_request['name']) ?></div></div>
          <div class="report-box"><div class="k">Email</div><div class="v" style="font-size:16px"><?= htmlspecialchars($selected_request['email']) ?></div></div>
          <div class="report-box"><div class="k">Student ID</div><div class="v" style="font-size:16px"><?= htmlspecialchars($selected_request['student_id'] ?? '-') ?></div></div>
          <div class="report-box"><div class="k">Course/Program</div><div class="v" style="font-size:16px"><?= htmlspecialchars($selected_request['course']) ?></div></div>
          <div class="report-box"><div class="k">Year Level</div><div class="v" style="font-size:16px"><?= (int)$selected_request['year_level'] ?></div></div>
          <div class="report-box"><div class="k">Semester</div><div class="v" style="font-size:16px"><?= htmlspecialchars($selected_request['semester']) ?></div></div>
          <div class="report-box"><div class="k">School Year</div><div class="v" style="font-size:16px"><?= htmlspecialchars($selected_request['school_year']) ?></div></div>
          <div class="report-box"><div class="k">Overall Status</div><div class="v" style="font-size:16px"><span class="badge <?= htmlspecialchars($selected_request['status']) ?>"><?= ucfirst($selected_request['status']) ?></span></div></div>
        </div>

        <table>
          <thead><tr><th>Office</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody>
          <?php if (!empty($selected_items)): foreach ($selected_items as $it): ?>
            <tr>
              <td><?= htmlspecialchars($office_map[$it['office']] ?? $it['office']) ?></td>
              <td><span class="badge <?= htmlspecialchars($it['status']) ?>"><?= ucfirst(str_replace('_', ' ', $it['status'])) ?></span></td>
              <td><?= htmlspecialchars($it['remarks'] ?? '-') ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3" class="small">No office items found for this request.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="small">Request not found.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'clearances'): ?>
    <div class="dash-grid">
      <div class="card">
        <div class="ch">Clearance Requests</div>
        <div class="pad">
          <table>
            <thead><tr><th>Student Name</th><th>Course</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!empty($requests)): foreach ($requests as $r): ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong><div class="small"><?= htmlspecialchars($r['email']) ?></div></td>
                <td><?= htmlspecialchars($r['course']) ?></td>
                <td><span class="badge <?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                <td><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
                <td>
                  <a class="btn b2" href="admin_dashboard.php?view=clearance_details&id=<?= (int)$r['id'] ?>">View Details</a>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="small">No clearance requests yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'reports'): ?>
    <div class="dash-grid">
      <div class="card">
        <div class="ch">Reports Overview</div>
        <div class="pad">
          <div class="report-grid">
            <div class="report-box"><div class="k">Cleared Students</div><div class="v"><?= $approved_requests ?></div></div>
            <div class="report-box"><div class="k">Pending Clearances</div><div class="v"><?= $pending_requests ?></div></div>
            <div class="report-box"><div class="k">Rejected Requests</div><div class="v"><?= $rejected_requests ?></div></div>
            <div class="report-box"><div class="k">Clearance Report</div><div class="v">&#129534;</div></div>
          </div>
          <a class="btn b1" href="admin_dashboard.php?view=reports" style="width:100%;margin-top:14px;text-align:center">Generate Report</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'users'): ?>
    <div class="forms">
      <div class="card">
        <div class="ch">User Management</div>
        <div class="pad">
          <form method="POST">
            <input type="hidden" name="admin_action" value="add_user">
            <div class="row">
              <div><label>Name</label><input name="name" required></div>
              <div><label>Email</label><input name="email" type="email" required></div>
            </div>
            <div class="row" style="margin-top:8px">
              <div><label>Role</label><select name="role" id="add_user_role"><option value="student">Student</option><option value="admin">Admin</option><option value="library">Library</option><option value="toolroom">Tool Room Officer (DHT)</option><option value="mis">MIS Officer (DIT)</option><option value="cashier">Cashier</option><option value="tvet_coordinator">TVET Coordinator</option><option value="tvet_director">TVET Director</option></select></div>
              <div><label>Password</label><input name="password" type="password" required></div>
            </div>
            <div class="row" style="margin-top:8px">
              <div><label>Student ID (if student)</label><input name="student_id"></div>
              <div><label>Year Level</label><select name="year_level"><option value="">-</option><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option></select></div>
            </div>
            <div class="row" id="add_course_row" style="margin-top:8px">
              <div>
                <label>Course</label>
                <select name="course">
                  <option value="DIT">DIT (Diploma in Information Technology)</option>
                  <option value="DHT">DHT (Diploma in Hospitality Technology)</option>
                </select>
              </div>
              <div></div>
            </div>
            <div style="margin-top:10px"><button class="btn b1" type="submit">Add User</button></div>
          </form>

          <div style="margin-top:12px;max-height:280px;overflow:auto">
            <table>
              <thead><tr><th>Name</th><th>Role</th><th>Course</th><th>Action</th></tr></thead>
              <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= htmlspecialchars($u['name']) ?><div class="small"><?= htmlspecialchars($u['email']) ?></div></td>
                  <td><?= htmlspecialchars($u['role']) ?></td>
                  <td><?= htmlspecialchars($u['course'] ?? 'DIT') ?></td>
                  <td>
                    <form method="POST" style="display:inline-flex;gap:4px;align-items:center">
                      <input type="hidden" name="admin_action" value="edit_user">
                      <input type="hidden" name="edit_id" value="<?= (int)$u['id'] ?>">
                      <input name="edit_name" value="<?= htmlspecialchars($u['name']) ?>" style="width:120px;padding:6px">
                      <select name="edit_role" style="width:120px;padding:6px">
                        <?php foreach (['student','admin','library','toolroom','mis','cashier','tvet_coordinator','tvet_director'] as $rr): ?>
                        <option value="<?= $rr ?>" <?= $u['role']===$rr?'selected':'' ?>><?= $rr ?></option>
                        <?php endforeach; ?>
                      </select>
                      <select name="edit_course" style="width:160px;padding:6px">
                        <option value="DIT" <?= (($u['course'] ?? 'DIT') === 'DIT') ? 'selected' : '' ?>>DIT</option>
                        <option value="DHT" <?= (($u['course'] ?? 'DIT') === 'DHT') ? 'selected' : '' ?>>DHT</option>
                      </select>
                      <button class="btn b2" type="submit">Edit</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                      <input type="hidden" name="admin_action" value="delete_user">
                      <input type="hidden" name="delete_id" value="<?= (int)$u['id'] ?>">
                      <button class="btn b3" type="submit" <?= ((int)$u['id'] === $user_id) ? 'disabled' : '' ?>>Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'offices'): ?>
    <div class="forms">
      <div>
        <div class="card" style="margin-bottom:14px">
          <div class="ch">Office Management</div>
          <div class="pad">
            <table>
              <thead><tr><th>Office</th><th>Status</th><th>Toggle</th></tr></thead>
              <tbody>
              <?php foreach ($office_map as $key => $label): ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?></td>
                  <td><span class="badge <?= $_SESSION['office_active'][$key] ? 'approved' : 'rejected' ?>"><?= $_SESSION['office_active'][$key] ? 'Active' : 'Inactive' ?></span></td>
                  <td>
                    <form method="POST">
                      <input type="hidden" name="admin_action" value="toggle_office">
                      <input type="hidden" name="office" value="<?= htmlspecialchars($key) ?>">
                      <button class="btn b2" type="submit">Toggle</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <div class="small" style="margin-top:8px">Prototype mode: office active/inactive state is stored in session for now.</div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'notifications'): ?>
    <div class="forms">
      <div>
        <div class="card" style="margin-bottom:14px">
          <div class="ch">Notifications Management</div>
          <div class="pad">
            <form method="POST">
              <input type="hidden" name="admin_action" value="send_notification">
              <label>Message</label>
              <textarea name="message" required placeholder="Send reminders, deadlines, or announcements..."></textarea>
              <div class="row" style="margin-top:8px">
                <div><label>Target</label><select name="target"><option value="students">Students only</option><option value="all">All users</option></select></div>
                <div><label>Type</label><select name="type"><option value="info">Info</option><option value="warning">Warning</option><option value="success">Success</option><option value="error">Error</option></select></div>
              </div>
              <div style="margin-top:10px"><button class="btn b1" type="submit">Send Notification</button></div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:14px">
      <div class="ch">Recent Notifications Log</div>
      <div class="pad">
        <table>
          <thead><tr><th>Time</th><th>User</th><th>Type</th><th>Message</th></tr></thead>
          <tbody>
          <?php if (!empty($recent_notifs)): foreach ($recent_notifs as $n): ?>
            <tr>
              <td><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></td>
              <td><?= htmlspecialchars($n['name'] ?? 'Unknown') ?></td>
              <td><span class="badge <?= htmlspecialchars($n['type']) ?>"><?= ucfirst($n['type']) ?></span></td>
              <td><?= htmlspecialchars($n['message']) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" class="small">No notifications yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'settings'): ?>
    <div class="forms">
      <div>
        <div class="card">
          <div class="ch">System Settings</div>
          <div class="pad">
            <form method="POST">
              <input type="hidden" name="admin_action" value="save_settings">
              <label>System Name</label>
              <input name="system_name" value="<?= htmlspecialchars($settings['system_name']) ?>">
              <div class="row" style="margin-top:8px">
                <div><label>School Year</label><input name="school_year" value="<?= htmlspecialchars($settings['school_year']) ?>"></div>
                <div><label>Clearance Start</label><input type="date" name="period_start" value="<?= htmlspecialchars($settings['period_start']) ?>"></div>
              </div>
              <div style="margin-top:8px"><label>Clearance End</label><input type="date" name="period_end" value="<?= htmlspecialchars($settings['period_end']) ?>"></div>
              <div style="margin-top:10px"><button class="btn b1" type="submit">Save Settings</button></div>
            </form>
          </div>
        </div>
      </div>
    </div>
    </div>
    <?php endif; ?>
  </main>
</div>
<script>
(function () {
  var roleSelect = document.getElementById('add_user_role');
  var courseRow = document.getElementById('add_course_row');
  if (!roleSelect || !courseRow) return;

  function syncCourseVisibility() {
    courseRow.style.display = (roleSelect.value === 'student') ? '' : 'none';
  }

  roleSelect.addEventListener('change', syncCourseVisibility);
  syncCourseVisibility();
})();
</script>
</body>
</html>


