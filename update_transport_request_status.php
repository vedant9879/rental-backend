<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// READ REQUEST DATA
// =====================================================

$requestId =
    intval($_POST['request_id'] ?? 0);

$ownerPhone =
    trim($_POST['owner_phone'] ?? '');

$status =
    strtolower(trim($_POST['status'] ?? ''));


// =====================================================
// VALIDATION
// =====================================================

if ($requestId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request ID"
    ]);
    exit();
}

if ($ownerPhone === '') {
    echo json_encode([
        "success" => false,
        "message" => "Owner phone is required"
    ]);
    exit();
}

if (
    $status !== 'accepted' &&
    $status !== 'rejected'
) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid status"
    ]);
    exit();
}


// =====================================================
// GET TRANSPORT REQUEST
// =====================================================

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
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $requestId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$request =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


// =====================================================
// REQUEST NOT FOUND
// =====================================================

if (!$request) {
    echo json_encode([
        "success" => false,
        "message" => "Transport request not found"
    ]);
    exit();
}


// =====================================================
// VERIFY OWNER
// =====================================================

if (
    trim($request['owner_phone']) !==
    $ownerPhone
) {
    echo json_encode([
        "success" => false,
        "message" => "You are not authorized to update this request"
    ]);
    exit();
}


// =====================================================
// PREVENT DUPLICATE ACTION
// =====================================================

$currentStatus =
    strtolower(
        trim($request['status'] ?? '')
    );

if (
    $currentStatus === 'accepted' ||
    $currentStatus === 'rejected'
) {
    echo json_encode([
        "success" => false,
        "message" =>
            "This transport request has already been " .
            $currentStatus
    ]);
    exit();
}


// =====================================================
// UPDATE STATUS
// =====================================================

$updateSql = "
    UPDATE transport_requests
    SET
        status = ?,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
      AND owner_phone = ?
    LIMIT 1
";

$updateStmt =
    mysqli_prepare(
        $conn,
        $updateSql
    );

if (!$updateStmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to update request"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $updateStmt,
    "sis",
    $status,
    $requestId,
    $ownerPhone
);

$updated =
    mysqli_stmt_execute($updateStmt);

mysqli_stmt_close($updateStmt);


if (!$updated) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update transport request"
    ]);
    exit();
}


// =====================================================
// NOTIFICATION TO CUSTOMER
// =====================================================

$requesterPhone =
    trim($request['requester_phone']);

$vehicleName =
    trim($request['vehicle_name']);

$pickup =
    trim($request['pickup']);

$dropLocation =
    trim($request['drop_location']);


// Check customer's booking notification preference

$preferenceSql = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$preferenceStmt =
    mysqli_prepare(
        $conn,
        $preferenceSql
    );

$sendNotification = true;

if ($preferenceStmt) {

    mysqli_stmt_bind_param(
        $preferenceStmt,
        "s",
        $requesterPhone
    );

    mysqli_stmt_execute(
        $preferenceStmt
    );

    $preferenceResult =
        mysqli_stmt_get_result(
            $preferenceStmt
        );

    $user =
        mysqli_fetch_assoc(
            $preferenceResult
        );

    if ($user) {
        $sendNotification =
            intval(
                $user['notification_booking']
            ) === 1;
    }

    mysqli_stmt_close(
        $preferenceStmt
    );
}


// =====================================================
// CREATE NOTIFICATION
// =====================================================

if (
    $sendNotification &&
    $requesterPhone !== ''
) {

    if ($status === 'accepted') {

        $notificationTitle =
            "Transport Request Accepted";

        $notificationMessage =
            "Your transport request for " .
            $vehicleName .
            " from " .
            $pickup .
            " to " .
            $dropLocation .
            " has been accepted.";

    } else {

        $notificationTitle =
            "Transport Request Rejected";

        $notificationMessage =
            "Your transport request for " .
            $vehicleName .
            " from " .
            $pickup .
            " to " .
            $dropLocation .
            " has been rejected.";
    }


    $notificationType =
        "booking";


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
        (?, ?, ?, ?, 0, CURRENT_TIMESTAMP)
    ";

    $notificationStmt =
        mysqli_prepare(
            $conn,
            $notificationSql
        );

    if ($notificationStmt) {

        mysqli_stmt_bind_param(
            $notificationStmt,
            "ssss",
            $requesterPhone,
            $notificationTitle,
            $notificationMessage,
            $notificationType
        );

        mysqli_stmt_execute(
            $notificationStmt
        );

        mysqli_stmt_close(
            $notificationStmt
        );
    }
}


// =====================================================
// SUCCESS RESPONSE
// =====================================================

echo json_encode([
    "success" => true,
    "message" =>
        $status === 'accepted'
            ? "Transport request accepted"
            : "Transport request rejected",

    "request_id" => $requestId,

    "status" => $status
]);

?>
