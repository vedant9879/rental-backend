<?php
include "db.php";

$user = $_GET['user_phone'];

$sql = "SELECT b.id, v.vehicle_name, b.start_date, b.end_date, b.total_price, b.status
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        WHERE b.user_phone='$user'
        ORDER BY b.id DESC";

$result = mysqli_query($conn, $sql);

$data = array();

while($row = mysqli_fetch_assoc($result)){
    $data[] = $row;
}

echo json_encode($data);
?>
