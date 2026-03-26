<?php

$conn = new mysqli(
    "mysql.railway.internal",
    "root",
    "KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk",
    "railway",
     3306
);

if ($conn->connect_error) {
    die("DB Failed: " . $conn->connect_error);
}

?>
