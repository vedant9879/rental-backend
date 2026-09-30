<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$userPhone = trim($_POST['user_phone'] ?? '');
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$type = trim($_POST['type'] ?? 'system');

if (
    $userPhone === '' ||
    $title === '' ||
    $message === ''
) {
    echo json_encode([
        "status" => "error",
        "message" => "Required notification fields are missing"
    ]);
    exit();
}

$sql = "
    INSERT INTO notifications
    (
        user_phone,
        title,
        message,
        type,
        is_read
    )
    VALUES (?, ?, ?, ?, 0)
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "ssss",
    $userPhone,
    $title,
    $message,
    $type
);

if (mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => "success",
        "message" => "Notification created successfully",
        "notification_id" => mysqli_insert_id($conn)
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to create notification"
    ]);
}

mysqli_stmt_close($stmt);

?>
