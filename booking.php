<?php
include "db.php";

$user = $_POST['user_phone'];
$vehicle = $_POST['vehicle_id'];
$start = $_POST['start_date'];
$end = $_POST['end_date'];
$total = $_POST['total_price'];

// ❗ Check if already booked
$check = $conn->query("SELECT * FROM bookings 
WHERE vehicle_id='$vehicle'
AND (
(start_date <= '$start' AND end_date >= '$start')
OR
(start_date <= '$end' AND end_date >= '$end')
)");

if($check->num_rows > 0){
    echo "already booked";
}else{

    // 🔥 ADD STATUS HERE
    $status = "pending";

    $conn->query("INSERT INTO bookings(user_phone,vehicle_id,start_date,end_date,total_price,status)
    VALUES('$user','$vehicle','$start','$end','$total','$status')");

    echo "success";
}
?>
