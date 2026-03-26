<?php

$conn = new mysqli(
    "mysql.railway.internal",
    "root",
    "KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk",
    "railway",
    MYSQLPORT_VALUE
);

if ($conn->connect_error) {
    die("DB Failed: " . $conn->connect_error);
}

?>
