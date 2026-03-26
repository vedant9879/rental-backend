<?php

$host = getenv("mysql.railway.internal");
$user = getenv("root");
$pass = getenv("KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk");
$db   = getenv("railway");
$port = getenv("3306");

$conn = mysqli_connect($host,$user,$pass,$db,$port);

if(!$conn){
    die("DB Connection Failed");
}

?>
