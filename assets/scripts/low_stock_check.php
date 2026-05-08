<?php
include "dbconn.php";

$result = mysqli_query($conn,"
SELECT productid, pname, totalstock, qtyalert
FROM products
WHERE totalstock <= qtyalert
ORDER BY totalstock ASC
");

$data = [];

while($row = mysqli_fetch_assoc($result)){
    $data[] = $row;
}

echo json_encode($data);