<?php
include "db.php";

$name = $_POST['vehicle_name'] ?? '';
$type = $_POST['vehicle_type'] ?? '';
$price = $_POST['price_per_day'] ?? '';
$owner = $_POST['owner_phone'] ?? '';

$image = $_FILES['image']['name'] ?? '';
$tmp   = $_FILES['image']['tmp_name'] ?? '';

$path = "uploads/" . $image;

if(move_uploaded_file($tmp,$path)){
    
    $conn->query("INSERT INTO vehicles(owner_phone,vehicle_name,vehicle_type,price_per_day,vehicle_image)
    VALUES('$owner','$name','$type','$price','$path')");

    echo "success";
    
}else{
    echo "image upload failed";
}
?>
