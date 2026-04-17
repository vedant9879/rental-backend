<?php
include "db.php";

$name     = $_POST['vehicle_name'] ?? '';
$type     = $_POST['vehicle_type'] ?? '';
$price    = $_POST['price_per_day'] ?? '0';
$owner    = $_POST['owner_phone'] ?? '';
$image    = $_POST['vehicle_image'] ?? '';

$city     = $_POST['city'] ?? '';
$address  = $_POST['address'] ?? '';
$quantity = $_POST['quantity'] ?? '1';
$deposit  = $_POST['deposit'] ?? '0';
$price6   = $_POST['price_6hr'] ?? '0';
$price12  = $_POST['price_12hr'] ?? '0';

if ($name == "" || $type == "" || $price == "" || $owner == "") {
    echo json_encode(["status"=>"empty"]);
    exit;
}

// image validation
if(strpos($image, "raw.githubusercontent.com") === false){
    echo json_encode(["status"=>"invalid_image"]);
    exit;
}

$sql = "INSERT INTO vehicles
(
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
)

VALUES
(
'$owner',
'$name',
'$type',
'$price',
'$image',
'$city',
'$address',
'$quantity',
'$deposit',
'$price6',
'$price12'
)";

if(mysqli_query($conn,$sql)){
    echo json_encode(["status"=>"success"]);
}else{
    echo json_encode(["status"=>"error"]);
}
?>
