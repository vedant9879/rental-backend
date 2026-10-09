<?php

session_start();

require_once "../db.php";
header("Content-Type: text/html; charset=UTF-8");
if (!isset($_SESSION["admin_id"])) {

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || $password === "") {

            header("Location: login.php?error=Username%20and%20password%20are%20required");
            exit;
        }

        $sql = "SELECT id, username, password
                FROM admin_users
                WHERE username = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            header("Location: login.php?error=Database%20error");
            exit;
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {

            $stmt->close();

            header("Location: login.php?error=Invalid%20admin%20credentials");
            exit;
        }

        $admin = $result->fetch_assoc();

        /*
         * Plain password comparison as requested.
         */
        if ($password !== $admin["password"]) {

            $stmt->close();

            header("Location: login.php?error=Invalid%20admin%20credentials");
            exit;
        }

        session_regenerate_id(true);

        $_SESSION["admin_id"] = (int)$admin["id"];
        $_SESSION["admin_username"] = $admin["username"];

        $stmt->close();

    } else {

        header("Location: login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

function getCount($conn, $table)
{
    $allowedTables = [
        "users",
        "vehicles",
        "bookings",
        "support_requests"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)$row["total"];
}

$totalUsers = getCount($conn, "users");
$totalVehicles = getCount($conn, "vehicles");
$totalBookings = getCount($conn, "bookings");
$totalSupport = getCount($conn, "support_requests");

/*
|--------------------------------------------------------------------------
| Support Status Counts
|--------------------------------------------------------------------------
*/

$openSupport = 0;
$progressSupport = 0;
$resolvedSupport = 0;

$result = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM support_requests
    GROUP BY status
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $status = strtolower(trim($row["status"]));
        $count = (int)$row["total"];

        if ($status === "open") {
            $openSupport = $count;
        }

        elseif ($status === "in progress") {
            $progressSupport = $count;
        }

        elseif ($status === "resolved") {
            $resolvedSupport = $count;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>RentX Admin Dashboard</title>

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
    padding: 22px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left h1 {
    margin: 0;
    font-size: 25px;
}

.header-left p {
    margin: 6px 0 0;
    color: #cbd5e1;
}

.logout {
    background: #dc2626;
    color: white;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 9px;
    font-weight: bold;
}

.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 20px;
}

.welcome {
    background: white;
    padding: 25px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
}

.welcome h2 {
    margin: 0 0 8px;
}

.welcome p {
    margin: 0;
    color: #6b7280;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
}

.card-title {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 10px;
}

.card-number {
    font-size: 32px;
    font-weight: bold;
}

.support-section {
    margin-top: 30px;
}

.support-section h2 {
    margin-bottom: 18px;
}

.support-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.support-card {
    background: white;
    padding: 22px;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
}

.support-card strong {
    display: block;
    font-size: 28px;
    margin-top: 8px;
}

.open {
    border-left: 5px solid #dc2626;
}

.progress {
    border-left: 5px solid #f59e0b;
}

.resolved {
    border-left: 5px solid #16a34a;
}

.action-section {
    margin-top: 30px;
}

.action-button {
    display: inline-block;
    background: #4f46e5;
    color: white;
    text-decoration: none;
    padding: 15px 22px;
    border-radius: 10px;
    font-weight: bold;
}

.action-button:hover {
    background: #4338ca;
}

@media (max-width: 900px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .support-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {

    .header {
        padding: 20px;
    }

    .header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .stats {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="header">

    <div class="header-left">

        <h1>RentX Admin Dashboard</h1>

        <p>
            Private Administrator Panel
        </p>

    </div>

    <a class="logout" href="logout.php">
        Logout
    </a>

</div>

<div class="container">

    <div class="welcome">

        <h2>
            Welcome,
            <?php echo htmlspecialchars($_SESSION["admin_username"]); ?>
        </h2>

        <p>
            Manage your RentX rental platform from this private dashboard.
        </p>

    </div>

    <div class="stats">

        <div class="card">

            <div class="card-title">
                Total Users
            </div>

            <div class="card-number">
                <?php echo $totalUsers; ?>
            </div>

        </div>

        <div class="card">

            <div class="card-title">
                Total Vehicles
            </div>

            <div class="card-number">
                <?php echo $totalVehicles; ?>
            </div>

        </div>

        <div class="card">

            <div class="card-title">
                Total Bookings
            </div>

            <div class="card-number">
                <?php echo $totalBookings; ?>
            </div>

        </div>

        <div class="card">

            <div class="card-title">
                Support Requests
            </div>

            <div class="card-number">
                <?php echo $totalSupport; ?>
            </div>

        </div>

    </div>

    <div class="support-section">

        <h2>
            Support Request Status
        </h2>

        <div class="support-grid">

            <div class="support-card open">

                Open

                <strong>
                    <?php echo $openSupport; ?>
                </strong>

            </div>

            <div class="support-card progress">

                In Progress

                <strong>
                    <?php echo $progressSupport; ?>
                </strong>

            </div>

            <div class="support-card resolved">

                Resolved

                <strong>
                    <?php echo $resolvedSupport; ?>
                </strong>

            </div>

        </div>

    </div>

    <div class="action-section">

        <a class="action-button"
           href="support.php">

            Manage Support Requests

        </a>

    </div>

</div>

</body>

</html>
