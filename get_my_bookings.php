<?php
include "db.php";

$user = $_GET['user_phone'];

$res = $conn->query("SELECT * FROM bookings WHERE user_phone='$user'");

$data = array();

while($row = $res->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>
