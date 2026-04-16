<?php
include "db.php";

$user = $_POST['user_phone'];
$vehicle = $_POST['vehicle_id'];
$start = $_POST['start_date'];
$end = $_POST['end_date'];
$total = $_POST['total_price'];

$payment = "COD"; // 🔥 CASH ON DELIVERY

// Check booking
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

    $conn->query("INSERT INTO bookings(user_phone,vehicle_id,start_date,end_date,total_price,payment_mode)
    VALUES('$user','$vehicle','$start','$end','$total','$payment')");

    echo "success";
}
?>
