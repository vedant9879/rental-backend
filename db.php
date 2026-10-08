<?php

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");

$host = getenv("mysql.railway.internal");
$user = getenv("root");
$password = getenv("KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk");
$database = getenv("railway");
$port = getenv("3306");

if (
    empty($host) ||
    empty($user) ||
    $password === false ||
    empty($database) ||
    empty($port)
) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database environment variables are missing"
    ]);

    exit;
}

$conn = new mysqli(
    $host,
    $user,
    $password,
    $database,
    (int)$port
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
