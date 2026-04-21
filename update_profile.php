<?php
include "db.php";

$name     = mysqli_real_escape_string($conn, $_POST['name']);
$phone    = mysqli_real_escape_string($conn, $_POST['phone']);
$email    = mysqli_real_escape_string($conn, $_POST['email']);
$address  = mysqli_real_escape_string($conn, $_POST['address']);
$city     = mysqli_real_escape_string($conn, $_POST['city']);
$pincode  = mysqli_real_escape_string($conn, $_POST['pincode']);
$aadhar   = mysqli_real_escape_string($conn, $_POST['aadhar']);
$license  = mysqli_real_escape_string($conn, $_POST['license']);

$sql = "UPDATE users SET
        name='$name',
        email='$email',
        address='$address',
        city='$city',
        pincode='$pincode',
        aadhar_number='$aadhar',
        license_number='$license'
        WHERE phone='$phone'";

if(mysqli_query($conn, $sql)){
    echo json_encode([
        "status"=>"success"
    ]);
}else{
    echo json_encode([
        "status"=>"error"
    ]);
}
?>
