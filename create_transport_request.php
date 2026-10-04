<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// RECEIVE REQUEST DATA
// =====================================================

$requesterPhone =
    trim($_POST['requester_phone'] ?? '');

$ownerPhone =
    trim($_POST['owner_phone'] ?? '');

$vehicleId =
    trim($_POST['vehicle_id'] ?? '');

$vehicleName =
    trim($_POST['vehicle_name'] ?? '');

$pickup =
    trim($_POST['pickup'] ?? '');

$dropLocation =
    trim($_POST['drop_location'] ?? '');

$goods =
    trim($_POST['goods'] ?? '');

$weight =
    trim($_POST['weight'] ?? '');

$vehicleRequired =
    trim($_POST['vehicle_required'] ?? '');

$transportDate =
    trim($_POST['transport_date'] ?? '');

$transportTime =
    trim($_POST['transport_time'] ?? '');

$baseFare =
    trim($_POST['base_fare'] ?? '0');

$minimumFare =
    trim($_POST['minimum_fare'] ?? '0');

$perKm =
    trim($_POST['per_km'] ?? '0');

$driverCharge =
    trim($_POST['driver_charge'] ?? '0');

$waitingCharge =
    trim($_POST['waiting_charge'] ?? '0');


// =====================================================
// VALIDATE REQUIRED DATA
// =====================================================

if (
    $requesterPhone === '' ||
    $ownerPhone === '' ||
    $vehicleId === '' ||
    $vehicleName === '' ||
    $pickup === '' ||
    $dropLocation === '' ||
    $goods === '' ||
    $weight === '' ||
    $vehicleRequired === '' ||
    $transportDate === '' ||
    $transportTime === ''
) {

    echo json_encode([
        "status" => "error",
        "message" => "Transport request data is incomplete"
    ]);

    exit();
}


// =====================================================
// VALIDATE VEHICLE ID
// =====================================================

if (!is_numeric($vehicleId)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle"
    ]);

    exit();
}


$vehicleId = (int)$vehicleId;


// =====================================================
// VALIDATE PRICING
// =====================================================

if (
    !is_numeric($baseFare) ||
    !is_numeric($minimumFare) ||
    !is_numeric($perKm) ||
    !is_numeric($driverCharge) ||
    !is_numeric($waitingCharge)
) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid transport pricing"
    ]);

    exit();
}


$baseFare =
    (float)$baseFare;

$minimumFare =
    (float)$minimumFare;

$perKm =
    (float)$perKm;

$driverCharge =
    (float)$driverCharge;

$waitingCharge =
    (float)$waitingCharge;


// =====================================================
// VERIFY VEHICLE
// =====================================================

$vehicleSql = "
    SELECT
        id,
        owner_phone,
        vehicle_name,
        service_type,
        transport_base_fare,
        transport_minimum_fare,
        transport_per_km,
        transport_driver_charge,
        transport_waiting_charge
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";


$vehicleStmt =
    mysqli_prepare(
        $conn,
        $vehicleSql
    );


if (!$vehicleStmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $vehicleStmt,
    "i",
    $vehicleId
);


mysqli_stmt_execute(
    $vehicleStmt
);


$vehicleResult =
    mysqli_stmt_get_result(
        $vehicleStmt
    );


if (
    !$vehicleResult ||
    mysqli_num_rows($vehicleResult) === 0
) {

    mysqli_stmt_close(
        $vehicleStmt
    );

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle not found"
    ]);

    exit();
}


$vehicle =
    mysqli_fetch_assoc(
        $vehicleResult
    );


mysqli_stmt_close(
    $vehicleStmt
);


// =====================================================
// VERIFY GOODS TRANSPORTATION
// =====================================================

if (
    !isset($vehicle['service_type']) ||
    strcasecmp(
        $vehicle['service_type'],
        'Goods Transportation'
    ) !== 0
) {

    echo json_encode([
        "status" => "error",
        "message" => "This vehicle is not available for goods transportation"
    ]);

    exit();
}


// =====================================================
// SECURITY
//
// Do not trust owner_phone/pricing sent by Android.
// Get them from the actual vehicle record.
// =====================================================

