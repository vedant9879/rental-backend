<?php

include "db.php";

$id       = $_POST['vehicle_id'] ?? '';
$name     = $_POST['vehicle_name'] ?? '';
$price    = $_POST['price_per_day'] ?? '';
$price6   = $_POST['price_6hr'] ?? '';
$qty      = $_POST['quantity'] ?? '';
$deposit  = $_POST['deposit'] ?? '';
$city     = $_POST['city'] ?? '';
$address  = $_POST['address'] ?? '';

/*
 * Check vehicle ID
 */
if (trim($id) === '') {
    echo "Vehicle ID Missing";
    exit();
}

/*
 * Check required fields
 */
if (
    trim($name) === '' ||
    trim($price) === '' ||
    trim($qty) === ''
) {
    echo "Required Fields Missing";
    exit();
}

/*
 * Update vehicle
 */
$sql = "
    UPDATE vehicles SET
        vehicle_name = ?,
        price_per_day = ?,
        price_6hr = ?,
        quantity = ?,
        deposit = ?,
        city = ?,
        address = ?
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "Database Error";
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "sssssssi",
    $name,
    $price,
    $price6,
    $qty,
    $deposit,
    $city,
    $address,
    $id
);

if (mysqli_stmt_execute($stmt)) {

    echo "Updated Successfully";

} else {

    echo "Update Failed";
}

mysqli_stmt_close($stmt);

?>
