<?php
include "db.php";

$name = $_POST['name'];
$phone = $_POST['phone'];
$password = $_POST['password'];
$role = $_POST['role'];

$sql = "INSERT INTO users(name,phone,password,role)
VALUES('$name','$phone','$password','$role')";

if($conn->query($sql)){
 echo "success";
}else{
 echo "fail";
}
?>