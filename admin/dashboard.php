<?php

session_start();

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| ADMIN LOGIN
|--------------------------------------------------------------------------
| Login form from login.php sends username/password here.
| After successful verification, admin_id is stored in session.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($username === "" || $password === "") {

            header("Location: login.php?error=Enter username and password");
            exit;
        }

        $sql = "SELECT id, username, password_hash
                FROM admin_users
                WHERE username = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            header("Location: login.php?error=Database error");
            exit;
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {

            $stmt->close();

            header("Location: login.php?error=Invalid admin credentials");
            exit;
        }

        $admin = $result->fetch_assoc();

        if (!password_verify($password, $admin["password_hash"])) {

            $stmt->close();

            header("Location: login.php?error=Invalid admin credentials");
            exit;
        }

        session_regenerate_id(true);

        $_SESSION["admin_id"] = (int)$admin["id"];
        $_SESSION["admin_username"] = $admin["username"];

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| FINAL SECURITY CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

function getCount($conn, $table) {

    $allowedTables = [
        "users",
        "vehicles",
        "bookings",
        "support_requests"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)$row["total"];
}


/*
|--------------------------------------------------------------------------
| MAIN COUNTS
|--------------------------------------------------------------------------
*/

$totalUsers = getCount($conn, "users");

$totalVehicles = getCount($conn, "vehicles");

$totalBookings = getCount($conn, "bookings");

$totalSupport = getCount($conn, "support_requests");


/*
|--------------------------------------------------------------------------
| SUPPORT STATUS COUNTS
|--------------------------------------------------------------------------
*/

$openRequests = 0;
$inProgressRequests = 0;
$resolvedRequests = 0;

$result = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM support_requests
     GROUP BY status"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $status = strtolower(trim($row["status"]));
        $total = (int)$row["total"];

        if ($status === "open") {

            $openRequests = $total;

        } elseif (
            $status === "in progress" ||
            $status === "in_progress"
        ) {

            $inProgressRequests = $total;

        } elseif (
            $status === "resolved" ||
            $status === "closed"
        ) {

            $resolvedRequests = $total;
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>RentX Admin Dashboard</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f6fa;

            color: #111827;
        }


        /* HEADER */

        .header {

            height: 72px;

            background: #111827;

            color: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;
        }


        .brand {

            font-size: 24px;

            font-weight: bold;
        }


        .admin-info {

            display: flex;

            align-items: center;

            gap: 18px;

            font-size: 14px;
        }


        .logout {

            background: #dc2626;

            color: white;

            text-decoration: none;

            padding: 9px 15px;

            border-radius: 8px;

            font-weight: bold;
        }


        /* PAGE */

        .container {

            max-width: 1400px;

            margin: auto;

            padding: 30px;
        }


        .welcome {

            margin-bottom: 25px;
        }


        .welcome h1 {

            margin: 0;

            font-size: 28px;
        }


        .welcome p {

            margin-top: 7px;

            color: #6b7280;
        }


        /* CARDS */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }


        .card {

            background: white;

            border-radius: 16px;

            padding: 24px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,0.06);
        }


        .card-title {

            color: #6b7280;

            font-size: 14px;

            margin-bottom: 12px;
        }


        .card-number {

            font-size: 30px;

            font-weight: bold;
        }


        .icon {

            font-size: 25px;

            margin-bottom: 12px;
        }


        /* SUPPORT */

        .support-title {

            font-size: 21px;

            font-weight: bold;

            margin-bottom: 18px;
        }


        .support-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }


        .support-card {

            background: white;

            padding: 25px;

            border-radius: 16px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,0.06);
        }


        .support-card h3 {

            margin: 0 0 10px;

            font-size: 16px;
        }


        .support-number {

            font-size: 28px;

            font-weight: bold;
        }


        .open {
            color: #dc2626;
        }


        .progress {
            color: #f59e0b;
        }


        .resolved {
            color: #16a34a;
        }


        .support-button {

            display: inline-block;

            margin-top: 25px;

            background: #4f46e5;

            color: white;

            text-decoration: none;

            padding: 12px 20px;

            border-radius: 9px;

            font-weight: bold;
        }


        /* RESPONSIVE */

        @media (max-width: 900px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .support-grid {

                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 600px) {

            .header {

                padding: 0 15px;
            }

            .container {

                padding: 20px;
            }

            .stats {

                grid-template-columns: 1fr;
            }

            .admin-info span {

                display: none;
            }
        }

    </style>

</head>


<body>


<!-- HEADER -->

<div class="header">

    <div class="brand">
        🚗 RentX Admin
    </div>


    <div class="admin-info">

        <span>
            👤
            <?php
            echo htmlspecialchars(
                $_SESSION["admin_username"]
            );
            ?>
        </span>


        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</div>


<!-- CONTENT -->

<div class="container">


    <div class="welcome">

        <h1>
            Admin Dashboard
        </h1>

        <p>
            Manage your RentX platform from one place.
        </p>

    </div>


    <!-- MAIN STATISTICS -->

    <div class="stats">


        <div class="card">

            <div class="icon">
                👥
            </div>

            <div class="card-title">
                Total Users
            </div>

            <div class="card-number">
                <?php echo $totalUsers; ?>
            </div>

        </div>


        <div class="card">

            <div class="icon">
                🚗
            </div>

            <div class="card-title">
                Total Vehicles
            </div>

            <div class="card-number">
                <?php echo $totalVehicles; ?>
            </div>

        </div>


        <div class="card">

            <div class="icon">
                📋
            </div>

            <div class="card-title">
                Total Bookings
            </div>

            <div class="card-number">
                <?php echo $totalBookings; ?>
            </div>

        </div>


        <div class="card">

            <div class="icon">
                🎫
            </div>

            <div class="card-title">
                Support Requests
            </div>

            <div class="card-number">
                <?php echo $totalSupport; ?>
            </div>

        </div>

    </div>


    <!-- SUPPORT -->

    <div class="support-title">
        Support Requests
    </div>


    <div class="support-grid">


        <div class="support-card">

            <h3>
                🔴 Open
            </h3>

            <div class="support-number open">
                <?php echo $openRequests; ?>
            </div>

        </div>


        <div class="support-card">

            <h3>
                🟠 In Progress
            </h3>

            <div class="support-number progress">
                <?php echo $inProgressRequests; ?>
            </div>

        </div>


        <div class="support-card">

            <h3>
                🟢 Resolved
            </h3>

            <div class="support-number resolved">
                <?php echo $resolvedRequests; ?>
            </div>

        </div>

    </div>


    <a
        href="support.php"
        class="support-button"
    >
        Manage Support Requests →
    </a>


</div>


</body>

</html>
