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
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Buyer phone is required"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD REQUESTS WITH THE CORRECT VEHICLE LISTING DETAILS
|--------------------------------------------------------------------------
| Keep the Android adapter's expected keys:
| vehicle_name, vehicle_type, vehicle_image, selling_price,
| address, city, listing_type.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        br.id,
        br.vehicle_id,
        br.buyer_phone,
        br.seller_phone,
        br.status,
        br.buyer_message,
        br.created_at,
        br.updated_at,

        v.vehicle_name AS vehicle_name,
        v.vehicle_type AS vehicle_type,
        v.vehicle_image AS vehicle_image,
        v.selling_price AS selling_price,
        v.address AS address,
        v.city AS city,
        v.listing_type AS listing_type,

        br.buyer_address AS buyer_address,
        br.buyer_city AS buyer_city,
        br.buyer_pincode AS buyer_pincode

    FROM buy_requests br

    LEFT JOIN vehicles v
        ON v.id = br.vehicle_id

    WHERE br.buyer_phone = ?

    ORDER BY br.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log(
        "get_my_buy_requests prepare error: " .
        mysqli_error($conn)
    );

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to prepare buyer requests query"
    ]);

    exit;
}

mysqli_stmt_bind_param($stmt, "s", $buyerPhone);

if (!mysqli_stmt_execute($stmt)) {
    error_log(
        "get_my_buy_requests execute error: " .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to load your buy requests"
    ]);

    exit;
}

$result = mysqli_stmt_get_result($stmt);
$requests = [];

while ($row = mysqli_fetch_assoc($result)) {

    // Return consistent values without replacing valid listing data.
    $row["vehicle_name"] =
        trim((string)($row["vehicle_name"] ?? ""));

    if ($row["vehicle_name"] === "") {
        $row["vehicle_name"] = "Vehicle";
    }

    $row["vehicle_type"] =
        trim((string)($row["vehicle_type"] ?? ""));

    $row["vehicle_image"] =
        trim((string)($row["vehicle_image"] ?? ""));

    $row["selling_price"] =
        $row["selling_price"] ?? "0.00";

    $row["address"] =
        trim((string)($row["address"] ?? ""));

    $row["city"] =
        trim((string)($row["city"] ?? ""));

    $row["listing_type"] =
        trim((string)($row["listing_type"] ?? ""));

    $row["buyer_address"] =
        trim((string)($row["buyer_address"] ?? ""));

    $row["buyer_city"] =
        trim((string)($row["buyer_city"] ?? ""));

    $row["buyer_pincode"] =
        trim((string)($row["buyer_pincode"] ?? ""));

    $requests[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode(
    $requests,
    JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);

?>
