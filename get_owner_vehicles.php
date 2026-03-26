<?php
include "db.php";

$owner = $_GET['owner_phone'];

$res = $conn->query("SELECT * FROM vehicles WHERE owner_phone='$owner'");

$data = array();

while($row = $res->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>
