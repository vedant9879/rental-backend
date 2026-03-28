<?php
include "db.php";

$phone = $_GET['phone'];

$sql = "SELECT name,email,phone,role,address,city,pincode FROM users WHERE phone='$phone'";
$result = mysqli_query($conn,$sql);

if($row = mysqli_fetch_assoc($result)){
    echo json_encode($row);
}else{
    echo json_encode(["status"=>"error"]);
}
?>
