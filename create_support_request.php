<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| INPUTS
|--------------------------------------------------------------------------
*/

$user_phone = trim($_POST["user_phone"] ?? "");
$booking_id = trim($_POST["booking_id"] ?? "");
$category = trim($_POST["category"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$description = trim($_POST["description"] ?? "");


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($user_phone === "") {
    echo json_encode([
        "success" => false,
        "message" => "User phone is required"
    ]);
    exit;
}

if ($category === "") {
    echo json_encode([
        "success" => false,
        "message" => "Support category is required"
    ]);
    exit;
}

if ($subject === "") {
    echo json_encode([
        "success" => false,
        "message" => "Subject is required"
    ]);
    exit;
}

if ($description === "") {
    echo json_encode([
        "success" => false,
        "message" => "Please describe your problem"
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| BOOKING ID
|--------------------------------------------------------------------------
*/

$bookingValue = null;

if ($booking_id !== "" && is_numeric($booking_id)) {
    $bookingValue = (int)$booking_id;
}


/*
|--------------------------------------------------------------------------
| INSERT SUPPORT REQUEST
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO support_requests
    (
        user_phone,
        booking_id,
        category,
        subject,
        description,
        status
    )
    VALUES (?, ?, ?, ?, ?, 'open')
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Database prepare failed"
    ]);
    exit;
}

$stmt->bind_param(
    "sisss",
    $user_phone,
    $bookingValue,
    $category,
    $subject,
    $description
);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Unable to create support request"
    ]);

    $stmt->close();
    exit;
}


/*
|--------------------------------------------------------------------------
| REQUEST ID
|--------------------------------------------------------------------------
*/

$request_id = $stmt->insert_id;


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Support request created successfully",
    "request_id" => $request_id,
    "status" => "open"
]);

$stmt->close();
$conn->close();

?>
