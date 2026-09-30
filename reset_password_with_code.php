<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$phone = trim($_POST['phone'] ?? '');
$recoveryCode = trim($_POST['recovery_code'] ?? '');
$newPassword = $_POST['password'] ?? '';

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

if (strlen($newPassword) < 8) {
    echo json_encode([
        "status" => "password_short",
        "message" => "Password must contain at least 8 characters"
    ]);
    exit();
}


/*
 * Verify recovery code.
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

$stmt = mysqli_prepare(
    $conn,
    $sql
);

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

mysqli_stmt_execute(
    $stmt
);

$result =
    mysqli_stmt_get_result(
        $stmt
    );

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
    mysqli_fetch_assoc(
        $result
    );

mysqli_stmt_close(
    $stmt
);


if ((int)$row['used'] === 1) {

    echo json_encode([
        "status" => "used",
        "message" => "Recovery code has already been used"
    ]);

    exit();
}


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
 * Hash new password.
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
 * Update password.
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

if (!mysqli_stmt_execute(
    $stmtUpdate
)) {

    mysqli_stmt_close(
        $stmtUpdate
    );

    echo json_encode([
        "status" => "error",
        "message" => "Password update failed"
    ]);

    exit();
}

mysqli_stmt_close(
    $stmtUpdate
);


/*
 * Mark recovery code as used.
 */

$sqlUsed = "
    UPDATE recovery_codes
    SET used = 1
    WHERE id = ?
    LIMIT 1
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

    mysqli_stmt_execute(
        $stmtUsed
    );

    mysqli_stmt_close(
        $stmtUsed
    );
}


/*
 * Create notification.
 */

$title =
    "Password Updated";

$message =
    "Your RentX account password was successfully changed.";

$type =
    "system";

$sqlNotification = "
    INSERT INTO notifications
    (
        user_phone,
        title,
        message,
        type,
        is_read
    )
    VALUES (?, ?, ?, ?, 0)
";

$stmtNotification =
    mysqli_prepare(
        $conn,
        $sqlNotification
    );

if ($stmtNotification) {

    mysqli_stmt_bind_param(
        $stmtNotification,
        "ssss",
        $phone,
        $title,
        $message,
        $type
    );

    mysqli_stmt_execute(
        $stmtNotification
    );

    mysqli_stmt_close(
        $stmtNotification
    );
}


echo json_encode([
    "status" => "success",
    "message" => "Password reset successfully"
]);

?>
