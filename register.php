<?php
include "db.php";

header("Access-Control-Allow-Origin: *");

$name = $_POST['name'] ?? $_REQUEST['name'] ?? '';
$phone = $_POST['phone'] ?? $_REQUEST['phone'] ?? '';
$password = $_POST['password'] ?? $_REQUEST['password'] ?? '';
$role = $_POST['role'] ?? $_REQUEST['role'] ?? 'user';

if($name=="" || $phone=="" || $password==""){
    echo "empty";
    exit;
}

// ❗ check duplicate user
$check = $conn->query("SELECT * FROM users WHERE phone='$phone'");

if($check->num_rows > 0){
    echo "exists";
}else{

    $conn->query("INSERT INTO users(name,phone,password,role)
    VALUES('$name','$phone','$password','$role')");

    echo "success";
}
?>
