<?php
include "db.php";

$name = $_POST['name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$password = $_POST['password'];
$role = strtolower(trim($_POST['role']));

$aadhar = $_POST['aadhar_number'] ?? '';
$license = $_POST['license_number'] ?? '';

$check = "SELECT * FROM users WHERE phone='$phone' OR email='$email'";
$res = mysqli_query($conn,$check);

if(mysqli_num_rows($res) > 0){
    echo json_encode(["status"=>"exists"]);
    exit();
}

$sql = "INSERT INTO users 
(name,email,phone,password,role,aadhar_number,license_number)
VALUES 
('$name','$email','$phone','$password','$role','$aadhar','$license')";

if(mysqli_query($conn,$sql)){
    echo json_encode(["status"=>"success"]);
}else{
    echo json_encode(["status"=>"error","msg"=>mysqli_error($conn)]);
}
?>
