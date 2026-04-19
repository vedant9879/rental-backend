<?php
include "db.php";

$id = $_GET['vehicle_id'];

$sql = "SELECT
vehicle_name,
price_per_day,
quantity,
deposit,
city,
address
FROM vehicles
WHERE id='$id'";

$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) > 0){

    $row = mysqli_fetch_assoc($result);

    echo json_encode($row);

}else{

    echo "{}";
}
?>
