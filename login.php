<?php
include "db.php";

header("Access-Control-Allow-Origin: *");

$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

if($phone == "" || $password == ""){
    echo json_encode(["status"=>"empty"]);
    exit;
}

$res = $conn->query("SELECT * FROM users WHERE phone='$phone' AND password='$password'");

if($res->num_rows > 0){

    $row = $res->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "role" => $row['role'],
        "name" => $row['name'],
        "phone" => $row['phone']
    ]);

}else{
    echo json_encode([
        "status" => "fail"
    ]);
}
?>
