<?php
include "db.php";

$phone = $_POST['phone'];
$password = $_POST['password'];

$check = mysqli_query($conn,
"SELECT * FROM users WHERE phone='$phone'");

if(mysqli_num_rows($check)>0){

mysqli_query($conn,
"UPDATE users
SET password='$password'
WHERE phone='$phone'");

echo "success";

}else{

echo "notfound";
}
?>
