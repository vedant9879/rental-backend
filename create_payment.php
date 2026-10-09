<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

function respond(int $httpCode, array $payload): void {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function inputData(): array {
    $raw = file_get_contents("php://input");
    $json = json_decode($raw ?: "", true);
    return is_array($json) ? array_merge($_POST, $json) : $_POST;
}

function normalizePhone($value): string {
    return preg_replace('/\s+/', '', trim((string)$value));
}

function moneyEquals($a, $b): bool {
    return abs(round((float)$a, 2) - round((float)$b, 2)) < 0.005;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, ["success" => false, "message" => "Use POST"]);
}

$data = inputData();

$payerPhone = normalizePhone($data["payer_phone"] ?? "");
$serviceType = strtolower(trim((string)($data["service_type"] ?? "")));
$serviceRecordId = (int)($data["service_record_id"] ?? $data["record_id"] ?? $data["request_id"] ?? 0);
$paymentMethod = strtolower(trim((string)($data["payment_method"] ?? "")));
$amountInput = trim((string)($data["amount"] ?? ""));
$upiReference = trim((string)($data["upi_transaction_reference"] ?? $data["transaction_reference"] ?? ""));
$notes = trim((string)($data["notes"] ?? ""));

if ($payerPhone === "" || $serviceRecordId <= 0 || $amountInput === "" ||
    !is_numeric($amountInput) || !is_finite((float)$amountInput) ||
    (float)$amountInput <= 0 || (float)$amountInput > 100000000) {
    respond(400, ["success" => false, "message" => "Valid payer_phone, service_record_id and amount are required"]);
}

if (!in_array($serviceType, ["rental", "transport", "sale"], true)) {
    respond(400, ["success" => false, "message" => "Invalid service_type"]);
}

if (!in_array($paymentMethod, ["upi_manual", "cod"], true)) {
    respond(400, ["success" => false, "message" => "Invalid payment_method"]);
}

if ($paymentMethod === "upi_manual" && $upiReference === "") {
    respond(400, ["success" => false, "message" => "Enter the UPI transaction reference before submitting payment"]);
}

if ($upiReference !== "" && strlen($upiReference) > 100) {
    respond(400, ["success" => false, "message" => "UPI transaction reference is too long"]);
}

if (strlen($notes) > 500) {
    $notes = substr($notes, 0, 500);
}

$amount = round((float)$amountInput, 2);
$recipientPhone = "";
$expectedAmount = 0.0;
$currency = "INR";

mysqli_begin_transaction($conn);

