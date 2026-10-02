<?php

header("Content-Type: application/json");

include "db.php";

$response = [
    "success" => false,
    "message" => ""
];

try {

    // -----------------------------
    // 1. GET INPUT
    // -----------------------------
    $bookingId = isset($_POST["booking_id"])
        ? intval($_POST["booking_id"])
        : 0;

    $cancelledBy = isset($_POST["cancelled_by"])
        ? trim($_POST["cancelled_by"])
        : "";

    $cancelledByPhone = isset($_POST["user_phone"])
        ? trim($_POST["user_phone"])
        : "";

    $reason = isset($_POST["cancellation_reason"])
        ? trim($_POST["cancellation_reason"])
        : "No reason provided";


    // -----------------------------
    // 2. VALIDATION
    // -----------------------------
    if ($bookingId <= 0) {
        throw new Exception("Invalid booking ID");
    }

    if ($cancelledByPhone === "") {
        throw new Exception("User phone is required");
    }

    if (
        $cancelledBy !== "customer" &&
        $cancelledBy !== "owner"
    ) {
        throw new Exception("Invalid cancellation type");
    }


    // -----------------------------
    // 3. GET BOOKING DETAILS
    // -----------------------------
    $sql = "
        SELECT
            b.id,
            b.vehicle_id,
            b.user_phone,
            b.owner_phone,
            b.total_price,
            b.status,
            b.payment_mode,
            b.vehicle_id,
            v.vehicle_name
        FROM bookings b
        LEFT JOIN vehicles v
            ON b.vehicle_id = v.id
        WHERE b.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Database query failed");
    }

    $stmt->bind_param("i", $bookingId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Booking not found");
    }

    $booking = $result->fetch_assoc();

    $stmt->close();


    // -----------------------------
    // 4. VERIFY CANCELLER
    // -----------------------------
    if ($cancelledBy === "customer") {

        if (
            $booking["user_phone"] === null ||
            $booking["user_phone"] !== $cancelledByPhone
        ) {
            throw new Exception(
                "You are not authorized to cancel this booking"
            );
        }

    } else {

        if (
            $booking["owner_phone"] === null ||
            $booking["owner_phone"] !== $cancelledByPhone
        ) {
            throw new Exception(
                "You are not authorized to cancel this booking"
            );
        }
    }


    // -----------------------------
    // 5. CHECK CURRENT STATUS
    // -----------------------------
    $currentStatus = strtolower(
        trim($booking["status"] ?? "")
    );

    if ($currentStatus === "cancelled") {
        throw new Exception("Booking is already cancelled");
    }

    if ($currentStatus === "completed") {
        throw new Exception(
            "Completed booking cannot be cancelled"
        );
    }

    if ($currentStatus === "picked_up") {
        throw new Exception(
            "Booking cannot be cancelled after vehicle pickup"
        );
    }


    // Only pending/accepted/confirmed/approved
    $allowedStatuses = [
        "pending",
        "accepted",
        "confirmed",
        "approved"
    ];

    if (!in_array($currentStatus, $allowedStatuses)) {
        throw new Exception(
            "Booking cannot be cancelled in its current status"
        );
    }


    // -----------------------------
    // 6. CALCULATE REFUND
    // -----------------------------
    $totalPrice = floatval(
        $booking["total_price"] ?? 0
    );

    $paymentMode = strtolower(
        trim($booking["payment_mode"] ?? "")
    );

    $refundAmount = 0;
    $refundStatus = "not_applicable";

    /*
     * COD / Cash bookings:
     * No money has been paid yet,
     * therefore there is no refund.
     */
    if (
        strpos($paymentMode, "cod") !== false ||
        strpos($paymentMode, "cash") !== false ||
        strpos($paymentMode, "delivery") !== false
    ) {

        $refundAmount = 0;
        $refundStatus = "not_applicable";

    } else {

        /*
         * Online payment:
         * Full booking amount is marked for refund.
         * Actual payment gateway refund can be
         * integrated later.
         */
        $refundAmount = $totalPrice;
        $refundStatus = "pending";
    }


    // -----------------------------
    // 7. UPDATE BOOKING
    // -----------------------------
    $updateSql = "
        UPDATE bookings
        SET
            status = 'cancelled',
            cancellation_reason = ?,
            cancelled_by = ?,
            cancelled_at = NOW(),
            refund_amount = ?,
            refund_status = ?
        WHERE id = ?
    ";

    $updateStmt = $conn->prepare($updateSql);

    if (!$updateStmt) {
        throw new Exception(
            "Unable to prepare cancellation update"
        );
    }

    $updateStmt->bind_param(
        "ssdsi",
        $reason,
        $cancelledBy,
        $refundAmount,
        $refundStatus,
        $bookingId
    );

    if (!$updateStmt->execute()) {
        throw new Exception(
            "Unable to cancel booking"
        );
    }

    $updateStmt->close();


    // -----------------------------
    // 8. NOTIFY OWNER
    // -----------------------------
    $vehicleName = $booking["vehicle_name"]
        ?: "Vehicle";

    $customerPhone = $booking["user_phone"];
    $ownerPhone = $booking["owner_phone"];

    if (
        $cancelledBy === "customer" &&
        !empty($ownerPhone)
    ) {

        $title = "Booking Cancelled";

        $message =
            "Booking #".$bookingId.
            " for ".$vehicleName.
            " was cancelled by the customer.";

        $notificationType = "booking_cancelled";

        $notifySql = "
            INSERT INTO notifications
            (
                user_phone,
                title,
                message,
                type
            )
            VALUES (?, ?, ?, ?)
        ";

        $notifyStmt = $conn->prepare($notifySql);

        if ($notifyStmt) {

            $notifyStmt->bind_param(
                "ssss",
                $ownerPhone,
                $title,
                $message,
                $notificationType
            );

            $notifyStmt->execute();
            $notifyStmt->close();
        }
    }


    // -----------------------------
    // 9. NOTIFY CUSTOMER
    // -----------------------------
    if (
        $cancelledBy === "owner" &&
        !empty($customerPhone)
    ) {

        $title = "Booking Cancelled";

        $message =
            "Booking #".$bookingId.
            " for ".$vehicleName.
            " was cancelled by the vehicle owner.";

        $notificationType = "booking_cancelled";

        $notifySql = "
            INSERT INTO notifications
            (
                user_phone,
                title,
                message,
                type
            )
            VALUES (?, ?, ?, ?)
        ";

        $notifyStmt = $conn->prepare($notifySql);

        if ($notifyStmt) {

            $notifyStmt->bind_param(
                "ssss",
                $customerPhone,
                $title,
                $message,
                $notificationType
            );

            $notifyStmt->execute();
            $notifyStmt->close();
        }
    }


    // -----------------------------
    // 10. SUCCESS RESPONSE
    // -----------------------------
    $response["success"] = true;

    $response["message"] =
        "Booking cancelled successfully";

    $response["booking_id"] = $bookingId;
    $response["status"] = "cancelled";
    $response["cancelled_by"] = $cancelledBy;
    $response["cancellation_reason"] = $reason;
    $response["refund_amount"] = $refundAmount;
    $response["refund_status"] = $refundStatus;

} catch (Exception $e) {

    $response["success"] = false;
    $response["message"] = $e->getMessage();
}

echo json_encode($response);

?>
