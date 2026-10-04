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
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $phone === '' ||
    $currentPassword === '' ||
    $newPassword === ''
) {

    echo json_encode([
        "status" => "error",
        "message" => "All password fields are required"
    ]);

    exit();
}


if (strlen($newPassword) < 8) {

    echo json_encode([
        "status" => "error",
        "message" => "New password must contain at least 8 characters"
    ]);

    exit();
}


if ($currentPassword === $newPassword) {

    echo json_encode([
        "status" => "error",
        "message" => "New password must be different from current password"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        password
    FROM users
    WHERE phone = ?
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


/*
|--------------------------------------------------------------------------
| USER NOT FOUND
|--------------------------------------------------------------------------
*/

if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "status" => "error",
        "message" => "Account not found"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| GET USER
|--------------------------------------------------------------------------
*/

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| VERIFY CURRENT PASSWORD
|--------------------------------------------------------------------------
*/

if (!password_verify(
    $currentPassword,
    $user['password']
)) {

    echo json_encode([
        "status" => "error",
        "message" => "Current password is incorrect"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| HASH NEW PASSWORD
|--------------------------------------------------------------------------
*/

$newPasswordHash = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);


if ($newPasswordHash === false) {

    echo json_encode([
        "status" => "error",
        "message" => "Password hashing failed"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| UPDATE PASSWORD
|--------------------------------------------------------------------------
*/

$sqlUpdate = "
    UPDATE users
    SET password = ?
    WHERE id = ?
    LIMIT 1
";


$stmtUpdate = mysqli_prepare(
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
    "si",
    $newPasswordHash,
    $user['id']
);


if (!mysqli_stmt_execute($stmtUpdate)) {

    mysqli_stmt_close($stmtUpdate);

    echo json_encode([
        "status" => "error",
        "message" => "Unable to update password"
    ]);

    exit();
}


mysqli_stmt_close($stmtUpdate);


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "success",
    "message" => "Password changed successfully"
]);

?>
