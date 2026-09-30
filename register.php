<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE REGISTRATION DATA
|--------------------------------------------------------------------------
*/

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$role = strtolower(trim($_POST['role'] ?? ''));

$aadhar = trim($_POST['aadhar_number'] ?? '');
$license = trim($_POST['license_number'] ?? '');


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $name === '' ||
    $email === '' ||
    $phone === '' ||
    $password === '' ||
    $role === ''
) {

    echo json_encode([
        "status" => "empty",
        "message" => "Required fields are missing"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| PASSWORD VALIDATION
|--------------------------------------------------------------------------
*/

if (strlen($password) < 6) {

    echo json_encode([
        "status" => "password_short"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| ROLE VALIDATION
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    "user",
    "owner"
];

if (!in_array($role, $allowedRoles, true)) {

    echo json_encode([
        "status" => "invalid_role"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING USER
|--------------------------------------------------------------------------
*/

$sqlCheck = "
    SELECT id
    FROM users
    WHERE phone = ?
       OR email = ?
    LIMIT 1
";

$stmtCheck = mysqli_prepare($conn, $sqlCheck);

if (!$stmtCheck) {

    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtCheck,
    "ss",
    $phone,
    $email
);

mysqli_stmt_execute($stmtCheck);

$resultCheck =
    mysqli_stmt_get_result($stmtCheck);


if (
    $resultCheck &&
    mysqli_num_rows($resultCheck) > 0
) {

    mysqli_stmt_close($stmtCheck);

    echo json_encode([
        "status" => "exists",
        "message" => "Phone or email already registered"
    ]);

    exit();
}


mysqli_stmt_close($stmtCheck);


/*
|--------------------------------------------------------------------------
| HASH PASSWORD
|--------------------------------------------------------------------------
*/

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

if ($hashedPassword === false) {

    echo json_encode([
        "status" => "error",
        "message" => "Password Hashing Failed"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| INSERT USER
|--------------------------------------------------------------------------
*/

$sqlInsert = "
    INSERT INTO users
    (
        name,
        email,
        phone,
        password,
        role,
        aadhar_number,
        license_number
    )
    VALUES
    (?, ?, ?, ?, ?, ?, ?)
";


$stmtInsert =
    mysqli_prepare($conn, $sqlInsert);

if (!$stmtInsert) {

    echo json_encode([
        "status" => "error",
        "message" => "Database Error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtInsert,
    "sssssss",
    $name,
    $email,
    $phone,
    $hashedPassword,
    $role,
    $aadhar,
    $license
);


/*
|--------------------------------------------------------------------------
| CREATE ACCOUNT
|--------------------------------------------------------------------------
*/

if (!mysqli_stmt_execute($stmtInsert)) {

    mysqli_stmt_close($stmtInsert);

    echo json_encode([
        "status" => "error",
        "message" => "Registration Failed"
    ]);

    exit();
}


mysqli_stmt_close($stmtInsert);


/*
|--------------------------------------------------------------------------
| GENERATE RECOVERY CODE
|--------------------------------------------------------------------------
|
| Example:
| RX-7K4P-92LM
|
| The user sees the code once.
| Only its hash is stored in MySQL.
|
|--------------------------------------------------------------------------
*/

$characters =
    "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";

$recoveryCode = "RX-";

for ($i = 0; $i < 8; $i++) {

    $recoveryCode .=
        $characters[random_int(
            0,
            strlen($characters) - 1
        )];

    if ($i === 3) {
        $recoveryCode .= "-";
    }
}


/*
|--------------------------------------------------------------------------
| HASH RECOVERY CODE
|--------------------------------------------------------------------------
*/

$recoveryCodeHash = password_hash(
    $recoveryCode,
    PASSWORD_DEFAULT
);

if ($recoveryCodeHash === false) {

    echo json_encode([
        "status" => "success",
        "message" => "Registration Successful",
        "recovery_code" => $recoveryCode
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| STORE RECOVERY CODE
|--------------------------------------------------------------------------
*/

$sqlRecovery = "
    INSERT INTO recovery_codes
    (
        user_phone,
        recovery_code_hash,
        used
    )
    VALUES
    (?, ?, 0)
";


$stmtRecovery =
    mysqli_prepare(
        $conn,
        $sqlRecovery
    );


if (!$stmtRecovery) {

    echo json_encode([
        "status" => "success",
        "message" => "Registration Successful",
        "recovery_code" => $recoveryCode
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtRecovery,
    "ss",
    $phone,
    $recoveryCodeHash
);


if (!mysqli_stmt_execute($stmtRecovery)) {

    mysqli_stmt_close($stmtRecovery);

    echo json_encode([
        "status" => "success",
        "message" => "Registration Successful",
        "recovery_code" => $recoveryCode
    ]);

    exit();
}


mysqli_stmt_close($stmtRecovery);


/*
|--------------------------------------------------------------------------
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "success",
    "message" => "Registration Successful",
    "recovery_code" => $recoveryCode
]);

?>
