<?php
include "db.php";

$base_url = "https://rental-backend-production-8cbf.up.railway.app/";

$sql = "SELECT * FROM vehicles";
$res = mysqli_query($conn,$sql);

$data = array();

while($row = mysqli_fetch_assoc($res)){
    
    // ✅ Convert to full URL
    if(!str_starts_with($row['vehicle_image'], "http")){
        $row['vehicle_image'] = $base_url . $row['vehicle_image'];
    }

    $data[] = $row;
}

echo json_encode($data);
?>
