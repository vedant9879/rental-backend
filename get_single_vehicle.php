<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$id = isset($_GET['vehicle_id'])
    ? intval($_GET['vehicle_id'])
    : 0;

if ($id <= 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle ID Missing"
    ]);

    exit();
}

$sql = "
    SELECT
        id,
        vehicle_name,
        vehicle_type,
        price_per_day,
        price_6hr,
        price_12hr,
        quantity,
        deposit,
        city,
        address,
        vehicle_image
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {

    $row = mysqli_fetch_assoc($result);

    echo json_encode($row);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Vehicle Not Found"
    ]);
}

mysqli_stmt_close($stmt);

?>
