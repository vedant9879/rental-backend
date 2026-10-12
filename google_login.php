<?php

include "db.php";

header("Content-Type: application/json; charset=UTF-8");

function respond($status, $message, $extra = []) {
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $extra));
    exit();
}

/*
|--------------------------------------------------------------------------
| RECEIVE REQUEST
|--------------------------------------------------------------------------
*/

$idToken = trim($_POST["id_token"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$requestedName = trim($_POST["name"] ?? "");
$requestedRole = trim($_POST["role"] ?? "user");

if ($idToken === "") {
    respond("error", "Firebase ID token is required");
}

/*
|--------------------------------------------------------------------------
| FIREBASE API CONFIGURATION
|--------------------------------------------------------------------------
*/

$apiKey = getenv("FIREBASE_WEB_API_KEY");

if (!$apiKey) {
    error_log("FIREBASE_WEB_API_KEY is not configured");
    respond("error", "Authentication service is not configured");
}

/*
|--------------------------------------------------------------------------
| VERIFY FIREBASE ID TOKEN
|--------------------------------------------------------------------------
*/

$ch = curl_init(
    "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key="
    . urlencode($apiKey)
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        "idToken" => $idToken
    ]),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($response === false || $curlError !== "") {
    error_log("Firebase token verification request failed");
    respond("error", "Unable to verify Google account. Try again.");
}

$firebaseData = json_decode($response, true);

if (
    $httpCode !== 200 ||
    empty($firebaseData["users"][0]["localId"])
) {
    respond("fail", "Google authentication could not be verified");
}

$firebaseUser = $firebaseData["users"][0];

$firebaseUid = $firebaseUser["localId"];
$email = strtolower(trim($firebaseUser["email"] ?? ""));
$firebaseName = trim($firebaseUser["displayName"] ?? "");

if (
    $email === "" ||
    ($firebaseUser["emailVerified"] ?? false) !== true
) {
    respond("fail", "A verified Google email is required");
}

/*
|--------------------------------------------------------------------------
| EXISTING GOOGLE ACCOUNT
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id, name, email, phone, role
    FROM users
    WHERE firebase_uid = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    respond("error", "Database error");
}

mysqli_stmt_bind_param($stmt, "s", $firebaseUid);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;

mysqli_stmt_close($stmt);

if ($user) {
    respond("success", "Google login successful", [
        "id" => $user["id"],
        "name" => $user["name"],
        "email" => $user["email"],
        "phone" => $user["phone"],
        "role" => $user["role"]
    ]);
}

/*
|--------------------------------------------------------------------------
| DO NOT SILENTLY LINK AN EXISTING ACCOUNT
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    respond("error", "Database error");
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$emailExists = $result && mysqli_num_rows($result) > 0;

mysqli_stmt_close($stmt);

if ($emailExists) {
    respond(
        "account_link_required",
        "This email already has a RentX account. Sign in using your existing method before linking Google."
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATE NEW GOOGLE REGISTRATION
|--------------------------------------------------------------------------
*/

if ($phone === "") {
    respond(
        "phone_required",
        "Enter your mobile number to create your RentX account."
    );
}

/* Match the existing RentX 10-digit mobile-number convention. */
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    respond(
        "invalid_phone",
        "Enter a valid 10-digit mobile number."
    );
}

$name = $requestedName !== ""
    ? $requestedName
    : $firebaseName;

if ($name === "") {
    $name = "RentX User";
}

if (!in_array($requestedRole, ["user", "owner"], true)) {
    $requestedRole = "user";
}

/*
|--------------------------------------------------------------------------
| CHECK UNIQUE PHONE NUMBER
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    respond("error", "Database error");
}

mysqli_stmt_bind_param($stmt, "s", $phone);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$phoneExists = $result && mysqli_num_rows($result) > 0;

mysqli_stmt_close($stmt);

if ($phoneExists) {
    respond(
        "phone_exists",
        "This mobile number already belongs to a RentX account. Sign in using that account instead."
    );
}

/*
|--------------------------------------------------------------------------
| CREATE GOOGLE USER
|--------------------------------------------------------------------------
|
| Google users do not need a local password.
| No phone OTP is required by this flow.
| The phone number must still be unique.
*/

$sql = "
    INSERT INTO users
        (name, email, phone, password, role, firebase_uid)
    VALUES
        (?, ?, ?, NULL, ?, ?)
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    respond("error", "Unable to prepare account creation");
}

mysqli_stmt_bind_param(
    $stmt,
    "sssss",
    $name,
    $email,
    $phone,
    $requestedRole,
    $firebaseUid
);

if (!mysqli_stmt_execute($stmt)) {
    $errorCode = mysqli_stmt_errno($stmt);

    mysqli_stmt_close($stmt);

    if ($errorCode === 1062) {
        respond(
            "account_conflict",
            "An account with these details already exists. Please sign in or use your existing account."
        );
    }

    error_log("Google account insertion failed: " . $errorCode);

    respond("error", "Unable to create your RentX account");
}

$userId = mysqli_insert_id($conn);

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| RETURN CREATED ACCOUNT
|--------------------------------------------------------------------------
*/

respond("success", "Google registration successful", [
    "id" => $userId,
    "name" => $name,
    "email" => $email,
    "phone" => $phone,
    "role" => $requestedRole
]);

?>
