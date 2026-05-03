<?php
// BACKEND
// =============================================
//  index.php - Login Page
//  Asian College Online Clearance System
// =============================================
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];
    $role     = mysqli_real_escape_string($conn, $_POST['role']);

    if (empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields.';
    } else {
        $sql    = "SELECT * FROM users WHERE email = '$email' AND role = '$role' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_name']   = $user['name'];
                $_SESSION['user_role']   = $user['role'];
                $_SESSION['user_email']  = $user['email'];
                $_SESSION['student_id']  = $user['student_id'];
                $_SESSION['year_level']  = $user['year_level'];
                $_SESSION['course']      = $user['course'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Incorrect password. Please try again.';
            }
        } else {
            $error = 'No account found with that email and role.';
        }
    }
}
?>
<!-- FRONTEND -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In - Diploma Program Clearance System</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<!-- LEFT: Login Form -->
<div class="login-left login-bg">
  <div class="form-card">
    <div class="login-form-header">
      <h2>Sign In</h2>
      <p>Enter Your Credentials To Access The Clearance Portal</p>
    </div>

    <?php if ($error): ?>
      <div class="error-box">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
      <div class="form-group">
        <label>Role</label>
        <div class="input-wrapper select-wrap">
          <div class="input-icon">&#128100;</div>
          <select name="role" required>
            <option value="">Select your role</option>
            <option value="student"          <?= (isset($_POST['role']) && $_POST['role']==='student')          ? 'selected':'' ?>>Student</option>
            <option value="admin"            <?= (isset($_POST['role']) && $_POST['role']==='admin')            ? 'selected':'' ?>>Administrator</option>
            <option value="library"          <?= (isset($_POST['role']) && $_POST['role']==='library')          ? 'selected':'' ?>>Library</option>
            <option value="toolroom"         <?= (isset($_POST['role']) && $_POST['role']==='toolroom')         ? 'selected':'' ?>>Tool Room Officer (DHT)</option>
            <option value="cashier"          <?= (isset($_POST['role']) && $_POST['role']==='cashier')          ? 'selected':'' ?>>Cashier</option>
            <option value="mis"              <?= (isset($_POST['role']) && $_POST['role']==='mis')              ? 'selected':'' ?>>MIS Officer (DIT)</option>
            <option value="tvet_coordinator" <?= (isset($_POST['role']) && $_POST['role']==='tvet_coordinator') ? 'selected':'' ?>>TVET Coordinator</option>
            <option value="tvet_director"    <?= (isset($_POST['role']) && $_POST['role']==='tvet_director')    ? 'selected':'' ?>>TVET Director</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <div class="input-wrapper">
          <div class="input-icon">&#9993;</div>
          <input type="email" name="email" placeholder="Enter your email address"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Password</label>
        <div class="input-wrapper">
          <div class="input-icon">&#128274;</div>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>
      </div>

      <button type="submit" class="login-btn">Sign In to Portal</button>
    </form>
  </div>
</div>

<!-- RIGHT: Branding -->
<div class="login-right">
  <div class="brand-logo">
    <img
      class="brand-logo-img"
      src="assets/img/logo.png"
      alt="School Logo"
      onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
    >
    <div class="brand-logo-fallback">AC</div>
  </div>
  <div class="brand-name">
    <h1>Asian College<br>Dumaguete</h1>
    <p>Online Clearance System - Diploma Program</p>
  </div>
  <div class="brand-divider"></div>
  <div class="features-wrap">
    <div class="feat-label">System Features</div>
    <div class="feature-item"><div class="feature-icon">&#128269;</div><span>Track clearance status in real time</span></div>
    <div class="feature-item"><div class="feature-icon">&#127970;</div><span>Submit requests to all offices digitally</span></div>
    <div class="feature-item"><div class="feature-icon">&#128276;</div><span>Get notified on every approval or update</span></div>
    <div class="feature-item"><div class="feature-icon">&#128196;</div><span>Paperless, fast and transparent process</span></div>
  </div>
</div>

</body>
</html>




