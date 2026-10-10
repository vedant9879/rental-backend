<?php
require_once "db.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

mysqli_set_charset($conn, "utf8mb4");

function sendResponse($status, $message, $extra = [])
{
    echo json_encode(
        array_merge([
            "status" => $status,
            "message" => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

$ownerPhone = trim(
    $_GET["seller_phone"]
        ?? $_GET["owner_phone"]
        ?? $_POST["seller_phone"]
        ?? $_POST["owner_phone"]
        ?? ""
);

if ($ownerPhone === "") {
    sendResponse("error", "Seller phone is required", [
        "data" => []
    ]);
}

/*
|--------------------------------------------------------------------------
| GET SELLER BUY REQUESTS
|--------------------------------------------------------------------------
| Vehicle information comes from vehicles.
| Buyer address comes from buy_requests.
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

        COALESCE(
            NULLIF(v.vehicle_name, ''),
            NULLIF(br.vehicle_name, ''),
            'Vehicle'
        ) AS vehicle_name,

        COALESCE(
            NULLIF(v.vehicle_type, ''),
            ''
        ) AS vehicle_type,

        COALESCE(
            NULLIF(v.vehicle_image, ''),
            ''
        ) AS vehicle_image,

        COALESCE(
            v.selling_price,
            br.selling_price,
            0
        ) AS selling_price,

        COALESCE(v.address, '') AS vehicle_address,
        COALESCE(v.city, '') AS vehicle_city,
        COALESCE(v.listing_type, '') AS listing_type,

        COALESCE(br.buyer_address, '') AS buyer_address,
        COALESCE(br.buyer_city, '') AS buyer_city,
        COALESCE(br.buyer_pincode, '') AS buyer_pincode

    FROM buy_requests br

    LEFT JOIN vehicles v
        ON v.id = br.vehicle_id

    WHERE br.seller_phone = ?

    ORDER BY br.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log(
        "get_buy_requests prepare error: " .
        mysqli_error($conn)
    );

    http_response_code(500);

    sendResponse("error", "Unable to prepare buy requests query", [
        "data" => []
    ]);
}

mysqli_stmt_bind_param($stmt, "s", $ownerPhone);

if (!mysqli_stmt_execute($stmt)) {
    error_log(
        "get_buy_requests execute error: " .
        mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);
    http_response_code(500);

    sendResponse("error", "Unable to load seller buy requests", [
        "data" => []
    ]);
}

$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    mysqli_stmt_close($stmt);
    http_response_code(500);

    sendResponse("error", "Unable to read buy requests", [
        "data" => []
    ]);
}

$requests = [];

while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = [
        "id" => (int) $row["id"],
        "vehicle_id" => (int) $row["vehicle_id"],
        "buyer_phone" => $row["buyer_phone"] ?? "",
        "seller_phone" => $row["seller_phone"] ?? "",
        "status" => $row["status"] ?? "pending",
        "buyer_message" => $row["buyer_message"] ?? "",
        "created_at" => $row["created_at"] ?? "",
        "updated_at" => $row["updated_at"] ?? "",

        "vehicle_name" => $row["vehicle_name"] ?? "Vehicle",
        "vehicle_type" => $row["vehicle_type"] ?? "",
        "vehicle_image" => $row["vehicle_image"] ?? "",
        "selling_price" => $row["selling_price"] ?? "0",

        "vehicle_address" => $row["vehicle_address"] ?? "",
        "vehicle_city" => $row["vehicle_city"] ?? "",
        "listing_type" => $row["listing_type"] ?? "",

        "buyer_address" => $row["buyer_address"] ?? "",
        "buyer_city" => $row["buyer_city"] ?? "",
        "buyer_pincode" => $row["buyer_pincode"] ?? ""
    ];
}

mysqli_stmt_close($stmt);

echo json_encode(
    $requests,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

?>
