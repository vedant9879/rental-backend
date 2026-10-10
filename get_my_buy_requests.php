<?php

require_once "db.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

mysqli_set_charset($conn, "utf8mb4");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

$buyerPhone = trim(
    $_GET["buyer_phone"]
        ?? $_GET["user_phone"]
        ?? $_POST["buyer_phone"]
        ?? $_POST["user_phone"]
        ?? ""
);

if ($buyerPhone === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Buyer phone is required"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH BUYER REQUESTS AND BOTH ADDRESSES
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        br.id,
        br.vehicle_id,
        br.buyer_phone,
        br.seller_phone,
        br.vehicle_name,
        br.selling_price,
        br.status,
        br.buyer_message,
        br.created_at,
        br.updated_at,

        v.vehicle_name AS listing_vehicle_name,
        v.selling_price AS listing_selling_price,
        v.address AS vehicle_address,
        v.city AS vehicle_city,

        br.buyer_address,
        br.buyer_city,
        br.buyer_pincode

    FROM buy_requests br

    LEFT JOIN vehicles v
        ON v.id = br.vehicle_id

    WHERE br.buyer_phone = ?

    ORDER BY br.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log("get_my_buy_requests prepare error: " . mysqli_error($conn));

    echo json_encode([
        "status" => "error",
        "message" => "Unable to prepare buyer requests query"
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $buyerPhone);

if (!mysqli_stmt_execute($stmt)) {
    error_log("get_my_buy_requests execute error: " . mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to load your buy requests"
    ]);
    exit;
}

$result = mysqli_stmt_get_result($stmt);
$requests = [];

while ($row = mysqli_fetch_assoc($result)) {

    // Use listing details when saved request fields are empty.
    if (empty($row["vehicle_name"])) {
        $row["vehicle_name"] =
            $row["listing_vehicle_name"] ?? "Vehicle";
    }

    if (
        !isset($row["selling_price"]) ||
        (float)$row["selling_price"] <= 0
    ) {
        $row["selling_price"] =
            $row["listing_selling_price"] ?? "0.00";
    }

    $row["vehicle_address"] =
        $row["vehicle_address"] ?? "";

    $row["vehicle_city"] =
        $row["vehicle_city"] ?? "";

    $row["buyer_address"] =
        $row["buyer_address"] ?? "";

    $row["buyer_city"] =
        $row["buyer_city"] ?? "";

    $row["buyer_pincode"] =
        $row["buyer_pincode"] ?? "";

    $requests[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode(
    $requests,
    JSON_UNESCAPED_SLASHES
);

?>
