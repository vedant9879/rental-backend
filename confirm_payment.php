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
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

function moneyEquals(mixed $a, mixed $b): bool
{
    return abs(round((float)$a, 2) - round((float)$b, 2)) < 0.005;
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

$payerPhone = normalizePhone($data["payer_phone"] ?? "");
$serviceType = strtolower(trim((string)($data["service_type"] ?? "")));
$serviceRecordId = (int)(
    $data["service_record_id"]
    ?? $data["record_id"]
    ?? $data["request_id"]
    ?? 0
);
$paymentMethod = strtolower(trim((string)($data["payment_method"] ?? "")));
$amountInput = trim((string)($data["amount"] ?? ""));
$upiReference = trim((string)(
    $data["upi_transaction_reference"]
    ?? $data["transaction_reference"]
    ?? ""
));
$notes = trim((string)($data["notes"] ?? ""));

if (
    $payerPhone === "" ||
    strlen($payerPhone) > 30 ||
    $serviceRecordId <= 0 ||
    $amountInput === "" ||
    !is_numeric($amountInput) ||
    !is_finite((float)$amountInput) ||
    (float)$amountInput <= 0 ||
    (float)$amountInput > 100000000
) {
    respond(400, [
        "success" => false,
        "message" => "Valid payer_phone, service_record_id and amount are required"
    ]);
}

if (!in_array($serviceType, ["rental", "transport", "sale"], true)) {
    respond(400, [
        "success" => false,
        "message" => "Invalid service_type"
    ]);
}

/*
 * Current payments table supports only these methods.
 * Card and net-banking payments require a real payment gateway.
 */
if (!in_array($paymentMethod, ["upi_manual", "cod"], true)) {
    respond(400, [
        "success" => false,
        "message" => "Unsupported payment method. Choose Manual UPI or COD."
    ]);
}

if ($paymentMethod === "upi_manual" && $upiReference === "") {
    respond(400, [
        "success" => false,
        "message" => "Enter the UPI transaction reference before submitting payment"
    ]);
}

if (strlen($upiReference) > 100) {
    respond(400, [
        "success" => false,
        "message" => "UPI transaction reference is too long"
    ]);
}

if (strlen($notes) > 500) {
    $notes = substr($notes, 0, 500);
}

$amount = round((float)$amountInput, 2);
$recipientPhone = "";
$expectedAmount = 0.0;
$currency = "INR";
$methodLabel = $paymentMethod === "upi_manual"
    ? "Manual UPI"
    : "Cash on Delivery";

try {
    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();

    /*
     * RENTAL BOOKING
     */
    if ($serviceType === "rental") {
        $sql = "
            SELECT user_phone, owner_phone, total_price, status
            FROM bookings
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Unable to verify rental booking");
        }

        $stmt->bind_param("i", $serviceRecordId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Unable to read rental booking");
        }

        $result = $stmt->get_result();
        $record = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$record) {
            throw new Exception("Rental booking not found");
        }

        $bookingStatus = strtolower(trim((string)$record["status"]));

        /*
         * Your actual bookings table uses pending, accepted,
         * completed and cancelled. Permit payment for accepted
         * bookings only, preventing new requests for completed
         * or cancelled bookings.
         */
        if ($bookingStatus !== "accepted") {
            throw new Exception(
                "Payment is available only after the owner accepts the booking"
            );
        }

        if (normalizePhone($record["user_phone"]) !== $payerPhone) {
            throw new Exception("Payer does not match the rental customer");
        }

        $recipientPhone = normalizePhone($record["owner_phone"]);
        $expectedAmount = (float)$record["total_price"];
    }

    /*
     * VEHICLE SALE
     * Preserves the existing sale verification branch.
     */
    elseif ($serviceType === "sale") {
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

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Unable to verify vehicle purchase request");
        }

        $stmt->bind_param("i", $serviceRecordId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Unable to read vehicle purchase request");
        }

        $result = $stmt->get_result();
        $record = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$record) {
            throw new Exception("Vehicle purchase request not found");
        }

        $purchaseStatus = strtolower(trim((string)$record["status"]));

        if (!in_array($purchaseStatus, ["accepted", "completed"], true)) {
            throw new Exception(
                "Purchase request must be accepted or completed before payment"
            );
        }

        if (normalizePhone($record["buyer_phone"]) !== $payerPhone) {
            throw new Exception("Payer does not match the buyer");
        }

        $listingType = strtolower(trim((string)$record["listing_type"]));

        if (!in_array(
            $listingType,
            ["sell", "rent + sell", "rent+sell"],
            true
        )) {
            throw new Exception("This vehicle is not listed for sale");
        }

        $recipientPhone = normalizePhone($record["seller_phone"]);
        $expectedAmount = (float)$record["selling_price"];
    }

    /*
     * TRANSPORT REQUEST
     * Preserves the existing transport verification branch.
     */
    else {
        $sql = "
            SELECT requester_phone, owner_phone, final_fare, status
            FROM transport_requests
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Unable to verify transport request");
        }

        $stmt->bind_param("i", $serviceRecordId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Unable to read transport request");
        }

        $result = $stmt->get_result();
        $record = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$record) {
            throw new Exception("Transport request not found");
        }

        $transportStatus = strtolower(trim((string)$record["status"]));

        if (!in_array($transportStatus, ["accepted", "delivered"], true)) {
            throw new Exception(
                "Transport request must be accepted or delivered before payment"
            );
        }

        if (normalizePhone($record["requester_phone"]) !== $payerPhone) {
            throw new Exception(
                "Payer does not match the transport customer"
            );
        }

        if (
            $record["final_fare"] === null ||
            !is_numeric($record["final_fare"]) ||
            (float)$record["final_fare"] <= 0
        ) {
            throw new Exception("Final transport fare is not available yet");
        }

        $recipientPhone = normalizePhone($record["owner_phone"]);
        $expectedAmount = (float)$record["final_fare"];
    }

    /*
     * VALIDATE SERVER-CALCULATED AMOUNT AND RECIPIENT
     */
    if ($recipientPhone === "" || strlen($recipientPhone) > 30) {
        throw new Exception("Recipient phone is missing or invalid");
    }

    if ($expectedAmount <= 0 || !is_finite($expectedAmount)) {
        throw new Exception("The server-calculated payment amount is invalid");
    }

    $expectedAmount = round($expectedAmount, 2);

    if (!moneyEquals($amount, $expectedAmount)) {
        throw new Exception(
            "Payment amount does not match the confirmed amount of INR " .
            number_format($expectedAmount, 2, ".", "")
        );
    }

    /*
     * PREVENT DUPLICATE ACTIVE PAYMENTS
     */
    $duplicateSql = "
        SELECT id, payment_reference, status, payment_method
        FROM payments
        WHERE service_type = ?
          AND service_record_id = ?
          AND status IN ('pending', 'submitted', 'paid', 'confirmed')
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $conn->prepare($duplicateSql);

    if (!$stmt) {
        throw new Exception("Unable to check existing payments");
    }

    $stmt->bind_param("si", $serviceType, $serviceRecordId);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Unable to check existing payments");
    }

    $result = $stmt->get_result();
    $duplicate = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($duplicate) {
        $conn->commit();

        respond(200, [
            "success" => true,
            "message" => "A payment request already exists for this service",
            "payment_reference" => $duplicate["payment_reference"],
            "status" => $duplicate["status"],
            "payment_method" => $duplicate["payment_method"],
            "service_type" => $serviceType,
            "service_record_id" => $serviceRecordId,
            "amount" => number_format($expectedAmount, 2, ".", ""),
            "currency" => $currency,
            "online_payment_processed" => false
        ]);
    }

    /*
     * CREATE PAYMENT RECORD
     */
    $paymentReference =
        "RXP" .
        date("ymdHis") .
        strtoupper(bin2hex(random_bytes(4)));

    $paymentStatus = $paymentMethod === "upi_manual"
        ? "submitted"
        : "pending";

    $storedUpiReference = $paymentMethod === "upi_manual"
        ? $upiReference
        : null;

    $storedNotes = $notes === "" ? null : $notes;

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

    $stmt = $conn->prepare($insertSql);

    if (!$stmt) {
        throw new Exception("Unable to prepare payment record");
    }

    $stmt->bind_param(
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

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Unable to create payment record");
    }

    $stmt->close();
    $conn->commit();

    $message = $paymentMethod === "upi_manual"
        ? "UPI payment submitted. The recipient must verify the transaction."
        : "Cash payment request recorded as pending. The recipient must confirm receipt.";

    respond(200, [
        "success" => true,
        "message" => $message,
        "payment_reference" => $paymentReference,
        "status" => $paymentStatus,
        "payer_phone" => $payerPhone,
        "recipient_phone" => $recipientPhone,
        "service_type" => $serviceType,
        "service_record_id" => $serviceRecordId,
        "payment_method" => $paymentMethod,
        "payment_method_label" => $methodLabel,
        "amount" => number_format($expectedAmount, 2, ".", ""),
        "currency" => $currency,
        "online_payment_processed" => false
    ]);

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // No active transaction to roll back.
    }

    error_log("create_payment.php error: " . $e->getMessage());

    respond(400, [
        "success" => false,
        "message" => $e instanceof mysqli_sql_exception
            ? "Unable to create payment record. Check the database configuration."
            : $e->getMessage()
    ]);
}
?>
