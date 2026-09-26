<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| GET PHONE
|--------------------------------------------------------------------------
*/

$phone = trim($_GET['phone'] ?? '');

if ($phone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "Phone number is required"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET USER PROFILE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        name,
        email,
        phone,
        role,
        address,
        city,
        pincode,
        aadhar_number,
        license_number,
        profile_image
    FROM users
    WHERE phone = ?
    LIMIT 1
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
| EXECUTE
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $phone
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/*
|--------------------------------------------------------------------------
| USER FOUND
|--------------------------------------------------------------------------
*/

if ($result && mysqli_num_rows($result) > 0) {

    $row = mysqli_fetch_assoc($result);


    /*
    |--------------------------------------------------------------------------
    | SAFE DEFAULT VALUES
    |--------------------------------------------------------------------------
    */

    $row['name'] =
        $row['name'] ?? '';

    $row['email'] =
        $row['email'] ?? '';

    $row['phone'] =
        $row['phone'] ?? '';

    $row['role'] =
        $row['role'] ?? '';

    $row['address'] =
        $row['address'] ?? '';

    $row['city'] =
        $row['city'] ?? '';

    $row['pincode'] =
        $row['pincode'] ?? '';

    $row['aadhar_number'] =
        $row['aadhar_number'] ?? '';

    $row['license_number'] =
        $row['license_number'] ?? '';

    $row['profile_image'] =
        $row['profile_image'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | PROFILE IMAGE URL
    |--------------------------------------------------------------------------
    */

    if (
        $row['profile_image'] !== '' &&
        strpos($row['profile_image'], 'http') !== 0
    ) {

        $row['profile_image'] =
            "https://rental-backend-production-8cbf.up.railway.app/"
            . ltrim($row['profile_image'], "/");
    }


    echo json_encode(
        $row,
        JSON_UNESCAPED_SLASHES
    );

} else {

    echo json_encode([
        "status" => "error",
        "message" => "User Not Found"
    ]);
}


mysqli_stmt_close($stmt);

?>
