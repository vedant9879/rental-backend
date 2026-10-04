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
        requester_phone,
        owner_phone,
        vehicle_id,
        vehicle_name,
        pickup,
        drop_location,
        goods,
        weight,
        vehicle_required,
        transport_date,
        transport_time,
        base_fare,
        minimum_fare,
        per_km,
        driver_charge,
        waiting_charge,
        status,
        created_at,
        updated_at
    FROM transport_requests
    WHERE requester_phone = ?
    ORDER BY id DESC
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
    "s",
    $userPhone
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to load transport requests"
    ]);
    exit();
}

$result = mysqli_stmt_get_result($stmt);

$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

mysqli_stmt_close($stmt);

echo json_encode($data);

?>
