<?php

session_start();

require_once "../db.php";


/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Request ID
|--------------------------------------------------------------------------
*/

$request_id = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($request_id <= 0) {

    header("Location: support.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Update Support Request
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $admin_reply = trim(
        $_POST["admin_reply"] ?? ""
    );

    $status = trim(
        $_POST["status"] ?? "open"
    );


    /*
    |--------------------------------------------------------------------------
    | Allowed Statuses
    |--------------------------------------------------------------------------
    */

    $allowed_statuses = [
        "open",
        "in progress",
        "resolved"
    ];

    if (!in_array(
        $status,
        $allowed_statuses,
        true
    )) {

        $status = "open";
    }


    /*
    |--------------------------------------------------------------------------
    | Get Existing Request
    |--------------------------------------------------------------------------
    */

    $get_sql = "
        SELECT
            id,
            user_phone,
            admin_reply,
            status
        FROM support_requests
        WHERE id = ?
        LIMIT 1
    ";

    $get_stmt = $conn->prepare($get_sql);

    if (!$get_stmt) {

        die("Database error.");
    }

    $get_stmt->bind_param(
        "i",
        $request_id
    );

    $get_stmt->execute();

    $get_result =
        $get_stmt->get_result();

    if (
        !$get_result ||
        $get_result->num_rows === 0
    ) {

        $get_stmt->close();
        $conn->close();

        header("Location: support.php");
        exit;
    }

    $existing_request =
        $get_result->fetch_assoc();

    $get_stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Existing Values
    |--------------------------------------------------------------------------
    */

    $user_phone =
        trim(
            $existing_request["user_phone"] ?? ""
        );

    $previous_reply =
        trim(
            $existing_request["admin_reply"] ?? ""
        );

    $previous_status =
        trim(
            $existing_request["status"] ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | Detect Changes
    |--------------------------------------------------------------------------
    */

    $isNewReply =
        (
            $admin_reply !== "" &&
            $admin_reply !== $previous_reply
        );

    $isStatusChanged =
        (
            $status !== $previous_status
        );


    /*
    |--------------------------------------------------------------------------
    | Update Support Request
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE support_requests
        SET
            admin_reply = ?,
            status = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        die("Database error.");
    }

    $stmt->bind_param(
        "ssi",
        $admin_reply,
        $status,
        $request_id
    );


    /*
    |--------------------------------------------------------------------------
    | Execute Update
    |--------------------------------------------------------------------------
    */

    if ($stmt->execute()) {


        /*
        |--------------------------------------------------------------------------
        | Notification
        |--------------------------------------------------------------------------
        */

        $notificationSent = false;


        /*
        |--------------------------------------------------------------------------
        | Only notify when reply or status changed
        |--------------------------------------------------------------------------
        */

        if (
            (
                $isNewReply ||
                $isStatusChanged
            )
            &&
            $user_phone !== ""
        ) {


            /*
            |--------------------------------------------------------------------------
            | Check Support Notification Preference
            |--------------------------------------------------------------------------
            */

            $sendNotification = true;

            $preference_sql = "
                SELECT notification_support
                FROM users
                WHERE phone = ?
                LIMIT 1
            ";

            $preference_stmt =
                $conn->prepare(
                    $preference_sql
                );

            if ($preference_stmt) {

                $preference_stmt->bind_param(
                    "s",
                    $user_phone
                );

                if (
                    $preference_stmt->execute()
                ) {

                    $preference_result =
                        $preference_stmt->get_result();

                    if (
                        $preference_result &&
                        $preference_result->num_rows > 0
                    ) {

                        $preference =
                            $preference_result
                                ->fetch_assoc();

                        $sendNotification =
                            (
                                (int)(
                                    $preference[
                                        "notification_support"
                                    ] ?? 1
                                ) === 1
                            );
                    }
                }

                $preference_stmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Create Notification
            |--------------------------------------------------------------------------
            */

            if ($sendNotification) {

                /*
                |------------------------------------------------------------------
                | Reply + Status Changed
                |------------------------------------------------------------------
                */

                if (
                    $isNewReply &&
                    $isStatusChanged
                ) {

                    $title =
                        "Support Request Updated";

                    $message =
                        "Admin replied to your support request #" .
                        $request_id .
                        " and changed its status to " .
                        ucwords($status) .
                        ".";


                /*
                |------------------------------------------------------------------
                | Only Reply Changed
                |------------------------------------------------------------------
                */

                } elseif ($isNewReply) {

                    $title =
                        "Support Reply";

                    $message =
                        "Admin replied to your support request #" .
                        $request_id .
                        ".";


                /*
                |------------------------------------------------------------------
                | Only Status Changed
                |------------------------------------------------------------------
                */

                } else {

                    $title =
                        "Support Status Updated";

                    $message =
                        "Your support request #" .
                        $request_id .
                        " status is now " .
                        ucwords($status) .
                        ".";
                }


                $type =
                    "support";


                /*
                |--------------------------------------------------------------------------
                | Insert Notification
                |--------------------------------------------------------------------------
                */

                $notification_sql = "
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

                $notification_stmt =
                    $conn->prepare(
                        $notification_sql
                    );

                if ($notification_stmt) {

                    $notification_stmt->bind_param(
                        "ssss",
                        $user_phone,
                        $title,
                        $message,
                        $type
                    );

                    if (
                        $notification_stmt->execute()
                    ) {

                        $notificationSent = true;
                    }

                    $notification_stmt->close();
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Close & Redirect
        |--------------------------------------------------------------------------
        */

        $stmt->close();
        $conn->close();

        header(
            "Location: support.php?updated=1"
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Failed
    |--------------------------------------------------------------------------
    */

    $stmt->close();

    die(
        "Unable to update support request."
    );
}


/*
|--------------------------------------------------------------------------
| Get Support Request
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        user_phone,
        booking_id,
        category,
        subject,
        description,
        status,
        admin_reply,
        created_at,
        updated_at
    FROM support_requests
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Database error.");
}

$stmt->bind_param(
    "i",
    $request_id
);

$stmt->execute();

$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Request Not Found
|--------------------------------------------------------------------------
*/

if (
    !$result ||
    $result->num_rows === 0
) {

    $stmt->close();
    $conn->close();

    header(
        "Location: support.php"
    );

    exit;
}


$request =
    $result->fetch_assoc();

$stmt->close();

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    RentX - Support Request
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}


/* =========================================================
   BODY
========================================================= */

body {

    margin: 0;

    font-family: Arial, sans-serif;

    background: #f5f6fa;

    color: #111827;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    background: #111827;

    color: white;

    padding: 20px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}


.header h1 {

    margin: 0;

    font-size: 24px;
}


.header p {

    margin: 6px 0 0;

    color: #cbd5e1;
}


/* =========================================================
   HEADER BUTTONS
========================================================= */

.header-buttons {

    display: flex;

    gap: 10px;
}


.header-buttons a {

    color: white;

    text-decoration: none;

    padding: 11px 17px;

    border-radius: 8px;

    font-weight: bold;
}


.dashboard-btn {

    background: #4f46e5;
}


.dashboard-btn:hover {

    background: #4338ca;
}


.logout-btn {

    background: #dc2626;
}


.logout-btn:hover {

    background: #b91c1c;
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    max-width: 950px;

    margin: 30px auto;

    padding: 0 20px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 20px;

    background: #4f46e5;

    color: white;

    text-decoration: none;

    padding: 12px 18px;

    border-radius: 9px;

    font-weight: bold;

    font-size: 15px;

    cursor: pointer;

    transition: 0.2s;
}


.back:hover {

    background: #4338ca;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border-radius: 16px;

    padding: 28px;

    box-shadow:
        0 5px 20px rgba(
            0,
            0,
            0,
            0.06
        );
}


/* =========================================================
   TITLE
========================================================= */

.title {

    border-bottom: 1px solid #e5e7eb;

    padding-bottom: 20px;

    margin-bottom: 22px;
}


.title h2 {

    margin: 0 0 8px;

    font-size: 24px;
}


.request-id {

    color: #6b7280;

    font-size: 14px;
}


/* =========================================================
   DETAILS
========================================================= */

.details {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}


.detail {

    background: #f9fafb;

    padding: 15px;

    border-radius: 10px;
}


.label {

    color: #6b7280;

    font-size: 12px;

    margin-bottom: 6px;
}


.value {

    font-weight: bold;

    font-size: 14px;

    word-break: break-word;
}


/* =========================================================
   SECTION
========================================================= */

.section {

    margin-top: 22px;
}


.section h3 {

    font-size: 16px;

    margin-bottom: 10px;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.description {

    background: #f9fafb;

    padding: 17px;

    border-radius: 10px;

    line-height: 1.6;

    color: #374151;

    word-break: break-word;
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 20px;
}


label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;
}


select,
textarea {

    width: 100%;

    border: 1px solid #d1d5db;

    border-radius: 10px;

    padding: 13px;

    font-size: 15px;

    outline: none;

    font-family: Arial, sans-serif;
}


select:focus,
textarea:focus {

    border-color: #4f46e5;

    box-shadow:
        0 0 0 3px rgba(
            79,
            70,
            229,
            0.10
        );
}


textarea {

    min-height: 160px;

    resize: vertical;
}


/* =========================================================
   SAVE BUTTON
========================================================= */

.save-btn {

    width: 100%;

    border: none;

    background: #4f46e5;

    color: white;

    padding: 15px;

    border-radius: 10px;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;
}


.save-btn:hover {

    background: #4338ca;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }


    .header-buttons {

        width: 100%;
    }


    .header-buttons a {

        flex: 1;

        text-align: center;
    }


    .details {

        grid-template-columns: 1fr;
    }


    .card {

        padding: 20px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
====================================================== -->

<div class="header">


    <div>

        <h1>
            RentX Admin
        </h1>

        <p>
            Support Request Details
        </p>

    </div>


    <div class="header-buttons">


        <a
            href="dashboard.php"
            class="dashboard-btn"
        >
            Dashboard
        </a>


        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>


    </div>


</div>


<!-- =====================================================
     MAIN
====================================================== -->

<div class="container">


    <!-- BACK BUTTON -->

    <a
        href="support.php"
        class="back"
    >
        ← Back to Support Requests
    </a>


    <!-- CARD -->

    <div class="card">


        <!-- TITLE -->

        <div class="title">


            <h2>

                <?php

                echo htmlspecialchars(
                    $request["subject"]
                );

                ?>

            </h2>


            <div class="request-id">

                Support Request #

                <?php

                echo (int)$request["id"];

                ?>

            </div>


        </div>


        <!-- =================================================
             REQUEST DETAILS
        ================================================== -->

        <div class="details">


            <!-- USER PHONE -->

            <div class="detail">

                <div class="label">
                    User Phone
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $request["user_phone"]
                    );

                    ?>

                </div>

            </div>


            <!-- CATEGORY -->

            <div class="detail">

                <div class="label">
                    Category
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $request["category"]
                    );

                    ?>

                </div>

            </div>


            <!-- BOOKING ID -->

            <div class="detail">

                <div class="label">
                    Booking ID
                </div>

                <div class="value">

                    <?php

                    if (
                        $request["booking_id"] !== null
                        &&
                        $request["booking_id"] !== ""
                    ) {

                        echo htmlspecialchars(
                            $request["booking_id"]
                        );

                    } else {

                        echo "Not linked";
                    }

                    ?>

                </div>

            </div>


        </div>


        <!-- =================================================
             USER DESCRIPTION
        ================================================== -->

        <div class="section">


            <h3>
                User Description
            </h3>


            <div class="description">

                <?php

                echo nl2br(
                    htmlspecialchars(
                        $request["description"]
                    )
                );

                ?>

            </div>


        </div>


        <!-- =================================================
             ADMIN RESPONSE
        ================================================== -->

        <div class="section">


            <h3>
                Admin Response
            </h3>


            <form
                method="POST"
                action=""
            >


                <!-- STATUS -->

                <div class="form-group">


                    <label
                        for="status"
                    >
                        Request Status
                    </label>


                    <select
                        id="status"
                        name="status"
                    >


                        <option
                            value="open"

                            <?php

                            echo (
                                $request["status"]
                                === "open"
                            )
                            ? "selected"
                            : "";

                            ?>
                        >

                            Open

                        </option>


                        <option
                            value="in progress"

                            <?php

                            echo (
                                $request["status"]
                                === "in progress"
                            )
                            ? "selected"
                            : "";

                            ?>
                        >

                            In Progress

                        </option>


                        <option
                            value="resolved"

                            <?php

                            echo (
                                $request["status"]
                                === "resolved"
                            )
                            ? "selected"
                            : "";

                            ?>
                        >

                            Resolved

                        </option>


                    </select>


                </div>


                <!-- REPLY -->

                <div class="form-group">


                    <label
                        for="admin_reply"
                    >
                        Reply to User
                    </label>


                    <textarea
                        id="admin_reply"
                        name="admin_reply"
                        placeholder="Write your reply to the user..."
                    ><?php

                    echo htmlspecialchars(
                        $request["admin_reply"] ?? ""
                    );

                    ?></textarea>


                </div>


                <!-- SAVE -->

                <button
                    type="submit"
                    class="save-btn"
                >

                    Save Reply & Update Status

                </button>


            </form>


        </div>


    </div>


</div>


</body>

</html>
