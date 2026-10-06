<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

// =========================================================
// GET BUYER PHONE
// =========================================================

$buyer_phone = trim($_GET["buyer_phone"] ?? "");


// =========================================================
// EMPTY PHONE
// =========================================================

if ($buyer_phone === "") {

    echo json_encode([
        "success" => true,
        "data" => []
    ]);

    exit;
}


// =========================================================
// SQL
// =========================================================

$sql = "
    SELECT
        br.id,
        br.vehicle_id,
        br.buyer_phone,
        br.seller_phone,
        br.status,
        br.buyer_message,
        br.created_at,

        v.vehicle_name,
        v.vehicle_type,
        v.vehicle_image,
        v.selling_price,
        v.city,
        v.address,
        v.listing_type

    FROM buy_requests br

    LEFT JOIN vehicles v
        ON br.vehicle_id = v.id

    WHERE br.buyer_phone = ?

    ORDER BY br.created_at DESC
";


// =========================================================
// PREPARE
// =========================================================

$stmt = $conn->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "SQL prepare failed". $conn->error
    ]);

    exit;
}


// =========================================================
// BIND
// =========================================================

$stmt->bind_param("s", $buyer_phone);


// =========================================================
// EXECUTE
// =========================================================

if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "SQL execute failed"
    ]);

    $stmt->close();

    exit;
}


// =========================================================
// BIND RESULT
// =========================================================

$stmt->bind_result(

    $id,
    $vehicle_id,
    $request_buyer_phone,
    $seller_phone,
    $status,
    $buyer_message,
    $created_at,

    $vehicle_name,
    $vehicle_type,
    $vehicle_image,
    $selling_price,
    $city,
    $address,
    $listing_type
);


// =========================================================
// BUILD DATA
// =========================================================

$data = [];

while ($stmt->fetch()) {

    $data[] = [

        "id" => (int)$id,

        "vehicle_id" => (int)$vehicle_id,

        "buyer_phone" => $request_buyer_phone ?? "",

        "seller_phone" => $seller_phone ?? "",

        "status" => $status ?? "pending",

        "buyer_message" => $buyer_message ?? "",

        "created_at" => $created_at ?? "",

        "vehicle_name" => $vehicle_name ?? "Vehicle",

        "vehicle_type" => $vehicle_type ?? "",

        "vehicle_image" => $vehicle_image ?? "",

        "selling_price" => $selling_price ?? "0",

        "city" => $city ?? "",

        "address" => $address ?? "",

        "listing_type" => $listing_type ?? ""
    ];
}


// =========================================================
// CLOSE
// =========================================================

$stmt->close();


// =========================================================
// FINAL JSON
// =========================================================

echo json_encode([
    "success" => true,
    "data" => $data
]);

?>
