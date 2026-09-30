<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$phone = trim($_POST['phone'] ?? '');
$recoveryCode = trim($_POST['recovery_code'] ?? '');

if ($phone === '' || $recoveryCode === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Phone number and recovery code are required"
    ]);
    exit();
}

$sql = "
    SELECT id, recovery_code_hash, used
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

$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "invalid",
        "message" => "Invalid recovery details"
    ]);

    exit();
}

$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| CHECK WHETHER CODE WAS ALREADY USED
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
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "success",
    "message" => "Recovery code verified"
]);

?>
