<?php
include "db.php";

header("Access-Control-Allow-Origin: *");

$res = mysqli_query($conn,"SELECT * FROM vehicles ORDER BY id DESC");

$data = array();

while($row = mysqli_fetch_assoc($res)){

    // ✅ Safe check (works in all PHP versions)
    if(strpos($row['vehicle_image'], "http") !== 0){
        $row['vehicle_image'] = "https://rental-backend-production-8cbf.up.railway.app/" . $row['vehicle_image'];
    }

    $data[] = $row;
}

echo json_encode($data);
?>
