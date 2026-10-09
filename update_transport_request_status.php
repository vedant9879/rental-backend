<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// =========================================================
// INPUT
// =========================================================

$requestId = intval(
    $_POST['request_id'] ?? 0
);

$ownerPhone = trim(
    $_POST['owner_phone'] ?? ''
);

$newStatus = strtolower(trim($_POST['status'] ?? ''));
$finalFareInput = trim((string)($_POST['final_fare'] ?? ''));
$finalFare = null;

if ($newStatus === 'accepted') {
    if ($finalFareInput === '' || !is_numeric($finalFareInput)) {
        echo json_encode([
            'success' => false,
            'message' => 'Enter the agreed final fare to accept this request'
        ]);
        exit();
    }

    $finalFare = (float)$finalFareInput;
    if ($finalFare <= 0 || $finalFare > 100000000) {
        echo json_encode([
            'success' => false,
            'message' => 'Final fare must be greater than zero and within the allowed limit'
        ]);
        exit();
    }
}


// =========================================================
// VALIDATION
// =========================================================

if (
    $requestId <= 0 ||
    $ownerPhone === '' ||
    $newStatus === ''
) {

    echo json_encode([
        "success" => false,
        "message" => "Request ID, owner phone and status are required"
    ]);

    exit();
}


// =========================================================
// ALLOWED STATUSES
// =========================================================

$allowedStatuses = [
    "accepted",
    "rejected",
    "pickup",
    "in_transit",
    "delivered"
];

if (!in_array($newStatus, $allowedStatuses, true)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid transport status"
    ]);

    exit();
}


// =========================================================
// GET REQUEST
// =========================================================

$sql = "
    SELECT
        id,
        requester_phone,
        owner_phone,
        vehicle_id,
        vehicle_name,
        pickup,
        drop_location,
        goods,
        status
    FROM transport_requests
    WHERE id = ?
      AND owner_phone = ?
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
    $ownerPhone
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "success" => false,
        "message" => "Unable to verify transport request"
    ]);

    exit();
}


$result =
    mysqli_stmt_get_result($stmt);

$request =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$request) {

    echo json_encode([
        "success" => false,
        "message" => "Transport request not found"
    ]);

    exit();
}


// =========================================================
// CURRENT STATUS
// =========================================================

$currentStatus =
    strtolower(
        trim(
            $request['status'] ?? 'pending'
        )
    );


// =========================================================
// VALID STATUS FLOW
// =========================================================

$validTransition = false;


// pending → accepted / rejected
if (
    $currentStatus === "pending" &&
    (
        $newStatus === "accepted" ||
        $newStatus === "rejected"
    )
) {

    $validTransition = true;
}


// accepted → pickup
if (
    $currentStatus === "accepted" &&
    $newStatus === "pickup"
) {

    $validTransition = true;
}


// pickup → in_transit
if (
    $currentStatus === "pickup" &&
    $newStatus === "in_transit"
) {

    $validTransition = true;
}


// in_transit → delivered
if (
    $currentStatus === "in_transit" &&
    $newStatus === "delivered"
) {

    $validTransition = true;
}


// =========================================================
// CHECK TRANSITION
// =========================================================

if (!$validTransition) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Invalid status transition: " .
            $currentStatus .
            " → " .
            $newStatus
    ]);

    exit();
}


// =========================================================
// UPDATE STATUS
// =========================================================

if ($newStatus === "accepted") {
    $updateSql = "
        UPDATE transport_requests
        SET status = ?, final_fare = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND owner_phone = ?
        LIMIT 1
    ";
} else {
    $updateSql = "
        UPDATE transport_requests
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND owner_phone = ?
        LIMIT 1
    ";
}

$updateStmt = mysqli_prepare($conn, $updateSql);

if (!$updateStmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to update request. Ensure transport_requests.final_fare exists."
    ]);
    exit();
}

if ($newStatus === "accepted") {
    mysqli_stmt_bind_param(
        $updateStmt,
        "sdis",
        $newStatus,
        $finalFare,
        $requestId,
        $ownerPhone
    );
} else {
    mysqli_stmt_bind_param(
        $updateStmt,
        "sis",
        $newStatus,
        $requestId,
        $ownerPhone
    );
}


if (!mysqli_stmt_execute($updateStmt)) {

    mysqli_stmt_close($updateStmt);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update transport request"
    ]);

    exit();
}


mysqli_stmt_close($updateStmt);


// =========================================================
// NOTIFICATION PREFERENCE
// =========================================================

$requesterPhone =
    $request['requester_phone'];

$vehicleName =
    $request['vehicle_name'];

$pickup =
    $request['pickup'];

$dropLocation =
    $request['drop_location'];


// =========================================================
// NOTIFICATION CONTENT
// =========================================================

$title = "";
$message = "";

switch ($newStatus) {

    case "accepted":

        $title =
            "Transport Request Accepted";

        $message =
            "Your transport request for " .
            $vehicleName .
            " from " .
            $pickup .
            " to " .
            $dropLocation .
            " has been accepted.";

        break;


    case "rejected":

        $title =
            "Transport Request Rejected";

        $message =
            "Your transport request for " .
            $vehicleName .
            " from " .
            $pickup .
            " to " .
            $dropLocation .
            " has been rejected.";

        break;


    case "pickup":

        $title =
            "Transport Pickup Started";

        $message =
            "Pickup for your " .
            $vehicleName .
            " transport request has started.";

        break;


    case "in_transit":

        $title =
            "Goods In Transit";

        $message =
            "Your goods are now in transit from " .
            $pickup .
            " to " .
            $dropLocation .
            ".";

        break;


    case "delivered":

        $title =
            "Goods Delivered";

        $message =
            "Your goods have been delivered successfully.";

        break;
}


// =========================================================
// CHECK CUSTOMER NOTIFICATION SETTING
// =========================================================

$notificationEnabled = true;

$prefSql = "
    SELECT notification_booking
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$prefStmt =
    mysqli_prepare(
        $conn,
        $prefSql
    );

if ($prefStmt) {

    mysqli_stmt_bind_param(
        $prefStmt,
        "s",
        $requesterPhone
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


// =========================================================
// CREATE NOTIFICATION
// =========================================================

if (
    $notificationEnabled &&
    $title !== '' &&
    $message !== ''
) {

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
            $requesterPhone,
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


// =========================================================
// SUCCESS MESSAGE
// =========================================================

$responseMessage = "";

switch ($newStatus) {

    case "accepted":
        $responseMessage =
            "Transport request accepted";
        break;

    case "rejected":
        $responseMessage =
            "Transport request rejected";
        break;

    case "pickup":
        $responseMessage =
            "Pickup started";
        break;

    case "in_transit":
        $responseMessage =
            "Transport is now in transit";
        break;

    case "delivered":
        $responseMessage =
            "Transport marked as delivered";
        break;

    default:
        $responseMessage =
            "Transport request updated";
}


// =========================================================
// FINAL RESPONSE
// =========================================================

echo json_encode([
    "success" => true,
    "message" => $responseMessage,
    "request_id" => $requestId,
    "status" => $newStatus,
    "final_fare" => ($newStatus === "accepted") ? number_format($finalFare, 2, ".", "") : null,
    "currency" => "INR"
]);

?>
