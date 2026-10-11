<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

mysqli_report(MYSQLI_REPORT_OFF);

function respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function normalizePhone(mixed $value): string
{
    return preg_replace('/\s+/', '', trim((string)$value)) ?? "";
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER["REQUEST_METHOD"], ["GET", "POST"], true)) {
    header("Allow: GET, POST, OPTIONS");

    respond(405, [
        "success" => false,
        "message" => "Only GET and POST requests are supported"
    ]);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    respond(500, [
        "success" => false,
        "message" => "Database connection unavailable"
    ]);
}

try {
    $conn->set_charset("utf8mb4");

    // Read GET, form POST, or JSON POST input.
    $input = $_GET;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $input = $_POST;
        $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

        if (stripos($contentType, "application/json") !== false) {
            $raw = file_get_contents("php://input");
            $json = json_decode($raw ?: "", true);

            if (!is_array($json)) {
                respond(400, [
                    "success" => false,
                    "message" => "Invalid JSON request body"
                ]);
            }

            $input = array_merge($input, $json);
        }
    }

    $phone = normalizePhone($input["phone"] ?? "");

    if ($phone === "" || strlen($phone) > 30) {
        respond(400, [
            "success" => false,
            "message" => "Valid phone number is required"
        ]);
    }

    $sql = "
        SELECT
            payment_reference,
            payer_phone,
            recipient_phone,
            service_type,
            service_record_id,
            payment_method,
            amount,
            currency,
            status,
            upi_transaction_reference,
            notes,
            confirmed_by_phone,
            confirmed_at,
            created_at,
            updated_at
        FROM payments
        WHERE REPLACE(REPLACE(payer_phone, ' ', ''), CHAR(9), '') = ?
           OR REPLACE(REPLACE(recipient_phone, ' ', ''), CHAR(9), '') = ?
        ORDER BY created_at DESC
        LIMIT 100
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            "Unable to prepare payment query"
        );
    }

    $stmt->bind_param("ss", $phone, $phone);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new RuntimeException(
            "Unable to retrieve payments"
        );
    }

    $result = $stmt->get_result();

    if ($result === false) {
        $stmt->close();

        throw new RuntimeException(
            "Unable to read payment results"
        );
    }

    $payments = [];

    while ($row = $result->fetch_assoc()) {
        $row["amount"] = (float)$row["amount"];
        $row["service_record_id"] = (int)$row["service_record_id"];

        $payments[] = $row;
    }

    $result->free();
    $stmt->close();

    respond(200, [
        "success" => true,
        "count" => count($payments),
        "payments" => $payments
    ]);

} catch (Throwable $e) {
    error_log("get_payments.php error: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to retrieve payments"
    ]);
}
?>
