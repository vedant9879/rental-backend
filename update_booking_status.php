<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE DATA
|--------------------------------------------------------------------------
*/

$id = trim($_POST['booking_id'] ?? '');
$status = trim($_POST['status'] ?? '');


/*
|--------------------------------------------------------------------------
| VALIDATE INPUT
|--------------------------------------------------------------------------
*/

if ($id === '' || $status === '') {

    echo "Missing Data";
    exit();
}


/*
|--------------------------------------------------------------------------
| ALLOWED STATUS VALUES
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    "pending",
    "accepted",
    "cancelled",
    "completed"
];

if (!in_array($status, $allowedStatuses, true)) {

    echo "Invalid Status";
    exit();
}


/*
|--------------------------------------------------------------------------
| GET CURRENT BOOKING
|--------------------------------------------------------------------------
*/

$sqlBooking = "
    SELECT
        id,
        vehicle_id,
        quantity,
        status
    FROM bookings
    WHERE id = ?
    LIMIT 1
";

$stmtBooking = mysqli_prepare($conn, $sqlBooking);

if (!$stmtBooking) {

    echo "Database Error";
    exit();
}

mysqli_stmt_bind_param(
    $stmtBooking,
    "i",
    $id
);

mysqli_stmt_execute($stmtBooking);

$resultBooking =
    mysqli_stmt_get_result($stmtBooking);

if (
    !$resultBooking ||
    mysqli_num_rows($resultBooking) === 0
) {

    mysqli_stmt_close($stmtBooking);

    echo "Booking Not Found";
    exit();
}

$booking = mysqli_fetch_assoc($resultBooking);

$currentStatus = $booking['status'];

mysqli_stmt_close($stmtBooking);


/*
|--------------------------------------------------------------------------
| CHECK STATUS TRANSITION
|--------------------------------------------------------------------------
*/

/*
| If the booking already has the requested status,
| there is nothing to change.
*/

if ($currentStatus === $status) {

    echo "success";
    exit();
}


/*
|--------------------------------------------------------------------------
| PREVENT INVALID TRANSITIONS
|--------------------------------------------------------------------------
*/

/*
| Cancelled bookings cannot be accepted/completed again.
*/

if ($currentStatus === "cancelled") {

    echo "Booking Already Cancelled";
    exit();
}


/*
| Completed bookings cannot be changed.
*/

if ($currentStatus === "completed") {

    echo "Booking Already Completed";
    exit();
}


/*
|--------------------------------------------------------------------------
| ACCEPT BOOKING
|--------------------------------------------------------------------------
*/

if ($status === "accepted") {

    /*
    | Only pending bookings can be accepted.
    */

    if ($currentStatus !== "pending") {

        echo "Only Pending Booking Can Be Accepted";
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK VEHICLE AVAILABILITY
    |--------------------------------------------------------------------------
    |
    | We calculate availability from existing pending/accepted
    | bookings instead of permanently changing vehicles.quantity.
    |
    */

    $vehicleId = (int)$booking['vehicle_id'];
    $requestedQty = (int)$booking['quantity'];


    /*
    | Get vehicle stock
    */

    $sqlVehicle = "
        SELECT quantity
        FROM vehicles
        WHERE id = ?
        LIMIT 1
    ";

    $stmtVehicle =
        mysqli_prepare($conn, $sqlVehicle);

    if (!$stmtVehicle) {

        echo "Database Error";
        exit();
    }

    mysqli_stmt_bind_param(
        $stmtVehicle,
        "i",
        $vehicleId
    );

    mysqli_stmt_execute($stmtVehicle);

    $resultVehicle =
        mysqli_stmt_get_result($stmtVehicle);

    if (
        !$resultVehicle ||
        mysqli_num_rows($resultVehicle) === 0
    ) {

        mysqli_stmt_close($stmtVehicle);

        echo "Vehicle Not Found";
        exit();
    }

    $vehicle = mysqli_fetch_assoc($resultVehicle);

    $stock = (int)$vehicle['quantity'];

    mysqli_stmt_close($stmtVehicle);


    /*
    |--------------------------------------------------------------------------
    | GET OTHER RESERVED QUANTITY
    |--------------------------------------------------------------------------
    |
    | The booking's own quantity is excluded because we are
    | checking whether this booking can safely become accepted.
    |
    */

    $sqlReserved = "
        SELECT
            IFNULL(SUM(quantity), 0) AS reserved
        FROM bookings
        WHERE vehicle_id = ?
        AND id != ?
        AND status = 'accepted'
    ";

    $stmtReserved =
        mysqli_prepare($conn, $sqlReserved);

    if (!$stmtReserved) {

        echo "Database Error";
        exit();
    }

    mysqli_stmt_bind_param(
        $stmtReserved,
        "ii",
        $vehicleId,
        $id
    );

    mysqli_stmt_execute($stmtReserved);

    $resultReserved =
        mysqli_stmt_get_result($stmtReserved);

    $reservedData =
        mysqli_fetch_assoc($resultReserved);

    $reserved =
        (int)($reservedData['reserved'] ?? 0);

    mysqli_stmt_close($stmtReserved);


    /*
    |--------------------------------------------------------------------------
    | AVAILABLE STOCK
    |--------------------------------------------------------------------------
    */

    $available = $stock - $reserved;

    if ($available < 0) {
        $available = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL AVAILABILITY CHECK
    |--------------------------------------------------------------------------
    */

    if ($requestedQty > $available) {

        echo "Not Enough Stock";
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
";

$stmtUpdate =
    mysqli_prepare($conn, $sqlUpdate);

if (!$stmtUpdate) {

    echo "Database Error";
    exit();
}

mysqli_stmt_bind_param(
    $stmtUpdate,
    "si",
    $status,
    $id
);


/*
|--------------------------------------------------------------------------
| EXECUTE UPDATE
|--------------------------------------------------------------------------
*/

if (mysqli_stmt_execute($stmtUpdate)) {

    echo "success";

} else {

    echo "Booking Status Update Failed";
}


mysqli_stmt_close($stmtUpdate);

?>
