<?php
include "db.php";

$user    = $_POST['user_phone'] ?? '';
$vehicle = $_POST['vehicle_id'] ?? '';
$start   = $_POST['start_date'] ?? '';
$end     = $_POST['end_date'] ?? '';
$total   = $_POST['total_price'] ?? '';
$qty     = $_POST['quantity'] ?? '1';
$plan    = $_POST['booking_plan'] ?? '1 Day';
$payment = $_POST['payment_mode'] ?? 'Cash on Delivery';

$status  = "pending";

if($user=='' || $vehicle=='' || $start=='' || $end==''){
    echo "Missing Data";
    exit();
}

/* GET OWNER + STOCK */
$get = mysqli_query($conn,
"SELECT owner_phone, quantity
 FROM vehicles
 WHERE id='$vehicle'");

if(mysqli_num_rows($get)==0){
    echo "Vehicle Not Found";
    exit();
}

$row = mysqli_fetch_assoc($get);

$owner = $row['owner_phone'];
$stock = $row['quantity'];

/* CHECK QTY */
if($qty > $stock){
    echo "Only $stock Available";
    exit();
}

/* DATE / TIME OVERLAP CHECK */
$check = mysqli_query($conn,
"SELECT id FROM bookings
 WHERE vehicle_id='$vehicle'
 AND status!='cancelled'
 AND (
 '$start' BETWEEN start_date AND end_date
 OR '$end' BETWEEN start_date AND end_date
 OR start_date BETWEEN '$start' AND '$end'
 )");

if(mysqli_num_rows($check)>0){
    echo "Already Booked";
    exit();
}

/* INSERT */
$sql =
"INSERT INTO bookings
(user_phone, owner_phone, vehicle_id,
start_date, end_date,
total_price, payment_mode,
quantity, booking_plan, status)

VALUES
('$user','$owner','$vehicle',
'$start','$end',
'$total','$payment',
'$qty','$plan','$status')";

if(mysqli_query($conn,$sql)){

    echo "success";

}else{

    echo "Booking Failed";
}
?>
