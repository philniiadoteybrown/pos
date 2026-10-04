<?php
include "assets/scripts/dbconn.php";

echo $id = $_GET['id'];

mysqli_query($conn,"DELETE FROM products WHERE productid='$id'");
mysqli_query($conn,"DELETE FROM purchase_items WHERE productid='$id'");
mysqli_query($conn,"DELETE FROM units WHERE product_id='$id'");

header("Location: products.php?deleted=1");