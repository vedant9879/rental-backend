<?php
include "db.php";

$id = $_POST['booking_id'];
$status = $_POST['status'];

$sql = "UPDATE bookings 
        SET status='$status' 
        WHERE id='$id'";

if(mysqli_query($conn, $sql)){

    echo "success";

}else{

    echo "error";
}
?>
