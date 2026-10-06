<?php

header("Content-Type: application/json");

require_once "db.php";

$response = [
    "success" => false,
    "message" => "Unable to send buy request"
];

try {

    $vehicle_id = intval(
        $_POST["vehicle_id"] ?? 0
    );

    $buyer_phone = trim(
        $_POST["buyer_phone"] ?? ""
    );

    $buyer_message = trim(
        $_POST["buyer_message"] ?? ""
    );


    // =========================================================
    // VALIDATION
    // =========================================================

    if ($vehicle_id <= 0) {

        $response["message"] =
            "Invalid vehicle";

        echo json_encode($response);
        exit;
    }


    if ($buyer_phone === "") {

        $response["message"] =
            "Please login again";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // GET VEHICLE
    // =========================================================

    $stmt = $conn->prepare(
        "SELECT
            id,
            owner_phone,
            vehicle_name,
            listing_type,
            selling_price
         FROM vehicles
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $vehicle_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();


    if ($result->num_rows === 0) {

        $response["message"] =
            "Vehicle not found";

        echo json_encode($response);
        exit;
    }


    $vehicle =
        $result->fetch_assoc();


    $seller_phone =
        trim(
            $vehicle["owner_phone"] ?? ""
        );


    $listing_type =
        strtolower(
            trim(
                $vehicle["listing_type"] ?? ""
            )
        );


    // =========================================================
    // ONLY SELL / RENT + SELL
    // =========================================================

    if (
        $listing_type !== "sell" &&
        $listing_type !== "rent + sell"
    ) {

        $response["message"] =
            "This vehicle is not available for purchase";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // SELLER PHONE VALIDATION
    // =========================================================

    if ($seller_phone === "") {

        $response["message"] =
            "Seller information unavailable";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // OWN VEHICLE CHECK
    // =========================================================

    if (
        $buyer_phone === $seller_phone
    ) {

        $response["message"] =
            "You cannot send a buy request for your own vehicle";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // CHECK EXISTING BUY REQUEST
    // =========================================================

    $check = $conn->prepare(
        "SELECT
            id,
            status
         FROM buy_requests
         WHERE vehicle_id = ?
         AND buyer_phone = ?
         AND status IN ('pending', 'accepted')
         LIMIT 1"
    );

    $check->bind_param(
        "is",
        $vehicle_id,
        $buyer_phone
    );

    $check->execute();

    $existing =
        $check->get_result();


    if ($existing->num_rows > 0) {

        $old =
            $existing->fetch_assoc();

        $response["message"] =
            "You already have a " .
            $old["status"] .
            " request for this vehicle";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // CHECK IF ANOTHER BUYER WAS ALREADY ACCEPTED
    // =========================================================

    $acceptedCheck = $conn->prepare(
        "SELECT id
         FROM buy_requests
         WHERE vehicle_id = ?
         AND status = 'accepted'
         LIMIT 1"
    );

    $acceptedCheck->bind_param(
        "i",
        $vehicle_id
    );

    $acceptedCheck->execute();


    if (
        $acceptedCheck
            ->get_result()
            ->num_rows > 0
    ) {

        $response["message"] =
            "This vehicle already has an accepted buyer";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // INSERT REQUEST
    // =========================================================

    $insert = $conn->prepare(
        "INSERT INTO buy_requests
        (
            vehicle_id,
            buyer_phone,
            seller_phone,
            status,
            buyer_message
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'pending',
            ?
        )"
    );


    $insert->bind_param(
        "isss",
        $vehicle_id,
        $buyer_phone,
        $seller_phone,
        $buyer_message
    );


    if (!$insert->execute()) {

        $response["message"] =
            "Unable to create buy request";

        echo json_encode($response);
        exit;
    }


    // =========================================================
    // SUCCESS
    // =========================================================

    $response["success"] =
        true;

    $response["message"] =
        "Buy request sent successfully";

    $response["request_id"] =
        $insert->insert_id;

    $response["status"] =
        "pending";


    echo json_encode($response);


} catch (Exception $e) {

    $response["message"] =
        "Server error";

    echo json_encode($response);
}
?>
