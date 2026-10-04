<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$userPhone = trim($_POST['user_phone'] ?? '');
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$type = trim($_POST['type'] ?? 'system');

if (
    $userPhone === '' ||
    $title === '' ||
    $message === ''
) {
    echo json_encode([
        "status" => "error",
        "message" => "Required notification fields are missing"
    ]);
    exit();
}


/*
 * Determine which notification preference
 * controls this notification.
 *
 * promotion -> notification_promotions
 * booking   -> notification_booking
 * support   -> notification_support
 * security  -> notification_security
 * system    -> notification_general
 */

$preferenceColumn = "notification_general";

$normalizedType = strtolower(
    trim($type)
);

if (
    $normalizedType === "promotion" ||
    $normalizedType === "promotions" ||
    $normalizedType === "offer" ||
    $normalizedType === "offers"
) {

    $preferenceColumn =
        "notification_promotions";

} elseif (
    $normalizedType === "booking" ||
    $normalizedType === "accepted" ||
    $normalizedType === "cancelled" ||
    $normalizedType === "completed"
) {

    $preferenceColumn =
        "notification_booking";

} elseif (
    $normalizedType === "support"
) {

    $preferenceColumn =
        "notification_support";

} elseif (
    $normalizedType === "security"
) {

    $preferenceColumn =
        "notification_security";

} else {

    $preferenceColumn =
        "notification_general";
}


/*
 * Check user's notification preference.
 *
 * The column name is selected internally above
 * and is NOT taken directly from user input.
 */

$sendNotification = true;

$sqlPreference = "
    SELECT $preferenceColumn
    FROM users
    WHERE phone = ?
    LIMIT 1
";

$stmtPreference = mysqli_prepare(
    $conn,
    $sqlPreference
);

if ($stmtPreference) {

    mysqli_stmt_bind_param(
        $stmtPreference,
        "s",
        $userPhone
    );

    if (
        mysqli_stmt_execute(
            $stmtPreference
        )
    ) {

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

            $sendNotification =
                (
                    (int)(
                        $preference[
                            $preferenceColumn
                        ] ?? 1
                    ) === 1
                );
        }
    }

    mysqli_stmt_close(
        $stmtPreference
    );
}


/*
 * Notification disabled by user.
 */

if (!$sendNotification) {

    echo json_encode([
        "status" => "success",
        "message" => "Notification skipped because the user disabled this notification type",
        "notification_sent" => false,
        "preference" => $preferenceColumn
    ]);

    exit();
}


/*
 * Create notification.
 */

$sql = "
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
    "ssss",
    $userPhone,
    $title,
    $message,
    $type
);

if (
    mysqli_stmt_execute(
        $stmt
    )
) {

    echo json_encode([
        "status" => "success",
        "message" => "Notification created successfully",
        "notification_id" => mysqli_insert_id($conn),
        "notification_sent" => true,
        "preference" => $preferenceColumn
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to create notification",
        "notification_sent" => false
    ]);
}

mysqli_stmt_close(
    $stmt
);

?>
