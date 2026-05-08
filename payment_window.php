<?php

$pagetitle="Customer Payment";
include "assets/scripts/dbconn.php";

$id = intval($_GET['id']);

$query = "SELECT * FROM customers where id='$id'";
$search_result = mysqli_query($conn,$query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
// ✅ safe checks
$customer_id = $_POST['customer_id'] ?? null;
$amount = $_POST['amount'] ?? null;

if(!$customer_id || !$amount){
    die("Missing payment data");
}

$customer_id = intval($customer_id);
$amount = floatval($amount);

if($amount <= 0){
    
    die($errmsg="Invalid amount entered.");
}

// get balance
$res = mysqli_query($conn,"SELECT balance FROM customers WHERE id=$customer_id");
$row = mysqli_fetch_assoc($res);

if($amount > $row['balance']){
    
    die($warnmsg="Amount exceeds balance.");
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

  $msg="Payment successful.";
echo "<script>window.close();</script>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include "assets/sections/headers/header_tag.php" ?>
    <style>
    body {
        font-family: Arial;
        margin: 0;
        background: #f4f6f9;
    }

    /* Center container inside window */
    .container {
        width: 100%;
        max-width: 500px;
        margin: 30px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    /* Header style like dialog */
    h3 {
        margin-top: 0;
        background: #007bff;
        color: #fff;
        padding: 12px;
        border-radius: 6px;
        text-align: center;
    }

    /* Inputs */
    input,
    button {
        width: 100%;
        padding: 10px;
        margin-top: 10px;
        box-sizing: border-box;
        font-size: 14px;
    }

    button {
        background: #022041;
        color: #fff;
        border: none;
        cursor: pointer;
        border-radius: 5px;
    }

    button:hover {
        background: #0056b3;
    }

    /* Small label styling */
    p {
        margin: 5px 0;
    }

    /* Chrome, Safari, Edge */
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    </style>
</head>

<body>

    <div class="container">
        <button type="button" onclick="window.close()"
            style="background:#6c757d;color:#fff;border:none;padding:10px;width:100%;margin-top:10px;border-radius:5px;cursor:pointer;">
            ← Go Back
        </button>
        <h3>Customer Payment</h3>
        <?php
if(mysqli_num_rows($search_result)>0){
while($fetch = mysqli_fetch_assoc($search_result)){
?>
        <p><strong>Name:</strong> <?= $fetch['name'] ?></p>
        <p><strong>Phone:</strong> <?= $fetch['phone'] ?></p>
        <p><strong>Balance:</strong> GHS <?= $fetch['balance'] ?></p>

        <form method="POST" action="">

            <input type="hidden" name="customer_id" value="<?= $id ?>">

            <label>Amount</label>
            <input type="number" step="0.01" name="amount" required>

            <button type="submit" name="pay">Submit Payment</button>

        </form>
        <?php } } ?>


    </div>

</body>

</html>