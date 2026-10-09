<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";
mysqli_report(MYSQLI_REPORT_OFF);
function respond($statusCode, $data)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET" &&
    $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: GET, POST");

    respond(405, [
        "success" => false,
        "message" => "Only GET and POST requests are supported"
    ]);
}

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection unavailable");
    }

    $conn->set_charset("utf8mb4");

    // Support GET parameters, regular POST forms, and JSON POST bodies.
    $input = $_GET;

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $input = $_POST;

        $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

        if (stripos($contentType, "application/json") !== false) {
            $rawBody = file_get_contents("php://input");
            $jsonBody = json_decode($rawBody, true);

            if (!is_array($jsonBody)) {
                respond(400, [
                    "success" => false,
                    "message" => "Invalid JSON request body"
                ]);
            }

            $input = $jsonBody;
        }
    }

    $phone = trim((string)($input["phone"] ?? ""));

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
        WHERE payer_phone = ?
           OR recipient_phone = ?
        ORDER BY created_at DESC
        LIMIT 100
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Unable to prepare payment query");
    }

    $stmt->bind_param("ss", $phone, $phone);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Unable to retrieve payments");
    }

    $result = $stmt->get_result();

    if ($result === false) {
        $stmt->close();
        throw new Exception("Unable to read payment results");
    }

    $payments = [];

    while ($row = $result->fetch_assoc()) {
        $row["amount"] = (float)$row["amount"];
        $row["service_record_id"] =
            (int)$row["service_record_id"];

        $payments[] = $row;
    }

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
