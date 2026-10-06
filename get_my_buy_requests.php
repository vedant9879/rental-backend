<?php

header("Content-Type: application/json");

require_once "db.php";

$buyer_phone = trim(
    $_GET["buyer_phone"] ?? ""
);


if ($buyer_phone === "") {

    echo json_encode([]);

    exit;
}


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

    INNER JOIN vehicles v
        ON br.vehicle_id = v.id

    WHERE br.buyer_phone = ?

    ORDER BY br.created_at DESC
";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "s",
    $buyer_phone
);


$stmt->execute();


$result =
    $stmt->get_result();


$data = [];


while (
    $row = $result->fetch_assoc()
) {

    $data[] = $row;
}


echo json_encode($data);

?>
