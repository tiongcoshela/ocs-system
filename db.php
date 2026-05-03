<?php

mysqli_report(MYSQLI_REPORT_OFF);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       
define('DB_PASS', '');           
define('DB_NAME', 'clearance_db');

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("
    <div style='font-family:sans-serif;padding:40px;text-align:center;'>
        <h2 style='color:#dc2626;'>⚠️ Database Connection Failed</h2>
        <p style='color:#475569;'>Could not connect to MySQL. Please check your credentials in <strong>db.php</strong>.</p>
        <p style='color:#94a3b8;font-size:13px;'>Error: " . mysqli_connect_error() . "</p>
    </div>");
}

mysqli_set_charset($conn, 'utf8mb4');
?>
