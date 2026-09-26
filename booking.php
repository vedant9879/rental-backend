<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=UTF-8");

/*
|--------------------------------------------------------------------------
| RECEIVE BOOKING DATA
|--------------------------------------------------------------------------
*/

$user    = trim($_POST['user_phone'] ?? '');
$vehicle = trim($_POST['vehicle_id'] ?? '');
$start   = trim($_POST['start_date'] ?? '');
$end     = trim($_POST['end_date'] ?? '');
$total   = trim($_POST['total_price'] ?? '');

$qty     = (int)($_POST['quantity'] ?? 1);
$plan    = trim($_POST['booking_plan'] ?? '1 Day');
$payment = trim($_POST['payment_mode'] ?? 'Cash on Delivery');

$status = "pending";


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $user === '' ||
    $vehicle === '' ||
    $start === '' ||
    $end === '' ||
    $total === ''
) {
    echo "Missing Data";
    exit();
}

if ($qty <= 0) {
    echo "Invalid Quantity";
    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK VEHICLE
|--------------------------------------------------------------------------
*/

$sqlVehicle = "
    SELECT owner_phone, quantity
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmtVehicle = mysqli_prepare($conn, $sqlVehicle);

if (!$stmtVehicle) {
    echo "Database Error";
    exit();
}

mysqli_stmt_bind_param(
    $stmtVehicle,
    "i",
    $vehicle
);

mysqli_stmt_execute($stmtVehicle);

$resultVehicle = mysqli_stmt_get_result($stmtVehicle);

if (!$resultVehicle || mysqli_num_rows($resultVehicle) === 0) {

    mysqli_stmt_close($stmtVehicle);

    echo "Vehicle Not Found";
    exit();
}

$vehicleData = mysqli_fetch_assoc($resultVehicle);

$owner = $vehicleData['owner_phone'];
$stock = (int)$vehicleData['quantity'];

mysqli_stmt_close($stmtVehicle);


/*
|--------------------------------------------------------------------------
| VALIDATE STOCK
|--------------------------------------------------------------------------
*/

if ($stock <= 0) {
    echo "Vehicle Not Available";
    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING BOOKINGS
|--------------------------------------------------------------------------
|
| A booking overlaps when:
|
| existing_start <= requested_end
| AND
| existing_end >= requested_start
|
| Only pending and accepted bookings block stock.
|--------------------------------------------------------------------------
*/

$sqlBooked = "
    SELECT IFNULL(SUM(quantity), 0) AS used
    FROM bookings
    WHERE vehicle_id = ?
    AND status IN ('pending', 'accepted')
    AND start_date <= ?
    AND end_date >= ?
";

$stmtBooked = mysqli_prepare($conn, $sqlBooked);

if (!$stmtBooked) {
    echo "Database Error";
    exit();
}

mysqli_stmt_bind_param(
    $stmtBooked,
    "iss",
    $vehicle,
    $end,
    $start
);

mysqli_stmt_execute($stmtBooked);

$resultBooked = mysqli_stmt_get_result($stmtBooked);

$bookedData = mysqli_fetch_assoc($resultBooked);

$used = (int)($bookedData['used'] ?? 0);

mysqli_stmt_close($stmtBooked);


/*
|--------------------------------------------------------------------------
| CALCULATE AVAILABLE STOCK
|--------------------------------------------------------------------------
*/

$available = $stock - $used;

if ($available < 0) {
    $available = 0;
}


/*
|--------------------------------------------------------------------------
| FINAL QUANTITY CHECK
|--------------------------------------------------------------------------
*/

if ($qty > $available) {

    echo "Only " . $available . " Available";

    exit();
}


/*
|--------------------------------------------------------------------------
| INSERT BOOKING
|--------------------------------------------------------------------------
*/

$sqlInsert = "
    INSERT INTO bookings
    (
        user_phone,
        owner_phone,
        vehicle_id,
        start_date,
        end_date,
        total_price,
        payment_mode,
        quantity,
        booking_plan,
        status
    )
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmtInsert = mysqli_prepare($conn, $sqlInsert);

if (!$stmtInsert) {
    echo "Database Error";
    exit();
}


/*
|--------------------------------------------------------------------------
| INSERT DATA
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmtInsert,
    "ssissssiss",
    $user,
    $owner,
    $vehicle,
    $start,
    $end,
    $total,
    $payment,
    $qty,
    $plan,
    $status
);


if (mysqli_stmt_execute($stmtInsert)) {

    echo "success";

} else {

    echo "Booking Failed";
}


mysqli_stmt_close($stmtInsert);

?>
