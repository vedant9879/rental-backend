<?php

require_once "db.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

mysqli_set_charset($conn, "utf8mb4");

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function sendResponse($status, $message, $extra = [])
{
    echo json_encode(
        array_merge(
            [
                "status" => $status,
                "message" => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| PREFLIGHT REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

/*
|--------------------------------------------------------------------------
| POST METHOD ONLY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    sendResponse(
        "error",
        "Use POST to submit a buy request"
    );
}

/*
|--------------------------------------------------------------------------
| READ REQUEST FIELDS
|--------------------------------------------------------------------------
*/

$vehicleId = trim($_POST["vehicle_id"] ?? "");

$buyerPhone = trim(
    $_POST["buyer_phone"]
        ?? $_POST["user_phone"]
        ?? ""
);

$sellerPhone = trim(
    $_POST["seller_phone"]
        ?? $_POST["owner_phone"]
        ?? ""
);

$buyerMessage = trim(
    $_POST["buyer_message"] ?? ""
);

$buyerAddress = trim(
    $_POST["buyer_address"] ?? ""
);

$buyerCity = trim(
    $_POST["buyer_city"] ?? ""
);

$buyerPincode = trim(
    $_POST["buyer_pincode"] ?? ""
);

/*
|--------------------------------------------------------------------------
| VALIDATE REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $vehicleId === "" ||
    $buyerPhone === "" ||
    $sellerPhone === ""
) {
    sendResponse(
        "error",
        "Vehicle, buyer and seller information are required"
    );
}

if (!ctype_digit($vehicleId) || (int)$vehicleId <= 0) {
    sendResponse(
        "error",
        "Invalid vehicle ID"
    );
}

if (
    !preg_match('/^[0-9]{7,20}$/', $buyerPhone) ||
    !preg_match('/^[0-9]{7,20}$/', $sellerPhone)
) {
    sendResponse(
        "error",
        "Invalid buyer or seller phone number"
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATE BUYER COLLECTION ADDRESS
|--------------------------------------------------------------------------
*/

if (
    $buyerAddress === "" ||
    $buyerCity === "" ||
    $buyerPincode === ""
) {
    sendResponse(
        "error",
        "Enter your collection address, city and pincode"
    );
}

if (
    mb_strlen($buyerAddress) > 255 ||
    mb_strlen($buyerCity) > 100
) {
    sendResponse(
        "error",
        "Address or city is too long"
    );
}

if (!preg_match('/^[1-9][0-9]{5}$/', $buyerPincode)) {
    sendResponse(
        "error",
        "Enter a valid 6-digit Indian pincode"
    );
}

if (mb_strlen($buyerMessage) > 5000) {
    sendResponse(
        "error",
        "Your message is too long"
    );
}

/*
|--------------------------------------------------------------------------
| VERIFY VEHICLE AND SELLER FROM DATABASE
|--------------------------------------------------------------------------
| The seller phone and selling price are read from the actual
| vehicle listing instead of trusting values sent by the app.
*/

$sqlVehicle = "
    SELECT
        id,
        owner_phone,
        vehicle_name,
        listing_type,
        selling_price,
        available
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$stmtVehicle = mysqli_prepare($conn, $sqlVehicle);

if (!$stmtVehicle) {
    error_log("Buy request vehicle prepare error: " . mysqli_error($conn));

    sendResponse(
        "error",
        "Unable to verify vehicle information"
    );
}

mysqli_stmt_bind_param(
    $stmtVehicle,
    "i",
    $vehicleId
);

if (!mysqli_stmt_execute($stmtVehicle)) {
    error_log("Buy request vehicle execute error: " . mysqli_stmt_error($stmtVehicle));

    mysqli_stmt_close($stmtVehicle);

    sendResponse(
        "error",
        "Unable to verify vehicle information"
    );
}

$resultVehicle = mysqli_stmt_get_result($stmtVehicle);

if (!$resultVehicle || mysqli_num_rows($resultVehicle) === 0) {
    mysqli_stmt_close($stmtVehicle);

    sendResponse(
        "error",
        "Vehicle listing not found"
    );
}

$vehicle = mysqli_fetch_assoc($resultVehicle);

mysqli_stmt_close($stmtVehicle);

/*
|--------------------------------------------------------------------------
| VERIFY SELLER
|--------------------------------------------------------------------------
*/

$actualSellerPhone = trim(
    $vehicle["owner_phone"] ?? ""
);

if (
    $actualSellerPhone === "" ||
    $actualSellerPhone !== $sellerPhone
) {
    sendResponse(
        "error",
        "Seller information does not match this vehicle"
    );
}

/*
|--------------------------------------------------------------------------
| PREVENT BUYING YOUR OWN VEHICLE
|--------------------------------------------------------------------------
*/

if ($buyerPhone === $actualSellerPhone) {
    sendResponse(
        "error",
        "You cannot send a buy request for your own vehicle"
    );
}

/*
|--------------------------------------------------------------------------
| VERIFY LISTING TYPE
|--------------------------------------------------------------------------
| This endpoint is for marketplace vehicles offered for sale.
*/

$listingType = strtolower(
    trim($vehicle["listing_type"] ?? "")
);

if ($listingType !== "sell") {
    sendResponse(
        "error",
        "This vehicle is not listed for sale"
    );
}

/*
|--------------------------------------------------------------------------
| VERIFY VEHICLE AVAILABILITY
|--------------------------------------------------------------------------
*/

if (isset($vehicle["available"]) && (int)$vehicle["available"] !== 1) {
    sendResponse(
        "error",
        "This vehicle is currently unavailable"
    );
}

/*
|--------------------------------------------------------------------------
| GET VERIFIED VEHICLE DETAILS
|--------------------------------------------------------------------------
*/

$vehicleName = trim(
    $vehicle["vehicle_name"] ?? ""
);

if ($vehicleName === "") {
    $vehicleName = "Vehicle";
}

$sellingPrice = (float)(
    $vehicle["selling_price"] ?? 0
);

if (!is_finite($sellingPrice) || $sellingPrice < 0) {
    sendResponse(
        "error",
        "Invalid vehicle selling price"
    );
}

/*
|--------------------------------------------------------------------------
| PREVENT DUPLICATE ACTIVE REQUESTS
|--------------------------------------------------------------------------
| A buyer may submit another request after an earlier request
| is cancelled or rejected.
*/

$sqlDuplicate = "
    SELECT id
    FROM buy_requests
    WHERE vehicle_id = ?
      AND buyer_phone = ?
      AND LOWER(status) IN ('pending', 'accepted')
    LIMIT 1
";

$stmtDuplicate = mysqli_prepare($conn, $sqlDuplicate);

if (!$stmtDuplicate) {
    error_log("Buy request duplicate prepare error: " . mysqli_error($conn));

    sendResponse(
        "error",
        "Unable to check existing requests"
    );
}

mysqli_stmt_bind_param(
    $stmtDuplicate,
    "is",
    $vehicleId,
    $buyerPhone
);

if (!mysqli_stmt_execute($stmtDuplicate)) {
    error_log("Buy request duplicate execute error: " . mysqli_stmt_error($stmtDuplicate));

    mysqli_stmt_close($stmtDuplicate);

    sendResponse(
        "error",
        "Unable to check existing requests"
    );
}

$resultDuplicate = mysqli_stmt_get_result($stmtDuplicate);

if ($resultDuplicate && mysqli_num_rows($resultDuplicate) > 0) {
    $existing = mysqli_fetch_assoc($resultDuplicate);

    mysqli_stmt_close($stmtDuplicate);

    sendResponse(
        "error",
        "You already have an active request for this vehicle",
        [
            "request_id" => (int)$existing["id"]
        ]
    );
}

mysqli_stmt_close($stmtDuplicate);

/*
|--------------------------------------------------------------------------
| SAVE BUY REQUEST AND COLLECTION ADDRESS
|--------------------------------------------------------------------------
*/

$sqlInsert = "
    INSERT INTO buy_requests
    (
        vehicle_id,
        buyer_phone,
        seller_phone,
        vehicle_name,
        selling_price,
        status,
        buyer_message,
        buyer_address,
        buyer_city,
        buyer_pincode
    )
    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
";

$stmtInsert = mysqli_prepare($conn, $sqlInsert);

if (!$stmtInsert) {
    error_log("Buy request insert prepare error: " . mysqli_error($conn));

    sendResponse(
        "error",
        "Unable to prepare your buy request"
    );
}

mysqli_stmt_bind_param(
    $stmtInsert,
    "isssdssss",
    $vehicleId,
    $buyerPhone,
    $actualSellerPhone,
    $vehicleName,
    $sellingPrice,
    $buyerMessage,
    $buyerAddress,
    $buyerCity,
    $buyerPincode
);

if (!mysqli_stmt_execute($stmtInsert)) {
    error_log("Buy request insert execute error: " . mysqli_stmt_error($stmtInsert));

    mysqli_stmt_close($stmtInsert);

    sendResponse(
        "error",
        "Unable to save your buy request. Please try again."
    );
}

$requestId = mysqli_insert_id($conn);

mysqli_stmt_close($stmtInsert);

/*
|--------------------------------------------------------------------------
| SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/

sendResponse(
    "success",
    "Buy request submitted successfully",
    [
        "request_id" => $requestId,
        "vehicle_id" => (int)$vehicleId,
        "buyer_phone" => $buyerPhone,
        "seller_phone" => $actualSellerPhone,
        "vehicle_name" => $vehicleName,
        "selling_price" => $sellingPrice,
        "status" => "pending",
        "buyer_address" => $buyerAddress,
        "buyer_city" => $buyerCity,
        "buyer_pincode" => $buyerPincode
    ]
);

?>
