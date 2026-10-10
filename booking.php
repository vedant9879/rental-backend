<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

mysqli_set_charset($conn, "utf8mb4");

/*
|--------------------------------------------------------------------------
| JSON RESPONSE HELPER
|--------------------------------------------------------------------------
*/

function sendResponse($status, $message, $extra = [])
{
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $extra));

    exit();
}

/*
|--------------------------------------------------------------------------
| GET BOOKING DATA
|--------------------------------------------------------------------------
*/

$userPhone  = trim($_POST['user_phone'] ?? '');
$vehicleId  = trim($_POST['vehicle_id'] ?? '');
$startDate  = trim($_POST['start_date'] ?? '');
$endDate    = trim($_POST['end_date'] ?? '');
$totalPrice = trim($_POST['total_price'] ?? '');
$quantity   = trim($_POST['quantity'] ?? '1');
$bookingPlan = trim($_POST['booking_plan'] ?? 'Daily Booking');
$paymentMode = trim($_POST['payment_mode'] ?? 'Cash on Delivery');

/*
|--------------------------------------------------------------------------
| NEW: BOOKING HANDOVER ADDRESS
|--------------------------------------------------------------------------
| These details belong to this booking, not the user's profile.
*/

$bookingAddress = trim($_POST['booking_address'] ?? '');
$bookingCity    = trim($_POST['booking_city'] ?? '');
$bookingPincode = trim($_POST['booking_pincode'] ?? '');

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
    sendResponse(
        "error",
        "Required booking fields are missing"
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATE NEW ADDRESS FIELDS
|--------------------------------------------------------------------------
*/

if (
    $bookingAddress === '' ||
    $bookingCity === '' ||
    $bookingPincode === ''
) {
    sendResponse(
        "error",
        "Please enter the booking address, city and pincode"
    );
}

if (strlen($bookingAddress) > 1000) {
    sendResponse("error", "Booking address is too long");
}

if (strlen($bookingCity) > 100) {
    sendResponse("error", "City name is too long");
}

/*
|--------------------------------------------------------------------------
| VALIDATE PINCODE
|--------------------------------------------------------------------------
| Accepts a six-digit Indian pincode.
*/

if (!preg_match('/^[1-9][0-9]{5}$/', $bookingPincode)) {
    sendResponse(
        "error",
        "Please enter a valid 6-digit pincode"
    );
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
    sendResponse(
        "error",
        "Invalid vehicle or quantity"
    );
}

if (
    !is_numeric($totalPrice) ||
    !is_finite((float)$totalPrice) ||
    (float)$totalPrice < 0
) {
    sendResponse(
        "error",
        "Invalid total price"
    );
}

$vehicleId = (int)$vehicleId;
$quantity = (int)$quantity;
$totalPrice = (float)$totalPrice;

if ($vehicleId <= 0) {
    sendResponse("error", "Invalid vehicle ID");
}

if ($quantity <= 0) {
    sendResponse(
        "error",
        "Quantity must be greater than zero"
    );
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

$stmtVehicle = mysqli_prepare($conn, $sqlVehicle);

if (!$stmtVehicle) {
    sendResponse("error", "Database error while checking vehicle");
}

mysqli_stmt_bind_param(
    $stmtVehicle,
    "i",
    $vehicleId
);

if (!mysqli_stmt_execute($stmtVehicle)) {
    mysqli_stmt_close($stmtVehicle);
    sendResponse("error", "Unable to check vehicle information");
}

$resultVehicle = mysqli_stmt_get_result($stmtVehicle);

if (
    !$resultVehicle ||
    mysqli_num_rows($resultVehicle) === 0
) {
    mysqli_stmt_close($stmtVehicle);
    sendResponse("error", "Vehicle not found");
}

$vehicle = mysqli_fetch_assoc($resultVehicle);

mysqli_stmt_close($stmtVehicle);

/*
|--------------------------------------------------------------------------
| VALIDATE VEHICLE OWNER
|--------------------------------------------------------------------------
*/

$ownerPhone = trim($vehicle['owner_phone'] ?? '');

if ($ownerPhone === '') {
    sendResponse(
        "error",
        "Vehicle owner information is missing"
    );
}

/*
|--------------------------------------------------------------------------
| CHECK ALREADY RESERVED QUANTITY
|--------------------------------------------------------------------------
| Only pending and accepted bookings reserve stock.
| The existing date-overlap logic is preserved.
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

$stmtOverlap = mysqli_prepare($conn, $sqlOverlap);

if (!$stmtOverlap) {
    sendResponse("error", "Database error while checking availability");
}

mysqli_stmt_bind_param(
    $stmtOverlap,
    "iss",
    $vehicleId,
    $endDate,
    $startDate
);

if (!mysqli_stmt_execute($stmtOverlap)) {
    mysqli_stmt_close($stmtOverlap);
    sendResponse("error", "Unable to check vehicle availability");
}

$resultOverlap = mysqli_stmt_get_result($stmtOverlap);

$used = 0;

if ($resultOverlap) {
    $row = mysqli_fetch_assoc($resultOverlap);
    $used = (int)($row['used'] ?? 0);
}

mysqli_stmt_close($stmtOverlap);

/*
|--------------------------------------------------------------------------
| CALCULATE AVAILABLE QUANTITY
|--------------------------------------------------------------------------
*/

$stock = (int)($vehicle['stock'] ?? 0);
$available = $stock - $used;

if ($quantity > $available) {
    sendResponse(
        "error",
        "Only " . max(0, $available) .
        " vehicle(s) available for these dates"
    );
}

/*
|--------------------------------------------------------------------------
| INSERT BOOKING AND ADDRESS
|--------------------------------------------------------------------------
| Both inserts are grouped in a transaction.
|
| IMPORTANT:
| - bookings must support transactions (normally InnoDB).
| - rental_booking_addresses must exist before this code runs.
*/

mysqli_begin_transaction($conn);

/*
|--------------------------------------------------------------------------
| INSERT ORIGINAL BOOKING RECORD
|--------------------------------------------------------------------------
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

$stmtBooking = mysqli_prepare($conn, $sqlBooking);

if (!$stmtBooking) {
    mysqli_rollback($conn);
    sendResponse("error", "Unable to prepare booking");
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
    mysqli_stmt_close($stmtBooking);
    mysqli_rollback($conn);
    sendResponse("error", "Booking failed");
}

$bookingId = mysqli_insert_id($conn);

mysqli_stmt_close($stmtBooking);

/*
|--------------------------------------------------------------------------
| INSERT NEW BOOKING ADDRESS
|--------------------------------------------------------------------------
*/

$sqlAddress = "
    INSERT INTO rental_booking_addresses
    (
        booking_id,
        address,
        city,
        pincode
    )
    VALUES (?, ?, ?, ?)
";

$stmtAddress = mysqli_prepare($conn, $sqlAddress);

if (!$stmtAddress) {
    mysqli_rollback($conn);
    sendResponse(
        "error",
        "Unable to save booking address. Please try again."
    );
}

mysqli_stmt_bind_param(
    $stmtAddress,
    "isss",
    $bookingId,
    $bookingAddress,
    $bookingCity,
    $bookingPincode
);

if (!mysqli_stmt_execute($stmtAddress)) {
    mysqli_stmt_close($stmtAddress);
    mysqli_rollback($conn);
    sendResponse(
        "error",
        "Unable to save booking address. Please try again."
    );
}

mysqli_stmt_close($stmtAddress);

/*
|--------------------------------------------------------------------------
| COMMIT BOOKING AND ADDRESS
|--------------------------------------------------------------------------
*/

if (!mysqli_commit($conn)) {
    mysqli_rollback($conn);
    sendResponse(
        "error",
        "Unable to complete booking. Please try again."
    );
}

/*
|--------------------------------------------------------------------------
| CHECK OWNER NOTIFICATION PREFERENCE
|--------------------------------------------------------------------------
| notification_booking = 1: ON
| notification_booking = 0: OFF
| Missing preference defaults to ON.
*/

$sendNotification = true;

$sqlPreference = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$stmtPreference = mysqli_prepare($conn, $sqlPreference);

if ($stmtPreference) {
    mysqli_stmt_bind_param(
        $stmtPreference,
        "s",
        $ownerPhone
    );

    if (mysqli_stmt_execute($stmtPreference)) {
        $resultPreference = mysqli_stmt_get_result($stmtPreference);

        if (
            $resultPreference &&
            mysqli_num_rows($resultPreference) > 0
        ) {
            $preference = mysqli_fetch_assoc($resultPreference);

            $sendNotification = (
                (int)($preference['notification_booking'] ?? 1) === 1
            );
        }
    }

    mysqli_stmt_close($stmtPreference);
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
        ($vehicle['vehicle_name'] ?? 'vehicle') .
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

        mysqli_stmt_execute($stmtNotification);
        mysqli_stmt_close($stmtNotification);
    }
}

/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

sendResponse(
    "success",
    "Booking request submitted successfully",
    [
        "booking_id" => $bookingId,
        "owner_phone" => $ownerPhone,
        "notification_sent" => $sendNotification
    ]
);

?>
