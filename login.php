<?php
include "db.php";

$phone = $_POST['phone'];
$password = $_POST['password'];

$result = $conn->query(
"SELECT * FROM users WHERE phone='$phone' AND password='$password'"
);

if($result->num_rows > 0){

$row = $result->fetch_assoc();
echo $row['role'];

}else{
echo "fail";
}
?>