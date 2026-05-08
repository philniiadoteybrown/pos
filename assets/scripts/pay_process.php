<?php
include "dbconn.php";

// ✅ safe checks
$customer_id = $_POST['customer_id'] ?? null;
$amount = $_POST['amount'] ?? null;

if(!$customer_id || !$amount){
    die("Missing payment data");
}

$customer_id = intval($customer_id);
$amount = floatval($amount);

if($amount <= 0){
    die("Invalid amount");
}

// get balance
$res = mysqli_query($conn,"SELECT balance FROM customers WHERE id=$customer_id");
$row = mysqli_fetch_assoc($res);

if($amount > $row['balance']){
    die("Amount exceeds balance");
}

// save payment
mysqli_query($conn,"
INSERT INTO customer_payments(customer_id, amount)
VALUES($customer_id, $amount)
");

// update balance
mysqli_query($conn,"
UPDATE customers 
SET balance = balance - $amount,
last_payment_date = CURDATE()
WHERE id=$customer_id
");

echo "Payment successful";
?>