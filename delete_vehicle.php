<?php
include "db.php";

$id = $_POST['vehicle_id'] ?? '';

if($id == ""){
    echo json_encode(["status"=>"error","msg"=>"ID missing"]);
    exit;
}

$sql = "DELETE FROM vehicles WHERE id='$id'";

if(mysqli_query($conn,$sql)){
    echo json_encode(["status"=>"success"]);
}else{
    echo json_encode(["status"=>"error"]);
}
?>
