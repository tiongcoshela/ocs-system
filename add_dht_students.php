<?php
session_start();
require_once 'db.php';

$students = [
    [
        'name' => 'Ana Abella',
        'email' => 'ana@asian.edu.ph',
        'role' => 'student',
        'year_level' => 1,
    ],
    [
        'name' => 'Venus Fabrigras',
        'email' => 'venus@asian.edu.ph',
        'role' => 'student',
        'year_level' => 2,
    ],
    [
        'name' => 'Derek Baisac',
        'email' => 'derek@asian.edu.ph',
        'role' => 'student',
        'year_level' => 3,
    ],
];

$default_password_hash = password_hash('password123', PASSWORD_DEFAULT);

foreach ($students as $student) {
    $name = mysqli_real_escape_string($conn, $student['name']);
    $email = mysqli_real_escape_string($conn, $student['email']);
    $role = mysqli_real_escape_string($conn, $student['role']);
    $year_level = (int)$student['year_level'];
    $student_id = mysqli_real_escape_string($conn, 'DHT-' . date('Y') . '-' . str_pad((string)$year_level, 2, '0', STR_PAD_LEFT) . '-' . substr(md5($email), 0, 4));
    $password = mysqli_real_escape_string($conn, $default_password_hash);

    $exists = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' LIMIT 1");
    if ($exists && mysqli_num_rows($exists) > 0) {
        echo "Skipped (already exists): {$student['email']}<br>";
        continue;
    }

    $sql = "INSERT INTO users (name, email, password, role, student_id, year_level, course)
            VALUES ('$name', '$email', '$password', '$role', '$student_id', $year_level, 'DHT')";
    if (mysqli_query($conn, $sql)) {
        echo "Inserted: {$student['name']} ({$student['email']})<br>";
    } else {
        echo "Failed: {$student['email']} - " . htmlspecialchars(mysqli_error($conn)) . "<br>";
    }
}

echo "<br>Done. Default password for inserted accounts: password123";
