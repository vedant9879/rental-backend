<?php
include "db.php";

$user    = $_POST['user_phone'];
$vehicle = $_POST['vehicle_id'];
$start   = $_POST['start_date'];
$end     = $_POST['end_date'];
$total   = $_POST['total_price'];

$qty  = $_POST['quantity'] ?? 1;
$plan = $_POST['booking_plan'] ?? '1 Day';

$payment = "COD";
$status  = "pending";

$getOwner = $conn->query(
"SELECT owner_phone FROM vehicles WHERE id='$vehicle'"
);

$row = $getOwner->fetch_assoc();
$owner = $row['owner_phone'];

$check = $conn->query("
SELECT id FROM bookings
WHERE vehicle_id='$vehicle'
AND status!='cancelled'
AND (
'$start' BETWEEN start_date AND end_date
OR '$end' BETWEEN start_date AND end_date
OR start_date BETWEEN '$start' AND '$end'
)
");

if($check->num_rows > 0){

    echo "already booked";

}else{

$conn->query("
INSERT INTO bookings
(user_phone, owner_phone, vehicle_id,
start_date, end_date, total_price,
payment_mode, quantity, booking_plan, status)

VALUES
('$user','$owner','$vehicle',
'$start','$end','$total',
'$payment','$qty','$plan','$status')
");

echo "success";
}
?>
