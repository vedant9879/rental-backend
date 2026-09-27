<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$response = array();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed"
    ]);

    exit;
}


// ============================================================
// GET DATA
// ============================================================

$vehicle_id =
    isset($_POST["vehicle_id"])
        ? intval($_POST["vehicle_id"])
        : 0;

$user_phone =
    isset($_POST["user_phone"])
        ? trim($_POST["user_phone"])
        : "";

$user_name =
    isset($_POST["user_name"])
        ? trim($_POST["user_name"])
        : "";

$rating =
    isset($_POST["rating"])
        ? floatval($_POST["rating"])
        : 0;

$comment =
    isset($_POST["comment"])
        ? trim($_POST["comment"])
        : "";


// ============================================================
// VALIDATION
// ============================================================

if ($vehicle_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid vehicle"
    ]);

    exit;
}


if ($user_phone === "") {

    echo json_encode([
        "success" => false,
        "message" => "User phone is required"
    ]);

    exit;
}


if ($user_name === "") {

    echo json_encode([
        "success" => false,
        "message" => "User name is required"
    ]);

    exit;
}


if ($rating < 1 || $rating > 5) {

    echo json_encode([
        "success" => false,
        "message" => "Rating must be between 1 and 5"
    ]);

    exit;
}


if ($comment === "") {

    echo json_encode([
        "success" => false,
        "message" => "Review comment is required"
    ]);

    exit;
}


// ============================================================
// CHECK VEHICLE
// ============================================================

$vehicleCheck = mysqli_prepare(
    $conn,
    "SELECT id FROM vehicles WHERE id = ? LIMIT 1"
);

mysqli_stmt_bind_param(
    $vehicleCheck,
    "i",
    $vehicle_id
);

mysqli_stmt_execute($vehicleCheck);

$vehicleResult =
    mysqli_stmt_get_result($vehicleCheck);


if (mysqli_num_rows($vehicleResult) === 0) {

    echo json_encode([
        "success" => false,
        "message" => "Vehicle not found"
    ]);

    mysqli_stmt_close($vehicleCheck);

    exit;
}

mysqli_stmt_close($vehicleCheck);


// ============================================================
// INSERT REVIEW
// ============================================================

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO reviews
    (
        vehicle_id,
        user_phone,
        user_name,
        rating,
        comment
    )
    VALUES (?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "issds",
    $vehicle_id,
    $user_phone,
    $user_name,
    $rating,
    $comment
);


if (mysqli_stmt_execute($stmt)) {

    $reviewId =
        mysqli_insert_id($conn);

    echo json_encode([
        "success" => true,
        "message" => "Review submitted successfully",
        "review_id" => $reviewId
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Failed to submit review"
    ]);
}


mysqli_stmt_close($stmt);

?>
