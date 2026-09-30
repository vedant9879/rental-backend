<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$userPhone = trim($_POST['user_phone'] ?? '');
$notificationId = trim($_POST['notification_id'] ?? '');

if ($userPhone === '' || $notificationId === '') {

    echo json_encode([
        "status" => "error",
        "message" => "User phone and notification ID are required"
    ]);

    exit();
}

if (!ctype_digit($notificationId)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid notification ID"
    ]);

    exit();
}

$id = (int)$notificationId;


$sql = "
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
    AND user_phone = ?
    LIMIT 1
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
    "is",
    $id,
    $userPhone
);


if (mysqli_stmt_execute($stmt)) {

    if (mysqli_stmt_affected_rows($stmt) > 0) {

        echo json_encode([
            "status" => "success",
            "message" => "Notification marked as read"
        ]);

    } else {

        echo json_encode([
            "status" => "not_found",
            "message" => "Notification not found"
        ]);
    }

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update notification"
    ]);
}


mysqli_stmt_close($stmt);

?>
