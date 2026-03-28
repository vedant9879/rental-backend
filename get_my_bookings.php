<?php
include "db.php";

if(isset($_GET['user_phone'])){

    $user = $_GET['user_phone'];

    $stmt = $conn->prepare("SELECT * FROM bookings WHERE user_phone=? ORDER BY id DESC");
    $stmt->bind_param("s", $user);
    $stmt->execute();

    $result = $stmt->get_result();

    $data = array();

    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);

}else{
    echo json_encode([
        "status" => "error",
        "message" => "user_phone missing"
    ]);
}
?>
