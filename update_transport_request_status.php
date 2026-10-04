<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// =====================================================
// READ REQUEST DATA
// =====================================================

$requestId = intval($_POST['request_id'] ?? 0);

$ownerPhone = trim(
    $_POST['owner_phone'] ?? ''
);

$status = strtolower(
    trim($_POST['status'] ?? '')
);


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


// =====================================================
// ALLOWED STATUS VALUES
// =====================================================

$allowedStatuses = [
    "accepted",
    "rejected",
    "pickup",
    "in_transit",
    "delivered"
];

if (!in_array($status, $allowedStatuses, true)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid transport status"
    ]);

    exit();
}


// =====================================================
// GET CURRENT REQUEST
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

$result = mysqli_stmt_get_result($stmt);

$request = mysqli_fetch_assoc($result);

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
        "message" =>
            "You are not authorized to update this request"
    ]);

    exit();
}


// =====================================================
// CURRENT STATUS
// =====================================================

$currentStatus = strtolower(
    trim($request['status'] ?? '')
);


// =====================================================
// STATUS TRANSITION VALIDATION
// =====================================================

$validTransition = false;


// PENDING → ACCEPTED / REJECTED

if ($currentStatus === "pending") {

    if (
        $status === "accepted" ||
        $status === "rejected"
    ) {
        $validTransition = true;
    }
}


// ACCEPTED → PICKUP

elseif ($currentStatus === "accepted") {

    if ($status === "pickup") {
        $validTransition = true;
    }
}


// PICKUP → IN TRANSIT

elseif ($currentStatus === "pickup") {

    if ($status === "in_transit") {
        $validTransition = true;
    }
}


// IN TRANSIT → DELIVERED

elseif ($currentStatus === "in_transit") {

    if ($status === "delivered") {
        $validTransition = true;
    }
}


// ALREADY FINAL

elseif (
    $currentStatus === "rejected" ||
    $currentStatus === "delivered"
) {

    $validTransition = false;
}


if (!$validTransition) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid status transition: " .
            $currentStatus .
            " → " .
            $status
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

$updateStmt = mysqli_prepare(
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

$updated = mysqli_stmt_execute(
    $updateStmt
);

mysqli_stmt_close($updateStmt);


if (!$updated) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to update transport request"
    ]);

    exit();
}


// =====================================================
// CUSTOMER DETAILS
// =====================================================

$requesterPhone =
    trim($request['requester_phone']);

$vehicleName =
    trim($request['vehicle_name']);

$pickup =
    trim($request['pickup']);

$dropLocation =
    trim($request['drop_location']);


// =====================================================
// CHECK CUSTOMER NOTIFICATION PREFERENCE
// =====================================================

$sendNotification = true;

$preferenceSql = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$preferenceStmt = mysqli_prepare(
    $conn,
    $preferenceSql
);

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
// NOTIFICATION CONTENT
// =====================================================

$notificationTitle = "";
$notificationMessage = "";

switch ($status) {

    case "accepted":

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

        break;


    case "pickup":

        $notificationTitle =
            "Transport Pickup Started";

        $notificationMessage =
            "Pickup for your " .
            $vehicleName .
            " transport request from " .
            $pickup .
            " to " .
            $dropLocation .
            " has started.";

        break;


    case "in_transit":

        $notificationTitle =
            "Transport In Transit";

        $notificationMessage =
            "Your " .
            $vehicleName .
            " transport from " .
            $pickup .
            " to " .
            $dropLocation .
            " is now in transit.";

        break;


    case "delivered":

        $notificationTitle =
            "Transport Delivered";

        $notificationMessage =
            "Your " .
            $vehicleName .
            " transport request from " .
            $pickup .
            " to " .
            $dropLocation .
            " has been delivered.";

        break;


    case "rejected":

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

        break;
}


// =====================================================
// INSERT CUSTOMER NOTIFICATION
// =====================================================

if (
    $sendNotification &&
    $requesterPhone !== ''
) {

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
    "message" => "Transport request status updated",
    "request_id" => $requestId,
    "old_status" => $currentStatus,
    "status" => $status
]);

?>
