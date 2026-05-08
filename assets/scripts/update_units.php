<?php
include "dbconn.php";

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$name = isset($_POST['unit_name']) ? mysqli_real_escape_string($conn, $_POST['unit_name']) : '';
$qty = isset($_POST['unit_qty']) ? floatval($_POST['unit_qty']) : 0;
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;

if($id <= 0){
    die("error: missing id");
}

$query = "
    UPDATE units
    SET 
        unit_name = '$name',
        unit_qty = '$qty',
        price = '$price'
    WHERE id = $id
";

$result = mysqli_query($conn, $query);

if(!$result){
    die("error: " . mysqli_error($conn));
}

echo "success";