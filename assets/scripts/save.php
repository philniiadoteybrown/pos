<?php
include "dbconn.php";

echo "<pre>";
print_r($_POST);
exit;

// if(isset($_POST['products'])){

//     $products = $_POST['products'];
//     $qtys     = $_POST['qty'];

//     // ✅ Now these will work correctly
//     $total    = isset($_POST['total']) ? floatval($_POST['total']) : 0;
//     $paid     = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
//     $balance  = isset($_POST['balance']) ? floatval($_POST['balance']) : 0;

//     $method   = $_POST['payment_method'];
//     $customer = $_POST['customer_id'];
//      $cphone = $_POST['phone'];
//     $new      = $_POST['new_customer'];

//     mysqli_begin_transaction($conn);

//     try {

//         // 👤 Create customer if new
//         if($method == 'credit' && !empty($new)){
//             mysqli_query($conn,"INSERT INTO customers(name, phone) VALUES('$new','$cphone')");
//             $customer = mysqli_insert_id($conn);
//         }

//         // 🧾 Collect product summary
//         $codes = [];
//         $names = [];

//         foreach($products as $pid){
//             $res = mysqli_query($conn,"SELECT pname FROM products WHERE productid='$pid'");
//             $row = mysqli_fetch_assoc($res);

//             $codes[] = $pid;
//             $names[] = $row['pname'];
//         }

//         $codes_str = implode(",", $codes);
//         $names_str = implode(",", $names);

//         // 💾 Save sale
//         mysqli_query($conn,"
//             INSERT INTO sales(payment_method,customer_id,total,paid,balance,product_codes,product_names)
//             VALUES('$method','$customer','$total','$paid','$balance','$codes_str','$names_str')
//         ");

//         $sale_id = mysqli_insert_id($conn);

//         // 📦 Save items
//         foreach($products as $i=>$pid){

//             $qty = floatval($qtys[$i]);

//             $res = mysqli_query($conn,"
//                 SELECT pname, qty, sellingprice 
//                 FROM products 
//                 WHERE productid='$pid'
//             ");
//             $row = mysqli_fetch_assoc($res);

//             if($row['qty'] < $qty){
//                 throw new Exception("Stock low: ".$row['pname']);
//             }

//             $price = $row['sellingprice'];
//             $subtotal = $qty * $price;

//             mysqli_query($conn,"
//                 INSERT INTO sales_items(sale_id,product_id,pname,qty,price,subtotal)
//                 VALUES($sale_id,'$pid','{$row['pname']}',$qty,$price,$subtotal)
//             ");

//             mysqli_query($conn,"
//                 UPDATE products 
//                 SET qty = qty - $qty 
//                 WHERE productid='$pid'
//             ");
//         }

//         // 💳 Credit
//         if($method == 'credit' && $customer){
//             mysqli_query($conn,"
//                 UPDATE customers 
//                 SET balance = balance + $total 
//                 WHERE id = $customer
//             ");
//         }

//         mysqli_commit($conn);

//          $msg="Sales transaction ended successfully.";
// header('refresh:2; url=../../pos.php');

//     } catch(Exception $e){

//         mysqli_rollback($conn);
//         echo $e->getMessage();
//     }
// }
?>