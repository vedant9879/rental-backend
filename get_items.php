<?php
include "db.php";

$base_url = "https://your-domain/"; // change

$sql = "SELECT * FROM vehicles";
$res = mysqli_query($conn,$sql);

$data = array();

while($row = mysqli_fetch_assoc($res)){
    $row['vehicle_image'] = $base_url . $row['vehicle_image'];
    $data[] = $row;
}

echo json_encode($data);
?>
