<?php
include "dbconn.php";



if(isset($_POST['addstock'])){

    $productid = mysqli_real_escape_string($conn, $_POST['productid']);
    $qpurchase = floatval($_POST['qpurchase']);

    // 🔍 Get product details
    $res = mysqli_query($conn,"SELECT * FROM products WHERE productid='$productid'");
    $p = mysqli_fetch_assoc($res);

    if(!$p){
        die("Product not found");
    }

    // ✅ 1. UPDATE PRODUCTS TABLE
    $update = mysqli_query($conn,"
        UPDATE products 
        SET qty = qty + $qpurchase 
        WHERE productid = '$productid'
    ");

    // ✅ 2. SAVE INTO purchase_items
    $insert = mysqli_query($conn,"
        INSERT INTO purchase_items
        (productid, pname, pdesc, unit, qty, unitprice, sellingprice, qtyalert, type)
        VALUES
        (
            '{$p['productid']}',
            '{$p['pname']}',
            '{$p['pdesc']}',
            '{$p['unit']}',
            '$qpurchase',
            '{$p['unitprice']}',
            '{$p['sellingprice']}',
            '{$p['qtyalert']}',
            'restock'
        )
    ");

    if($update && $insert){
           $msg="Successfully Saved.";
header('refresh:2; url=../../products.php');
    }else{
        echo "<script>alert('Error updating stock'); window.history.back();</script>";
    }

}
?>