<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode([]);
    exit;
}

$owner_phone = isset($_GET["owner_phone"])
    ? trim($_GET["owner_phone"])
    : "";

if ($owner_phone === "") {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT
        id,
        owner_phone,
        vehicle_name,
        vehicle_type,
        vehicle_image,
        price_per_day,
        price_6hr,
        price_12hr,
        city,
        address,
        quantity,
        deposit
    FROM vehicles
    WHERE owner_phone = ?
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database query preparation failed"
    ]);
    exit;
}

$stmt->bind_param(
    "s",
    $owner_phone
);

$stmt->execute();

$result = $stmt->get_result();

$vehicles = [];

while ($row = $result->fetch_assoc()) {

    $vehicles[] = [
        "id" => (int)$row["id"],
        "owner_phone" => $row["owner_phone"],
        "vehicle_name" => $row["vehicle_name"],
        "vehicle_type" => $row["vehicle_type"],
        "vehicle_image" => $row["vehicle_image"],
        "price_per_day" => $row["price_per_day"],
        "price_6hr" => $row["price_6hr"],
        "price_12hr" => $row["price_12hr"],
        "city" => $row["city"],
        "address" => $row["address"],
        "quantity" => $row["quantity"],
        "deposit" => $row["deposit"]
    ];
}

$stmt->close();
$conn->close();

echo json_encode($vehicles);

?>
