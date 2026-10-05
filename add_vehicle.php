<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// RECEIVE NORMAL VEHICLE DATA
// =====================================================

$ownerPhone = trim($_POST['owner_phone'] ?? '');

$vehicleName = trim($_POST['vehicle_name'] ?? '');

$vehicleType = trim($_POST['vehicle_type'] ?? '');

$serviceType = trim(
    $_POST['service_type'] ?? 'Self Drive'
);


// =====================================================
// MARKETPLACE / LISTING TYPE
// =====================================================

$listingType = trim(
    $_POST['listing_type'] ?? 'Rent'
);

$sellingPrice = trim(
    $_POST['selling_price'] ?? '0'
);

$vehicleCondition = trim(
    $_POST['vehicle_condition'] ?? ''
);

$manufacturingYear = trim(
    $_POST['manufacturing_year'] ?? '0'
);

$kilometersDriven = trim(
    $_POST['kilometers_driven'] ?? '0'
);

$ownership = trim(
    $_POST['ownership'] ?? ''
);


// =====================================================
// RENTAL PRICING
// =====================================================

$pricePerDay = trim(
    $_POST['price_per_day'] ?? ''
);

$price6hr = trim(
    $_POST['price_6hr'] ?? '0'
);

$price12hr = trim(
    $_POST['price_12hr'] ?? '0'
);


// =====================================================
// LOCATION
// =====================================================

$city = trim(
    $_POST['city'] ?? ''
);

$address = trim(
    $_POST['address'] ?? ''
);


// =====================================================
// QUANTITY / DEPOSIT
// =====================================================

$quantity = trim(
    $_POST['quantity'] ?? ''
);

$deposit = trim(
    $_POST['deposit'] ?? '0'
);


// =====================================================
// VEHICLE IMAGE
// =====================================================

$vehicleImage = trim(
    $_POST['vehicle_image'] ?? ''
);


// =====================================================
// RECEIVE TRANSPORT PRICING
// =====================================================

$transportBaseFare =
    trim(
        $_POST['transport_base_fare'] ?? '0'
    );

$transportMinimumFare =
    trim(
        $_POST['transport_minimum_fare'] ?? '0'
    );

$transportPerKm =
    trim(
        $_POST['transport_per_km'] ?? '0'
    );

$transportDriverCharge =
    trim(
        $_POST['transport_driver_charge'] ?? '0'
    );

$transportWaitingCharge =
    trim(
        $_POST['transport_waiting_charge'] ?? '0'
    );


// =====================================================
// VALIDATE REQUIRED FIELDS
// =====================================================

if (
    $ownerPhone === '' ||
    $vehicleName === '' ||
    $vehicleType === '' ||
    $serviceType === '' ||
    $listingType === '' ||
    $pricePerDay === '' ||
    $city === '' ||
    $quantity === ''
) {

    echo json_encode([
        "status" => "error",
        "message" => "Required vehicle fields are missing"
    ]);

    exit();
}


// =====================================================
// VALIDATE LISTING TYPE
// =====================================================

$allowedListingTypes = [

    "Rent",
    "Sell",
    "Rent + Sell"
];

if (!in_array(
    $listingType,
    $allowedListingTypes,
    true
)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid listing type"
    ]);

    exit();
}


// =====================================================
// VALIDATE SERVICE TYPE
// =====================================================

$allowedServiceTypes = [

    "Self Drive",
    "With Driver",
    "Goods Transportation",
    "Self Drive + With Driver"
];

if (!in_array(
    $serviceType,
    $allowedServiceTypes,
    true
)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid service type"
    ]);

    exit();
}


// =====================================================
// VALIDATE NORMAL NUMERIC VALUES
// =====================================================

if (
    !is_numeric($pricePerDay) ||
    !is_numeric($price6hr) ||
    !is_numeric($price12hr) ||
    !is_numeric($quantity) ||
    !is_numeric($deposit)
) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid vehicle values"
    ]);

    exit();
}


// =====================================================
// CONVERT NORMAL VALUES
// =====================================================

$pricePerDay =
    (float)$pricePerDay;

$price6hr =
    (float)$price6hr;

$price12hr =
    (float)$price12hr;

