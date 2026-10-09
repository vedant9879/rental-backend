**File:** `create_payment.php`

```php
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
$service = trim($input["service_type"] ?? "");
$recordId = filter_var(
    $input["service_record_id"] ?? null,
    FILTER_VALIDATE_INT
);
$method = trim($input["payment_method"] ?? "");
$amount = $input["amount"] ?? null;
$upiReference = trim(
    $input["upi_transaction_reference"] ?? ""
);

if (
    $payer === "" ||
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

/*
 * Determine the payer, recipient and amount from the
 * existing transaction. Do not trust the amount or
 * recipient supplied by the Android app.
 */

$recipient = "";
$expectedAmount = 0.0;

if ($service === "rental") {
    $stmt = $conn->prepare(
        "SELECT user_phone, owner_phone, total_price, status
         FROM bookings
         WHERE id = ?
         LIMIT 1"
    );
} elseif ($service === "transport") {
    $stmt = $conn->prepare(
        "SELECT requester_phone, owner_phone, minimum_fare,
                status
         FROM transport_requests
         WHERE id = ?
         LIMIT 1"
    );
} else {
    $stmt = $conn->prepare(
        "SELECT buyer_phone, seller_phone, selling_price, status
         FROM buy_requests
         INNER JOIN vehicles
             ON vehicles.id = buy_requests.vehicle_id
         WHERE buy_requests.id = ?
         LIMIT 1"
    );
}

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Unable to prepare payment lookup"
    ]);
}

$stmt->bind_param("i", $recordId);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();
$stmt->close();

if (!$transaction) {
    respond(404, [
        "success" => false,
        "message" => "Transaction not found"
    ]);
}

if ($service === "rental") {
    $actualPayer = $transaction["user_phone"];
    $recipient = $transaction["owner_phone"];
    $expectedAmount = (float)$transaction["total_price"];

    if (!in_array(
        $transaction["status"],
        ["accepted", "completed"],
        true
    )) {
        respond(409, [
            "success" => false,
            "message" => "Booking is not eligible for payment"
        ]);
    }
} elseif ($service === "transport") {
    $actualPayer = $transaction["requester_phone"];
    $recipient = $transaction["owner_phone"];
    $expectedAmount = (float)$transaction["minimum_fare"];

    if (!in_array(
        $transaction["status"],
        ["accepted", "pickup", "in_transit", "delivered"],
        true
    )) {
        respond(409, [
            "success" => false,
            "message" => "Transport request is not eligible for payment"
        ]);
    }
} else {
    $actualPayer = $transaction["buyer_phone"];
    $recipient = $transaction["seller_phone"];
    $expectedAmount = (float)$transaction["selling_price"];

    if (!in_array(
        $transaction["status"],
        ["accepted", "completed"],
        true
    )) {
        respond(409, [
            "success" => false,
            "message" => "Sale request is not eligible for payment"
        ]);
    }
}

if (
    !hash_equals((string)$actualPayer, (string)$payer) ||
    $recipient === "" ||
    $actualPayer === $recipient
) {
    respond(403, [
        "success" => false,
        "message" => "Payment participants are invalid"
    ]);
}

/*
 * Until partial payments and split payments are supported,
 * require the amount to match the transaction amount.
 */
if (abs((float)$amount - $expectedAmount) > 0.009) {
    respond(400, [
        "success" => false,
        "message" => "Amount does not match the transaction",
        "expected_amount" => $expectedAmount
    ]);
}

if ($expectedAmount <= 0) {
    respond(400, [
        "success" => false,
        "message" => "Transaction amount is invalid"
    ]);
}

/*
 * Transport minimum_fare is only a provisional amount.
 * The actual final fare must be calculated and stored
 * before transport payments are enabled.
 */
if ($service === "transport") {
    respond(409, [
        "success" => false,
        "message" =>
            "Final transport fare must be calculated before payment"
    ]);
}

/*
 * Avoid creating another active payment record for
 * the same transaction.
 */
$stmt = $conn->prepare(
    "SELECT payment_reference, status
     FROM payments
     WHERE service_type = ?
       AND service_record_id = ?
       AND status IN ('pending', 'submitted', 'paid')
     LIMIT 1"
);

$stmt->bind_param("si", $service, $recordId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    respond(409, [
        "success" => false,
        "message" => "An active payment already exists",
        "payment_reference" => $existing["payment_reference"],
        "status" => $existing["status"]
    ]);
}

$paymentReference = bin2hex(random_bytes(16));

$stmt = $conn->prepare(
    "INSERT INTO payments (
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'INR', 'pending', NULLIF(?, ''))"
);

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Unable to prepare payment record"
    ]);
}

$stmt->bind_param(
    "ssssisd s",
    $paymentReference,
    $actualPayer,
    $recipient,
    $service,
    $recordId,
    $method,
    $expectedAmount,
    $upiReference
);

if (!$stmt->execute()) {
    $stmt->close();

    respond(500, [
        "success" => false,
        "message" => "Could not create payment record"
    ]);
}

$stmt->close();

respond(201, [
    "success" => true,
    "message" => "Payment record created",
    "payment_reference" => $paymentReference,
    "service_type" => $service,
    "payment_method" => $method,
    "amount" => $expectedAmount,
    "status" => "pending"
]);
?>
```
