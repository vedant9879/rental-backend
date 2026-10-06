<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";


// ============================================================
// ERROR HANDLING
// ============================================================

mysqli_report(MYSQLI_REPORT_OFF);


// ============================================================
// GET OWNER PHONE
// ============================================================

$owner_phone = isset($_GET["owner_phone"])
    ? trim($_GET["owner_phone"])
    : "";


// ============================================================
// VALIDATION
// ============================================================

if ($owner_phone === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "owner_phone is required",
        "data" => []
    ]);

    exit;
}


// ============================================================
// DATABASE CHECK
// ============================================================

if (!isset($conn) || !$conn) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "data" => []
    ]);

    exit;
}


// ============================================================
// QUERY
// ============================================================
//
// IMPORTANT:
//
// We explicitly return ALL marketplace fields.
//
// This is what fixes the My Vehicles category counts.
//
// ============================================================

$sql = "
    SELECT
        id,
        owner_phone,

        vehicle_name,
        vehicle_type,
        vehicle_image,

        price_per_day,
        price_6hr,
        price_12hr,

        city,
        address,

        quantity,
        deposit,

        listing_type,

        selling_price,
        vehicle_condition,
        manufacturing_year,
        kilometers_driven,
        ownership,

        service_type,

        transport_base_fare,
        transport_minimum_fare,
        transport_per_km,
        transport_driver_charge,
        transport_waiting_charge

    FROM vehicles

    WHERE owner_phone = ?

    ORDER BY id DESC
";


// ============================================================
// PREPARE
// ============================================================

$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare vehicle query",
        "data" => []
    ]);

    exit;
}


// ============================================================
// BIND OWNER PHONE
// ============================================================

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $owner_phone
);


// ============================================================
// EXECUTE
// ============================================================

if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load vehicles",
        "data" => []
    ]);

    exit;
}


// ============================================================
// RESULT
// ============================================================

$result = mysqli_stmt_get_result(
    $stmt
);


if (!$result) {

    mysqli_stmt_close($stmt);

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to read vehicle data",
        "data" => []
    ]);

    exit;
}


// ============================================================
// VEHICLE ARRAY
// ============================================================

$vehicles = [];


// ============================================================
// LOOP
// ============================================================

while ($row = mysqli_fetch_assoc($result)) {

    // --------------------------------------------------------
    // NORMALIZE NULL VALUES
    // --------------------------------------------------------

    foreach ($row as $key => $value) {

        if ($value === null) {

            $row[$key] = "";
        }
    }


    // --------------------------------------------------------
    // IMPORTANT:
    //
    // Keep listing_type exactly as stored.
    //
    // Examples:
    //
    // Rent
    // Sell
    // Rent + Sell
    // Goods Transportation
    // --------------------------------------------------------

    $row["listing_type"] =
        trim(
            (string)$row["listing_type"]
        );


    // --------------------------------------------------------
    // IMPORTANT:
    //
    // Keep service_type exactly as stored.
    //
    // Transport vehicles can be identified through:
    //
    // service_type = Goods Transportation
    //
    // even if an older vehicle has:
    //
    // listing_type = Rent
    // --------------------------------------------------------

    $row["service_type"] =
        trim(
            (string)$row["service_type"]
        );


    // --------------------------------------------------------
    // ADD VEHICLE
    // --------------------------------------------------------

    $vehicles[] = $row;
}


// ============================================================
// CLOSE
// ============================================================

mysqli_stmt_close(
    $stmt
);


// ============================================================
// RESPONSE
// ============================================================

echo json_encode(
    [
        "success" => true,
        "message" => "Vehicles loaded successfully",
        "data" => $vehicles
    ],
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

exit;

?>