$quantity =
    (int)$quantity;

$deposit =
    (float)$deposit;


// =====================================================
// QUANTITY VALIDATION
// =====================================================

if ($quantity <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Quantity must be at least 1"
    ]);

    exit();
}


// =====================================================
// PREVENT NEGATIVE NORMAL VALUES
// =====================================================

if (
    $pricePerDay < 0 ||
    $price6hr < 0 ||
    $price12hr < 0 ||
    $deposit < 0
) {

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle prices cannot be negative"
    ]);

    exit();
}


// =====================================================
// MARKETPLACE SELLING VALIDATION
// =====================================================

if (
    $listingType === "Sell" ||
    $listingType === "Rent + Sell"
) {

    // -------------------------------------------------
    // Selling Price
    // -------------------------------------------------

    if (
        $sellingPrice === '' ||
        !is_numeric($sellingPrice)
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Valid selling price is required"
        ]);

        exit();
    }

    $sellingPrice =
        (float)$sellingPrice;


    if ($sellingPrice <= 0) {

        echo json_encode([
            "status" => "error",
            "message" => "Selling price must be greater than 0"
        ]);

        exit();
    }


    // -------------------------------------------------
    // Condition
    // -------------------------------------------------

    $allowedConditions = [

        "New",
        "Used"
    ];

    if (!in_array(
        $vehicleCondition,
        $allowedConditions,
        true
    )) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid vehicle condition"
        ]);

        exit();
    }


    // -------------------------------------------------
    // Manufacturing Year
    // -------------------------------------------------

    if (
        $manufacturingYear === '' ||
        !is_numeric($manufacturingYear)
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Valid manufacturing year is required"
        ]);

        exit();
    }

    $manufacturingYear =
        (int)$manufacturingYear;


    if (
        $manufacturingYear < 1900 ||
        $manufacturingYear > 2100
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid manufacturing year"
        ]);

        exit();
    }


    // -------------------------------------------------
    // Kilometers Driven
    // -------------------------------------------------

    if (
        $kilometersDriven === '' ||
        !is_numeric($kilometersDriven)
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Valid kilometers driven is required"
        ]);

        exit();
    }

    $kilometersDriven =
        (int)$kilometersDriven;


    if ($kilometersDriven < 0) {

        echo json_encode([
            "status" => "error",
            "message" => "Kilometers cannot be negative"
        ]);

        exit();
    }


    // -------------------------------------------------
    // Ownership
    // -------------------------------------------------

    $allowedOwnership = [

        "1st Owner",
        "2nd Owner",
        "3rd Owner",
        "4th Owner+"
    ];

    if (!in_array(
        $ownership,
        $allowedOwnership,
        true
    )) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid ownership"
        ]);

        exit();
    }

} else {

    // =================================================
    // RENT ONLY
    // =================================================

    $sellingPrice = 0;

    $vehicleCondition = '';

    $manufacturingYear = 0;

    $kilometersDriven = 0;

    $ownership = '';
}


// =====================================================
// VALIDATE TRANSPORT PRICING
// =====================================================

if (
    $serviceType === "Goods Transportation"
) {

    // -------------------------------------------------
    // Required transport values
    // -------------------------------------------------

    if (
        $transportBaseFare === '' ||
        $transportMinimumFare === '' ||
        $transportPerKm === '' ||
        $transportDriverCharge === ''
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Transport pricing fields are required"
        ]);

        exit();
    }


    // -------------------------------------------------
    // Numeric validation
    // -------------------------------------------------

    if (
        !is_numeric($transportBaseFare) ||
        !is_numeric($transportMinimumFare) ||
        !is_numeric($transportPerKm) ||
        !is_numeric($transportDriverCharge) ||
        !is_numeric($transportWaitingCharge)
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Invalid transport pricing values"
        ]);

        exit();
    }


    // -------------------------------------------------
    // Convert values
    // -------------------------------------------------

    $transportBaseFare =
        (float)$transportBaseFare;

    $transportMinimumFare =
        (float)$transportMinimumFare;

    $transportPerKm =
        (float)$transportPerKm;

    $transportDriverCharge =
        (float)$transportDriverCharge;

    $transportWaitingCharge =
        (float)$transportWaitingCharge;


    // -------------------------------------------------
    // Prevent negative values
    // -------------------------------------------------

    if (
        $transportBaseFare < 0 ||
        $transportMinimumFare < 0 ||
        $transportPerKm < 0 ||
        $transportDriverCharge < 0 ||
        $transportWaitingCharge < 0
    ) {

        echo json_encode([
            "status" => "error",
            "message" => "Transport pricing cannot be negative"
        ]);

        exit();
    }

} else {

    // =================================================
    // NON-TRANSPORT VEHICLES
    // =================================================

    $transportBaseFare = 0;

    $transportMinimumFare = 0;

    $transportPerKm = 0;

    $transportDriverCharge = 0;

    $transportWaitingCharge = 0;
}


