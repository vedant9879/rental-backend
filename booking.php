<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| GET BOOKING DATA
|--------------------------------------------------------------------------
*/

$userPhone = trim($_POST['user_phone'] ?? '');
$vehicleId = trim($_POST['vehicle_id'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$totalPrice = trim($_POST['total_price'] ?? '');
$quantity = trim($_POST['quantity'] ?? '1');
$bookingPlan = trim($_POST['booking_plan'] ?? 'Daily Booking');
$paymentMode = trim($_POST['payment_mode'] ?? 'Cash on Delivery');


/*
|--------------------------------------------------------------------------
| VALIDATE REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $userPhone === '' ||
    $vehicleId === '' ||
    $startDate === '' ||
    $endDate === '' ||
    $totalPrice === ''
) {

    echo json_encode([
        "status" => "error",
        "message" => "Required booking fields are missing"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| VALIDATE NUMBERS
|--------------------------------------------------------------------------
*/

if (
    !ctype_digit($vehicleId) ||
    !ctype_digit($quantity)
) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle or quantity"
    ]);

    exit();
}


$vehicleId = (int)$vehicleId;
$quantity = (int)$quantity;
$totalPrice = (float)$totalPrice;


if ($quantity <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Quantity must be greater than zero"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET VEHICLE INFORMATION
|--------------------------------------------------------------------------
*/

$sqlVehicle = "
    SELECT
        id,
        vehicle_name,
        owner_phone,
        quantity AS stock
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";


$stmtVehicle = mysqli_prepare(
    $conn,
    $sqlVehicle
);


if (!$stmtVehicle) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtVehicle,
    "i",
    $vehicleId
);


mysqli_stmt_execute(
    $stmtVehicle
);


$resultVehicle = mysqli_stmt_get_result(
    $stmtVehicle
);


if (
    !$resultVehicle ||
    mysqli_num_rows($resultVehicle) === 0
) {

    mysqli_stmt_close(
        $stmtVehicle
    );

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle not found"
    ]);

    exit();
}


$vehicle = mysqli_fetch_assoc(
    $resultVehicle
);


mysqli_stmt_close(
    $stmtVehicle
);


/*
|--------------------------------------------------------------------------
| VALIDATE VEHICLE OWNER
|--------------------------------------------------------------------------
*/

$ownerPhone = trim(
    $vehicle['owner_phone'] ?? ''
);


if ($ownerPhone === '') {

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle owner information is missing"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK ALREADY RESERVED QUANTITY
|--------------------------------------------------------------------------
|
| Only pending and accepted bookings reserve vehicles.
|
*/

$sqlOverlap = "
    SELECT
        IFNULL(SUM(quantity), 0) AS used
    FROM bookings
    WHERE vehicle_id = ?
    AND status IN ('pending', 'accepted')
    AND start_date <= ?
    AND end_date >= ?
";


$stmtOverlap = mysqli_prepare(
    $conn,
    $sqlOverlap
);


if (!$stmtOverlap) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtOverlap,
    "iss",
    $vehicleId,
    $endDate,
    $startDate
);


mysqli_stmt_execute(
    $stmtOverlap
);


$resultOverlap = mysqli_stmt_get_result(
    $stmtOverlap
);


$used = 0;


if ($resultOverlap) {

    $row = mysqli_fetch_assoc(
        $resultOverlap
    );

    $used = (int)(
        $row['used'] ?? 0
    );
}


mysqli_stmt_close(
    $stmtOverlap
);


/*
|--------------------------------------------------------------------------
| CALCULATE AVAILABLE QUANTITY
|--------------------------------------------------------------------------
*/

$stock = (int)$vehicle['stock'];

$available = $stock - $used;


if ($quantity > $available) {

    echo json_encode([
        "status" => "error",
        "message" =>
            "Only " .
            max(0, $available) .
            " vehicle(s) available for these dates"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| INSERT BOOKING
|--------------------------------------------------------------------------
|
| owner_phone is stored with the booking.
|
*/

$sqlBooking = "
    INSERT INTO bookings
    (
        user_phone,
        owner_phone,
        vehicle_id,
        start_date,
        end_date,
        total_price,
        quantity,
        booking_plan,
        payment_mode,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
";


$stmtBooking = mysqli_prepare(
    $conn,
    $sqlBooking
);


if (!$stmtBooking) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtBooking,
    "ssissdiss",
    $userPhone,
    $ownerPhone,
    $vehicleId,
    $startDate,
    $endDate,
    $totalPrice,
    $quantity,
    $bookingPlan,
    $paymentMode
);


if (!mysqli_stmt_execute($stmtBooking)) {

    mysqli_stmt_close(
        $stmtBooking
    );

    echo json_encode([
        "status" => "error",
        "message" => "Booking failed"
    ]);

    exit();
}


$bookingId = mysqli_insert_id(
    $conn
);


mysqli_stmt_close(
    $stmtBooking
);


/*
|--------------------------------------------------------------------------
| CHECK OWNER NOTIFICATION PREFERENCE
|--------------------------------------------------------------------------
|
| Notification preference is stored in the users table.
|
| notification_booking = 1
| → Booking notification is ON
|
| notification_booking = 0
| → Booking notification is OFF
|
| If the owner record or preference cannot be found,
| notification remains ON by default.
|
*/

$sendNotification = true;


$sqlPreference = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";


$stmtPreference = mysqli_prepare(
    $conn,
    $sqlPreference
);


if ($stmtPreference) {

    mysqli_stmt_bind_param(
        $stmtPreference,
        "s",
        $ownerPhone
    );

    mysqli_stmt_execute(
        $stmtPreference
    );

    $resultPreference = mysqli_stmt_get_result(
        $stmtPreference
    );

    if (
        $resultPreference &&
        mysqli_num_rows($resultPreference) > 0
    ) {

        $preference = mysqli_fetch_assoc(
            $resultPreference
        );

        $sendNotification =
            ((int)(
                $preference['notification_booking'] ?? 1
            ) === 1);
    }

    mysqli_stmt_close(
        $stmtPreference
    );
}


/*
|--------------------------------------------------------------------------
| CREATE OWNER NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($sendNotification) {

    $title = "New Booking Request";

    $message =
        "A customer has requested to book your " .
        $vehicle['vehicle_name'] .
        ".";

    $type = "booking";


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


    $stmtNotification = mysqli_prepare(
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
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "success",
    "message" => "Booking request submitted successfully",
    "booking_id" => $bookingId,
    "owner_phone" => $ownerPhone,
    "notification_sent" => $sendNotification
]);

?>
