<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

function respond($status, $message, $httpCode = 200, $extra = []) {
    http_response_code($httpCode);
    echo json_encode(
        array_merge([
            "status" => $status,
            "message" => $message
        ], $extra),
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond("error", "Only POST requests are allowed", 405);
}

require_once __DIR__ . "/db.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    respond("error", "Database connection is unavailable", 500);
}

$conn->set_charset("utf8mb4");

$vehicleId = filter_input(INPUT_POST, "vehicle_id", FILTER_VALIDATE_INT);

if (!$vehicleId || $vehicleId <= 0) {
    respond("error", "A valid vehicle_id is required", 400);
}

function postValue($key) {
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return null;
    }

    return trim((string) $_POST[$key]);
}

function normalizeType($value) {
    $value = strtolower(trim((string) $value));
    $value = str_replace(["_", "-"], " ", $value);

    if (
        strpos($value, "transport") !== false ||
        strpos($value, "goods") !== false ||
        strpos($value, "logistic") !== false
    ) {
        return "transport";
    }

    if (
        strpos($value, "buy") !== false ||
        strpos($value, "sell") !== false ||
        strpos($value, "marketplace") !== false ||
        strpos($value, "sale") !== false
    ) {
        return "buy_sell";
    }

    if (
        strpos($value, "rent") !== false ||
        strpos($value, "rental") !== false
    ) {
        return "rent";
    }

    return "";
}

/*
 * Read the actual vehicle record first.
 * The listing type stored in the database is authoritative;
 * this endpoint does not allow the client to change listing type.
 */
$stmt = $conn->prepare(
    "SELECT * FROM vehicles WHERE id = ? LIMIT 1"
);

if (!$stmt) {
    respond("error", "Unable to prepare vehicle lookup", 500);
}

$stmt->bind_param("i", $vehicleId);

if (!$stmt->execute()) {
    $stmt->close();
    respond("error", "Unable to load vehicle", 500);
}

$result = $stmt->get_result();
$vehicle = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$vehicle) {
    respond("error", "Vehicle not found", 404);
}

/*
 * Inspect the schema so optional columns are only updated
 * when they actually exist in this database.
 */
$columnsResult = $conn->query("SHOW COLUMNS FROM `vehicles`");

if (!$columnsResult) {
    respond("error", "Unable to inspect vehicle database columns", 500);
}

$columns = [];

while ($column = $columnsResult->fetch_assoc()) {
    $columns[$column["Field"]] = $column;
}

$columnsResult->free();

$listingType = normalizeType($vehicle["listing_type"] ?? "");

if ($listingType === "") {
    /*
     * Some databases may use a different listing discriminator.
     * Check service_type only when it identifies transport.
     */
    $listingType = normalizeType($vehicle["service_type"] ?? "");
}

if ($listingType === "") {
    respond(
        "error",
        "Unable to determine listing type from the database. Check listing_type.",
        422
    );
}

/*
 * Validate values and create a prepared UPDATE.
 * Only these explicitly approved fields can be updated.
 */
$commonFields = [
    "vehicle_name" => "text",
    "city"         => "text",
    "address"      => "text"
];

$rentFields = [
    "price_per_day" => "number",
    "price_6hr"     => "number",
    "price_12hr"    => "number",
    "quantity"      => "integer",
    "deposit"       => "number"
];

$buySellFields = [
    "selling_price"      => "number",
    "vehicle_condition"  => "text",
    "manufacturing_year" => "integer",
    "kilometers_driven"  => "integer",
    "ownership"          => "text"
];

$transportFields = [
    "service_type"             => "text",
    "transport_base_fare"      => "number",
    "transport_minimum_fare"   => "number",
    "transport_per_km"         => "number",
    "transport_driver_charge"  => "number",
    "transport_waiting_charge" => "number"
];

$allowedFields = $commonFields;

if ($listingType === "rent") {
    $allowedFields = array_merge($allowedFields, $rentFields);
} elseif ($listingType === "buy_sell") {
    $allowedFields = array_merge($allowedFields, $buySellFields);
} elseif ($listingType === "transport") {
    $allowedFields = array_merge($allowedFields, $transportFields);
}

$updates = [];
$values = [];
$types = "";

foreach ($allowedFields as $field => $kind) {

    // Do not attempt to update columns absent from this database.
    if (!isset($columns[$field])) {
        continue;
    }

    // Common fields must be supplied by the client.
    if (array_key_exists($field, $commonFields) && postValue($field) === null) {
        respond("error", "Missing required field: " . $field, 400);
    }

    $value = postValue($field);

    // Do not accidentally overwrite an existing value if a parameter
    // was not sent by an older Android client.
    if ($value === null) {
        continue;
    }

    if ($kind === "text") {
        if ($field === "vehicle_name" && $value === "") {
            respond("error", "Vehicle name cannot be empty", 422);
        }

        if (($field === "city" || $field === "address") && $value === "") {
            respond("error", ucfirst($field) . " cannot be empty", 422);
        }

        $updates[$field] = $value;
        $values[] = $value;
        $types .= "s";
        continue;
    }

    /*
     * Blank optional numeric values:
     * use NULL if the column permits NULL; otherwise preserve its
     * existing value rather than converting blank input to zero.
     */
    if ($value === "") {
        if (($columns[$field]["Null"] ?? "NO") === "YES") {
            $updates[$field] = null;
            $values[] = null;
            $types .= "s";
        }
        continue;
    }

    if (!is_numeric($value)) {
        respond("error", "Invalid numeric value for " . $field, 422);
    }

    $number = (float) $value;

    if (!is_finite($number) || $number < 0) {
        respond("error", $field . " cannot be negative", 422);
    }

    if ($kind === "integer") {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            respond("error", $field . " must be a whole number", 422);
        }

        $integer = (int) $value;

        if ($field === "quantity" && $integer < 1) {
            respond("error", "Quantity must be at least 1", 422);
        }

        if (
            $field === "manufacturing_year" &&
            ($integer < 1900 || $integer > ((int) date("Y") + 1))
        ) {
            respond("error", "Manufacturing year is invalid", 422);
        }

        $updates[$field] = $integer;
        $values[] = $integer;
        $types .= "i";
    } else {
        $updates[$field] = $value;
        $values[] = $value;
        $types .= "s";
    }
}

if (empty($updates)) {
    respond("error", "No compatible fields were provided for update", 422);
}

$setParts = [];

foreach (array_keys($updates) as $field) {
    // Field names come only from the hard-coded allowlist above.
    $setParts[] = "`" . $field . "` = ?";
}

$sql = "UPDATE `vehicles` SET " .
       implode(", ", $setParts) .
       " WHERE `id` = ? LIMIT 1";

$values[] = $vehicleId;
$types .= "i";

$updateStmt = $conn->prepare($sql);

if (!$updateStmt) {
    respond("error", "Unable to prepare vehicle update", 500);
}

/*
 * mysqli bind_param requires references.
 */
$bindArgs = [$types];

foreach ($values as $index => $value) {
    $bindArgs[] = &$values[$index];
}

if (!call_user_func_array([$updateStmt, "bind_param"], $bindArgs)) {
    $updateStmt->close();
    respond("error", "Unable to bind vehicle update values", 500);
}

if (!$updateStmt->execute()) {
    $updateStmt->close();
    respond("error", "Database update failed", 500);
}

$updateStmt->close();

respond(
    "success",
    "Updated Successfully",
    200,
    [
        "vehicle_id" => (int) $vehicleId,
        "listing_type" => $listingType,
        "updated_fields" => array_keys($updates)
    ]
);
?>
