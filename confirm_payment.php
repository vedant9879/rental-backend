<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

mysqli_report(MYSQLI_REPORT_OFF);

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
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

$paymentReference = trim($input["payment_reference"] ?? "");
$recipientPhone = trim($input["recipient_phone"] ?? "");

if ($paymentReference === "" || $recipientPhone === "") {
    respond(400, [
        "success" => false,
        "message" => "Payment reference and recipient phone are required"
    ]);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT id, payer_phone, recipient_phone, service_type,
               service_record_id, payment_method, status
        FROM payments
        WHERE payment_reference = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param("s", $paymentReference);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception("Payment not found");
    }

    if (!hash_equals(
        (string)$payment["recipient_phone"],
        (string)$recipientPhone
    )) {
        throw new Exception("You are not authorized to confirm this payment");
    }

    if ($payment["status"] === "paid") {
        throw new Exception("Payment is already confirmed");
    }

    if (!in_array($payment["status"], ["pending", "submitted"], true)) {
        throw new Exception("This payment cannot be confirmed");
    }

    // UPI or cash must be physically received before confirmation.
    $update = $conn->prepare("
        UPDATE payments
        SET status = 'paid',
            confirmed_by_phone = ?,
            confirmed_at = NOW()
        WHERE id = ?
          AND status IN ('pending', 'submitted')
    ");
    $paymentId = (int)$payment["id"];
    $update->bind_param("si", $recipientPhone, $paymentId);
    $update->execute();

    if ($update->affected_rows !== 1) {
        $update->close();
        throw new Exception("Payment status changed; refresh and try again");
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
