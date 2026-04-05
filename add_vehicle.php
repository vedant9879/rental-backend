<?php
include "db.php";

header("Access-Control-Allow-Origin: *");

// 🔹 Get data
$name  = $_POST['vehicle_name'] ?? '';
$type  = $_POST['vehicle_type'] ?? '';
$price = $_POST['price_per_day'] ?? 0;
$owner = $_POST['owner_phone'] ?? '';

// 🔹 Base URL
$base_url = "https://rental-backend-production-8cbf.up.railway.app/";

// 🔹 Validation
if($name == "" || $price == 0 || $owner == ""){
    echo json_encode(["status"=>"error","msg"=>"Missing fields"]);
    exit;
}

// 🔹 Default image
$path = $base_url . "uploads/default.jpg";

// 🔥 IMAGE UPLOAD
if(isset($_FILES['image']) && $_FILES['image']['name'] != ""){

    $image = time() . "_" . $_FILES['image']['name'];
    $tmp   = $_FILES['image']['tmp_name'];

    $local_path = "uploads/" . $image;

    if(move_uploaded_file($tmp, $local_path)){
        $path = $base_url . $local_path;
    }
}

// 🔹 Insert into DB
$sql = "INSERT INTO vehicles
(owner_phone,vehicle_name,vehicle_type,price_per_day,vehicle_image)
VALUES
('$owner','$name','$type','$price','$path')";

if(mysqli_query($conn,$sql)){
    echo json_encode(["status"=>"success"]);
}else{
    echo json_encode([
        "status"=>"error",
        "msg"=>mysqli_error($conn)
    ]);
}
?>
