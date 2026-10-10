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
        JSON_UNESCAPED_SLASHES
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
    sendResponse("error", "Seller phone is required");
}

/*
|--------------------------------------------------------------------------
| FETCH SELLER REQUESTS AND BOTH ADDRESSES
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

        v.address AS vehicle_address,
        v.city AS vehicle_city,

        br.buyer_address,
        br.buyer_city,
        br.buyer_pincode

    FROM buy_requests br

    INNER JOIN vehicles v
        ON v.id = br.vehicle_id

    WHERE br.seller_phone = ?

    ORDER BY br.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    error_log("get_buy_requests prepare error: " . mysqli_error($conn));
    sendResponse("error", "Unable to prepare buy requests query");
}

mysqli_stmt_bind_param($stmt, "s", $ownerPhone);

if (!mysqli_stmt_execute($stmt)) {
    error_log("get_buy_requests execute error: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    sendResponse("error", "Unable to load seller buy requests");
}

$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    mysqli_stmt_close($stmt);
    sendResponse("error", "Unable to read buy requests");
}

$requests = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row["vehicle_address"] = $row["vehicle_address"] ?? "";
    $row["vehicle_city"] = $row["vehicle_city"] ?? "";

    $row["buyer_address"] = $row["buyer_address"] ?? "";
    $row["buyer_city"] = $row["buyer_city"] ?? "";
    $row["buyer_pincode"] = $row["buyer_pincode"] ?? "";

    $requests[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode(
    $requests,
    JSON_UNESCAPED_SLASHES
);

?>
