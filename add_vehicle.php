<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE VEHICLE DATA
|--------------------------------------------------------------------------
*/

$name =
    trim($_POST['vehicle_name'] ?? '');

$type =
    trim($_POST['vehicle_type'] ?? '');

$price =
    trim($_POST['price_per_day'] ?? '0');

$owner =
    trim($_POST['owner_phone'] ?? '');

$image =
    trim($_POST['vehicle_image'] ?? '');

$city =
    trim($_POST['city'] ?? '');

$address =
    trim($_POST['address'] ?? '');

$quantity =
    trim($_POST['quantity'] ?? '1');

$deposit =
    trim($_POST['deposit'] ?? '0');

$price6 =
    trim($_POST['price_6hr'] ?? '0');

$price12 =
    trim($_POST['price_12hr'] ?? '0');


/*
|--------------------------------------------------------------------------
| REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $name === '' ||
    $type === '' ||
    $price === '' ||
    $owner === ''
) {

    echo json_encode([
        "status" => "empty"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| IMAGE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $image === '' ||
    strpos(
        $image,
        "raw.githubusercontent.com"
    ) === false
) {

    echo json_encode([
        "status" => "invalid_image"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| NUMERIC VALIDATION
|--------------------------------------------------------------------------
*/

if (!is_numeric($price)) {

    echo json_encode([
        "status" => "invalid_price"
    ]);

    exit;
}

if (
    $price6 !== '' &&
    !is_numeric($price6)
) {

    echo json_encode([
        "status" => "invalid_price6"
    ]);

    exit;
}

if (
    $price12 !== '' &&
    !is_numeric($price12)
) {

    echo json_encode([
        "status" => "invalid_price12"
    ]);

    exit;
}

if (
    !is_numeric($quantity) ||
    (int)$quantity < 1
) {

    echo json_encode([
        "status" => "invalid_quantity"
    ]);

    exit;
}

if (
    $deposit !== '' &&
    !is_numeric($deposit)
) {

    echo json_encode([
        "status" => "invalid_deposit"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| INSERT VEHICLE
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO vehicles
    (
        owner_phone,
        vehicle_name,
        vehicle_type,
        price_per_day,
        vehicle_image,
        city,
        address,
        quantity,
        deposit,
        price_6hr,
        price_12hr
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| BIND PARAMETERS
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "sssssssssss",
    $owner,
    $name,
    $type,
    $price,
    $image,
    $city,
    $address,
    $quantity,
    $deposit,
    $price6,
    $price12
);


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if (
    mysqli_stmt_execute($stmt)
) {

    echo json_encode([
        "status" => "success",
        "message" => "Vehicle added successfully",
        "vehicle_id" => mysqli_insert_id($conn)
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to add vehicle"
    ]);
}


mysqli_stmt_close($stmt);

?>
