<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");


// ============================================================
// GET VEHICLE ID
// ============================================================

$vehicle_id =
    isset($_GET["vehicle_id"])
        ? intval($_GET["vehicle_id"])
        : 0;


// ============================================================
// VALIDATION
// ============================================================

if ($vehicle_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid vehicle",
        "reviews" => [],
        "average_rating" => 0,
        "review_count" => 0
    ]);

    exit;
}


// ============================================================
// GET REVIEWS
// ============================================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        vehicle_id,
        user_name,
        rating,
        comment,
        created_at
     FROM reviews
     WHERE vehicle_id = ?
     ORDER BY id DESC"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $vehicle_id
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);


// ============================================================
// BUILD REVIEW LIST
// ============================================================

$reviews = array();

while ($row = mysqli_fetch_assoc($result)) {

    $reviews[] = [
        "id" => intval($row["id"]),
        "vehicle_id" => intval($row["vehicle_id"]),
        "user_name" => $row["user_name"],
        "rating" => floatval($row["rating"]),
        "comment" => $row["comment"],
        "created_at" => $row["created_at"]
    ];
}


// ============================================================
// AVERAGE RATING
// ============================================================

$averageRating = 0;

if (count($reviews) > 0) {

    $totalRating = 0;

    foreach ($reviews as $review) {

        $totalRating +=
            floatval($review["rating"]);
    }

    $averageRating =
        $totalRating / count($reviews);
}


// ============================================================
// RESPONSE
// ============================================================

echo json_encode([
    "success" => true,
    "message" => "Reviews loaded successfully",
    "vehicle_id" => $vehicle_id,
    "average_rating" => round($averageRating, 1),
    "review_count" => count($reviews),
    "reviews" => $reviews
]);


mysqli_stmt_close($stmt);

?>
