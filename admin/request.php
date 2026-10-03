<?php

session_start();

require_once "../db.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$request_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($request_id <= 0) {
    header("Location: support.php");
    exit;
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Update Support Request
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $admin_reply = trim($_POST["admin_reply"] ?? "");
    $status = trim($_POST["status"] ?? "open");

    $allowed_statuses = [
        "open",
        "in progress",
        "resolved"
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        $status = "open";
    }

    $sql = "UPDATE support_requests
            SET admin_reply = ?,
                status = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "ssi",
            $admin_reply,
            $status,
            $request_id
        );

        if ($stmt->execute()) {
            $message = "Support request updated successfully.";
        } else {
            $error = "Unable to update support request.";
        }

        $stmt->close();

    } else {
        $error = "Database error.";
    }
}

/*
|--------------------------------------------------------------------------
| Get Request
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
        WHERE id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $request_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: support.php");
    exit;
}

$request = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
RentX - Support Request
</title>

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

.header-buttons {
    display: flex;
    gap: 10px;
}

.header-buttons a {
    color: white;
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: bold;
}

.dashboard-btn {
    background: #4f46e5;
}

.logout-btn {
    background: #dc2626;
}

.container {
    max-width: 950px;
    margin: 30px auto;
    padding: 0 20px;
}

.back {
    display: inline-block;
    margin-bottom: 20px;
    color: #4f46e5;
    text-decoration: none;
    font-weight: bold;
}

.card {
    background: white;
    border-radius: 16px;
    padding: 28px;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.06);
}

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

.details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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
}

.section {
    margin-top: 22px;
}

.section h3 {
    font-size: 16px;
    margin-bottom: 10px;
}

.description {
    background: #f9fafb;
    padding: 17px;
    border-radius: 10px;
    line-height: 1.6;
    color: #374151;
}

.alert-success {
    background: #ecfdf5;
    color: #15803d;
    padding: 13px;
    border-radius: 9px;
    margin-bottom: 20px;
}

.alert-error {
    background: #fef2f2;
    color: #dc2626;
    padding: 13px;
    border-radius: 9px;
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
}

select:focus,
textarea:focus {
    border-color: #4f46e5;
}

textarea {
    min-height: 150px;
    resize: vertical;
}

.form-group {
    margin-bottom: 20px;
}

.save-btn {
    width: 100%;
    border: none;
    background: #4f46e5;
    color: white;
    padding: 14px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.save-btn:hover {
    background: #4338ca;
}

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

}

</style>

</head>

<body>

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


<div class="container">

    <a
        href="support.php"
        class="back"
    >
        ← Back to Support Requests
    </a>


    <div class="card">

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


        <?php if ($message !== ""): ?>

            <div class="alert-success">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert-error">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- Request Details -->

        <div class="details">

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


        <!-- User Description -->

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


        <!-- Admin Update Form -->

        <div class="section">

            <h3>
                Admin Response
            </h3>

            <form
                method="POST"
                action=""
            >

                <div class="form-group">

                    <label for="status">
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


                <div class="form-group">

                    <label for="admin_reply">
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
