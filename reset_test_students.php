<?php
require_once 'db.php';

$target_students = ['Ana Abella', 'Marc Mapili'];
$escaped_names = array_map(static function ($name) use ($conn) {
    return "'" . mysqli_real_escape_string($conn, $name) . "'";
}, $target_students);

$name_list_sql = implode(',', $escaped_names);

mysqli_begin_transaction($conn);

try {
    $student_ids = [];
    $students_res = mysqli_query(
        $conn,
        "SELECT id FROM users WHERE user_name IN ($name_list_sql)"
    );
    if ($students_res === false) {
        throw new Exception(mysqli_error($conn));
    }

    if ($students_res) {
        while ($row = mysqli_fetch_assoc($students_res)) {
            $student_ids[] = (int)$row['id'];
        }
    }

    if (!empty($student_ids)) {
        $student_ids_sql = implode(',', $student_ids);

        $request_ids = [];
        $requests_res = mysqli_query(
            $conn,
            "SELECT id FROM clearance_requests WHERE student_id IN ($student_ids_sql)"
        );
        if ($requests_res === false) {
            throw new Exception(mysqli_error($conn));
        }

        if ($requests_res) {
            while ($row = mysqli_fetch_assoc($requests_res)) {
                $request_ids[] = (int)$row['id'];
            }
        }

        mysqli_query(
            $conn,
            "UPDATE clearance_requests SET status='pending' WHERE student_id IN ($student_ids_sql)"
        );
        if (mysqli_error($conn)) {
            throw new Exception(mysqli_error($conn));
        }

        if (!empty($request_ids)) {
            $request_ids_sql = implode(',', $request_ids);

            mysqli_query(
                $conn,
                "UPDATE clearance_items
                 SET status='not_started', remarks=NULL
                 WHERE request_id IN ($request_ids_sql)"
            );
            if (mysqli_error($conn)) {
                throw new Exception(mysqli_error($conn));
            }

            mysqli_query(
                $conn,
                "UPDATE clearance_items
                 SET status='pending'
                 WHERE request_id IN ($request_ids_sql) AND office='library'"
            );
            if (mysqli_error($conn)) {
                throw new Exception(mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);
    echo "Ana Abella and Marc Mapili clearance requests have been reset.";
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo "Reset failed: " . $e->getMessage();
}
