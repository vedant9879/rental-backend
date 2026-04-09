<?php
include "db.php";

$name  = $_POST['vehicle_name'] ?? '';
$type  = $_POST['vehicle_type'] ?? '';
$price = $_POST['price_per_day'] ?? 0;
$owner = $_POST['owner_phone'] ?? '';
$image = $_POST['vehicle_image'] ?? '';  // 🔥 IMPORTANT

// 🔥 VALIDATION (VERY IMPORTANT)
if(strpos($image, "raw.githubusercontent.com") === false){
    echo json_encode(["status"=>"invalid_image"]);
    exit;
}

$sql = "INSERT INTO vehicles
(owner_phone, vehicle_name, vehicle_type, price_per_day, vehicle_image)
VALUES
('$owner','$name','$type','$price','$image')";

if(mysqli_query($conn,$sql)){
    echo json_encode(["status"=>"success"]);
}else{
    echo json_encode(["status"=>"error"]);
}
?>
