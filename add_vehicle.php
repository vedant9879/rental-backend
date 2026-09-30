<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$ownerPhone = trim($_POST['owner_phone'] ?? '');
$vehicleName = trim($_POST['vehicle_name'] ?? '');
$vehicleType = trim($_POST['vehicle_type'] ?? '');
$pricePerDay = trim($_POST['price_per_day'] ?? '');
$price6hr = trim($_POST['price_6hr'] ?? '0');
$price12hr = trim($_POST['price_12hr'] ?? '0');
$city = trim($_POST['city'] ?? '');
$address = trim($_POST['address'] ?? '');
$quantity = trim($_POST['quantity'] ?? '');
$deposit = trim($_POST['deposit'] ?? '');
$vehicleImage = trim($_POST['vehicle_image'] ?? '');

if (
    $ownerPhone === '' ||
    $vehicleName === '' ||
    $vehicleType === '' ||
    $pricePerDay === '' ||
    $city === '' ||
    $quantity === ''
) {
    echo json_encode([
        "status" => "error",
        "message" => "Required vehicle fields are missing"
    ]);
    exit();
}

if (!is_numeric($pricePerDay) ||
    !is_numeric($price6hr) ||
    !is_numeric($price12hr) ||
    !is_numeric($quantity) ||
    !is_numeric($deposit)
) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle values"
    ]);
    exit();
}

$pricePerDay = (float)$pricePerDay;
$price6hr = (float)$price6hr;
$price12hr = (float)$price12hr;
$quantity = (int)$quantity;
$deposit = (float)$deposit;


/*
 * Insert vehicle
 */

$sql = "
    INSERT INTO vehicles
    (
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
        deposit
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
    "ssssdddssid",
    $ownerPhone,
    $vehicleName,
    $vehicleType,
    $vehicleImage,
    $pricePerDay,
    $price6hr,
    $price12hr,
    $city,
    $address,
    $quantity,
    $deposit
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to add vehicle"
    ]);

    exit();
}


$vehicleId =
    mysqli_insert_id($conn);

mysqli_stmt_close($stmt);


/*
 * Create notification for owner.
 */

$title =
    "Vehicle Listed Successfully";

$message =
    $vehicleName .
    " has been successfully added to your RentX listings.";

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


/*
 * Final response.
 */

echo json_encode([
    "status" => "success",
    "message" => "Vehicle added successfully",
    "vehicle_id" => $vehicleId
]);

?>
