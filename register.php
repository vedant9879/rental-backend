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
        "status" => "exists"
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

if (mysqli_stmt_execute($stmtInsert)) {

    echo json_encode([
        "status" => "success",
        "message" => "Registration Successful"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Registration Failed"
    ]);
}


mysqli_stmt_close($stmtInsert);

?>
