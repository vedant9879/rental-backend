```php
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

$idToken = trim($_POST["id_token"] ?? "");

if ($idToken === "") {
    respond("error", "Firebase ID token is required");
}

/*
 * Store this value as a Railway environment variable.
 * Never hardcode private credentials in your source code.
 */
$apiKey = getenv("FIREBASE_WEB_API_KEY");

if (!$apiKey) {
    error_log("FIREBASE_WEB_API_KEY is not configured");
    respond("error", "Authentication service is not configured");
}

/*
 * Ask Firebase to validate the ID token.
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
    error_log("Firebase verification request failed");
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
$name = trim($firebaseUser["displayName"] ?? "");

if (
    $email === "" ||
    ($firebaseUser["emailVerified"] ?? false) !== true
) {
    respond("fail", "A verified Google email is required");
}

/*
 * Only log in accounts already explicitly linked
 * to this Firebase UID.
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
 * Do not silently link a Google account to an existing
 * password account just because its email matches.
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
 * New Google users must complete the separate
 * phone-verification and account-creation flow.
 */
respond(
    "phone_verification_required",
    "Verify your mobile number before creating your RentX account."
);

?>
```
