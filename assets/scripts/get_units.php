<?php
include "dbconn.php";

if(!isset($_GET['product_id'])){
    echo json_encode([]);
    exit;
}

$product_id = mysqli_real_escape_string($conn, $_GET['product_id']);

$res = mysqli_query($conn, "
    SELECT unit_name, unit_qty, price 
    FROM units
    WHERE product_id = '$product_id'
");

$units = [];

while($row = mysqli_fetch_assoc($res)){
    $units[] = $row;
}

header('Content-Type: application/json');
echo json_encode($units);
?>