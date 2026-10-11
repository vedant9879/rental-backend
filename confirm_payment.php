<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

mysqli_report(MYSQLI_REPORT_OFF);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizePhone(string $phone): string {
    return preg_replace('/\s+/', '', trim($phone));
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "POST method required"
    ]);
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = $_POST;
}

$paymentReference = trim(
    (string)($input["payment_reference"] ?? "")
);

$recipientPhone = normalizePhone(
    (string)($input["recipient_phone"] ?? "")
);

if ($paymentReference === "" || $recipientPhone === "") {
    respond(400, [
        "success" => false,
        "message" => "Payment reference and recipient phone are required"
    ]);
}

if (strlen($paymentReference) > 100 ||
    strlen($recipientPhone) > 30) {
    respond(400, [
        "success" => false,
        "message" => "Invalid payment reference or phone number"
    ]);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT id, recipient_phone, payment_method, status
        FROM payments
        WHERE payment_reference = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Unable to load payment");
    }

    $stmt->bind_param("s", $paymentReference);
    $stmt->execute();

    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception("Payment not found");
    }

    $storedRecipient = normalizePhone(
        (string)$payment["recipient_phone"]
    );

    if (!hash_equals($storedRecipient, $recipientPhone)) {
        throw new Exception(
            "You are not authorized to confirm this payment"
        );
    }

    if ($payment["status"] === "paid") {
        throw new Exception("Payment is already confirmed");
    }

    if (!in_array(
        $payment["status"],
        ["pending", "submitted"],
        true
    )) {
        throw new Exception("This payment cannot be confirmed");
    }

    // Manual confirmation is only for the existing UPI and COD flow.
    // The recipient must verify that money was actually received.
    $method = strtolower(trim((string)$payment["payment_method"]));

    if (!in_array($method, ["upi_manual", "cod"], true)) {
        throw new Exception(
            "This payment method requires its own verification process"
        );
    }

    $paymentId = (int)$payment["id"];

    $update = $conn->prepare("
        UPDATE payments
        SET status = 'paid',
            confirmed_by_phone = ?,
            confirmed_at = NOW()
        WHERE id = ?
          AND status IN ('pending', 'submitted')
    ");

    if (!$update) {
        throw new Exception("Unable to prepare payment confirmation");
    }

    $update->bind_param("si", $recipientPhone, $paymentId);
    $update->execute();

    if ($update->affected_rows !== 1) {
        $update->close();
        throw new Exception(
            "Payment status changed; refresh and try again"
        );
    }

    $update->close();
    $conn->commit();

    respond(200, [
        "success" => true,
        "message" => "Payment marked as received",
        "payment_reference" => $paymentReference,
        "status" => "paid"
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    $known = [
        "Payment not found",
        "You are not authorized to confirm this payment",
        "Payment is already confirmed",
        "This payment cannot be confirmed",
        "Payment status changed; refresh and try again"
    ];

    $message = in_array($e->getMessage(), $known, true)
        ? $e->getMessage()
        : "Unable to confirm payment";

    respond(400, [
        "success" => false,
        "message" => $message
    ]);
}
?>
