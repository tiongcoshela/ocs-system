<?php

require_once __DIR__ . '/db.php';

$emails = [
    'library@asian.edu.ph',
    'toolroom@asian.edu.ph',
    'cashier@asian.edu.ph',
    'mis@asian.edu.ph',
    'coordinator@asian.edu.ph',
    'director@asian.edu.ph',
];

$newPasswordHash = password_hash('password123', PASSWORD_DEFAULT);

if ($newPasswordHash === false) {
    die("Failed to generate password hash.\n");
}

$updated = 0;
$notFound = [];

$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ? LIMIT 1");
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn) . "\n");
}

foreach ($emails as $email) {
    mysqli_stmt_bind_param($stmt, 'ss', $newPasswordHash, $email);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_errno($stmt)) {
        echo "Error updating {$email}: " . mysqli_stmt_error($stmt) . "\n";
        continue;
    }

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $updated++;
    } else {
        $notFound[] = $email;
    }
}

mysqli_stmt_close($stmt);

echo "Updated accounts: {$updated}\n";

if (!empty($notFound)) {
    echo "No matching user row for:\n";
    foreach ($notFound as $email) {
        echo "- {$email}\n";
    }
}
