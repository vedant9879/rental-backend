<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE VEHICLE DATA
|--------------------------------------------------------------------------
*/

$id       = trim($_POST['vehicle_id'] ?? '');
$name     = trim($_POST['vehicle_name'] ?? '');
$price    = trim($_POST['price_per_day'] ?? '');
$price6   = trim($_POST['price_6hr'] ?? '');
$price12  = trim($_POST['price_12hr'] ?? '');
$qty      = trim($_POST['quantity'] ?? '');
$deposit  = trim($_POST['deposit'] ?? '');
$city     = trim($_POST['city'] ?? '');
$address  = trim($_POST['address'] ?? '');


/*
|--------------------------------------------------------------------------
| CHECK VEHICLE ID
|--------------------------------------------------------------------------
*/

if ($id === '') {

    echo "Vehicle ID Missing";
    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $name === '' ||
    $price === '' ||
    $qty === ''
) {

    echo "Required Fields Missing";
    exit();
}


/*
|--------------------------------------------------------------------------
| VALIDATE NUMERIC VALUES
|--------------------------------------------------------------------------
*/

if (!is_numeric($price)) {

    echo "Invalid Daily Price";
    exit();
}

if ($price6 !== '' && !is_numeric($price6)) {

    echo "Invalid 6 Hour Price";
    exit();
}

if ($price12 !== '' && !is_numeric($price12)) {

    echo "Invalid 12 Hour Price";
    exit();
}

if (!is_numeric($qty) || (int)$qty < 1) {

    echo "Invalid Quantity";
    exit();
}

if ($deposit !== '' && !is_numeric($deposit)) {

    echo "Invalid Deposit";
    exit();
}


/*
|--------------------------------------------------------------------------
| UPDATE VEHICLE
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE vehicles SET
        vehicle_name = ?,
        price_per_day = ?,
        price_6hr = ?,
        price_12hr = ?,
        quantity = ?,
        deposit = ?,
        city = ?,
        address = ?
    WHERE id = ?
";


$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo "Database Error";
    exit();
}


/*
|--------------------------------------------------------------------------
| BIND PARAMETERS
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "ssssssssi",
    $name,
    $price,
    $price6,
    $price12,
    $qty,
    $deposit,
    $city,
    $address,
    $id
);


/*
|--------------------------------------------------------------------------
| EXECUTE UPDATE
|--------------------------------------------------------------------------
*/

if (mysqli_stmt_execute($stmt)) {

    echo "Updated Successfully";

} else {

    echo "Update Failed";
}


mysqli_stmt_close($stmt);

?>
