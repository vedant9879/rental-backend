<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// GET ALL VEHICLES
// =====================================================

$sql = "
    SELECT *
    FROM vehicles
    ORDER BY id DESC
";

$res = mysqli_query($conn, $sql);

$data = array();


// =====================================================
// CHECK DATABASE RESULT
// =====================================================

if ($res) {

    while ($row = mysqli_fetch_assoc($res)) {


        // =================================================
        // IMAGE URL
        // =================================================

        if (
            isset($row['vehicle_image']) &&
            !empty($row['vehicle_image']) &&
            strpos($row['vehicle_image'], "http") !== 0
        ) {

            $row['vehicle_image'] =
                "https://rental-backend-production-8cbf.up.railway.app/" .
                ltrim(
                    $row['vehicle_image'],
                    "/"
                );
        }


        // =================================================
        // NORMAL VEHICLE DEFAULTS
        // =================================================

        $row['vehicle_name'] =
            $row['vehicle_name'] ?? "";

        $row['vehicle_type'] =
            $row['vehicle_type'] ?? "";

        $row['service_type'] =
            $row['service_type'] ?? "Self Drive";

        $row['city'] =
            $row['city'] ?? "";

        $row['address'] =
            $row['address'] ?? "";

        $row['quantity'] =
            $row['quantity'] ?? "1";

        $row['deposit'] =
            $row['deposit'] ?? "0";

        $row['price_per_day'] =
            $row['price_per_day'] ?? "0";

        $row['price_6hr'] =
            $row['price_6hr'] ?? "0";

        $row['price_12hr'] =
            $row['price_12hr'] ?? "0";


        // =================================================
        // TRANSPORT PRICING DEFAULTS
        // =================================================

        $row['transport_base_fare'] =
            $row['transport_base_fare'] ?? "0";

        $row['transport_minimum_fare'] =
            $row['transport_minimum_fare'] ?? "0";

        $row['transport_per_km'] =
            $row['transport_per_km'] ?? "0";

        $row['transport_driver_charge'] =
            $row['transport_driver_charge'] ?? "0";

        $row['transport_waiting_charge'] =
            $row['transport_waiting_charge'] ?? "0";


        // =================================================
        // ADD VEHICLE
        // =================================================

        $data[] = $row;
    }
}


// =====================================================
// JSON RESPONSE
// =====================================================

echo json_encode($data);

?>
