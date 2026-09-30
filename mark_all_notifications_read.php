<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$userPhone = trim($_POST['user_phone'] ?? '');

if ($userPhone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "User phone is required"
    ]);

    exit();
}


$sql = "
    UPDATE notifications
    SET is_read = 1
    WHERE user_phone = ?
    AND is_read = 0
";


$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmt,
    "s",
    $userPhone
);


if (mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => "success",
        "message" => "All notifications marked as read"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update notifications"
    ]);
}


mysqli_stmt_close($stmt);

?>
