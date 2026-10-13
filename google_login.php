<?php

require_once "db.php";

header("Content-Type: application/json; charset=UTF-8");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function respond($status, $message, $extra = []) {
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $extra));
    exit();
}

$idToken = trim($_POST["id_token"] ?? "");
$phone = trim($_POST["phone"] ?? "");

if ($idToken === "") {
    respond("error", "Firebase ID token is required");
}

$apiKey = getenv("FIREBASE_WEB_API_KEY");

if (!$apiKey) {
    error_log("FIREBASE_WEB_API_KEY is not configured");
    respond("error", "Authentication service is not configured");
}

/* Verify the Firebase ID token with Firebase. */
$ch = curl_init(
    "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key="
    . urlencode($apiKey)
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["idToken" => $idToken]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    respond("fail", "Google authentication could not be verified");
}

$data = json_decode($response, true);
$google = $data["users"][0] ?? null;

if (!$google || empty($google["localId"])) {
    respond("fail", "Invalid Google account");
}

$firebaseUid = $google["localId"];
$email = strtolower(trim($google["email"] ?? ""));
$name = trim($google["displayName"] ?? "");

if (
    $email === "" ||
    ($google["emailVerified"] ?? false) !== true
) {
    respond("fail", "A verified Google email is required");
}

if ($name === "") {
    $name = strstr($email, "@", true) ?: "RentX User";
}

/* Existing Google account: do not generate another recovery code. */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, email, phone, role
     FROM users WHERE firebase_uid = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $firebaseUid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($user) {
    respond("success", "Google login successful", [
        "is_new_user" => false,
        "id" => (int)$user["id"],
        "name" => $user["name"],
        "email" => $user["email"],
        "phone" => $user["phone"],
        "role" => $user["role"]
    ]);
}

/* Never silently link an existing password account by email. */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE email = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$emailExists = mysqli_num_rows($result) > 0;
mysqli_stmt_close($stmt);

if ($emailExists) {
    respond(
        "account_link_required",
        "This email already has a RentX account. Sign in using your existing method."
    );
}

/* New account requires a unique 10-digit phone number. No OTP is used. */
if ($phone === "") {
    respond("phone_required", "Enter your 10-digit mobile number.");
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    respond("invalid_phone", "Enter a valid 10-digit mobile number.");
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE phone = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $phone);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$phoneExists = mysqli_num_rows($result) > 0;
mysqli_stmt_close($stmt);

if ($phoneExists) {
    respond(
        "phone_exists",
        "This mobile number already belongs to a RentX account."
    );
}

/* Generate a recovery code compatible with the existing RX- format. */
$alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
$part1 = "";
$part2 = "";

for ($i = 0; $i < 4; $i++) {
    $part1 .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $part2 .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}

$recoveryCode = "RX-" . $part1 . "-" . $part2;
$recoveryHash = password_hash($recoveryCode, PASSWORD_DEFAULT);

if ($recoveryHash === false) {
    respond("error", "Could not generate recovery code.");
}

try {
    mysqli_begin_transaction($conn);

    /* Google registrations receive the normal customer role. */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO users
         (name, email, phone, password, role, firebase_uid)
         VALUES (?, ?, ?, NULL, 'user', ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $name,
        $email,
        $phone,
        $firebaseUid
    );

    mysqli_stmt_execute($stmt);
    $userId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO recovery_codes
         (user_phone, recovery_code_hash, used)
         VALUES (?, ?, 0)"
    );

    mysqli_stmt_bind_param($stmt, "ss", $phone, $recoveryHash);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);

    /* Return the plaintext recovery code only once, at registration. */
    respond("success", "Google registration successful", [
        "is_new_user" => true,
        "id" => $userId,
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "role" => "user",
        "recovery_code" => $recoveryCode
    ]);

} catch (Throwable $e) {
    try {
        mysqli_rollback($conn);
    } catch (Throwable $ignored) {
    }

    error_log("RentX Google registration failed: " . $e->getMessage());

    if ($e instanceof mysqli_sql_exception && $e->getCode() === 1062) {
        respond(
            "account_conflict",
            "An account with these details already exists. Please sign in."
        );
    }

    respond("error", "Unable to create your RentX account. Please try again.");
}
