<?php
$conn = new mysqli("localhost","root","","rental_db");

if($conn->connect_error){
 die("Connection Failed");
}
?>