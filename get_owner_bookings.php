<?php
include "db.php";

$owner =
$_GET['owner_phone'] ?? '';

if($owner == ''){
    echo "[]";
    exit();
}

$sql =
"SELECT
b.id,
v.vehicle_name,
b.user_phone,
b.owner_phone,
b.start_date,
b.end_date,
b.total_price,
b.status,
b.quantity,
b.booking_plan,
b.payment_mode

FROM bookings b

JOIN vehicles v
ON b.vehicle_id = v.id

WHERE b.owner_phone='$owner'

ORDER BY b.id DESC";

$result =
mysqli_query($conn,$sql);

$data = array();

while($row =
mysqli_fetch_assoc($result)){

    $data[] = $row;
}

echo json_encode($data);
?>
