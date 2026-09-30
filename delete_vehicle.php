<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$vehicleId = trim($_POST['vehicle_id'] ?? '');

if ($vehicleId === '' || !ctype_digit($vehicleId)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid vehicle ID"
    ]);

    exit();
}

$vehicleId = (int)$vehicleId;


/*
 * Get vehicle information before deleting.
 */

$sqlVehicle = "
    SELECT
        vehicle_name,
        owner_phone
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmtVehicle = mysqli_prepare(
    $conn,
    $sqlVehicle
);

if (!$stmtVehicle) {

    echo json_encode([
        "success" => false,
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

$resultVehicle = mysqli_stmt_get_result(
    $stmtVehicle
);

if (
    !$resultVehicle ||
    mysqli_num_rows($resultVehicle) === 0
) {

    mysqli_stmt_close($stmtVehicle);

    echo json_encode([
        "success" => false,
        "message" => "Vehicle not found"
    ]);

    exit();
}

$vehicle = mysqli_fetch_assoc(
    $resultVehicle
);

mysqli_stmt_close(
    $stmtVehicle
);


/*
 * Delete vehicle.
 */

$sqlDelete = "
    DELETE FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmtDelete = mysqli_prepare(
    $conn,
    $sqlDelete
);

if (!$stmtDelete) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmtDelete,
    "i",
    $vehicleId
);

$deleted =
    mysqli_stmt_execute(
        $stmtDelete
    );

mysqli_stmt_close(
    $stmtDelete
);

if (!$deleted) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to remove vehicle"
    ]);

    exit();
}


/*
 * Notification.
 */

$title =
    "Vehicle Removed";

$message =
    $vehicle['vehicle_name'] .
    " has been removed from your RentX listings.";

$type =
    "vehicle";

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
        $vehicle['owner_phone'],
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


echo json_encode([
    "success" => true,
    "message" => "Vehicle removed successfully"
]);

?>
