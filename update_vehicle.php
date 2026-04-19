<?php
include "db.php";

$id       = $_POST['vehicle_id'];
$name     = $_POST['vehicle_name'];
$price    = $_POST['price_per_day'];
$qty      = $_POST['quantity'];
$deposit  = $_POST['deposit'];
$city     = $_POST['city'];
$address  = $_POST['address'];

if($id == ""){
    echo "Vehicle ID Missing";
    exit();
}

$sql = "
UPDATE vehicles SET
vehicle_name = '$name',
price_per_day = '$price',
quantity = '$qty',
deposit = '$deposit',
city = '$city',
address = '$address'
WHERE id = '$id'
";

if(mysqli_query($conn, $sql)){

    echo "Updated Successfully";

}else{

    echo "Update Failed";
}
?>```
