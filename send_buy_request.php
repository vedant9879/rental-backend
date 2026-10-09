```php
<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [
    "success" => false,
    "message" => "Unable to send buy request"
];

try {
    $vehicleId = (int)($_POST["vehicle_id"] ?? 0);
    $buyerPhone = trim($_POST["buyer_phone"] ?? "");
    $buyerMessage = trim($_POST["buyer_message"] ?? "");

    if ($vehicleId <= 0) {
        throw new Exception("Invalid vehicle");
    }

    if ($buyerPhone === "") {
        throw new Exception("Please login again");
    }

    if (strlen($buyerPhone) > 30) {
        throw new Exception("Invalid buyer phone");
    }

    if (strlen($buyerMessage) > 1000) {
        throw new Exception("Message must not exceed 1000 characters");
    }

    $conn->begin_transaction();

    // Lock the vehicle row while checking and creating a request.
    $stmt = $conn->prepare("
        SELECT id, owner_phone, vehicle_name, listing_type, selling_price
        FROM vehicles
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();

    $vehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vehicle) {
        throw new Exception("Vehicle not found");
    }

    $sellerPhone = trim($vehicle["owner_phone"] ?? "");
    $listingType = strtolower(trim($vehicle["listing_type"] ?? ""));
    $sellingPrice = (float)($vehicle["selling_price"] ?? 0);

    if (!in_array($listingType, ["sell", "rent + sell"], true)) {
        throw new Exception("This vehicle is not available for purchase");
    }

    if ($sellerPhone === "") {
        throw new Exception("Seller information unavailable");
    }

    if ($buyerPhone === $sellerPhone) {
        throw new Exception("You cannot send a buy request for your own vehicle");
    }

    if ($sellingPrice <= 0) {
        throw new Exception("The seller has not set a valid selling price");
    }

    // Prevent another pending/accepted request from this buyer.
    $check = $conn->prepare("
        SELECT id, status
        FROM buy_requests
        WHERE vehicle_id = ?
          AND buyer_phone = ?
          AND status IN ('pending', 'accepted')
        LIMIT 1
    ");
    $check->bind_param("is", $vehicleId, $buyerPhone);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        throw new Exception(
            "You already have a " . $existing["status"] .
            " request for this vehicle"
        );
    }

    // A vehicle must not be accepted for two buyers.
    $acceptedCheck = $conn->prepare("
        SELECT id
        FROM buy_requests
        WHERE vehicle_id = ?
          AND status = 'accepted'
        LIMIT 1
    ");
    $acceptedCheck->bind_param("i", $vehicleId);
    $acceptedCheck->execute();
    $alreadyAccepted = $acceptedCheck->get_result()->num_rows > 0;
    $acceptedCheck->close();

    if ($alreadyAccepted) {
        throw new Exception("This vehicle already has an accepted buyer");
    }

    // Store the buyer's request; do not mark it as paid here.
    $insert = $conn->prepare("
        INSERT INTO buy_requests
            (vehicle_id, buyer_phone, seller_phone, status, buyer_message)
        VALUES (?, ?, ?, 'pending', ?)
    ");
    $insert->bind_param(
        "isss",
        $vehicleId,
        $buyerPhone,
        $sellerPhone,
        $buyerMessage
    );
    $insert->execute();
    $requestId = (int)$conn->insert_id;
    $insert->close();

    $conn->commit();

    $response["success"] = true;
    $response["message"] = "Buy request sent successfully";
    $response["request_id"] = $requestId;
    $response["status"] = "pending";
    $response["selling_price"] = number_format($sellingPrice, 2, ".", "");
    $response["currency"] = "INR";

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    $response["success"] = false;

    // Show expected validation errors, but do not expose SQL details.
    $response["message"] = $e->getMessage();

    http_response_code(
        in_array($e->getMessage(), [
            "Invalid vehicle",
            "Please login again",
            "Invalid buyer phone",
            "Message must not exceed 1000 characters",
            "Vehicle not found",
            "This vehicle is not available for purchase",
            "Seller information unavailable",
            "You cannot send a buy request for your own vehicle",
            "The seller has not set a valid selling price",
            "This vehicle already has an accepted buyer"
        ], true) ? 400 : 500
    );
}

echo json_encode($response);
?>
```
