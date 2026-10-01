<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| GET OWNER PHONE
|--------------------------------------------------------------------------
*/

$owner = trim($_GET['owner_phone'] ?? '');

if ($owner === '') {

    echo json_encode([]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET OWNER BOOKINGS
|--------------------------------------------------------------------------
|
| The owner is identified from the VEHICLES table.
|
| bookings.vehicle_id
|        ↓
| vehicles.id
|        ↓
| vehicles.owner_phone
|
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
        v.owner_phone,

        b.user_phone,
        b.owner_phone,

        b.start_date,
        b.end_date,

        b.total_price,
        b.status,
        b.quantity,
        b.booking_plan,
        b.payment_mode,

        b.pickup_time,
        b.return_time,
        b.pickup_condition,
        b.return_condition,
        b.pickup_confirmed,
        b.return_confirmed

    FROM bookings b

    INNER JOIN vehicles v
        ON b.vehicle_id = v.id

    WHERE v.owner_phone = ?

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
| EXECUTE QUERY
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $owner
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
    |--------------------------------------------------------------------------
    | COMPLETE VEHICLE IMAGE URL
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | SAFE DEFAULT VALUES
    |--------------------------------------------------------------------------
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


    /*
    |--------------------------------------------------------------------------
    | PICKUP / RETURN DEFAULTS
    |--------------------------------------------------------------------------
    */

    $row['pickup_time'] =
        $row['pickup_time'] ?? null;

    $row['return_time'] =
        $row['return_time'] ?? null;

    $row['pickup_condition'] =
        $row['pickup_condition'] ?? '';

    $row['return_condition'] =
        $row['return_condition'] ?? '';

    $row['pickup_confirmed'] =
        $row['pickup_confirmed'] ?? '0';

    $row['return_confirmed'] =
        $row['return_confirmed'] ?? '0';


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
