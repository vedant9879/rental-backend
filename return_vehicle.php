<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$bookingId = trim($_POST['booking_id'] ?? '');
$condition = trim($_POST['return_condition'] ?? '');

if ($bookingId === '') {
    echo json_encode([
        "success" => false,
        "message" => "Booking ID is required"
    ]);
    exit();
}

if (!ctype_digit($bookingId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid booking ID"
    ]);
    exit();
}

$bookingId = (int)$bookingId;

if ($condition === '') {
    $condition = "Vehicle condition not specified";
}


/*
 * Get booking information
 */

$sqlBooking = "
    SELECT
        b.id,
        b.status,
        b.user_phone,
        b.owner_phone,
        b.vehicle_id,
        v.vehicle_name
    FROM bookings b
    LEFT JOIN vehicles v
        ON b.vehicle_id = v.id
    WHERE b.id = ?
    LIMIT 1
";

$stmtBooking = mysqli_prepare(
    $conn,
    $sqlBooking
);

if (!$stmtBooking) {
    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmtBooking,
    "i",
    $bookingId
);

mysqli_stmt_execute(
    $stmtBooking
);

$resultBooking = mysqli_stmt_get_result(
    $stmtBooking
);

if (
    !$resultBooking ||
    mysqli_num_rows($resultBooking) === 0
) {

    mysqli_stmt_close(
        $stmtBooking
    );

    echo json_encode([
        "success" => false,
        "message" => "Booking not found"
    ]);

    exit();
}

$booking = mysqli_fetch_assoc(
    $resultBooking
);

mysqli_stmt_close(
    $stmtBooking
);


/*
 * Return is allowed only after pickup.
 */

$currentStatus = strtolower(
    trim(
        $booking['status'] ?? ''
    )
);

if ($currentStatus !== 'picked_up') {

    echo json_encode([
        "success" => false,
        "message" =>
            "Vehicle can be returned only after pickup is confirmed"
    ]);

    exit();
}


/*
 * Record return.
 */

$returnTime = date(
    "Y-m-d H:i:s"
);

$sqlUpdate = "
    UPDATE bookings
    SET
        return_time = ?,
        return_condition = ?,
        return_confirmed = 1,
        status = 'completed'
    WHERE id = ?
";

$stmtUpdate = mysqli_prepare(
    $conn,
    $sqlUpdate
);

if (!$stmtUpdate) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmtUpdate,
    "ssi",
    $returnTime,
    $condition,
    $bookingId
);

if (!mysqli_stmt_execute($stmtUpdate)) {

    mysqli_stmt_close(
        $stmtUpdate
    );

    echo json_encode([
        "success" => false,
        "message" => "Unable to confirm vehicle return"
    ]);

    exit();
}

mysqli_stmt_close(
    $stmtUpdate
);


/*
 * Notify renter.
 */

$title =
    "Vehicle Returned";

$message =
    "Your " .
    ($booking['vehicle_name'] ?? 'vehicle') .
    " has been returned and the rental is completed.";

$type =
    "booking";


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

    $userPhone =
        $booking['user_phone'];

    mysqli_stmt_bind_param(
        $stmtNotification,
        "ssss",
        $userPhone,
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


/*
 * Final response.
 */

echo json_encode([
    "success" => true,
    "message" => "Vehicle returned successfully",
    "booking_id" => $bookingId,
    "status" => "completed",
    "return_time" => $returnTime
]);

?>
