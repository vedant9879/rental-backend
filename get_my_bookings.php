<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| GET USER PHONE
|--------------------------------------------------------------------------
*/

$user = trim($_GET['user_phone'] ?? '');

if ($user === '') {
    echo json_encode([]);
    exit();
}


/*
|--------------------------------------------------------------------------
| GET USER BOOKINGS
|--------------------------------------------------------------------------
|
| We join bookings with vehicles so the Android app receives
| the vehicle name along with booking information.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        b.id,
        b.vehicle_id,
        v.vehicle_name,
        v.vehicle_type,
        v.vehicle_image,
        v.city,
        b.user_phone,
        b.owner_phone,
        b.start_date,
        b.end_date,
        b.total_price,
        b.status,
        b.quantity,
        b.booking_plan,
        b.payment_mode

    FROM bookings b

    INNER JOIN vehicles v
        ON b.vehicle_id = v.id

    WHERE b.user_phone = ?

    ORDER BY b.id DESC
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
    $user
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/*
|--------------------------------------------------------------------------
| BUILD RESPONSE
|--------------------------------------------------------------------------
*/

$data = [];

while ($row = mysqli_fetch_assoc($result)) {

    /*
    | Make vehicle image URL complete
    */

    if (
        isset($row['vehicle_image']) &&
        $row['vehicle_image'] !== '' &&
        strpos($row['vehicle_image'], 'http') !== 0
    ) {

        $row['vehicle_image'] =
            "https://rental-backend-production-8cbf.up.railway.app/"
            . ltrim($row['vehicle_image'], "/");
    }


    /*
    | Safe defaults
    */

    $row['vehicle_type'] =
        $row['vehicle_type'] ?? '';

    $row['city'] =
        $row['city'] ?? '';

    $row['quantity'] =
        $row['quantity'] ?? '1';

    $row['total_price'] =
        $row['total_price'] ?? '0';

    $row['booking_plan'] =
        $row['booking_plan'] ?? '';

    $row['payment_mode'] =
        $row['payment_mode'] ?? '';

    $row['status'] =
        $row['status'] ?? 'pending';


    $data[] = $row;
}


/*
|--------------------------------------------------------------------------
| CLOSE STATEMENT
|--------------------------------------------------------------------------
*/

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| RETURN JSON
|--------------------------------------------------------------------------
*/

echo json_encode(
    $data,
    JSON_UNESCAPED_SLASHES
);

?>
