<?php

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");

$host = "mysql.railway.internal";
$user = "root";
$password = "KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk";
$database = "railway";
$port = 3306;

$conn = new mysqli(
    $host,
    $user,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);

    exit;
}

$conn->set_charset("utf8mb4");

?>
