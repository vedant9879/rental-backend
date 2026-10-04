<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'user');

$aadharNumber = trim($_POST['aadhar_number'] ?? '');
$licenseNumber = trim($_POST['license_number'] ?? '');


/*
 * Basic validation
 */

if (
    $name === '' ||
    $email === '' ||
    $phone === '' ||
    $password === ''
) {

    echo json_encode([
        "status" => "error",
        "message" => "Required fields are missing"
    ]);

    exit();
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid email address"
    ]);

    exit();
}


if (strlen($password) < 8) {

    echo json_encode([
        "status" => "error",
        "message" => "Password must contain at least 8 characters"
    ]);

    exit();
}


if (!in_array($role, ['user', 'owner'], true)) {

    $role = 'user';
}


/*
 * Check existing user
 */

$sqlCheck = "
    SELECT id
    FROM users
    WHERE phone = ?
       OR email = ?
    LIMIT 1
";

$stmtCheck =
    mysqli_prepare(
        $conn,
        $sqlCheck
    );


if (!$stmtCheck) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtCheck,
    "ss",
    $phone,
    $email
);


mysqli_stmt_execute(
    $stmtCheck
);


$resultCheck =
    mysqli_stmt_get_result(
        $stmtCheck
    );


if (
    $resultCheck &&
    mysqli_num_rows($resultCheck) > 0
) {

    mysqli_stmt_close(
        $stmtCheck
    );

    echo json_encode([
        "status" => "exists",
        "message" => "User already exists"
    ]);

    exit();
}


mysqli_stmt_close(
    $stmtCheck
);


/*
 * Hash password
 */

$hashedPassword =
    password_hash(
        $password,
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
 * Insert user
 */

$sqlUser = "
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
    VALUES (?, ?, ?, ?, ?, ?, ?)
";


$stmtUser =
    mysqli_prepare(
        $conn,
        $sqlUser
    );


if (!$stmtUser) {

    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);

    exit();
}


mysqli_stmt_bind_param(
    $stmtUser,
    "sssssss",
    $name,
    $email,
    $phone,
    $hashedPassword,
    $role,
    $aadharNumber,
    $licenseNumber
);


if (!mysqli_stmt_execute($stmtUser)) {

    mysqli_stmt_close(
        $stmtUser
    );

    echo json_encode([
        "status" => "error",
        "message" => "Registration failed"
    ]);

    exit();
}


mysqli_stmt_close(
    $stmtUser
);


/*
 * Generate recovery code
 */

$characters =
    "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";

$partOne = "";
$partTwo = "";


for ($i = 0; $i < 4; $i++) {

    $partOne .=
        $characters[
            random_int(
                0,
                strlen($characters) - 1
            )
        ];
}


for ($i = 0; $i < 4; $i++) {

    $partTwo .=
        $characters[
            random_int(
                0,
                strlen($characters) - 1
            )
        ];
}


$recoveryCode =
    "RX-" .
    $partOne .
    "-" .
    $partTwo;


/*
 * Store recovery code hash
 */

$recoveryHash =
    password_hash(
        $recoveryCode,
        PASSWORD_DEFAULT
    );


if ($recoveryHash !== false) {

    $sqlRecovery = "
        INSERT INTO recovery_codes
        (
            user_phone,
            recovery_code_hash,
            used
        )
        VALUES (?, ?, 0)
    ";


    $stmtRecovery =
        mysqli_prepare(
            $conn,
            $sqlRecovery
        );


    if ($stmtRecovery) {

        mysqli_stmt_bind_param(
            $stmtRecovery,
            "ss",
            $phone,
            $recoveryHash
        );


        mysqli_stmt_execute(
            $stmtRecovery
        );


        mysqli_stmt_close(
            $stmtRecovery
        );
    }
}


/*
|--------------------------------------------------------------------------
| CREATE GENERAL / WELCOME NOTIFICATION
|--------------------------------------------------------------------------
|
| notification_general = 1
| → Welcome notification is created
|
| notification_general = 0
| → Welcome notification is not created
|
| New users normally have the database default value.
| If the value cannot be read, notification remains ON.
|
*/

$sendGeneralNotification = true;


/*
 * Read the newly registered user's
 * general notification preference.
 */

$sqlPreference = "
    SELECT notification_general
    FROM users
    WHERE phone = ?
    LIMIT 1
";


$stmtPreference =
    mysqli_prepare(
        $conn,
        $sqlPreference
    );


if ($stmtPreference) {

    mysqli_stmt_bind_param(
        $stmtPreference,
        "s",
        $phone
    );

    mysqli_stmt_execute(
        $stmtPreference
    );

    $resultPreference =
        mysqli_stmt_get_result(
            $stmtPreference
        );


    if (
        $resultPreference &&
        mysqli_num_rows($resultPreference) > 0
    ) {

        $preference =
            mysqli_fetch_assoc(
                $resultPreference
            );


        $sendGeneralNotification =
            (
                (int)(
                    $preference['notification_general']
                    ?? 1
                ) === 1
            );
    }


    mysqli_stmt_close(
        $stmtPreference
    );
}


/*
|--------------------------------------------------------------------------
| INSERT WELCOME NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($sendGeneralNotification) {

    $title =
        "Welcome to RentX";


    $message =
        "Welcome " .
        $name .
        "! Your RentX account is ready. Start exploring vehicles or list your own vehicle.";


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
}


/*
 * Final response
 */

echo json_encode([
    "status" => "success",
    "message" => "Registration Successful",
    "recovery_code" => $recoveryCode,
    "notification_sent" => $sendGeneralNotification
]);

?>
