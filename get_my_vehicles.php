<?php
include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$owner = $_GET['owner_phone'] ?? '';

$sql = "SELECT * FROM vehicles 
        WHERE owner_phone='$owner'
        ORDER BY id DESC";

$res = mysqli_query($conn, $sql);

$data = array();

while($row = mysqli_fetch_assoc($res)){

    // Full image URL fix
    if(isset($row['vehicle_image']) && strpos($row['vehicle_image'], "http") !== 0){
        $row['vehicle_image'] =
        "https://rental-backend-production-8cbf.up.railway.app/" . $row['vehicle_image'];
    }

    // Default values
    $row['city'] = $row['city'] ?? "";
    $row['address'] = $row['address'] ?? "";
    $row['quantity'] = $row['quantity'] ?? "1";
    $row['deposit'] = $row['deposit'] ?? "0";
    $row['price_6hr'] = $row['price_6hr'] ?? "0";
    $row['price_12hr'] = $row['price_12hr'] ?? "0";

    $data[] = $row;
}

echo json_encode($data);
?>
