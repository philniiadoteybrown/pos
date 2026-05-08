<?php
include "dbconn.php";

$productid = mysqli_real_escape_string($conn,$_POST['productid']);
$qtysold = floatval($_POST['qtysold']);

// fetch product
$p = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT * FROM products WHERE productid='$productid'
"));

if(!$p){
    die("Product not found");
}

if($p['qty'] < $qtysold){
    die("Not enough stock");
}

// total calculation
$total = $qtysold * $p['sellingprice'];

// 1. insert into sales
mysqli_query($conn,"
INSERT INTO sales (total, created_at)
VALUES ('$total', NOW())
");

$sale_id = mysqli_insert_id($conn);

// 2. insert into sales_items
mysqli_query($conn,"
INSERT INTO sales_items
(sale_id, productid, pname, qty, unitprice, total)
VALUES
(
'$sale_id',
'$productid',
'{$p['pname']}',
'$qtysold',
'{$p['sellingprice']}',
'$total'
)
");

// 3. update stock
mysqli_query($conn,"
UPDATE products 
SET qty = qty - $qtysold
WHERE productid='$productid'
");

  $msg="Successfully Saved.";
header('refresh:2; url=products.php');