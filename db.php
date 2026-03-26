<?php

$host = getenv("mysql.railway.internal");
$user = getenv("root");
$pass = getenv("KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk");
$db   = getenv("railway");
$port = getenv("3306");

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("DB Failed");
}

?>
