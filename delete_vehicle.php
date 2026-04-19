<?php
include "db.php";

$id = $_POST['vehicle_id'] ?? '';

if($id == ''){
    echo "ID missing";
    exit();
}

$check = mysqli_query($conn,
"SELECT id FROM vehicles WHERE id='$id'");

if(mysqli_num_rows($check) == 0){
    echo "Vehicle not found";
    exit();
}

$sql = "DELETE FROM vehicles
        WHERE id='$id'";

if(mysqli_query($conn, $sql)){

    echo "success";

}else{

    echo "error";
}
?>
