<?php
include "db.php";

header("Access-Control-Allow-Origin: *");

$owner = $_GET['owner_phone'] ?? '';

$res = mysqli_query($conn,
    "SELECT * FROM vehicles WHERE owner_phone='$owner' ORDER BY id DESC"
);

$data = [];

while($row = mysqli_fetch_assoc($res)){
    $data[] = $row;
}

echo json_encode($data);
?>
