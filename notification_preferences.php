<?php

header("Content-Type: application/json");
include "db.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    $phone = trim($_GET['phone'] ?? '');

    if ($phone === '') {
        echo json_encode([
            "status" => "error",
            "message" => "Phone number is required"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            booking_updates,
            support_updates,
            promotions,
            security_alerts,
            general_updates
        FROM notification_preferences
        WHERE user_phone = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $phone);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        echo json_encode([
            "status" => "success",
            "preferences" => [
                "booking_updates" => (bool)$row['booking_updates'],
                "support_updates" => (bool)$row['support_updates'],
                "promotions" => (bool)$row['promotions'],
                "security_alerts" => (bool)$row['security_alerts'],
                "general_updates" => (bool)$row['general_updates']
            ]
        ]);

    } else {

        // Default preferences for a new user
        echo json_encode([
            "status" => "success",
            "preferences" => [
                "booking_updates" => true,
                "support_updates" => true,
                "promotions" => true,
                "security_alerts" => true,
                "general_updates" => true
            ]
        ]);
    }

    $stmt->close();
    exit;
}


/*
|--------------------------------------------------------------------------
| SAVE PREFERENCES
|--------------------------------------------------------------------------
*/

if ($method === 'POST') {

    $phone = trim($_POST['phone'] ?? '');

    if ($phone === '') {
        echo json_encode([
            "status" => "error",
            "message" => "Phone number is required"
        ]);
        exit;
    }

    $bookingUpdates  = isset($_POST['booking_updates']) ? (int)$_POST['booking_updates'] : 1;
    $supportUpdates  = isset($_POST['support_updates']) ? (int)$_POST['support_updates'] : 1;
    $promotions      = isset($_POST['promotions']) ? (int)$_POST['promotions'] : 1;
    $securityAlerts  = isset($_POST['security_alerts']) ? (int)$_POST['security_alerts'] : 1;
    $generalUpdates  = isset($_POST['general_updates']) ? (int)$_POST['general_updates'] : 1;

    // Keep values strictly 0 or 1
    $bookingUpdates = $bookingUpdates ? 1 : 0;
    $supportUpdates = $supportUpdates ? 1 : 0;
    $promotions = $promotions ? 1 : 0;
    $securityAlerts = $securityAlerts ? 1 : 0;
    $generalUpdates = $generalUpdates ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO notification_preferences
        (
            user_phone,
            booking_updates,
            support_updates,
            promotions,
            security_alerts,
            general_updates
        )
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            booking_updates = VALUES(booking_updates),
            support_updates = VALUES(support_updates),
            promotions = VALUES(promotions),
            security_alerts = VALUES(security_alerts),
            general_updates = VALUES(general_updates)
    ");

    $stmt->bind_param(
        "siiiii",
        $phone,
        $bookingUpdates,
        $supportUpdates,
        $promotions,
        $securityAlerts,
        $generalUpdates
    );

    if ($stmt->execute()) {

        echo json_encode([
            "status" => "success",
            "message" => "Notification preferences saved successfully"
        ]);

    } else {

        echo json_encode([
            "status" => "error",
            "message" => "Failed to save notification preferences"
        ]);
    }

    $stmt->close();
    exit;
}


echo json_encode([
    "status" => "error",
    "message" => "Invalid request method"
]);
