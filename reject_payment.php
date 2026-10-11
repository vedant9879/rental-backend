<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, [
        "success" => false,
        "message" => "Use POST"
    ]);
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = $_POST;
}

$paymentReference = trim(
    (string)($input["payment_reference"] ?? "")
);

$recipientPhone = preg_replace(
    '/\s+/',
    '',
    trim((string)($input["recipient_phone"] ?? ""))
);

if ($paymentReference === "" || $recipientPhone === "") {
    respond(400, [
        "success" => false,
        "message" => "Payment reference and recipient phone are required"
    ]);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT id, recipient_phone, status
        FROM payments
        WHERE payment_reference = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Unable to check payment");
    }

    $stmt->bind_param("s", $paymentReference);
    $stmt->execute();

    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception("Payment not found");
    }

    $storedPhone = preg_replace(
        '/\s+/',
        '',
        trim((string)$payment["recipient_phone"])
    );

    if (!hash_equals($storedPhone, $recipientPhone)) {
        throw new Exception("Not authorized to reject this payment");
    }

    if (!in_array(
        $payment["status"],
        ["pending", "submitted"],
        true
    )) {
        throw new Exception(
            "Only pending or submitted payments can be rejected"
        );
    }

    $paymentId = (int)$payment["id"];

    $update = $conn->prepare("
        UPDATE payments
        SET status = 'rejected'
        WHERE id = ?
          AND status IN ('pending', 'submitted')
    ");

    if (!$update) {
        throw new Exception("Unable to prepare payment update");
    }

    $update->bind_param("i", $paymentId);
    $update->execute();

    if ($update->affected_rows !== 1) {
        $update->close();
        throw new Exception("Payment changed; refresh and try again");
    }

    $update->close();
    $conn->commit();

    respond(200, [
        "success" => true,
        "message" => "Payment rejected",
        "payment_reference" => $paymentReference,
        "status" => "rejected"
    ]);

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    $known = [
        "Payment not found",
        "Not authorized to reject this payment",
        "Only pending or submitted payments can be rejected",
        "Payment changed; refresh and try again"
    ];

    $message = in_array($e->getMessage(), $known, true)
        ? $e->getMessage()
        : "Unable to reject payment";

    respond(400, [
        "success" => false,
        "message" => $message
    ]);
}
?>

