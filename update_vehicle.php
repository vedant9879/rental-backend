<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$vehicleId = trim($_POST['vehicle_id'] ?? '');

$name = trim($_POST['vehicle_name'] ?? '');
$price = trim($_POST['price_per_day'] ?? '');
$price6 = trim($_POST['price_6hr'] ?? '0');
$price12 = trim($_POST['price_12hr'] ?? '0');
$quantity = trim($_POST['quantity'] ?? '');
$deposit = trim($_POST['deposit'] ?? '');
$city = trim($_POST['city'] ?? '');
$address = trim($_POST['address'] ?? '');

if (
    $vehicleId === '' ||
    $name === '' ||
    $price === '' ||
    $quantity === '' ||
    $city === ''
) {
    echo json_encode([
        "status" => "error",
        "message" => "Required fields are missing"
    ]);
    exit();
}

if (!ctype_digit($vehicleId)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle ID"
    ]);
    exit();
}

if (
    !is_numeric($price) ||
    !is_numeric($price6) ||
    !is_numeric($price12) ||
    !is_numeric($quantity) ||
    !is_numeric($deposit)
) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle values"
    ]);
    exit();
}

$vehicleId = (int)$vehicleId;
$price = (float)$price;
$price6 = (float)$price6;
$price12 = (float)$price12;
$quantity = (int)$quantity;
$deposit = (float)$deposit;


/*
 * Get owner information.
 */

$sqlVehicle = "
    SELECT
        vehicle_name,
        owner_phone
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmtVehicle = mysqli_prepare(
    $conn,
    $sqlVehicle
);

if (!$stmtVehicle) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmtVehicle,
    "i",
    $vehicleId
);

mysqli_stmt_execute(
    $stmtVehicle
);

$resultVehicle =
    mysqli_stmt_get_result(
        $stmtVehicle
    );

if (
    !$resultVehicle ||
    mysqli_num_rows($resultVehicle) === 0
) {
    mysqli_stmt_close($stmtVehicle);

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle not found"
    ]);

    exit();
}

$vehicle =
    mysqli_fetch_assoc(
        $resultVehicle
    );

mysqli_stmt_close(
    $stmtVehicle
);


/*
 * Update vehicle.
 */

$sqlUpdate = "
    UPDATE vehicles
    SET
        vehicle_name = ?,
        price_per_day = ?,
        price_6hr = ?,
        price_12hr = ?,
        quantity = ?,
        deposit = ?,
        city = ?,
        address = ?
    WHERE id = ?
    LIMIT 1
";

$stmtUpdate =
    mysqli_prepare(
        $conn,
        $sqlUpdate
    );

if (!$stmtUpdate) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmtUpdate,
    "sdddidssi",
    $name,
    $price,
    $price6,
    $price12,
    $quantity,
    $deposit,
    $city,
    $address,
    $vehicleId
);

if (!mysqli_stmt_execute($stmtUpdate)) {

    mysqli_stmt_close($stmtUpdate);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update vehicle"
    ]);

    exit();
}

mysqli_stmt_close(
    $stmtUpdate
);


/*
 * Create notification.
 */

$title =
    "Vehicle Updated";

$message =
    $name .
    " has been updated successfully in your RentX listings.";

$type =
    "vehicle";

$sqlNotification = "
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

$stmtNotification =
    mysqli_prepare(
        $conn,
        $sqlNotification
    );

if ($stmtNotification) {

    $ownerPhone =
        $vehicle['owner_phone'];

    mysqli_stmt_bind_param(
        $stmtNotification,
        "ssss",
        $ownerPhone,
        $title,
        $message,
        $type
    );

    mysqli_stmt_execute(
        $stmtNotification
    );

    mysqli_stmt_close(
        $stmtNotification
    );
}


echo json_encode([
    "status" => "success",
    "message" => "Updated Successfully"
]);

?>
