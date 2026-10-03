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
| Get Support Requests
|--------------------------------------------------------------------------
*/

$sql = "SELECT
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
        ORDER BY created_at DESC";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>RentX - Support Requests</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f6fa;
    color: #111827;
}

/* Header */

.header {
    background: #111827;
    color: white;
    padding: 20px 35px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left h1 {
    margin: 0;
    font-size: 24px;
}

.header-left p {
    margin: 6px 0 0;
    color: #cbd5e1;
}

.header-right {
    display: flex;
    gap: 10px;
}

.back-btn,
.logout-btn {
    text-decoration: none;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: bold;
}

.back-btn {
    background: #4f46e5;
}

.logout-btn {
    background: #dc2626;
}

/* Container */

.container {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 20px;
}

/* Page title */

.page-title {
    margin-bottom: 25px;
}

.page-title h2 {
    margin: 0;
    font-size: 26px;
}

.page-title p {
    margin-top: 7px;
    color: #6b7280;
}

/* Request Card */

.request-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 20px;
    padding: 25px;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.06);
}

/* Top */

.request-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;

    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 18px;
}

.request-id {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 6px;
}

.subject {
    font-size: 20px;
    font-weight: bold;
    color: #111827;
}

/* Status */

.status {
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.status-open {
    background: #fef2f2;
    color: #dc2626;
}

.status-progress {
    background: #fffbeb;
    color: #d97706;
}

.status-resolved {
    background: #ecfdf5;
    color: #16a34a;
}

/* Details */

.details {
    display: grid;
    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-top: 20px;
}

.detail-box {
    background: #f9fafb;
    padding: 15px;
    border-radius: 10px;
}

.detail-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 5px;
}

.detail-value {
    font-size: 14px;
    font-weight: bold;
    color: #111827;
}

/* Description */

.description {
    margin-top: 20px;
}

.description h3 {
    font-size: 15px;
    margin-bottom: 8px;
}

.description p {
    background: #f9fafb;
    padding: 15px;
    border-radius: 10px;
    line-height: 1.6;
    color: #374151;
    margin: 0;
}

/* Admin Reply */

.reply {
    margin-top: 20px;
}

.reply h3 {
    font-size: 15px;
    margin-bottom: 8px;
}

.reply-box {
    background: #eef2ff;
    border-left: 4px solid #4f46e5;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
}

.no-reply {
    color: #9ca3af;
    font-style: italic;
}

/* Date */

.date {
    margin-top: 18px;
    color: #9ca3af;
    font-size: 12px;
}

/* Empty */

.empty {
    background: white;
    padding: 50px 20px;
    text-align: center;
    border-radius: 16px;
    box-shadow:
        0 5px 20px rgba(0,0,0,0.06);
}

.empty h3 {
    margin-bottom: 8px;
}

.empty p {
    color: #6b7280;
}

/* Mobile */

@media (max-width: 900px) {

    .details {
        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 600px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .header-right {
        width: 100%;
    }

    .back-btn,
    .logout-btn {
        flex: 1;
        text-align: center;
    }

    .request-top {
        flex-direction: column;
    }

    .details {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<!-- Header -->

<div class="header">

    <div class="header-left">

        <h1>
            RentX Admin
        </h1>

        <p>
            Support Request Management
        </p>

    </div>

    <div class="header-right">

        <a
            href="dashboard.php"
            class="back-btn"
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


<!-- Main -->

<div class="container">

    <div class="page-title">

        <h2>
            Support Requests
        </h2>

        <p>
            View and manage requests submitted by RentX users.
        </p>

    </div>


<?php if ($result && $result->num_rows > 0): ?>


    <?php while ($request = $result->fetch_assoc()): ?>

        <?php

        $status = strtolower(
            trim($request["status"] ?? "open")
        );

        $statusClass = "status-open";

        if ($status === "in progress") {
            $statusClass = "status-progress";
        }

        if ($status === "resolved") {
            $statusClass = "status-resolved";
        }

        ?>

        <div class="request-card">

            <!-- Request Header -->

            <div class="request-top">

                <div>

                    <div class="request-id">

                        Support Request
                        #<?php echo (int)$request["id"]; ?>

                    </div>

                    <div class="subject">

                        <?php
                        echo htmlspecialchars(
                            $request["subject"]
                        );
                        ?>

                    </div>

                </div>


                <div
                    class="status <?php echo $statusClass; ?>"
                >

                    <?php
                    echo htmlspecialchars(
                        ucwords($status)
                    );
                    ?>

                </div>

            </div>


            <!-- Details -->

            <div class="details">

                <div class="detail-box">

                    <div class="detail-label">
                        User Phone
                    </div>

                    <div class="detail-value">

                        <?php
                        echo htmlspecialchars(
                            $request["user_phone"]
                        );
                        ?>

                    </div>

                </div>


                <div class="detail-box">

                    <div class="detail-label">
                        Category
                    </div>

                    <div class="detail-value">

                        <?php
                        echo htmlspecialchars(
                            $request["category"]
                        );
                        ?>

                    </div>

                </div>


                <div class="detail-box">

                    <div class="detail-label">
                        Booking ID
                    </div>

                    <div class="detail-value">

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


                <div class="detail-box">

                    <div class="detail-label">
                        Created
                    </div>

                    <div class="detail-value">

                        <?php
                        echo htmlspecialchars(
                            $request["created_at"]
                        );
                        ?>

                    </div>

                </div>

            </div>


            <!-- Description -->

            <div class="description">

                <h3>
                    User Description
                </h3>

                <p>

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $request["description"]
                        )
                    );
                    ?>

                </p>

            </div>


            <!-- Admin Reply -->

            <div class="reply">

                <h3>
                    Admin Reply
                </h3>


                <?php

                if (
                    !empty($request["admin_reply"])
                ):

                ?>

                    <div class="reply-box">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $request["admin_reply"]
                            )
                        );
                        ?>

                    </div>

                <?php else: ?>

                    <div class="no-reply">

                        No admin reply yet.

                    </div>

                <?php endif; ?>

            </div>


            <!-- Date -->

            <div class="date">

                Last Updated:
                <?php
                echo htmlspecialchars(
                    $request["updated_at"]
                );
                ?>

            </div>

        </div>

    <?php endwhile; ?>


<?php else: ?>

    <div class="empty">

        <h3>
            No Support Requests
        </h3>

        <p>
            There are currently no support requests from users.
        </p>

    </div>

<?php endif; ?>


</div>

</body>

</html>
