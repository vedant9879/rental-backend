<?php

header("Content-Type: application/json");

require_once "db.php";


$response = [
    "success" => false,
    "message" => "Unable to update request"
];


try {

    $request_id = intval(
        $_POST["request_id"] ?? 0
    );

    $seller_phone = trim(
        $_POST["seller_phone"] ?? ""
    );

    $new_status = strtolower(
        trim(
            $_POST["status"] ?? ""
        )
    );


    // =========================================================
    // VALIDATION
    // =========================================================

    if ($request_id <= 0) {

        $response["message"] =
            "Invalid request";

        echo json_encode($response);

        exit;
    }


    if ($seller_phone === "") {

        $response["message"] =
            "Seller login required";

        echo json_encode($response);

        exit;
    }


    $allowedStatuses = [
        "accepted",
        "rejected",
        "completed"
    ];


    if (
        !in_array(
            $new_status,
            $allowedStatuses,
            true
        )
    ) {

        $response["message"] =
            "Invalid status";

        echo json_encode($response);

        exit;
    }


    // =========================================================
    // GET REQUEST
    // =========================================================

    $check = $conn->prepare(
        "SELECT
            id,
            vehicle_id,
            seller_phone,
            status
         FROM buy_requests
         WHERE id = ?
         LIMIT 1"
    );


    $check->bind_param(
        "i",
        $request_id
    );


    $check->execute();


    $result =
        $check->get_result();


    if (
        $result->num_rows === 0
    ) {

        $response["message"] =
            "Buy request not found";

        echo json_encode($response);

        exit;
    }


    $request =
        $result->fetch_assoc();


    // =========================================================
    // SECURITY
    // =========================================================

    if (
        $request["seller_phone"] !==
        $seller_phone
    ) {

        $response["message"] =
            "You are not authorized";

        echo json_encode($response);

        exit;
    }


    $old_status =
        strtolower(
            $request["status"]
        );


    // =========================================================
    // STATUS RULES
    // =========================================================

    if (
        $new_status === "accepted"
    ) {

        if (
            $old_status !== "pending"
        ) {

            $response["message"] =
                "Only pending requests can be accepted";

            echo json_encode($response);

            exit;
        }


        // Check another accepted buyer

        $accepted =
            $conn->prepare(
                "SELECT id
                 FROM buy_requests
                 WHERE vehicle_id = ?
                 AND status = 'accepted'
                 AND id != ?
                 LIMIT 1"
            );


        $accepted->bind_param(
            "ii",
            $request["vehicle_id"],
            $request_id
        );


        $accepted->execute();


        if (
            $accepted
                ->get_result()
                ->num_rows > 0
        ) {

            $response["message"] =
                "Another buyer is already accepted";

            echo json_encode($response);

            exit;
        }
    }


    if (
        $new_status === "rejected"
    ) {

        if (
            $old_status !== "pending"
        ) {

            $response["message"] =
                "Only pending requests can be rejected";

            echo json_encode($response);

            exit;
        }
    }


    if (
        $new_status === "completed"
    ) {

        if (
            $old_status !== "accepted"
        ) {

            $response["message"] =
                "Only accepted requests can be completed";

            echo json_encode($response);

            exit;
        }
    }


    // =========================================================
    // UPDATE
    // =========================================================

    $update =
        $conn->prepare(
            "UPDATE buy_requests
             SET status = ?
             WHERE id = ?"
        );


    $update->bind_param(
        "si",
        $new_status,
        $request_id
    );


    if (!$update->execute()) {

        $response["message"] =
            "Unable to update request";

        echo json_encode($response);

        exit;
    }


    $response["success"] =
        true;

    $response["message"] =
        "Request " .
        $new_status .
        " successfully";

    $response["status"] =
        $new_status;


    echo json_encode($response);


} catch (Exception $e) {

    $response["message"] =
        "Server error";

    echo json_encode($response);
}

?>
