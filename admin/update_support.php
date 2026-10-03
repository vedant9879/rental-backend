<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

session_start();

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Admin authentication required"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Request Method
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
| Input
|--------------------------------------------------------------------------
*/

$request_id = (int)($_POST["request_id"] ?? 0);
$admin_reply = trim($_POST["admin_reply"] ?? "");
$status = trim($_POST["status"] ?? "");

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($request_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid support request ID"
    ]);

    exit;
}

$allowed_statuses = [
    "open",
    "in progress",
    "resolved"
];

if (!in_array($status, $allowed_statuses, true)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid support request status"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Update Support Request
|--------------------------------------------------------------------------
*/

$sql = "UPDATE support_requests
        SET admin_reply = ?,
            status = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit;
}

$stmt->bind_param(
    "ssi",
    $admin_reply,
    $status,
    $request_id
);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Failed to update support request"
    ]);

    $stmt->close();
    $conn->close();

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Request
|--------------------------------------------------------------------------
*/

if ($stmt->affected_rows === 0) {

    $check_sql = "SELECT id
                  FROM support_requests
                  WHERE id = ?
                  LIMIT 1";

    $check_stmt = $conn->prepare($check_sql);

    $check_stmt->bind_param(
        "i",
        $request_id
    );

    $check_stmt->execute();

    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {

        echo json_encode([
            "success" => false,
            "message" => "Support request not found"
        ]);

        $check_stmt->close();
        $stmt->close();
        $conn->close();

        exit;
    }

    $check_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Support request updated successfully",
    "request_id" => $request_id,
    "status" => $status
]);

$stmt->close();
$conn->close();

?>