$ownerPhone =
    trim(
        $vehicle['owner_phone'] ?? ''
    );


$vehicleName =
    trim(
        $vehicle['vehicle_name'] ?? $vehicleName
    );


$baseFare =
    (float)(
        $vehicle['transport_base_fare'] ?? 0
    );


$minimumFare =
    (float)(
        $vehicle['transport_minimum_fare'] ?? 0
    );


$perKm =
    (float)(
        $vehicle['transport_per_km'] ?? 0
    );


$driverCharge =
    (float)(
        $vehicle['transport_driver_charge'] ?? 0
    );


$waitingCharge =
    (float)(
        $vehicle['transport_waiting_charge'] ?? 0
    );


// =====================================================
// PREVENT REQUESTING OWN VEHICLE
// =====================================================

if (
    $requesterPhone === $ownerPhone
) {

    echo json_encode([
        "status" => "error",
        "message" => "You cannot request your own vehicle"
    ]);

    exit();
}


// =====================================================
// INSERT TRANSPORT REQUEST
// =====================================================

$sql = "
    INSERT INTO transport_requests
    (
        requester_phone,
        owner_phone,
        vehicle_id,
        vehicle_name,

        pickup,
        drop_location,

        goods,
        weight,
        vehicle_required,

        transport_date,
        transport_time,

        base_fare,
        minimum_fare,
        per_km,
        driver_charge,
        waiting_charge,

        status
    )

    VALUES
    (
        ?, ?, ?, ?,

        ?, ?,

        ?, ?, ?,

        ?, ?,

        ?, ?, ?, ?, ?,

        'pending'
    )
";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to create transport request",
        "error" => mysqli_error($conn)
    ]);

    exit();
}


// =====================================================
// BIND
// =====================================================

mysqli_stmt_bind_param(
    $stmt,

    "ssissssssssddddd",

    $requesterPhone,
    $ownerPhone,
    $vehicleId,
    $vehicleName,

    $pickup,
    $dropLocation,

    $goods,
    $weight,
    $vehicleRequired,

    $transportDate,
    $transportTime,

    $baseFare,
    $minimumFare,
    $perKm,
    $driverCharge,
    $waitingCharge
);


// =====================================================
// EXECUTE
// =====================================================

if (!mysqli_stmt_execute($stmt)) {

    $error =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to create transport request",
        "error" => $error
    ]);

    exit();
}


$requestId =
    mysqli_insert_id($conn);


mysqli_stmt_close($stmt);


// =====================================================
// PROVIDER NOTIFICATION PREFERENCE
// =====================================================

$sendNotification = true;


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


if ($preferenceStmt) {

    mysqli_stmt_bind_param(
        $preferenceStmt,
        "s",
        $ownerPhone
    );


    mysqli_stmt_execute(
        $preferenceStmt
    );


    $preferenceResult =
        mysqli_stmt_get_result(
            $preferenceStmt
        );


    if ($preferenceResult) {

        $preferenceRow =
            mysqli_fetch_assoc(
                $preferenceResult
            );


        if ($preferenceRow !== null) {

            $sendNotification =
                (int)(
                    $preferenceRow[
                        'notification_booking'
                    ] ?? 1
                ) === 1;
        }
    }


    mysqli_stmt_close(
        $preferenceStmt
    );
}


// =====================================================
// NOTIFY VEHICLE PROVIDER
// =====================================================

if ($sendNotification) {

    $notificationTitle =
        "New Transport Request";


    $notificationMessage =
        "New request for " .
        $vehicleName .
        " from " .
        $pickup .
        " to " .
        $dropLocation;


    $notificationType =
        "booking";


    $notificationSql = "
        INSERT INTO notifications
        (
            user_phone,
            title,
            message,
            type,
            is_read
        )

        VALUES
        (?, ?, ?, ?, 0)
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

            $ownerPhone,
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
    "status" => "success",
    "message" => "Transport request sent successfully",
    "request_id" => $requestId,
    "vehicle_id" => $vehicleId,
    "vehicle_name" => $vehicleName,
    "status_value" => "pending"
]);

?>
