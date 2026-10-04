<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE DATA
|--------------------------------------------------------------------------
*/

$bookingId = trim($_POST['booking_id'] ?? '');
$newStatus = strtolower(trim($_POST['status'] ?? ''));


/*
|--------------------------------------------------------------------------
| VALIDATE INPUT
|--------------------------------------------------------------------------
*/

if ($bookingId === '' || $newStatus === '') {

    echo json_encode([
        "status" => "error",
        "message" => "Booking ID and status are required"
    ]);

    exit();
}


if (!ctype_digit($bookingId)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid booking ID"
    ]);

    exit();
}


$bookingId = (int)$bookingId;


$allowedStatuses = [
    "pending",
    "accepted",
    "cancelled",
    "completed"
];


if (!in_array($newStatus, $allowedStatuses, true)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid booking status"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET BOOKING INFORMATION
|--------------------------------------------------------------------------
|
| We need:
| - renter phone
| - vehicle name
| - vehicle ID
| - quantity
| - dates
| - current status
| - vehicle stock
|
*/

$sqlBooking = "
    SELECT
        b.id,
        b.user_phone,
        b.vehicle_id,
        b.quantity,
        b.start_date,
        b.end_date,
        b.status,
        v.vehicle_name,
        v.quantity AS vehicle_stock
    FROM bookings b
    INNER JOIN vehicles v
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
        "status" => "error",
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


$resultBooking =
    mysqli_stmt_get_result(
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
        "status" => "not_found",
        "message" => "Booking not found"
    ]);

    exit();
}


$booking =
    mysqli_fetch_assoc(
        $resultBooking
    );


mysqli_stmt_close(
    $stmtBooking
);


/*
|--------------------------------------------------------------------------
| CURRENT STATUS
|--------------------------------------------------------------------------
*/

$currentStatus =
    strtolower(
        trim(
            $booking['status']
        )
    );


/*
|--------------------------------------------------------------------------
| SAME STATUS CHECK
|--------------------------------------------------------------------------
*/

