<?php
include "db.php";

$name  = $_REQUEST['vehicle_name'] ?? '';
$type  = $_REQUEST['vehicle_type'] ?? '';
$price = $_REQUEST['price_per_day'] ?? 0;
$owner = $_REQUEST['owner_phone'] ?? '';

$path = "uploads/default.jpg";

if(isset($_FILES['image']) && $_FILES['image']['name'] != ""){
    $image = $_FILES['image']['name'];
    $tmp   = $_FILES['image']['tmp_name'];
    $path = "uploads/" . $image;
    move_uploaded_file($tmp,$path);
}

$conn->query("INSERT INTO vehicles(owner_phone,vehicle_name,vehicle_type,price_per_day,vehicle_image)
VALUES('$owner','$name','$type','$price','$path')");

echo "success";
?>
