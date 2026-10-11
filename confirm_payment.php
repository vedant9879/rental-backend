<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

function inputData(): array
{
    $raw = file_get_contents("php://input");
    $json = json_decode($raw ?: "", true);

    return is_array($json)
        ? array_merge($_POST, $json)
        : $_POST;
}

function normalizePhone(mixed $value): string
{
    return preg_replace('/\s+/', '', trim((string)$value)) ?? "";
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST, OPTIONS");
    respond(405, [
        "success" => false,
        "message" => "Use POST"
    ]);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    respond(500, [
        "success" => false,
        "message" => "Database connection unavailable"
    ]);
}

$data = inputData();

$paymentReference = trim(
    (string)($data["payment_reference"] ?? "")
);

$recipientPhone = normalizePhone(
    $data["recipient_phone"] ?? ""
);

if (
    $paymentReference === "" ||
    strlen($paymentReference) > 64 ||
    $recipientPhone === "" ||
    strlen($recipientPhone) > 30
) {
    respond(400, [
        "success" => false,
        "message" => "Valid payment reference and recipient phone are required"
    ]);
}

$transactionStarted = false;

try {
    $conn->set_charset("utf8mb4");

    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start payment transaction");
    }

    $transactionStarted = true;

    /*
     * Lock the payment record while verifying and updating it.
     */
    $sql = "
        SELECT
            payment_reference,
            recipient_phone,
            payment_method,
            status
        FROM payments
        WHERE payment_reference = ?
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException("Unable to prepare payment verification");
    }

    $stmt->bind_param("s", $paymentReference);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException("Unable to read payment record");
    }

    $result = $stmt->get_result();
    $payment = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$payment) {
        $conn->rollback();
        $transactionStarted = false;

        respond(404, [
            "success" => false,
            "message" => "Payment record not found"
        ]);
    }

    /*
     * Only the intended recipient can confirm this record.
     * Phone matching alone is not account authentication.
     */
    $storedRecipient = normalizePhone(
        $payment["recipient_phone"] ?? ""
    );

    if (
        $storedRecipient === "" ||
        !hash_equals($storedRecipient, $recipientPhone)
    ) {
        $conn->rollback();
        $transactionStarted = false;

        respond(403, [
            "success" => false,
            "message" => "You are not the recipient of this payment"
        ]);
    }

    $method = strtolower(
        trim((string)$payment["payment_method"])
    );

    if (!in_array($method, ["upi_manual", "cod"], true)) {
        $conn->rollback();
        $transactionStarted = false;

        respond(400, [
            "success" => false,
            "message" => "Unsupported payment method"
        ]);
    }

    $status = strtolower(trim((string)$payment["status"]));

    if ($status === "paid") {
        $conn->commit();
        $transactionStarted = false;

        respond(200, [
            "success" => true,
            "message" => "Payment has already been confirmed",
            "payment_reference" => $paymentReference,
            "status" => "paid"
        ]);
    }

    if (!in_array($status, ["pending", "submitted"], true)) {
        $conn->rollback();
        $transactionStarted = false;

        respond(409, [
            "success" => false,
            "message" => "This payment cannot be confirmed in its current status",
            "status" => $status
        ]);
    }

    /*
     * Record confirmation using the existing payments schema.
     */
    $updateSql = "
        UPDATE payments
        SET
            status = 'paid',
            confirmed_by_phone = ?,
            confirmed_at = NOW()
        WHERE payment_reference = ?
          AND status IN ('pending', 'submitted')
    ";

    $update = $conn->prepare($updateSql);

    if (!$update) {
        throw new RuntimeException("Unable to prepare payment confirmation");
    }

    $update->bind_param(
        "ss",
        $storedRecipient,
        $paymentReference
    );

    if (!$update->execute()) {
        $update->close();
        throw new RuntimeException("Unable to confirm payment");
    }

    $affectedRows = $update->affected_rows;
    $update->close();

    if ($affectedRows !== 1) {
        $conn->rollback();
        $transactionStarted = false;

        respond(409, [
            "success" => false,
            "message" => "Payment status changed. Refresh payment history and try again."
        ]);
    }

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to save payment confirmation");
    }

    $transactionStarted = false;

    respond(200, [
        "success" => true,
        "message" => "Payment confirmed successfully",
        "payment_reference" => $paymentReference,
        "status" => "paid"
    ]);

} catch (Throwable $e) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // Preserve the original error.
        }
    }

    error_log("confirm_payment.php: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Unable to confirm payment. Please try again."
    ]);
}
?>
