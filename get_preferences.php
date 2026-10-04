<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$phone = trim($_GET['phone'] ?? '');

if ($phone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "Phone number is required"
    ]);

    exit();
}

$sql = "
    SELECT
        notification_booking,
        notification_promotions,
        notification_support,
        notification_security,
        notification_general,
        preferred_vehicle_type
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $phone
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {

    $row = mysqli_fetch_assoc($result);

    echo json_encode([
        "status" => "success",
        "notification_booking" =>
            (int)($row['notification_booking'] ?? 1),

        "notification_promotions" =>
            (int)($row['notification_promotions'] ?? 1),

        "notification_support" =>
            (int)($row['notification_support'] ?? 1),

        "notification_security" =>
            (int)($row['notification_security'] ?? 1),

        "notification_general" =>
            (int)($row['notification_general'] ?? 1),

        "preferred_vehicle_type" =>
            $row['preferred_vehicle_type'] ?? "Any Vehicle"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "User Not Found"
    ]);
}

mysqli_stmt_close($stmt);

?>
