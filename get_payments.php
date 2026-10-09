<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/db.php";

mysqli_report(MYSQLI_REPORT_OFF);

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET" &&
    $_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "GET or POST method required"
    ]);
}

$input = $_SERVER["REQUEST_METHOD"] === "POST"
    ? json_decode(file_get_contents("php://input"), true)
    : $_GET;

if (!is_array($input)) {
    $input = $_POST;
}

$phone = trim((string)($input["phone"] ?? ""));

if ($phone === "" || strlen($phone) > 30) {
    respond(400, [
        "success" => false,
        "message" => "Valid phone number is required"
    ]);
}

try {
    $stmt = $conn->prepare("
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
        WHERE payer_phone = ? OR recipient_phone = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");

    if (!$stmt) {
        throw new Exception("Unable to prepare payment query");
    }

    $stmt->bind_param("ss", $phone, $phone);

    if (!$stmt->execute()) {
        throw new Exception("Payment query failed");
    }

    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
        $row["amount"] = (float)$row["amount"];
        $row["service_record_id"] = (int)$row["service_record_id"];
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
