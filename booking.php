<?php
include "db.php";

$user    = $_POST['user_phone'] ?? '';
$vehicle = $_POST['vehicle_id'] ?? '';
$start   = $_POST['start_date'] ?? '';
$end     = $_POST['end_date'] ?? '';
$total   = $_POST['total_price'] ?? '';

$qty     = (int)($_POST['quantity'] ?? 1);
$plan    = $_POST['booking_plan'] ?? '1 Day';
$payment = $_POST['payment_mode'] ?? 'Cash on Delivery';

$status = "pending";

/* CHECK EMPTY */
if($user=='' || $vehicle=='' || $start=='' || $end==''){
    echo "Missing Data";
    exit();
}

/* GET VEHICLE */
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
$stock = (int)$row['quantity'];

/* CHECK ALREADY BOOKED QTY FOR SAME DATE */
$booked = mysqli_query($conn,
"SELECT IFNULL(SUM(quantity),0) as used
 FROM bookings
 WHERE vehicle_id='$vehicle'
 AND status IN ('pending','accepted')
 AND (
      start_date <= '$end'
      AND end_date >= '$start'
 )");

$b = mysqli_fetch_assoc($booked);

$used = (int)$b['used'];

$available = $stock - $used;

if($available < 0){
    $available = 0;
}

/* FINAL STOCK CHECK */
if($qty > $available){

    echo "Only $available Available";
    exit();
}

/* INSERT BOOKING */
$sql = "INSERT INTO bookings
(user_phone, owner_phone, vehicle_id,
start_date, end_date, total_price,
payment_mode, quantity,
booking_plan, status)

VALUES
('$user','$owner','$vehicle',
'$start','$end','$total',
'$payment','$qty',
'$plan','$status')";

if(mysqli_query($conn,$sql)){

    echo "success";

}else{

    echo "Booking Failed";
}
?>
