<?php
include "db.php";

$id     = $_POST['booking_id'];
$status = $_POST['status'];

/* GET BOOKING DETAILS */
$get = $conn->query("
SELECT vehicle_id, quantity, status
FROM bookings
WHERE id='$id'
");

if($get->num_rows == 0){
    echo "Booking not found";
    exit();
}

$row = $get->fetch_assoc();

$vehicle = $row['vehicle_id'];
$qty     = (int)$row['quantity'];
$old     = $row['status'];

/* ACCEPT BOOKING */
if($status == "accepted"){

    if($old != "accepted"){

        $updateStock = $conn->query("
        UPDATE vehicles
        SET quantity = quantity - $qty
        WHERE id='$vehicle'
        AND quantity >= $qty
        ");

        if(!$updateStock || $conn->affected_rows == 0){
            echo "Not enough stock";
            exit();
        }
    }
}

/* CANCEL BOOKING */
if($status == "cancelled"){

    if($old == "accepted"){

        $conn->query("
        UPDATE vehicles
        SET quantity = quantity + $qty
        WHERE id='$vehicle'
        ");
    }
}

/* UPDATE BOOKING STATUS */
$done = $conn->query("
UPDATE bookings
SET status='$status'
WHERE id='$id'
");

if($done){
    echo "success";
}else{
    echo "error";
}
?>
