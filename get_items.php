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
        // BASIC VEHICLE DATA
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


        // =================================================
        // LISTING TYPE
        // =================================================
        // Rent
        // Sell
        // Rent + Sell
        // Goods Transportation

        $row['listing_type'] =
            !empty($row['listing_type'])
                ? $row['listing_type']
                : "Rent";


        // =================================================
        // RENTAL PRICING
        // =================================================

        $row['price_per_day'] =
            $row['price_per_day'] ?? "0";

        $row['price_6hr'] =
            $row['price_6hr'] ?? "0";

        $row['price_12hr'] =
            $row['price_12hr'] ?? "0";

        $row['deposit'] =
            $row['deposit'] ?? "0";


        // =================================================
        // SELLING INFORMATION
        // =================================================

        $row['selling_price'] =
            $row['selling_price'] ?? "0";

        $row['vehicle_condition'] =
            $row['vehicle_condition'] ?? "";

        $row['manufacturing_year'] =
            $row['manufacturing_year'] ?? "";

        $row['kilometers_driven'] =
            $row['kilometers_driven'] ?? "0";

        $row['ownership'] =
            $row['ownership'] ?? "";


        // =================================================
        // TRANSPORT PRICING
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
        // GOODS TRANSPORT DRIVER
        // =================================================

        if (
            $row['listing_type'] ===
            "Goods Transportation"
        ) {

            $row['driver_required'] = true;

        } else {

            $row['driver_required'] = false;
        }


        // =================================================
        // RENT AVAILABLE
        // =================================================

        if (
            $row['listing_type'] === "Rent" ||
            $row['listing_type'] === "Rent + Sell"
        ) {

            $row['available_for_rent'] = true;

        } else {

            $row['available_for_rent'] = false;
        }


        // =================================================
        // AVAILABLE FOR SALE
        // =================================================

        if (
            $row['listing_type'] === "Sell" ||
            $row['listing_type'] === "Rent + Sell"
        ) {

            $row['available_for_sale'] = true;

        } else {

            $row['available_for_sale'] = false;
        }


        // =================================================
        // AVAILABLE FOR TRANSPORT
        // =================================================

        if (
            $row['listing_type'] ===
            "Goods Transportation"
        ) {

            $row['available_for_transport'] = true;

        } else {

            $row['available_for_transport'] = false;
        }


        // =================================================
        // ADD VEHICLE
        // =================================================

        $data[] = $row;
    }
}


// =====================================================
// DATABASE ERROR
// =====================================================

else {

    echo json_encode([
        "status" => "error",
        "message" => "Unable to load vehicles"
    ]);

    exit;
}


// =====================================================
// JSON RESPONSE
// =====================================================

echo json_encode($data);

?>