if ($currentStatus === $newStatus) {

    echo json_encode([
        "status" => "success",
        "message" => "Booking already has this status"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| COMPLETED / CANCELLED BOOKINGS
| CANNOT BE CHANGED AGAIN
|--------------------------------------------------------------------------
*/

if (
    $currentStatus === "cancelled" ||
    $currentStatus === "completed"
) {

    echo json_encode([
        "status" => "error",
        "message" => "This booking can no longer be changed"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| ONLY PENDING BOOKINGS CAN BE ACCEPTED
|--------------------------------------------------------------------------
*/

if (
    $newStatus === "accepted" &&
    $currentStatus !== "pending"
) {

    echo json_encode([
        "status" => "error",
        "message" => "Only pending bookings can be accepted"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK AVAILABLE VEHICLE QUANTITY
|--------------------------------------------------------------------------
|
| When accepting a booking, check the quantity
| already reserved by other accepted bookings.
|
*/

if ($newStatus === "accepted") {

    $sqlReserved = "
        SELECT
            IFNULL(
                SUM(quantity),
                0
            ) AS reserved
        FROM bookings
        WHERE vehicle_id = ?
        AND id != ?
        AND status = 'accepted'
        AND start_date <= ?
        AND end_date >= ?
    ";


    $stmtReserved =
        mysqli_prepare(
            $conn,
            $sqlReserved
        );


    if (!$stmtReserved) {

        echo json_encode([
            "status" => "error",
            "message" => "Database error"
        ]);

        exit();
    }


    mysqli_stmt_bind_param(
        $stmtReserved,
        "iiss",
        $booking['vehicle_id'],
        $bookingId,
        $booking['end_date'],
        $booking['start_date']
    );


    mysqli_stmt_execute(
        $stmtReserved
    );


    $resultReserved =
        mysqli_stmt_get_result(
            $stmtReserved
        );


    $reserved = 0;


    if ($resultReserved) {

        $reservedRow =
            mysqli_fetch_assoc(
                $resultReserved
            );


        $reserved =
            (int)(
                $reservedRow['reserved']
                ?? 0
            );
    }


    mysqli_stmt_close(
        $stmtReserved
    );


    $vehicleStock =
        (int)$booking['vehicle_stock'];


    $requestedQuantity =
        (int)$booking['quantity'];


    $available =
        $vehicleStock - $reserved;


    if (
        $requestedQuantity >
        $available
    ) {

        echo json_encode([
            "status" => "error",
            "message" =>
                "Not enough vehicles available for these dates"
        ]);

        exit();
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE BOOKING STATUS
|--------------------------------------------------------------------------
*/

$sqlUpdate = "
    UPDATE bookings
    SET status = ?
    WHERE id = ?
    LIMIT 1
";


$stmtUpdate =
    mysqli_prepare(
        $conn,
        $sqlUpdate
    );


if (!$stmtUpdate) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtUpdate,
    "si",
    $newStatus,
    $bookingId
);


$updated =
    mysqli_stmt_execute(
        $stmtUpdate
    );


mysqli_stmt_close(
    $stmtUpdate
);


if (!$updated) {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update booking"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| RENTER INFORMATION
|--------------------------------------------------------------------------
*/

$userPhone =
    trim(
        $booking['user_phone']
    );


$vehicleName =
    $booking['vehicle_name'];


/*
|--------------------------------------------------------------------------
| NOTIFICATION CONTENT
|--------------------------------------------------------------------------
*/

$title = "";
$message = "";
$type = "booking";


switch ($newStatus) {

    case "accepted":

        $title =
            "Booking Accepted";

        $message =
            "Your booking for " .
            $vehicleName .
            " has been accepted by the owner.";

        $type =
            "accepted";

        break;


    case "cancelled":

        $title =
            "Booking Cancelled";

        $message =
            "Your booking for " .
            $vehicleName .
            " has been cancelled.";

        $type =
            "cancelled";

        break;


    case "completed":

        $title =
            "Booking Completed";

        $message =
            "Your booking for " .
            $vehicleName .
            " has been completed.";

        $type =
            "booking";

        break;


    case "pending":

        $title =
            "Booking Updated";

        $message =
            "Your booking for " .
            $vehicleName .
            " is pending confirmation.";

        $type =
            "booking";

        break;
}


/*
|--------------------------------------------------------------------------
| CHECK RENTER BOOKING NOTIFICATION PREFERENCE
|--------------------------------------------------------------------------
|
| notification_booking = 1
| → Booking notifications ON
|
| notification_booking = 0
| → Booking notifications OFF
|
| Default remains ON if the preference cannot
| be found.
|
*/

$sendNotification = true;


$sqlPreference = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";


$stmtPreference =
    mysqli_prepare(
        $conn,
        $sqlPreference
    );


if ($stmtPreference) {

    mysqli_stmt_bind_param(
        $stmtPreference,
        "s",
        $userPhone
    );


    mysqli_stmt_execute(
        $stmtPreference
    );


    $resultPreference =
        mysqli_stmt_get_result(
            $stmtPreference
        );


    if (
        $resultPreference &&
        mysqli_num_rows($resultPreference) > 0
    ) {

        $preference =
            mysqli_fetch_assoc(
                $resultPreference
            );


        $sendNotification =
            (
                (int)(
                    $preference['notification_booking']
                    ?? 1
                ) === 1
            );
    }


    mysqli_stmt_close(
        $stmtPreference
    );
}


/*
|--------------------------------------------------------------------------
| CREATE BOOKING STATUS NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($sendNotification) {

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


    $stmtNotification =
        mysqli_prepare(
            $conn,
            $sqlNotification
        );


    if ($stmtNotification) {

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
}


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "success",
    "message" =>
        "Booking status updated successfully",
    "notification_sent" =>
        $sendNotification
]);

?>