try {
    // Verify the underlying service record and calculate the amount on the server.
    if ($serviceType === "rental") {
        $sql = "
            SELECT user_phone, owner_phone, total_price, status
            FROM bookings
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Unable to verify rental booking");
        }
        mysqli_stmt_bind_param($stmt, "i", $serviceRecordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $record = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$record) {
            throw new Exception("Rental booking not found");
        }
        if (strtolower(trim((string)$record["status"])) !== "accepted") {
            throw new Exception("Rental booking must be accepted before payment");
        }
        if (normalizePhone($record["user_phone"]) !== $payerPhone) {
            throw new Exception("Payer does not match the rental customer");
        }

        $recipientPhone = normalizePhone($record["owner_phone"]);
        $expectedAmount = (float)$record["total_price"];

    } elseif ($serviceType === "sale") {
        $sql = "
            SELECT
                br.buyer_phone,
                br.seller_phone,
                br.status,
                v.selling_price,
                v.listing_type
            FROM buy_requests br
            INNER JOIN vehicles v ON v.id = br.vehicle_id
            WHERE br.id = ?
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Unable to verify vehicle purchase request");
        }
        mysqli_stmt_bind_param($stmt, "i", $serviceRecordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $record = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$record) {
            throw new Exception("Vehicle purchase request not found");
        }
        if (strtolower(trim((string)$record["status"])) !== "accepted") {
            throw new Exception("Purchase request must be accepted before payment");
        }
        if (normalizePhone($record["buyer_phone"]) !== $payerPhone) {
            throw new Exception("Payer does not match the buyer");
        }

        $listingType = strtolower(trim((string)$record["listing_type"]));
        if (!in_array($listingType, ["sell", "rent + sell", "rent+sell"], true)) {
            throw new Exception("This vehicle is not listed for sale");
        }

        $recipientPhone = normalizePhone($record["seller_phone"]);
        $expectedAmount = (float)$record["selling_price"];

    } else { // transport
        $sql = "
            SELECT requester_phone, owner_phone, final_fare, status
            FROM transport_requests
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Unable to verify transport request");
        }
        mysqli_stmt_bind_param($stmt, "i", $serviceRecordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $record = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$record) {
            throw new Exception("Transport request not found");
        }
        if (strtolower(trim((string)$record["status"])) !== "accepted") {
            throw new Exception("Transport request must be accepted before payment");
        }
        if (normalizePhone($record["requester_phone"]) !== $payerPhone) {
            throw new Exception("Payer does not match the transport customer");
        }
        if ($record["final_fare"] === null || !is_numeric($record["final_fare"]) ||
            (float)$record["final_fare"] <= 0) {
            throw new Exception("Final transport fare is not available yet");
        }

        $recipientPhone = normalizePhone($record["owner_phone"]);
        $expectedAmount = (float)$record["final_fare"];
    }

    if ($recipientPhone === "") {
        throw new Exception("Recipient phone is missing from the service record");
    }
    if ($expectedAmount <= 0 || !is_finite($expectedAmount)) {
        throw new Exception("The server-calculated payment amount is invalid");
    }
    if (!moneyEquals($amount, $expectedAmount)) {
        throw new Exception("Payment amount does not match the confirmed amount of INR " .
            number_format($expectedAmount, 2, ".", ""));
    }

    // Prevent another active payment for the same service record.
    $duplicateSql = "
        SELECT id, payment_reference, status
        FROM payments
        WHERE service_type = ?
          AND service_record_id = ?
          AND status IN ('pending', 'submitted', 'paid')
        LIMIT 1
        FOR UPDATE
    ";
    $duplicateStmt = mysqli_prepare($conn, $duplicateSql);
    if (!$duplicateStmt) {
        throw new Exception("Unable to check existing payments");
    }
    mysqli_stmt_bind_param($duplicateStmt, "si", $serviceType, $serviceRecordId);
    mysqli_stmt_execute($duplicateStmt);
    $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
    $duplicate = mysqli_fetch_assoc($duplicateResult);
    mysqli_stmt_close($duplicateStmt);

    if ($duplicate) {
        mysqli_commit($conn);
        respond(200, [
            "success" => true,
            "message" => "A payment already exists for this service",
            "payment_reference" => $duplicate["payment_reference"],
            "status" => $duplicate["status"],
            "service_type" => $serviceType,
            "service_record_id" => $serviceRecordId,
            "amount" => number_format($expectedAmount, 2, ".", ""),
            "currency" => $currency
        ]);
    }

    $paymentReference = "RXP" . date("ymdHis") . strtoupper(bin2hex(random_bytes(4)));
    $paymentStatus = ($paymentMethod === "upi_manual") ? "submitted" : "pending";
    $storedUpiReference = ($paymentMethod === "upi_manual") ? $upiReference : null;
    $storedNotes = ($notes === "") ? null : $notes;
    $expectedAmount = round($expectedAmount, 2);

    $insertSql = "
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
            upi_transaction_reference,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        throw new Exception("Unable to prepare payment record");
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        "ssssisdssss",
        $paymentReference,
        $payerPhone,
        $recipientPhone,
        $serviceType,
        $serviceRecordId,
        $paymentMethod,
        $expectedAmount,
        $currency,
        $paymentStatus,
        $storedUpiReference,
        $storedNotes
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        $error = mysqli_stmt_error($insertStmt);
        mysqli_stmt_close($insertStmt);
        throw new Exception("Unable to create payment record: " . $error);
    }
    mysqli_stmt_close($insertStmt);

    mysqli_commit($conn);

    respond(200, [
        "success" => true,
        "message" => ($paymentMethod === "upi_manual")
            ? "Payment submitted. The recipient must verify the UPI receipt."
            : "Cash on delivery payment recorded as pending.",
        "payment_reference" => $paymentReference,
        "status" => $paymentStatus,
        "payer_phone" => $payerPhone,
        "recipient_phone" => $recipientPhone,
        "service_type" => $serviceType,
        "service_record_id" => $serviceRecordId,
        "payment_method" => $paymentMethod,
        "amount" => number_format($expectedAmount, 2, ".", ""),
        "currency" => $currency
    ]);

} catch (Throwable $e) {
    mysqli_rollback($conn);
    respond(400, [
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