// =====================================================
// INSERT VEHICLE
// =====================================================

$sql = "

    INSERT INTO vehicles
    (

        owner_phone,

        vehicle_name,

        vehicle_type,

        service_type,

        listing_type,

        vehicle_image,


        price_per_day,

        price_6hr,

        price_12hr,


        selling_price,

        vehicle_condition,

        manufacturing_year,

        kilometers_driven,

        ownership,


        city,

        address,


        quantity,

        deposit,


        transport_base_fare,

        transport_minimum_fare,

        transport_per_km,

        transport_driver_charge,

        transport_waiting_charge

    )

    VALUES
    (

        ?, ?, ?, ?, ?, ?,

        ?, ?, ?,

        ?, ?, ?, ?, ?,

        ?, ?,

        ?, ?,

        ?, ?, ?, ?, ?

    )

";


// =====================================================
// PREPARE STATEMENT
// =====================================================

$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {

    echo json_encode([

        "status" => "error",

        "message" => "Database error",

        "error" => mysqli_error($conn)

    ]);

    exit();
}


// =====================================================
// BIND PARAMETERS
// =====================================================
//
// s = string
// d = decimal
// i = integer
//
// =====================================================

mysqli_stmt_bind_param(

    $stmt,

    "ssssssdddssdisssidsdddd",

    $ownerPhone,
    $vehicleName,
    $vehicleType,
    $serviceType,
    $listingType,
    $vehicleImage,

    $pricePerDay,
    $price6hr,
    $price12hr,

    $sellingPrice,
    $vehicleCondition,
    $manufacturingYear,
    $kilometersDriven,
    $ownership,

    $city,
    $address,

    $quantity,
    $deposit,

    $transportBaseFare,
    $transportMinimumFare,
    $transportPerKm,
    $transportDriverCharge,
    $transportWaitingCharge
);
// =====================================================
// EXECUTE INSERT
// =====================================================

if (!mysqli_stmt_execute($stmt)) {

    $error =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    echo json_encode([

        "status" => "error",

        "message" => "Unable to add vehicle",

        "error" => $error

    ]);

    exit();
}


// =====================================================
// GET NEW VEHICLE ID
// =====================================================

$vehicleId =
    mysqli_insert_id($conn);


mysqli_stmt_close($stmt);


// =====================================================
// CREATE NOTIFICATION FOR OWNER
// =====================================================

$title =
    "Vehicle Listed Successfully";

$message =
    $vehicleName .
    " has been successfully added to your RentX listings.";

$type =
    "vehicle";


// =====================================================
// CHECK NOTIFICATION PREFERENCE
// =====================================================

$notificationEnabled = true;


$preferenceSql = "

    SELECT notification_general

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

            $notificationEnabled =
                (int)(
                    $preferenceRow[
                        'notification_general'
                    ] ?? 1
                ) === 1;
        }
    }


    mysqli_stmt_close(
        $preferenceStmt
    );
}


// =====================================================
// INSERT NOTIFICATION
// =====================================================

if ($notificationEnabled) {

    $sqlNotification = "

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
}


// =====================================================
// FINAL RESPONSE
// =====================================================

echo json_encode([

    "status" => "success",

    "message" => "Vehicle added successfully",

    "vehicle_id" => $vehicleId,

    "listing_type" => $listingType,

    "service_type" => $serviceType

]);

?>
