<?php
include "db.php";

$sql = "SELECT * FROM vehicles";
$res = mysqli_query($conn,$sql);

$data = array();

while($row = mysqli_fetch_assoc($res)){
    // ✅ Already full URL stored in DB → no need to change
    $data[] = $row;
}

echo json_encode($data);
?>
