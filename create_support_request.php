<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

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

$stmt->close();


/*
|--------------------------------------------------------------------------
| CHECK SUPPORT NOTIFICATION PREFERENCE
|--------------------------------------------------------------------------
|
| notification_support = 1
| → Support notification ON
|
| notification_support = 0
| → Support notification OFF
|
| If the user preference cannot be found,
| notification remains ON by default.
|
*/

$sendNotification = true;


$sqlPreference = "
    SELECT notification_support
    FROM users
    WHERE phone = ?
    LIMIT 1
";


$stmtPreference = $conn->prepare(
    $sqlPreference
);


if ($stmtPreference) {

    $stmtPreference->bind_param(
        "s",
        $user_phone
    );

    $stmtPreference->execute();

    $resultPreference =
        $stmtPreference->get_result();


    if (
        $resultPreference &&
        $resultPreference->num_rows > 0
    ) {

        $preference =
            $resultPreference->fetch_assoc();


        $sendNotification =
            ((int)(
                $preference["notification_support"] ?? 1
            ) === 1);
    }


    $stmtPreference->close();
}


/*
|--------------------------------------------------------------------------
| CREATE SUPPORT NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($sendNotification) {

    $title = "Support Request Created";

    $message =
        "Your support request #" .
        $request_id .
        " has been created successfully.";

    $type = "support";


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


    $stmtNotification = $conn->prepare(
        $sqlNotification
    );


    if ($stmtNotification) {

        $stmtNotification->bind_param(
            "ssss",
            $user_phone,
            $title,
            $message,
            $type
        );

        $stmtNotification->execute();

        $stmtNotification->close();
    }
}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Support request created successfully",
    "request_id" => $request_id,
    "status" => "open",
    "notification_sent" => $sendNotification
]);


$conn->close();

?>
