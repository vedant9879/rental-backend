<?php

require_once "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only GET requests are allowed"
    ]);
    exit;
}

$id = isset($_GET["vehicle_id"])
    ? (int) $_GET["vehicle_id"]
    : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Vehicle ID Missing"
    ]);
    exit;
}

$sql = "
    SELECT *
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to load vehicle"
    ]);
    mysqli_stmt_close($stmt);
    exit;
}

$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $vehicle = mysqli_fetch_assoc($result);

    echo json_encode(
        $vehicle,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Vehicle Not Found"
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
exit;
?>
