<?php
session_start();

if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = $_GET["error"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>RentX Admin Login</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.10);
        }

        .logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #111827;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 14px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #4f46e5;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #4f46e5;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #4338ca;
        }

        .private {
            text-align: center;
            margin-top: 20px;
            color: #9ca3af;
            font-size: 12px;
        }

        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

    </style>

</head>

<body>

<div class="login-box">

    <div class="logo">
        RentX Admin
    </div>

    <div class="subtitle">
        Private Administrator Access
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <form action="dashboard.php" method="POST">

        <label>Admin Username</label>

        <input
            type="text"
            name="username"
            placeholder="Enter admin username"
            required
        >


        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter admin password"
            required
        >


        <button type="submit">
            LOGIN
        </button>

    </form>


    <div class="private">
        🔐 Authorized RentX Administrator Only
    </div>

</div>

</body>

</html>
