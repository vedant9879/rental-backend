<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE PROFILE DATA
|--------------------------------------------------------------------------
*/

$name    = trim($_POST['name'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$email   = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$city    = trim($_POST['city'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');
$aadhar  = trim($_POST['aadhar'] ?? '');
$license = trim($_POST['license'] ?? '');


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($name === '' || $phone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "Name and phone are required"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| UPDATE USER
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE users
    SET
        name = ?,
        email = ?,
        address = ?,
        city = ?,
        pincode = ?,
        aadhar_number = ?,
        license_number = ?
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
| BIND PARAMETERS
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "ssssssss",
    $name,
    $email,
    $address,
    $city,
    $pincode,
    $aadhar,
    $license,
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
        "message" => "Profile Updated Successfully"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Profile Update Failed"
    ]);
}


mysqli_stmt_close($stmt);

?>
