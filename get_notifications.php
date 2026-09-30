<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$userPhone = trim($_GET['user_phone'] ?? '');

if ($userPhone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "User phone is required"
    ]);

    exit();
}


$sql = "
    SELECT
        id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_phone = ?
    ORDER BY created_at DESC
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


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result($stmt);


if (!$result) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to load notifications"
    ]);

    exit();
}


$notifications = [];


while ($row = mysqli_fetch_assoc($result)) {

    $notifications[] = [

        "id" =>
            (int)$row['id'],

        "title" =>
            $row['title'],

        "message" =>
            $row['message'],

        "type" =>
            $row['type'],

        "is_read" =>
            (int)$row['is_read'],

        "created_at" =>
            $row['created_at']
    ];
}


mysqli_stmt_close($stmt);


echo json_encode(
    $notifications,
    JSON_UNESCAPED_UNICODE
);

?>
