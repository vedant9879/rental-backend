<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$vehicleId = trim($_POST['vehicle_id'] ?? '');
$userPhone = trim($_POST['user_phone'] ?? '');
$userName = trim($_POST['user_name'] ?? '');
$rating = trim($_POST['rating'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (
    $vehicleId === '' ||
    $userPhone === '' ||
    $userName === '' ||
    $rating === '' ||
    $comment === ''
) {
    echo json_encode([
        "status" => "error",
        "message" => "Required review fields are missing"
    ]);
    exit();
}

if (!ctype_digit($vehicleId)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle ID"
    ]);
    exit();
}

if (!is_numeric($rating)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid rating"
    ]);
    exit();
}

$vehicleId = (int)$vehicleId;
$rating = (float)$rating;

if ($rating < 1 || $rating > 5) {
    echo json_encode([
        "status" => "error",
        "message" => "Rating must be between 1 and 5"
    ]);
    exit();
}


/*
 * Get vehicle and owner information.
 */

$sqlVehicle = "
    SELECT
        id,
        vehicle_name,
        owner_phone
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmtVehicle =
    mysqli_prepare(
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

$resultVehicle =
    mysqli_stmt_get_result(
        $stmtVehicle
    );

if (
    !$resultVehicle ||
    mysqli_num_rows($resultVehicle) === 0
) {
    mysqli_stmt_close($stmtVehicle);

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle not found"
    ]);
    exit();
}

$vehicle =
    mysqli_fetch_assoc(
        $resultVehicle
    );

mysqli_stmt_close(
    $stmtVehicle
);


/*
 * Insert review.
 */

$sqlReview = "
    INSERT INTO reviews
    (
        vehicle_id,
        user_phone,
        user_name,
        rating,
        comment
    )
    VALUES (?, ?, ?, ?, ?)
";

$stmtReview =
    mysqli_prepare(
        $conn,
        $sqlReview
    );

if (!$stmtReview) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmtReview,
    "issds",
    $vehicleId,
    $userPhone,
    $userName,
    $rating,
    $comment
);

if (!mysqli_stmt_execute($stmtReview)) {
    mysqli_stmt_close($stmtReview);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to add review"
    ]);
    exit();
}

mysqli_stmt_close($stmtReview);


/*
 * Create notification for vehicle owner.
 */

$ownerPhone =
    $vehicle['owner_phone'];

$vehicleName =
    $vehicle['vehicle_name'];

$title =
    "New Vehicle Review";

$message =
    $userName .
    " rated your " .
    $vehicleName .
    " " .
    number_format($rating, 1) .
    "/5 and left a new review.";

$type =
    "review";


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


/*
 * Final response.
 */

echo json_encode([
    "status" => "success",
    "message" => "Review added successfully"
]);

?>
