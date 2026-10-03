<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Only POST requests are allowed"
    ]);

    exit;
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {

    echo json_encode([
        "success" => false,
        "message" => "Username and password are required"
    ]);

    exit;
}

$sql = "SELECT id, username, password_hash
        FROM admin_users
        WHERE username = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit;
}

$stmt->bind_param("s", $username);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid admin credentials"
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

$admin = $result->fetch_assoc();

if (!password_verify($password, $admin["password_hash"])) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid admin credentials"
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Admin login successful",
    "admin_id" => (int)$admin["id"],
    "username" => $admin["username"]
]);

$stmt->close();
$conn->close();

?>
