<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$requestId = intval(
    $_POST['request_id'] ?? 0
);

$requesterPhone = trim(
    $_POST['requester_phone'] ?? ''
);

if (
    $requestId <= 0 ||
    $requesterPhone === ''
) {
    echo json_encode([
        "success" => false,
        "message" => "Request ID and requester phone are required"
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| Find request
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        requester_phone,
        owner_phone,
        vehicle_id,
        vehicle_name,
        pickup,
        drop_location,
        status
    FROM transport_requests
    WHERE id = ?
      AND requester_phone = ?
    LIMIT 1
";

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "is",
    $requestId,
    $requesterPhone
);

if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "success" => false,
        "message" => "Unable to verify transport request"
    ]);

    exit();
}

$result = mysqli_stmt_get_result($stmt);

$request = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Request not found
|--------------------------------------------------------------------------
*/

if (!$request) {

    echo json_encode([
        "success" => false,
        "message" => "Transport request not found"
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Current status
|--------------------------------------------------------------------------
*/

$currentStatus = strtolower(
    trim(
        $request['status'] ?? 'pending'
    )
);

/*
|--------------------------------------------------------------------------
| Customer can cancel ONLY pending request
|--------------------------------------------------------------------------
*/

if ($currentStatus !== "pending") {

    echo json_encode([
        "success" => false,
        "message" =>
            "This transport request cannot be cancelled because its current status is " .
            $currentStatus
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Update status
|--------------------------------------------------------------------------
*/

$updateSql = "
    UPDATE transport_requests
    SET
        status = 'cancelled',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
      AND requester_phone = ?
      AND status = 'pending'
    LIMIT 1
";

$updateStmt = mysqli_prepare(
    $conn,
    $updateSql
);

if (!$updateStmt) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare cancellation"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $updateStmt,
    "is",
    $requestId,
    $requesterPhone
);

if (!mysqli_stmt_execute($updateStmt)) {

    mysqli_stmt_close($updateStmt);

    echo json_encode([
        "success" => false,
        "message" => "Unable to cancel transport request"
    ]);

    exit();
}

$affectedRows =
    mysqli_stmt_affected_rows(
        $updateStmt
    );

mysqli_stmt_close($updateStmt);

if ($affectedRows <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Request could not be cancelled"
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Provider notification preference
|--------------------------------------------------------------------------
*/

$ownerPhone =
    $request['owner_phone'];

$vehicleName =
    $request['vehicle_name'];

$pickup =
    $request['pickup'];

$dropLocation =
    $request['drop_location'];

$notificationEnabled = true;

$prefSql = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$prefStmt = mysqli_prepare(
    $conn,
    $prefSql
);

if ($prefStmt) {

    mysqli_stmt_bind_param(
        $prefStmt,
        "s",
        $ownerPhone
    );

    if (mysqli_stmt_execute($prefStmt)) {

        $prefResult =
            mysqli_stmt_get_result(
                $prefStmt
            );

        $pref =
            mysqli_fetch_assoc(
                $prefResult
            );

        if ($pref) {

            $notificationEnabled =
                intval(
                    $pref['notification_booking']
                ) === 1;
        }
    }

    mysqli_stmt_close(
        $prefStmt
    );
}

/*
|--------------------------------------------------------------------------
| Notify provider
|--------------------------------------------------------------------------
*/

if ($notificationEnabled) {

    $title =
        "Transport Request Cancelled";

    $message =
        "Customer cancelled the transport request for " .
        $vehicleName .
        " from " .
        $pickup .
        " to " .
        $dropLocation .
        ".";

    $notificationSql = "
        INSERT INTO notifications
        (
            user_phone,
            title,
            message,
            type,
            is_read,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'booking',
            0,
            CURRENT_TIMESTAMP
        )
    ";

    $notificationStmt =
        mysqli_prepare(
            $conn,
            $notificationSql
        );

    if ($notificationStmt) {

        mysqli_stmt_bind_param(
            $notificationStmt,
            "sss",
            $ownerPhone,
            $title,
            $message
        );

        mysqli_stmt_execute(
            $notificationStmt
        );

        mysqli_stmt_close(
            $notificationStmt
        );
    }
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Transport request cancelled successfully",
    "request_id" => $requestId,
    "status" => "cancelled"
]);

?>
