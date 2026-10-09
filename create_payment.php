<?php
header("Content-Type: application/json; charset=UTF-8");

mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . "/db.php";

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

$payer = trim($input["payer_phone"] ?? "");
$service = strtolower(trim($input["service_type"] ?? ""));
$recordId = filter_var(
    $input["service_record_id"] ?? null,
    FILTER_VALIDATE_INT
);
$method = strtolower(trim($input["payment_method"] ?? ""));
$amount = $input["amount"] ?? null;
$upiReference = trim($input["upi_transaction_reference"] ?? "");

if (
    $payer === "" ||
    strlen($payer) > 30 ||
    !$recordId ||
    !in_array($service, ["rental", "transport", "sale"], true) ||
    !in_array($method, ["upi_manual", "cod"], true) ||
    !is_numeric($amount) ||
    (float)$amount <= 0 ||
    (float)$amount > 100000000
) {
    respond(400, [
        "success" => false,
        "message" => "Invalid payment details"
    ]);
}

if (strlen($upiReference) > 100) {
    respond(400, [
        "success" => false,
        "message" => "UPI reference is too long"
    ]);
}

if ($method === "upi_manual" && $upiReference === "") {
    respond(400, [
        "success" => false,
        "message" => "Enter the UPI transaction reference"
    ]);
}

if ($method === "cod") {
    $upiReference = "";
}

try {
    $conn->begin_transaction();

    if ($service === "rental") {
        $stmt = $conn->prepare("
            SELECT user_phone, owner_phone, total_price, status
            FROM bookings
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
    } elseif ($service === "transport") {
        $stmt = $conn->prepare("
            SELECT requester_phone, owner_phone, minimum_fare, status
            FROM transport_requests
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT br.buyer_phone, br.seller_phone, br.status,
                   v.selling_price, v.listing_type
            FROM buy_requests br
            INNER JOIN vehicles v ON v.id = br.vehicle_id
            WHERE br.id = ?
            LIMIT 1
            FOR UPDATE
        ");
    }

    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transaction) {
        throw new Exception("Transaction not found");
    }

    if ($service === "rental") {
        $actualPayer = trim($transaction["user_phone"] ?? "");
        $recipient = trim($transaction["owner_phone"] ?? "");
        $expectedAmount = (float)$transaction["total_price"];
        $status = strtolower(trim($transaction["status"] ?? ""));

        if (!in_array($status, ["accepted"], true)) {
            throw new Exception("Only accepted bookings can be paid");
        }
    } elseif ($service === "transport") {
        // No payment is allowed until the final fare is stored.
        throw new Exception(
            "Final transport fare must be calculated before payment"
        );
    } else {
        $actualPayer = trim($transaction["buyer_phone"] ?? "");
        $recipient = trim($transaction["seller_phone"] ?? "");
        $expectedAmount = (float)$transaction["selling_price"];
        $status = strtolower(trim($transaction["status"] ?? ""));
        $listingType = strtolower(trim($transaction["listing_type"] ?? ""));

        if ($status !== "accepted") {
            throw new Exception(
                "The seller must accept the buy request before payment"
            );
        }

        if (!in_array($listingType, ["sell", "rent + sell"], true)) {
            throw new Exception("Vehicle is not available for sale");
        }
    }

    if (
        !hash_equals($actualPayer, $payer) ||
        $recipient === "" ||
        hash_equals($actualPayer, $recipient)
    ) {
        throw new Exception("Payment participants are invalid");
    }

    if ($expectedAmount <= 0) {
        throw new Exception("Transaction amount is invalid");
    }

    if (abs((float)$amount - $expectedAmount) > 0.009) {
        throw new Exception("Amount does not match the transaction");
    }

    $check = $conn->prepare("
        SELECT payment_reference, status
        FROM payments
        WHERE service_type = ?
          AND service_record_id = ?
          AND status IN ('pending', 'submitted', 'paid')
        LIMIT 1
        FOR UPDATE
    ");
    $check->bind_param("si", $service, $recordId);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        throw new Exception(
            "An active payment already exists: " .
            $existing["payment_reference"]
        );
    }

    $paymentReference = "RXP" . strtoupper(bin2hex(random_bytes(12)));

    // COD remains pending; manual UPI awaits recipient confirmation.
    $paymentStatus = ($method === "upi_manual") ? "submitted" : "pending";
    $savedReference = ($method === "upi_manual") ? $upiReference : null;

    $insert = $conn->prepare("
        INSERT INTO payments (
            payment_reference,
            payer_phone,
            recipient_phone,
            service_type,
            service_record_id,
            payment_method,
            amount,
            currency,
            status,
            upi_transaction_reference
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'INR', ?, ?)
    ");

    // 10 parameters: s, s, s, s, i, s, d, s, s
    $insert->bind_param(
        "ssssisdss",
        $paymentReference,
        $actualPayer,
        $recipient,
        $service,
        $recordId,
        $method,
        $expectedAmount,
        $paymentStatus,
        $savedReference
    );

    $insert->execute();
    $insert->close();

    $conn->commit();

    respond(201, [
        "success" => true,
        "message" => "Payment record created; recipient confirmation is required",
        "payment_reference" => $paymentReference,
        "service_type" => $service,
        "service_record_id" => $recordId,
        "payment_method" => $method,
        "amount" => number_format($expectedAmount, 2, ".", ""),
        "currency" => "INR",
        "status" => $paymentStatus,
        "recipient_phone" => $recipient
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    $knownErrors = [
        "Transaction not found",
        "Only accepted bookings can be paid",
        "Final transport fare must be calculated before payment",
        "The seller must accept the buy request before payment",
        "Vehicle is not available for sale",
        "Payment participants are invalid",
        "Transaction amount is invalid",
        "Amount does not match the transaction"
    ];

    $message = in_array($e->getMessage(), $knownErrors, true)
        ? $e->getMessage()
        : (strpos($e->getMessage(), "An active payment already exists:") === 0
            ? $e->getMessage()
            : "Unable to create payment record. Check server logs.");

    $code = in_array($e->getMessage(), $knownErrors, true) ? 409 : 400;
    respond($code, [
        "success" => false,
        "message" => $message
    ]);
}
?>
