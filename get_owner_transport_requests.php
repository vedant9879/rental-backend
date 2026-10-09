<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// RECEIVE OWNER PHONE
// =====================================================

$ownerPhone = trim($_GET['owner_phone'] ?? '');


// =====================================================
// VALIDATE
// =====================================================

if ($ownerPhone === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Owner phone is required"
    ]);
    exit();
}


// =====================================================
// GET TRANSPORT REQUESTS
// Includes final_fare for owner fare/payment workflow.
// Requires transport_requests.final_fare column.
// =====================================================

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
        final_fare,
        status,
        created_at,
        updated_at
    FROM transport_requests
    WHERE owner_phone = ?
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


// =====================================================
// BIND OWNER PHONE
// =====================================================

mysqli_stmt_bind_param($stmt, "s", $ownerPhone);


// =====================================================
// EXECUTE
// =====================================================

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status" => "error",
        "message" => "Unable to load transport requests"
    ]);
    mysqli_stmt_close($stmt);
    exit();
}


// =====================================================
// RESULT
// =====================================================

$result = mysqli_stmt_get_result($stmt);
$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Return final_fare as null until the owner sets the actual agreed fare.
        $row['final_fare'] = $row['final_fare'] ?? null;
        $data[] = $row;
    }
}

mysqli_stmt_close($stmt);


// =====================================================
// SUCCESS
// =====================================================

echo json_encode($data, JSON_UNESCAPED_SLASHES);

?>
