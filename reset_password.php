<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE DATA
|--------------------------------------------------------------------------
*/

$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($phone === '' || $password === '') {

    echo "Missing Data";
    exit();
}


/*
|--------------------------------------------------------------------------
| BASIC PASSWORD VALIDATION
|--------------------------------------------------------------------------
*/

if (strlen($password) < 6) {

    echo "Password Too Short";
    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK USER
|--------------------------------------------------------------------------
*/

$sqlCheck = "
    SELECT id
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$stmtCheck = mysqli_prepare($conn, $sqlCheck);

if (!$stmtCheck) {

    echo "Database Error";
    exit();
}

mysqli_stmt_bind_param(
    $stmtCheck,
    "s",
    $phone
);

mysqli_stmt_execute($stmtCheck);

$resultCheck =
    mysqli_stmt_get_result($stmtCheck);


if (
    !$resultCheck ||
    mysqli_num_rows($resultCheck) === 0
) {

    mysqli_stmt_close($stmtCheck);

    echo "notfound";
    exit();
}

$user = mysqli_fetch_assoc($resultCheck);

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

    echo "failed";
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
";


$stmtUpdate = mysqli_prepare(
    $conn,
    $sqlUpdate
);

if (!$stmtUpdate) {

    echo "Database Error";
    exit();
}


$userId = (int)$user['id'];

mysqli_stmt_bind_param(
    $stmtUpdate,
    "si",
    $hashedPassword,
    $userId
);


/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if (mysqli_stmt_execute($stmtUpdate)) {

    echo "success";

} else {

    echo "failed";
}


mysqli_stmt_close($stmtUpdate);

?>
