
<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

mysqli_set_charset($conn, "utf8mb4");

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
| Existing booking and vehicle information is preserved.
|
| NEW:
| rental_booking_addresses stores the handover address
| entered specifically for each rental booking.
|
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
        b.return_confirmed,

        /* NEW: CUSTOMER BOOKING ADDRESS */
        rba.address AS booking_address,
        rba.city AS booking_city,
        rba.pincode AS booking_pincode

    FROM bookings b

    INNER JOIN vehicles v
        ON b.vehicle_id = v.id

    LEFT JOIN rental_booking_addresses rba
        ON rba.booking_id = b.id

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

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to retrieve owner bookings"
    ]);

    exit();
}

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
    | EXISTING VEHICLE DEFAULTS
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
    | EXISTING PICKUP / RETURN DEFAULTS
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

    /*
    |--------------------------------------------------------------------------
    | NEW: BOOKING ADDRESS DEFAULTS
    |--------------------------------------------------------------------------
    */

    $row['booking_address'] =
        $row['booking_address'] ?? '';

    $row['booking_city'] =
        $row['booking_city'] ?? '';

    $row['booking_pincode'] =
        $row['booking_pincode'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADD BOOKING TO RESPONSE
    |--------------------------------------------------------------------------
    */

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
