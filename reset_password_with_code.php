<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE DATA
|--------------------------------------------------------------------------
*/

$phone = trim($_POST['phone'] ?? '');
$recoveryCode = trim($_POST['recovery_code'] ?? '');
$newPassword = $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $phone === '' ||
    $recoveryCode === '' ||
    $newPassword === ''
) {

    echo json_encode([
        "status" => "error",
        "message" => "Required fields are missing"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| PASSWORD VALIDATION
|--------------------------------------------------------------------------
*/

if (strlen($newPassword) < 8) {

    echo json_encode([
        "status" => "password_short",
        "message" => "Password must contain at least 8 characters"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET RECOVERY CODE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        recovery_code_hash,
        used
    FROM recovery_codes
    WHERE user_phone = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmt,
    "s",
    $phone
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);


if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "invalid",
        "message" => "Invalid recovery details"
    ]);

    exit();
}


$row =
    mysqli_fetch_assoc($result);


mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| CHECK CODE USED
|--------------------------------------------------------------------------
*/

if ((int)$row['used'] === 1) {

    echo json_encode([
        "status" => "used",
        "message" => "Recovery code has already been used"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| VERIFY RECOVERY CODE
|--------------------------------------------------------------------------
*/

if (!password_verify(
    $recoveryCode,
    $row['recovery_code_hash']
)) {

    echo json_encode([
        "status" => "invalid",
        "message" => "Invalid recovery code"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| HASH NEW PASSWORD
|--------------------------------------------------------------------------
*/

$hashedPassword =
    password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );


if ($hashedPassword === false) {

    echo json_encode([
        "status" => "error",
        "message" => "Password hashing failed"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| UPDATE USER PASSWORD
|--------------------------------------------------------------------------
*/

$sqlUpdate = "
    UPDATE users
    SET password = ?
    WHERE phone = ?
    LIMIT 1
";


$stmtUpdate =
    mysqli_prepare(
        $conn,
        $sqlUpdate
    );


if (!$stmtUpdate) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtUpdate,
    "ss",
    $hashedPassword,
    $phone
);


if (!mysqli_stmt_execute($stmtUpdate)) {

    mysqli_stmt_close($stmtUpdate);

    echo json_encode([
        "status" => "error",
        "message" => "Password update failed"
    ]);

    exit();
}


mysqli_stmt_close($stmtUpdate);


/*
|--------------------------------------------------------------------------
| MARK RECOVERY CODE AS USED
|--------------------------------------------------------------------------
*/

$sqlUsed = "
    UPDATE recovery_codes
    SET used = 1
    WHERE id = ?
";


$stmtUsed =
    mysqli_prepare(
        $conn,
        $sqlUsed
    );


if ($stmtUsed) {

    mysqli_stmt_bind_param(
        $stmtUsed,
        "i",
        $row['id']
    );

    mysqli_stmt_execute($stmtUsed);

    mysqli_stmt_close($stmtUsed);
}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "success",
    "message" => "Password reset successfully"
]);

?>
