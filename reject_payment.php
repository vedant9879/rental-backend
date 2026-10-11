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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    respond(405, [
        "success" => false,
        "message" => "Only POST requests are supported"
    ]);
}

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection unavailable");
    }

    $conn->set_charset("utf8mb4");

    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";
    $input = $_POST;

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

    $paymentReference = trim(
        (string)($input["payment_reference"] ?? "")
    );

    $recipientPhone = trim(
        (string)($input["recipient_phone"] ?? "")
    );

    if ($paymentReference === "" || strlen($paymentReference) > 64) {
        respond(400, [
            "success" => false,
            "message" => "Valid payment reference is required"
        ]);
    }

    if ($recipientPhone === "" || strlen($recipientPhone) > 30) {
        respond(400, [
            "success" => false,
            "message" => "Valid recipient phone is required"
        ]);
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare(
        "SELECT recipient_phone, status
         FROM payments
         WHERE payment_reference = ?
         LIMIT 1
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception("Could not prepare payment lookup");
    }

    $stmt->bind_param("s", $paymentReference);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Could not retrieve payment");
    }

    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        $conn->rollback();

        respond(404, [
            "success" => false,
            "message" => "Payment record not found"
        ]);
    }

    if (!hash_equals(
        (string)$payment["recipient_phone"],
        $recipientPhone
    )) {
        $conn->rollback();

        respond(403, [
            "success" => false,
            "message" => "Recipient phone does not match this payment"
        ]);
    }

    $status = strtolower((string)$payment["status"]);

    if (!in_array($status, ["pending", "submitted"], true)) {
        $conn->rollback();

        respond(409, [
            "success" => false,
            "message" => "Only pending or submitted payments can be rejected"
        ]);
    }

    $update = $conn->prepare(
        "UPDATE payments
         SET status = 'rejected',
             notes = CASE
                 WHEN notes IS NULL OR notes = ''
                 THEN 'Rejected by recipient'
                 ELSE CONCAT(notes, ' | Rejected by recipient')
             END
         WHERE payment_reference = ?
           AND status IN ('pending', 'submitted')"
    );

    if (!$update) {
        throw new Exception("Could not prepare payment rejection");
    }

    $update->bind_param("s", $paymentReference);

    if (!$update->execute()) {
        $update->close();
        throw new Exception("Could not reject payment");
    }

    $changed = $update->affected_rows;
    $update->close();

    if ($changed !== 1) {
        $conn->rollback();

        respond(409, [
            "success" => false,
            "message" => "Payment status changed. Refresh and try again."
        ]);
    }

    $conn->commit();

    respond(200, [
        "success" => true,
        "message" => "Payment rejected successfully",
        "payment_reference" => $paymentReference,
        "status" => "rejected"
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    error_log("reject_payment.php: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Server error while rejecting payment"
    ]);
}
?>
