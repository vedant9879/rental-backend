<?php
include "db.php";

$phone = $_POST['phone'];
$password = $_POST['password'];

/* Check user exists */
$check = mysqli_query(
$conn,
"SELECT id FROM users WHERE phone='$phone'"
);

if(mysqli_num_rows($check) > 0){

    /* Update password */
    $update = mysqli_query(
    $conn,
    "UPDATE users
     SET password='$password'
     WHERE phone='$phone'"
    );

    if($update){
        echo "success";
    }else{
        echo "failed";
    }

}else{

    echo "notfound";
}
?>
