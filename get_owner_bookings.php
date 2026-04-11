<?php
include "db.php";

$owner = $_GET['owner_phone'];

$sql = "SELECT b.id, b.user_phone, b.status, v.vehicle_name
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        WHERE v.owner_phone='$owner'
        ORDER BY b.id DESC";

$res = mysqli_query($conn,$sql);

$data = [];

while($row = mysqli_fetch_assoc($res)){
    $data[] = $row;
}

echo json_encode($data);
?>
