<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/db.php";

mysqli_report(MYSQLI_REPORT_OFF);

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respond(405, [
        "status" => "error",
        "message" => "GET method required"
    ]);
}

$userPhone = trim((string)($_GET["user_phone"] ?? ""));

if ($userPhone === "" || strlen($userPhone) > 30) {
    respond(400, [
        "status" => "error",
        "message" => "Valid user phone is required"
    ]);
}

try {
    $sql = "
        SELECT
            id,
            requester_phone,
            owner_phone,
            vehicle_id,
            vehicle_name,
            pickup,
            drop_location,
            goods,
            weight,
            vehicle_required,
            transport_date,
            transport_time,
            base_fare,
            minimum_fare,
            per_km,
            driver_charge,
            waiting_charge,
            final_fare,
            status,
            created_at,
            updated_at
        FROM transport_requests
        WHERE requester_phone = ?
        ORDER BY id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Unable to prepare transport query");
    }

    $stmt->bind_param("s", $userPhone);

    if (!$stmt->execute()) {
        throw new Exception("Unable to load transport requests");
    }

    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["vehicle_id"] = (int)$row["vehicle_id"];

        foreach ([
            "base_fare",
            "minimum_fare",
            "per_km",
            "driver_charge",
            "waiting_charge"
        ] as $field) {
            $row[$field] = (float)($row[$field] ?? 0);
        }

        $row["final_fare"] = $row["final_fare"] === null
            ? null
            : (float)$row["final_fare"];

        $data[] = $row;
    }

    $stmt->close();

    respond(200, $data);

} catch (Throwable $e) {
    error_log("get_my_transport_requests.php error: " . $e->getMessage());
    respond(500, [
        "status" => "error",
        "message" => "Unable to load transport requests"
    ]);
}
?>
