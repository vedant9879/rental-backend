<?php
include "db.php";

$name = $_POST['name'];
$phone = $_POST['phone'];
$email = $_POST['email'];
$address = $_POST['address'];
$city = $_POST['city'];
$pincode = $_POST['pincode'];

$sql = "UPDATE users SET 
        name='$name',
        email='$email',
        address='$address',
        city='$city',
        pincode='$pincode'
        WHERE phone='$phone'";

if(mysqli_query($conn, $sql)){
    echo json_encode(["status"=>"success"]);
}else{
    echo json_encode(["status"=>"error"]);
}
?>
