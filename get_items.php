<?php
include "db.php";

$res = $conn->query("SELECT * FROM items");

$data = array();

while($row = $res->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>