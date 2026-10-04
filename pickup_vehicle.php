<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$bookingId = trim($_POST['booking_id'] ?? '');
$condition = trim($_POST['pickup_condition'] ?? '');

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
 * Get booking information.
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

$stmtBooking = mysqli_prepare($conn, $sqlBooking);

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

mysqli_stmt_execute($stmtBooking);

$resultBooking = mysqli_stmt_get_result($stmtBooking);

if (
    !$resultBooking ||
    mysqli_num_rows($resultBooking) === 0
) {
    mysqli_stmt_close($stmtBooking);

    echo json_encode([
        "success" => false,
        "message" => "Booking not found"
    ]);

    exit();
}

$booking = mysqli_fetch_assoc($resultBooking);

mysqli_stmt_close($stmtBooking);


/*
 * Pickup is allowed only for accepted bookings.
 */
$currentStatus = strtolower(
    trim($booking['status'] ?? '')
);

if (
    $currentStatus !== 'accepted' &&
    $currentStatus !== 'confirmed' &&
    $currentStatus !== 'approved'
) {
    echo json_encode([
        "success" => false,
        "message" =>
            "Vehicle can be picked up only after booking is accepted"
    ]);

    exit();
}


/*
 * Record pickup.
 */
$pickupTime = date("Y-m-d H:i:s");

$sqlUpdate = "
    UPDATE bookings
    SET
        pickup_time = ?,
        pickup_condition = ?,
        pickup_confirmed = 1,
        status = 'picked_up'
    WHERE id = ?
";

$stmtUpdate = mysqli_prepare($conn, $sqlUpdate);

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
    $pickupTime,
    $condition,
    $bookingId
);

if (!mysqli_stmt_execute($stmtUpdate)) {
    mysqli_stmt_close($stmtUpdate);

    echo json_encode([
        "success" => false,
        "message" => "Unable to confirm pickup"
    ]);

    exit();
}

mysqli_stmt_close($stmtUpdate);


/*
 * Check renter's Booking Updates notification preference.
 *
 * notification_booking = 1
 *     -> create notification
 *
 * notification_booking = 0
 *     -> do not create notification
 *
 * If the preference cannot be read, default to ON
 * so existing notification behaviour is preserved.
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

    $userPhone = $booking['user_phone'];

    mysqli_stmt_bind_param(
        $stmtPreference,
        "s",
        $userPhone
    );

    if (mysqli_stmt_execute($stmtPreference)) {

        $resultPreference =
            mysqli_stmt_get_result($stmtPreference);

        if (
            $resultPreference &&
            mysqli_num_rows($resultPreference) > 0
        ) {

            $preference =
                mysqli_fetch_assoc($resultPreference);

            $sendNotification =
                ((int)($preference['notification_booking'] ?? 1) === 1);
        }
    }

    mysqli_stmt_close($stmtPreference);
}


/*
 * Notify renter only when Booking Updates are enabled.
 */
$notificationSent = false;

if ($sendNotification) {

    $title = "Vehicle Picked Up";

    $message =
        "Your " .
        ($booking['vehicle_name'] ?? 'vehicle') .
        " has been marked as picked up.";

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

        if (mysqli_stmt_execute($stmtNotification)) {
            $notificationSent = true;
        }

        mysqli_stmt_close($stmtNotification);
    }
}


/*
 * Final response.
 */
echo json_encode([
    "success" => true,
    "message" => "Vehicle pickup confirmed successfully",
    "booking_id" => $bookingId,
    "status" => "picked_up",
    "pickup_time" => $pickupTime,
    "notification_sent" => $notificationSent
]);

?>
