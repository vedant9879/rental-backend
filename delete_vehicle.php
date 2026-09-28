<?php

include "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| RECEIVE VEHICLE ID
|--------------------------------------------------------------------------
*/

$id = isset($_POST['vehicle_id'])
    ? intval($_POST['vehicle_id'])
    : 0;


/*
|--------------------------------------------------------------------------
| CHECK VEHICLE ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Vehicle ID is required"
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK VEHICLE EXISTS
|--------------------------------------------------------------------------
*/

$checkSql = "
    SELECT id
    FROM vehicles
    WHERE id = ?
    LIMIT 1
";

$checkStmt = mysqli_prepare($conn, $checkSql);

if (!$checkStmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $checkStmt,
    "i",
    $id
);

mysqli_stmt_execute($checkStmt);

$result = mysqli_stmt_get_result($checkStmt);

if (!$result || mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($checkStmt);

    echo json_encode([
        "success" => false,
        "message" => "Vehicle not found"
    ]);

    exit();
}

mysqli_stmt_close($checkStmt);


/*
|--------------------------------------------------------------------------
| DELETE VEHICLE
|--------------------------------------------------------------------------
*/

$deleteSql = "
    DELETE FROM vehicles
    WHERE id = ?
";

$deleteStmt = mysqli_prepare(
    $conn,
    $deleteSql
);

if (!$deleteStmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error"
    ]);

    exit();
}

mysqli_stmt_bind_param(
    $deleteStmt,
    "i",
    $id
);


/*
|--------------------------------------------------------------------------
| EXECUTE DELETE
|--------------------------------------------------------------------------
*/

if (mysqli_stmt_execute($deleteStmt)) {

    echo json_encode([
        "success" => true,
        "message" => "Vehicle removed successfully"
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete vehicle"
    ]);
}


mysqli_stmt_close($deleteStmt);

?>
