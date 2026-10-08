<?php

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");

/*
 * Railway MySQL connection
 * Uses environment variables instead of hard-coded credentials.
 */

$host = getenv("mysql.railway.internal");
$user = getenv("root");
$password = getenv("KvBeENpOYKXzuUUKnUBTdqewBJBDoIgk");
$database = getenv("railway");
$port = getenv("3306");

/*
 * Fallback values for Railway's standard MySQL variables.
 */
if (!$host) {
    $host = getenv("MYSQL_HOST");
}

if (!$user) {
    $user = getenv("MYSQL_USER");
}

if (!$password) {
    $password = getenv("MYSQL_PASSWORD");
}

if (!$database) {
    $database = getenv("MYSQL_DATABASE");
}

if (!$port) {
    $port = getenv("MYSQL_PORT");
}

/*
 * Check that Railway variables exist.
 */
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

$port = (int) $port;

/*
 * Connect to MySQL.
 */
$conn = new mysqli(
    $host,
    $user,
    $password,
    $database,
    $port
);

/*
 * Connection error.
 */
if ($conn->connect_error) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);

    exit;
}

/*
 * UTF-8 support.
 */
$conn->set_charset("utf8mb4");

?>
