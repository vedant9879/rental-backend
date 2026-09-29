<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$owner = isset($_GET['owner_phone'])
    ? trim($_GET['owner_phone'])
    : '';

if ($owner === '') {

    echo json_encode([]);

    exit();
}

$sql = "
    SELECT
        id,
        owner_phone,
        vehicle_name,
        vehicle_type,
        price_per_day,
        vehicle_image,
        city,
        address,
        quantity,
        deposit,
        price_6hr,
        price_12hr
    FROM vehicles
    WHERE owner_phone = ?
    ORDER BY id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([]);

    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $owner
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = array();

while ($row = mysqli_fetch_assoc($result)) {

    if (
        isset($row['vehicle_image']) &&
        !empty($row['vehicle_image']) &&
        strpos($row['vehicle_image'], "http") !== 0
    ) {

        $row['vehicle_image'] =
            "https://rental-backend-production-8cbf.up.railway.app/" .
            ltrim($row['vehicle_image'], "/");
    }

    $row['city'] =
        $row['city'] ?? "";

    $row['address'] =
        $row['address'] ?? "";

    $row['quantity'] =
        $row['quantity'] ?? "1";

    $row['deposit'] =
        $row['deposit'] ?? "0";

    $row['price_6hr'] =
        $row['price_6hr'] ?? "0";

    $row['price_12hr'] =
        $row['price_12hr'] ?? "0";

    $data[] = $row;
}

echo json_encode($data);

mysqli_stmt_close($stmt);

?>
