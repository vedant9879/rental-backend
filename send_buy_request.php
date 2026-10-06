<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");


// =====================================================
// READ POST DATA
// =====================================================

$vehicleId =
    isset($_POST['vehicle_id'])
        ? (int) $_POST['vehicle_id']
        : 0;

$buyerPhone =
    isset($_POST['buyer_phone'])
        ? trim($_POST['buyer_phone'])
        : "";


// =====================================================
// VALIDATION
// =====================================================

if ($vehicleId <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle"
    ]);

    exit;
}


if ($buyerPhone === "") {

    echo json_encode([
        "status" => "error",
        "message" => "Buyer login required"
    ]);

    exit;
}


// =====================================================
// GET VEHICLE
// =====================================================

$sql = "
    SELECT
        id,
        vehicle_name,
        listing_type,
        selling_price,
        owner_phone
    FROM vehicles
    WHERE id = ?
    LIMIT 1
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

    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $vehicleId
);


mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result($stmt);


$vehicle =
    mysqli_fetch_assoc($result);


mysqli_stmt_close($stmt);


// =====================================================
// VEHICLE NOT FOUND
// =====================================================

if (!$vehicle) {

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle not found"
    ]);

    exit;
}


// =====================================================
// CHECK SALE LISTING
// =====================================================

$listingType =
    trim(
        $vehicle['listing_type'] ?? ""
    );


if (
    strcasecmp($listingType, "Sell") !== 0 &&
    strcasecmp($listingType, "Rent + Sell") !== 0
) {

    echo json_encode([
        "status" => "error",
        "message" => "This vehicle is not available for purchase"
    ]);

    exit;
}


// =====================================================
// SELLER PHONE
// =====================================================

$sellerPhone =
    trim(
        $vehicle['owner_phone'] ?? ""
    );


if ($sellerPhone === "") {

    echo json_encode([
        "status" => "error",
        "message" => "Seller contact is not available"
    ]);

    exit;
}


// =====================================================
// PREVENT BUYING OWN VEHICLE
// =====================================================

if (
    preg_replace(
        '/\D+/',
        '',
        $buyerPhone
    )
    ===
    preg_replace(
        '/\D+/',
        '',
        $sellerPhone
    )
) {

    echo json_encode([
        "status" => "error",
        "message" => "You cannot send a buy request for your own vehicle"
    ]);

    exit;
}


// =====================================================
// CHECK EXISTING ACTIVE REQUEST
// =====================================================

$checkSql = "
    SELECT
        id,
        status
    FROM buy_requests
    WHERE vehicle_id = ?
      AND buyer_phone = ?
      AND status IN ('Pending', 'Accepted')
    ORDER BY id DESC
    LIMIT 1
";


$checkStmt =
    mysqli_prepare(
        $conn,
        $checkSql
    );


if ($checkStmt) {

    mysqli_stmt_bind_param(
        $checkStmt,
        "is",
        $vehicleId,
        $buyerPhone
    );

    mysqli_stmt_execute(
        $checkStmt
    );

    $checkResult =
        mysqli_stmt_get_result(
            $checkStmt
        );

    $existing =
        mysqli_fetch_assoc(
            $checkResult
        );

    mysqli_stmt_close(
        $checkStmt
    );


    if ($existing) {

        echo json_encode([
            "status" => "already_exists",
            "message" => "You already have an active request for this vehicle",
            "request_id" => (int)$existing['id'],
            "request_status" => $existing['status']
        ]);

        exit;
    }
}


// =====================================================
// VEHICLE INFORMATION
// =====================================================

$vehicleName =
    trim(
        $vehicle['vehicle_name'] ?? "Vehicle"
    );


$sellingPrice =
    (float)(
        $vehicle['selling_price'] ?? 0
    );


// =====================================================
// INSERT REQUEST
// =====================================================

$insertSql = "
    INSERT INTO buy_requests
    (
        vehicle_id,
        buyer_phone,
        seller_phone,
        vehicle_name,
        selling_price,
        status
    )
    VALUES
    (?, ?, ?, ?, ?, 'Pending')
";


$insertStmt =
    mysqli_prepare(
        $conn,
        $insertSql
    );


if (!$insertStmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to create request"
    ]);

    exit;
}


mysqli_stmt_bind_param(
    $insertStmt,
    "isssd",
    $vehicleId,
    $buyerPhone,
    $sellerPhone,
    $vehicleName,
    $sellingPrice
);


if (
    !mysqli_stmt_execute(
        $insertStmt
    )
) {

    $error =
        mysqli_stmt_error(
            $insertStmt
        );

    mysqli_stmt_close(
        $insertStmt
    );

    echo json_encode([
        "status" => "error",
        "message" => "Unable to send buy request",
        "error" => $error
    ]);

    exit;
}


$requestId =
    mysqli_insert_id(
        $conn
    );


mysqli_stmt_close(
    $insertStmt
);


// =====================================================
// SELLER NOTIFICATION
// =====================================================

$title =
    "New Buy Request";


$message =
    $buyerPhone .
    " sent a buy request for " .
    $vehicleName .
    ".";


$type =
    "buy_request";


$notificationSql = "
    INSERT INTO notifications
    (
        user_phone,
        title,
        message,
        type,
        is_read
    )
    VALUES
    (?, ?, ?, ?, 0)
";


$notificationStmt =
    mysqli_prepare(
        $conn,
        $notificationSql
    );


if ($notificationStmt) {

    mysqli_stmt_bind_param(
        $notificationStmt,
        "ssss",
        $sellerPhone,
        $title,
        $message,
        $type
    );

    mysqli_stmt_execute(
        $notificationStmt
    );

    mysqli_stmt_close(
        $notificationStmt
    );
}


// =====================================================
// SUCCESS
// =====================================================

echo json_encode([
    "status" => "success",
    "message" => "Buy request sent successfully",
    "request_id" => $requestId,
    "request_status" => "Pending"
]);

?>
