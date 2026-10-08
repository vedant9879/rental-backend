<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

$response = [
    "success" => false,
    "message" => "Unable to delete vehicle"
];

try {

    // =====================================================
    // GET VEHICLE ID
    // Accept both vehicle_id and id
    // =====================================================

    $vehicle_id = 0;

    if (isset($_POST["vehicle_id"])) {
        $vehicle_id = intval($_POST["vehicle_id"]);
    } elseif (isset($_POST["id"])) {
        $vehicle_id = intval($_POST["id"]);
    } elseif (isset($_GET["vehicle_id"])) {
        $vehicle_id = intval($_GET["vehicle_id"]);
    } elseif (isset($_GET["id"])) {
        $vehicle_id = intval($_GET["id"]);
    }

    // =====================================================
    // VALIDATE ID
    // =====================================================

    if ($vehicle_id <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid vehicle ID"
        ]);

        exit;
    }

    // =====================================================
    // CHECK VEHICLE EXISTS
    // =====================================================

    $checkSql = "
        SELECT id
        FROM vehicles
        WHERE id = ?
        LIMIT 1
    ";

    $checkStmt = $conn->prepare($checkSql);

    if (!$checkStmt) {

        echo json_encode([
            "success" => false,
            "message" => "Database prepare error"
        ]);

        exit;
    }

    $checkStmt->bind_param(
        "i",
        $vehicle_id
    );

    $checkStmt->execute();

    $checkStmt->store_result();

    if ($checkStmt->num_rows === 0) {

        $checkStmt->close();

        echo json_encode([
            "success" => false,
            "message" => "Vehicle not found"
        ]);

        exit;
    }

    $checkStmt->close();

    // =====================================================
    // DELETE VEHICLE
    // =====================================================

    $deleteSql = "
        DELETE FROM vehicles
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {

        echo json_encode([
            "success" => false,
            "message" => "Unable to prepare delete request"
        ]);

        exit;
    }

    $deleteStmt->bind_param(
        "i",
        $vehicle_id
    );

    if ($deleteStmt->execute()) {

        if ($deleteStmt->affected_rows > 0) {

            echo json_encode([
                "success" => true,
                "message" => "Vehicle removed successfully",
                "vehicle_id" => $vehicle_id
            ]);

        } else {

            echo json_encode([
                "success" => false,
                "message" => "Vehicle could not be deleted"
            ]);
        }

    } else {

        echo json_encode([
            "success" => false,
            "message" => "Database delete failed"
        ]);
    }

    $deleteStmt->close();

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

?>
