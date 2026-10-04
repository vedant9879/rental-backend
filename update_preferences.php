<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE DATA
|--------------------------------------------------------------------------
*/

$phone = trim($_POST['phone'] ?? '');

$notification_booking =
    isset($_POST['notification_booking'])
        ? (int)$_POST['notification_booking']
        : 1;

$notification_promotions =
    isset($_POST['notification_promotions'])
        ? (int)$_POST['notification_promotions']
        : 1;

$notification_support =
    isset($_POST['notification_support'])
        ? (int)$_POST['notification_support']
        : 1;

$notification_security =
    isset($_POST['notification_security'])
        ? (int)$_POST['notification_security']
        : 1;

$notification_general =
    isset($_POST['notification_general'])
        ? (int)$_POST['notification_general']
        : 1;

$preferred_vehicle_type =
    trim($_POST['preferred_vehicle_type'] ?? 'Any Vehicle');


/*
|--------------------------------------------------------------------------
| VALIDATE PHONE
|--------------------------------------------------------------------------
*/

if ($phone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "Phone number is required"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| VALIDATE NOTIFICATION VALUES
|--------------------------------------------------------------------------
*/

$notification_booking =
    $notification_booking ? 1 : 0;

$notification_promotions =
    $notification_promotions ? 1 : 0;

$notification_support =
    $notification_support ? 1 : 0;

$notification_security =
    $notification_security ? 1 : 0;

$notification_general =
    $notification_general ? 1 : 0;


/*
|--------------------------------------------------------------------------
| VALIDATE VEHICLE TYPE
|--------------------------------------------------------------------------
*/

$allowed_vehicle_types = [
    "Any Vehicle",
    "Car",
    "Bike",
    "Scooter",
    "SUV"
];

if (!in_array(
    $preferred_vehicle_type,
    $allowed_vehicle_types,
    true
)) {

    $preferred_vehicle_type = "Any Vehicle";
}


/*
|--------------------------------------------------------------------------
| UPDATE PREFERENCES
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE users
    SET
        notification_booking = ?,
        notification_promotions = ?,
        notification_support = ?,
        notification_security = ?,
        notification_general = ?,
        preferred_vehicle_type = ?
    WHERE phone = ?
";


$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| BIND
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "iiiiiss",
    $notification_booking,
    $notification_promotions,
    $notification_support,
    $notification_security,
    $notification_general,
    $preferred_vehicle_type,
    $phone
);


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if (mysqli_stmt_execute($stmt)) {

    echo json_encode([
        "status" => "success",
        "message" => "Preferences Updated Successfully"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Preferences Update Failed"
    ]);
}


mysqli_stmt_close($stmt);

?>
