<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "db.php";

/*
|--------------------------------------------------------------------------
| USER PHONE
|--------------------------------------------------------------------------
*/

$user_phone = trim($_GET["user_phone"] ?? "");

if ($user_phone === "") {
    echo json_encode([
        "success" => false,
        "message" => "User phone is required"
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| GET SUPPORT REQUESTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        user_phone,
        booking_id,
        category,
        subject,
        description,
        status,
        admin_reply,
        created_at,
        updated_at
    FROM support_requests
    WHERE user_phone = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Database prepare failed"
    ]);
    exit;
}

$stmt->bind_param("s", $user_phone);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to load support requests"
    ]);

    $stmt->close();
    $conn->close();
    exit;
}


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

$result = $stmt->get_result();

$requests = [];

while ($row = $result->fetch_assoc()) {

    $requests[] = [
        "id" => (int)$row["id"],
        "user_phone" => $row["user_phone"],
        "booking_id" => $row["booking_id"] !== null
            ? (int)$row["booking_id"]
            : null,
        "category" => $row["category"],
        "subject" => $row["subject"],
        "description" => $row["description"],
        "status" => $row["status"],
        "admin_reply" => $row["admin_reply"],
        "created_at" => $row["created_at"],
        "updated_at" => $row["updated_at"]
    ];
}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "count" => count($requests),
    "requests" => $requests
]);

$stmt->close();
$conn->close();

?>
