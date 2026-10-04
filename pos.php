<?php
$pagetitle="POS Terminal";
include "assets/scripts/auth.php";

include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";

$role = $_SESSION['role'] ?? '';

/* =========================================================
   AUTO-DETECT THIS POS SERVER LAN IP
   Used only to build the phone scanner address when the POS
   itself is opened as http://localhost/...
   Compatible with older PHP versions used by XAMPP.
========================================================= */
function isPrivateIpv4($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $parts = explode('.', $ip);
    if (count($parts) !== 4) {
        return false;
    }

    $a = (int)$parts[0];
    $b = (int)$parts[1];

    return (
        $a === 10 ||
        ($a === 172 && $b >= 16 && $b <= 31) ||
        ($a === 192 && $b === 168)
    );
}

function detectPosServerIp()
{
    /* First use SERVER_ADDR when it is already a LAN address. */
    $serverAddr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    if (isPrivateIpv4($serverAddr)) {
        return $serverAddr;
    }

    /* Then ask Windows/PHP for the host's resolved IPv4 addresses. */
    $hostname = gethostname();
    if ($hostname) {
        $ips = @gethostbynamel($hostname);
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if (isPrivateIpv4($ip)) {
                    return $ip;
                }
            }
        }
    }

    return $serverAddr && filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? $serverAddr
        : '';
}

$posServerIp = detectPosServerIp();
//////////////////////


$where = "";

if($search != ""){
    $where = "WHERE pname LIKE '%$search%' 
              OR productid LIKE '%$search%'";
}

// total rows
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM products $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// fetch data
$res = mysqli_query($conn,"
SELECT * FROM products 
$where
ORDER BY pname ASC
LIMIT $offset, $limit
");



////////////////////////////


if(isset($_POST['products']) && isset($_POST['qty']) && isset($_POST['unit_qty'])){

    $products   = $_POST['products'];
    $qtys       = $_POST['qty'];
   $unit_qtys = $_POST['unit_qty']; // 🔥 IMPORTANT

    $total    = floatval($_POST['total'] ?? 0);
    $paid     = floatval($_POST['paid_amount'] ?? 0);
    $balance  = floatval($_POST['balance'] ?? 0);

    $method   = $_POST['payment_method'] ?? '';
    $customer = $_POST['customer_id'] ?? 0;
    $cphone   = $_POST['phone'] ?? '';
    $new      = $_POST['new_customer'] ?? '';
$prices = $_POST['price'];

    if(empty($products)){
        die("❌ Missing sales data");
    }

    mysqli_begin_transaction($conn);

    try {

        // 👤 Create customer if credit
        if($method == 'credit' && !empty($new)){
            $name  = mysqli_real_escape_string($conn,$new);
            $phone = mysqli_real_escape_string($conn,$cphone);

            mysqli_query($conn,"INSERT INTO customers(name,phone) VALUES('$name','$phone')");
            $customer = mysqli_insert_id($conn);
        }

        // 🧾 Summary
        $codes = [];
        $names = [];

        foreach($products as $pid){
            $pid = mysqli_real_escape_string($conn,$pid);

            $res = mysqli_query($conn,"SELECT pname FROM products WHERE productid='$pid'");
            $row = mysqli_fetch_assoc($res);

            $codes[] = $pid;
            $names[] = $row['pname'];
        }

        $codes_str = implode(",", $codes);
        $names_str = implode(",", $names);

        // 💾 SAVE SALE
        mysqli_query($conn,"
            INSERT INTO sales(payment_method,customer_id,total,paid,balance,product_codes,product_names)
            VALUES('$method','$customer','$total','$paid','$balance','$codes_str','$names_str')
        ");

        $sale_id = mysqli_insert_id($conn);

        // 📦 PROCESS ITEMS
       foreach($products as $i => $pid){

    $pid        = mysqli_real_escape_string($conn,$pid);
    $qty_input  = floatval($qtys[$i]);
    $unit_qty   = floatval($unit_qtys[$i]);
    $price      = floatval($prices[$i]); // ✅ use posted price ONLY

    // 🔍 GET PRODUCT
    $res = mysqli_query($conn,"
        SELECT pname,totalstock,qtyperunit
        FROM products
        WHERE productid='$pid'
    ");

    $row = mysqli_fetch_assoc($res);

    if(!$row){
        throw new Exception("Product not found");
    }

    $pname   = $row['pname'];
    $stock   = floatval($row['totalstock']);   // stored in pieces
    $perunit = floatval($row['qtyperunit']);

    // 🔥 FINAL CONVERSION (ONLY ONCE)
    $qty_pieces = $qty_input * $unit_qty;

    // ❌ Prevent oversell
    if($qty_pieces > $stock){
        throw new Exception("Insufficient stock for $pname");
    }

    // 🧮 CALCULATIONS
    $new_stock = $stock - $qty_pieces;
    $new_qty   = ($perunit > 0) ? floor($new_stock / $perunit) : 0;

    $subtotal = $qty_input * $price;

    // 💾 SAVE ITEM (NOW UNIT-AWARE)
    mysqli_query($conn,"
        INSERT INTO sales_items
        (sale_id, product_id, pname, qty, price, subtotal, unit_qty)
        VALUES
        ('$sale_id','$pid','$pname','$qty_input','$total','$subtotal','$unit_qty')
    ");

    // 🔄 UPDATE STOCK (ALWAYS IN PIECES)
    mysqli_query($conn,"
        UPDATE products 
        SET totalstock='$new_stock', qty='$new_qty'
        WHERE productid='$pid'
    ");
}

        // 💳 CREDIT
        if($method == 'credit' && $customer){
            mysqli_query($conn,"
                UPDATE customers 
                SET balance = balance + '$total'
                WHERE id='$customer'
            ");
        }

        mysqli_commit($conn);

//         echo "<script>
// let w = window.open('receipt_thermal.php?sale_id=$sale_id', '_blank', 'width=300,height=600');
// w.onload = function(){
//     w.print();
// };
// window.location.href='pos.php';
// </script>";
// exit;
//header("Location: receipt_thermal.php?sale_id=$sale_id");
           $msg="Sales transaction ended successfully.";
        header('refresh:2; url=pos.php');

    } catch(Exception $e){

        mysqli_rollback($conn);

        $errmsg="Sales transaction failed.";
    }

} else {
    $warnmsg="Missing sales data.";
    
}


?>

<!DOCTYPE html>
<html>


<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>
    /* body {
        font-family: Arial;
        padding: 20px;
    } */

    .search-box {
        width: 100%;
        padding: 8px;
    }

    .results {
        border: 1px solid #ccc;
        max-width: 100%;
    }

    .results div {
        padding: 8px;
        cursor: pointer;
    }

    .results div:hover {
        background: #f0f0f0;
    }

    table {
        margin-top: 20px;
        border-collapse: collapse;
        width: 100%;
    }

    table,
    th,
    td {
        border: 1px solid #ddd;
    }

    th,
    td {
        padding: 10px;
        text-align: left;
    }

    .remove {
        color: red;
        cursor: pointer;
    }

    /* Chrome, Safari, Edge */
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }



    /* 🔢 NUMPAD FULL COL-4 MODE */
    #numpad {
        width: 100%;
        background: #222;
        padding: 12px;
        border-radius: 10px;
        display: none;
    }

    .keypad button {
        font-size: 40px !important;
        padding: 5px;
        font-weight: bold;
    }

    /* GRID fills full width */
    .numpad-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        /* was 10px */
    }

    /* BIG buttons */
    #numpad button {
        width: 100%;
        height: 75px;
        /* 🔥 controls size */
        font-size: 50px;
        /* 🔥 big numbers */
        border: none;
        background: #333;
        color: white;
        border-radius: 10px;
        font-weight: bold;
    }

    /* Wide buttons (bottom row) */
    .numpad-wide {
        width: 100%;
        margin-top: 10px;
        height: 65px;
        font-size: 60px;
        font-weight: bold;
    }

    #numpad button {
        height: 90px;
        font-size: 30px;
    }

    /* Hover */
    #numpad button:hover {
        background: #666;
    }

    #numpad {
        width: 100%;
        max-width: 100%;
    }

    #numpad {
        margin-top: 15px;
    }


    /* bottom row container */
    .numpad-bottom {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    /* backspace takes most space */
    .btn-backspace {
        flex: 3;
        height: 70px;
        font-size: 24px;
        font-weight: bold;
        background: #444;
        color: white;
        border: none;
        border-radius: 10px;
    }

    /* small red close button */
    .btn-close {
        flex: 1;
        height: 70px;
        font-size: 22px;
        font-weight: bold;
        background: #d9534f;
        /* red */
        color: white;
        border: none;
        border-radius: 10px;
    }

    /* press effect */
    .btn-backspace:active,
    .btn-close:active {
        transform: scale(0.95);
    }


    /* 🔥 FULLSCREEN MODE */
    .pos-mode #wrapper,
    .pos-mode .content-page,
    .pos-mode .content,
    .pos-mode .page-content-wrapper,
    .pos-mode .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 5px !important;
    }

    /* ❌ Hide distractions */
    .pos-mode .left,
    .pos-mode .sidebar,
    .pos-mode .topbar,
    .pos-mode .footer {
        display: none !important;
    }

    /* 📱 Bigger inputs */
    .pos-mode input,
    .pos-mode select {
        font-size: 22px !important;
        height: 60px !important;
    }

    /* 🔘 Bigger buttons */
    .pos-mode button {
        font-size: 20px !important;
        padding: 15px !important;
    }

    /* 🧾 Table scaling */
    .pos-mode table {
        font-size: 18px !important;
    }

    /* 💰 Total highlight */
    .pos-mode #total {
        font-size: 40px !important;
        font-weight: bold;
        color: green;
    }

    /* 💳 Paid input huge */
    .pos-mode #paid {
        font-size: 50px !important;
        height: 80px !important;
    }


    .pos-mode #wrapper,
    .pos-mode .content-page,
    .pos-mode .content,
    .pos-mode .page-content-wrapper,
    .pos-mode .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 5px !important;
    }

    .pos-mode .left,
    .pos-mode .sidebar,
    .pos-mode .topbar,
    .pos-mode .footer {
        display: none !important;
    }

    .pos-mode input,
    .pos-mode select {
        font-size: 22px !important;
        height: 60px !important;
    }

    .pos-mode button {
        font-size: 20px !important;
        padding: 15px !important;
    }

    .pos-mode table {
        font-size: 18px !important;
    }

    .pos-mode #total {
        font-size: 40px !important;
        font-weight: bold;
        color: green;
    }

    .pos-mode #paid {
        font-size: 50px !important;
        height: 80px !important;
    }

    .checkout-btn {
        height: 50px;
        font-size: 28px;
        font-weight: bold;
        border-radius: 10px;
    }

    .checkout-btn:active {
        transform: scale(0.98);
    }

    #productTable tbody {
        font-size: 18px;
    }

    #productTable td,
    #productTable th {
        padding: 12px;
    }

    .pos-mode body,
    .pos-mode {
        font-size: 13px;
    }

    /* 🔲 Reduce spacing everywhere */
    .pos-mode .card-body {
        padding: 8px !important;
    }

    /* 📦 Inputs smaller but still touch-friendly */
    .pos-mode input,
    .pos-mode select {
        font-size: 16px !important;
        height: 38px !important;
        padding: 5px 8px !important;
    }

    /* 🔘 Buttons compact */
    .pos-mode button,
    .pos-mode .btn {
        font-size: 14px !important;
        padding: 6px 10px !important;
    }

    /* 🧾 TABLE COMPACT MODE */
    .pos-mode table {
        font-size: 13px !important;
    }

    .pos-mode #productTable th,
    .pos-mode #productTable td {
        padding: 6px 8px !important;
    }

    /* 🧾 Scroll area tighter */
    .pos-mode div[style*="overflow-y: auto"] {
        max-height: 280px !important;
    }

    /* 💰 TOTAL AREA smaller */
    .pos-mode #total {
        font-size: 24px !important;
    }

    .pos-mode #balanceLabel {
        font-size: 18px !important;
    }

    /* 💳 Paid input still visible but smaller */
    .pos-mode #paid {
        font-size: 26px !important;
        height: 45px !important;
    }

    /* 🔍 Search box compact */
    .pos-mode .search-box {
        font-size: 14px !important;
        height: 35px !important;
    }

    /* 📊 Remove extra spacing */
    .pos-mode br {
        display: none;
    }


    /* Qty input: enough room for at least 3 digits */
    #productTable .qty {
        width: 85px !important;
        min-width: 85px !important;
        max-width: 85px !important;
        text-align: center;
        padding-left: 6px !important;
        padding-right: 6px !important;
    }

    #productTable td:has(.qty) {
        min-width: 100px;
    }

    @media (max-width: 575.98px) {
        #productTable .qty {
            width: 85px !important;
            min-width: 85px !important;
            max-width: 85px !important;
        }
    }


    .sales-history-frame {
        width: 100%;
        overflow: hidden;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
    }

    .sales-history-frame iframe {
        display: block;
        width: 100%;
        border: 0;
    }

    @media (max-width: 991.98px) {
        .sales-history-frame {
            overflow-x: auto;
        }

        .sales-history-frame iframe {
            min-width: 520px;
        }
    }

    /* =========================================================
       RESPONSIVE POS LAYOUT
       Desktop: Product/transaction area left, search right.
       Tablet/Phone: Product Search first, transaction area below.
       ========================================================= */

    .pos-layout {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
    }

    .pos-main-panel {
        order: 1;
        min-width: 0;
    }

    .pos-search-panel {
        order: 2;
        min-width: 0;
    }

    .pos-table-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
    }

    .pos-table-scroll #productTable {
        width: 100%;
        min-width: 0;
        margin-bottom: 0;
    }

    .pos-table-scroll #productTable th,
    .pos-table-scroll #productTable td {
        vertical-align: middle;
    }

    /* Distinct GH¢ payment field */
    .payment-field {
        display: flex;
        width: 100%;
        min-height: 82px;
        border: 3px solid #198754;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 3px 10px rgba(0,0,0,.08);
    }

    .payment-currency {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 86px;
        padding: 0 14px;
        background: #198754;
        color: #fff;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: .3px;
    }

    .payment-field #paid {
        flex: 1;
        width: 100%;
        min-width: 0;
        height: 76px !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        outline: none;
        padding: 8px 16px !important;
        font-size: 38px !important;
        font-weight: 700;
        text-align: right;
    }

    .payment-field #paid:focus {
        box-shadow: inset 0 0 0 2px rgba(25,135,84,.18) !important;
    }

    /* Tablet and phone: stack Search FIRST */
    @media (max-width: 991.98px) {
        .pos-layout {
            display: flex;
            flex-direction: column;
        }

        .pos-search-panel,
        .pos-main-panel {
            width: 100%;
            max-width: 100%;
            flex: 0 0 100%;
        }

        .pos-search-panel {
            order: 1;
        }

        .pos-main-panel {
            order: 2;
        }

        .pos-search-panel .card,
        .pos-main-panel .card {
            width: 100%;
        }

        .pos-search-panel .search-box {
            height: 52px !important;
            font-size: 18px !important;
        }

        .pos-table-scroll #productTable {
            min-width: 0 !important;
        }

        .pos-table-scroll {
            overflow-x: hidden !important;
        }

        .payment-field {
            min-height: 72px;
        }

        .payment-currency {
            min-width: 78px;
            font-size: 25px;
        }

        .payment-field #paid {
            height: 66px !important;
            font-size: 32px !important;
        }

        /* The custom keypad must never appear on tablet/phone */
        #numpad {
            display: none !important;
        }
    }

    /* Phone-specific sizing */
    @media (max-width: 575.98px) {
        .pos-layout {
            margin-left: -5px;
            margin-right: -5px;
        }

        .pos-main-panel,
        .pos-search-panel {
            padding-left: 5px;
            padding-right: 5px;
        }

        .pos-search-panel .card-body,
        .pos-main-panel .card-body {
            padding: 10px !important;
        }

        .pos-search-panel h4 {
            font-size: 18px;
        }

        .pos-table-scroll #productTable {
            min-width: 0 !important;
        }

        .pos-table-scroll #productTable th,
        .pos-table-scroll #productTable td {
            padding: 8px !important;
            font-size: 14px !important;
        }

        .payment-field {
            min-height: 68px;
            border-width: 2px;
        }

        .payment-currency {
            min-width: 70px;
            padding: 0 10px;
            font-size: 22px;
        }

        .payment-field #paid {
            height: 64px !important;
            font-size: 28px !important;
            padding: 6px 10px !important;
        }

        .checkout-btn {
            min-height: 56px;
            font-size: 21px !important;
        }

        /* Prevent the custom keypad from being displayed by JS */
        body #numpad {
            display: none !important;
            visibility: hidden !important;
        }
    }


    /* =========================================================
       FULL POS PRODUCT TABLE
       Desktop: all six columns remain visible.
       Mobile: all six columns remain visible inside a touch-friendly
       horizontal scroll area. Nothing is hidden or stacked.
       ========================================================= */

    #productTable {
        width: 100%;
        min-width: 760px !important;
        margin-bottom: 0;
        table-layout: fixed;
        border-collapse: collapse;
    }

    #productTable th,
    #productTable td {
        box-sizing: border-box;
        vertical-align: middle;
    }

    /* Column widths */
    #productTable th:nth-child(1),
    #productTable td:nth-child(1) {
        width: 28%;
    }

    #productTable th:nth-child(2),
    #productTable td:nth-child(2) {
        width: 20%;
    }

    #productTable th:nth-child(3),
    #productTable td:nth-child(3) {
        width: 14%;
    }

    #productTable th:nth-child(4),
    #productTable td:nth-child(4) {
        width: 12%;
    }

    #productTable th:nth-child(5),
    #productTable td:nth-child(5) {
        width: 16%;
    }

    #productTable th:nth-child(6),
    #productTable td:nth-child(6) {
        width: 10%;
    }

    .cart-product-cell {
        white-space: normal !important;
        overflow-wrap: anywhere;
    }

    .cart-product-name {
        display: block;
        font-weight: 600;
        line-height: 1.25;
    }

    .cart-unit-cell {
        white-space: normal !important;
    }

    .cart-unit-cell .unit-select {
        width: 100%;
        min-width: 0;
        height: 36px;
        padding: 4px 7px;
        box-sizing: border-box;
    }

    .cart-price-cell,
    .cart-subtotal-cell {
        white-space: nowrap !important;
        font-weight: 600;
    }

    .cart-qty-cell {
        text-align: center;
    }

    #productTable .qty {
        width: 78px !important;
        min-width: 78px !important;
        max-width: 78px !important;
        height: 36px;
        text-align: center;
        padding: 4px 6px !important;
        box-sizing: border-box;
    }

    .cart-action-cell {
        text-align: center !important;
        white-space: nowrap !important;
    }

    .cart-action-cell .remove {
        display: inline-block;
        padding: 6px 8px;
        background: #dc3545;
        color: #fff;
        border-radius: 4px;
        font-size: 11px;
        line-height: 1.15;
        cursor: pointer;
        white-space: nowrap;
    }

    .cart-action-cell .remove:hover {
        background: #c82333;
    }

    /* Make the cart itself the mobile scroll area. */
    .pos-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: thin;
    }

    .pos-table-scroll #productTable {
        width: 760px;
        min-width: 760px !important;
    }

    @media (max-width: 991.98px) {
        .pos-table-scroll {
            overflow-x: auto !important;
        }

        .pos-table-scroll #productTable {
            width: 760px;
            min-width: 760px !important;
        }

        #productTable th,
        #productTable td {
            padding: 6px !important;
            font-size: 12px !important;
        }

        .cart-unit-cell .unit-select,
        #productTable .qty {
            height: 34px;
            font-size: 12px !important;
        }

        #productTable .qty {
            width: 74px !important;
            min-width: 74px !important;
            max-width: 74px !important;
        }

        .cart-action-cell .remove {
            font-size: 10px;
            padding: 6px 7px;
        }
    }

    @media (max-width: 575.98px) {
        /* Keep all six columns. The user can swipe left/right. */
        .pos-table-scroll {
            margin: 0;
            padding: 0;
            overflow-x: auto !important;
            overflow-y: auto;
            border-radius: 5px;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-x pan-y;
        }

        .pos-table-scroll #productTable {
            width: 760px !important;
            min-width: 760px !important;
            table-layout: fixed !important;
        }

        #productTable th,
        #productTable td {
            padding: 6px !important;
            font-size: 11px !important;
            box-sizing: border-box;
        }

        .cart-product-name {
            font-size: 12px;
            line-height: 1.2;
        }

        .cart-unit-cell .unit-select {
            width: 100% !important;
            height: 32px !important;
            font-size: 11px !important;
            padding: 3px 5px !important;
        }

        #productTable .qty {
            width: 70px !important;
            min-width: 70px !important;
            max-width: 70px !important;
            height: 32px !important;
            font-size: 11px !important;
        }

        .cart-price-cell,
        .cart-subtotal-cell {
            font-size: 11px !important;
        }

        .cart-action-cell .remove {
            font-size: 9px;
            padding: 5px 6px;
        }
    }

    @media (max-width: 360px) {
        .pos-table-scroll #productTable {
            width: 720px !important;
            min-width: 720px !important;
        }

        #productTable th,
        #productTable td {
            padding: 5px !important;
            font-size: 10px !important;
        }

        .cart-product-name {
            font-size: 11px;
        }

        .cart-unit-cell .unit-select {
            height: 30px !important;
            font-size: 10px !important;
        }

        #productTable .qty {
            width: 66px !important;
            min-width: 66px !important;
            max-width: 66px !important;
            height: 30px !important;
            font-size: 10px !important;
        }

        .cart-action-cell .remove {
            font-size: 8px;
            padding: 5px 5px;
        }
    }

    /* =========================================================
       BARCODE SEARCH / SCANNER
       ========================================================= */

    .barcode-scan-btn {
        min-width: 125px;
        white-space: nowrap;
    }

    .barcode-search-row {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }

    .barcode-search-row .search-box {
        flex: 1;
        min-width: 0;
    }

    .barcode-feedback {
        display: none;
        margin-top: 8px;
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
    }

    .barcode-feedback.error {
        display: block;
        color: #842029;
        background: #f8d7da;
        border: 1px solid #f5c2c7;
    }

    .barcode-feedback.success {
        display: block;
        color: #0f5132;
        background: #d1e7dd;
        border: 1px solid #badbcc;
    }

    @media (max-width: 575.98px) {
        .barcode-search-row {
            flex-direction: column;
        }

        .barcode-scan-btn {
            width: 100%;
            min-height: 48px;
        }
    }

    
    /* =========================================================
       REMOTE MOBILE SCANNER
       ========================================================= */

    .mobile-scanner-destination-row {
        display: flex;
        gap: 8px;
        align-items: stretch;
        margin-bottom: 12px;
    }

    .mobile-scanner-destination-row input {
        flex: 1;
        min-width: 0;
    }

    .mobile-scanner-save-btn {
        min-width: 82px;
        white-space: nowrap;
    }

    .mobile-scanner-save-note {
        margin-top: -7px;
        margin-bottom: 12px;
        font-size: 11px;
        color: #6c757d;
    }

    .mobile-scanner-auto-note {
        margin-bottom: 12px;
        padding: 9px 10px;
        border-radius: 6px;
        background: #f8fbff;
        border: 1px solid #d9e8ff;
        color: #495057;
        font-size: 12px;
        line-height: 1.45;
    }

    .mobile-open-scanner-btn {
        display: none;
    }

    .mobile-scanner-desktop-btn {
        display: inline-flex;
    }

    html,
    body {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }

    .pos-layout,
    .pos-main-panel,
    .pos-search-panel,
    .pos-main-panel .card,
    .pos-search-panel .card,
    .pos-main-panel .card-body,
    .pos-search-panel .card-body {
        min-width: 0;
    }

    .mobile-scanner-btn {
        margin-left: 5px;
    }

    .mobile-scanner-connection-box {
        margin-top: 10px;
        border: 1px solid #cfd7e0;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(0,0,0,.08);
        overflow: hidden;
    }

    .mobile-scanner-connection-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        background: #f5f7fa;
        border-bottom: 1px solid #e1e5ea;
    }

    .mobile-scanner-close {
        border: 0;
        background: #dc3545;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: 5px;
        font-size: 20px;
        line-height: 1;
        cursor: pointer;
    }

    .mobile-scanner-connection-body {
        padding: 12px;
    }

    .mobile-scanner-status-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }

    .mobile-scanner-status-row strong {
        color: #856404;
    }

    .mobile-scanner-label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .mobile-scanner-url-row {
        display: flex;
        gap: 6px;
        margin-bottom: 12px;
    }

    .mobile-scanner-url-row input {
        min-width: 0;
        font-size: 13px;
    }

    .mobile-scanner-code-box {
        border: 2px dashed #0d6efd;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
        margin: 10px 0;
        background: #f8fbff;
    }

    .mobile-scanner-code-label {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 2px;
    }

    #mobileScannerCode {
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 6px;
        line-height: 1.1;
    }

    .mobile-scanner-help {
        line-height: 1.45;
        margin-bottom: 12px;
    }

    .mobile-scanner-connection-actions {
        display: flex;
        justify-content: flex-end;
    }

    @media (max-width: 991.98px) {
        .mobile-scanner-desktop-btn {
            display: none !important;
        }

        .mobile-open-scanner-btn {
            display: flex !important;
            width: 100%;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            margin-top: 5px;
            white-space: nowrap;
        }

        .mobile-scanner-destination-row,
        .mobile-scanner-url-row {
            flex-direction: column;
        }

        .mobile-scanner-destination-row .btn,
        .mobile-scanner-url-row .btn {
            width: 100%;
        }

        .mobile-scanner-destination-row input,
        .mobile-scanner-url-row input {
            width: 100%;
        }

        #mobileScannerCode {
            font-size: 26px;
        }

        .pos-main-panel,
        .pos-search-panel {
            padding-left: 6px;
            padding-right: 6px;
        }

        .pos-search-panel .card-body,
        .pos-main-panel .card-body {
            padding: 10px !important;
        }

        .card-header {
            padding: 10px !important;
        }

        .card-header h2 {
            font-size: 22px;
            margin-bottom: 0;
        }

        .checkout-btn {
            width: 100%;
        }
    }

    @media (max-width: 575.98px) {
        .mobile-scanner-connection-header {
            padding: 9px 10px;
        }

        .mobile-scanner-connection-body {
            padding: 10px;
        }

        .mobile-scanner-status-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 3px;
        }

        .mobile-scanner-destination-row {
            gap: 6px;
        }

        .mobile-scanner-save-btn {
            min-height: 44px;
        }

        .mobile-scanner-url-row {
            gap: 6px;
        }

        .pos-search-panel h4 {
            font-size: 18px;
        }

        .barcode-search-row {
            gap: 6px;
        }

        .barcode-search-row .search-box {
            min-height: 48px;
            font-size: 16px !important;
        }

        .barcode-scan-btn,
        .mobile-open-scanner-btn {
            min-height: 48px;
            font-size: 15px !important;
        }

        .payment-field {
            min-height: 64px;
        }

        .payment-currency {
            min-width: 66px;
            font-size: 21px;
        }

        .payment-field #paid {
            height: 60px !important;
            font-size: 27px !important;
        }

        .sales-history-section {
            margin-top: 12px;
            padding: 0 5px;
        }

        .sales-history-toggle {
            min-height: 46px;
            font-size: 15px;
        }
    }

    /* =========================================================
       SALES HISTORY - HIDDEN UNTIL REQUESTED
       ========================================================= */

    .sales-history-section {
        width: 100%;
        margin-top: 20px;
    }

    .sales-history-toggle {
        width: 100%;
        text-align: left;
    }

    .sales-history-panel {
        display: none !important;
        width: 100%;
        margin-top: 12px;
    }

    .sales-history-panel.show {
        display: block !important;
    }

    .sales-history-filter {
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
    }

    .sales-history-filter label {
        font-weight: 600;
    }

    .sales-history-frame {
        width: 100%;
        overflow: hidden;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
    }

    .sales-history-frame iframe {
        display: block;
        width: 100%;
        min-height: 500px;
        border: 0;
    }

    @media (max-width: 767.98px) {
        .sales-history-filter .row > div {
            margin-bottom: 10px;
        }

        .sales-history-frame {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sales-history-frame iframe {
            min-width: 850px;
        }
    }

</style>
</head>

<body class="fixed-left">
    <!-- Loader -->
    <!-- <div id="preloader">
        <div id="status">
            <div class="spinner"></div>
        </div>
    </div>Begin page -->
    <div id="wrapper">
        <!-- ========== Left Sidebar Start ========== -->
        <?php include "assets/sections/leftside.php" ?>
        <!-- Left Sidebar End -->
        <!-- Start right Content here -->
        <div class="content-page">
            <!-- Start content -->
            <div class="content">
                <!-- Top Bar Start -->
                <?php include "assets/sections/topbar.php" ?>
                <!-- Top Bar End -->
                <div class="page-content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-sm-12">
                                <!-- <div class="page-title-box">
                                    <h4 class="page-title">Datatable</h4>
                                </div> -->
                                <br>
                            </div>
                        </div><!-- end page title end breadcrumb -->
                        <div class="row pos-layout">
                            <div class="col-7 pos-main-panel">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h2><strong>Sales Point</strong></h2>



                                            <button onclick="exitPOS()" class="btn btn-sm btn-danger">
                                                Exit
                                            </button>
                                        </div>
                                        <?php if(isset($msg)){ ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert"
                                                aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                            <?php echo $msg ?>
                                        </div>
                                        <?php } ?>
                                        <?php if(isset($errmsg)){ ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert"
                                                aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                            <?php echo $errmsg ?>
                                        </div>
                                        <?php } ?>
                                        <form method="POST" action=""
                                            onsubmit="updateHiddenFields(); return validatePayment();">
                                            <div class="card-body">
                                                <div class="small text-muted d-md-none mb-1">
                                                    All cart columns remain available on mobile. Swipe the table left or right to view them.
                                                </div>
                                                <div class="pos-table-scroll" style="height: 350px; overflow-y: auto; ">
                                                    <table id="productTable" class="table table-bordered"
                                                        style="margin-bottom:0;">
                                                        <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
                                                            <tr>
                                                                <th>Product</th>
                                                                <th>Sale Type</th>
                                                                <th>Price (GHc)</th>
                                                                <th>Qty</th>
                                                                <th>Subtotal</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                                <br>
                                                <h3><strong>Total: GH¢ <span id="total">0.00</span></strong></h3>

                                                <br>


                                                    <label><h4><b>Payment Method:</b></h4></label><br>
                                                    <select class="form-control" name="payment_method" id="payment_method"
                                                        onchange="toggleCredit()">
                                                        <option value="cash">Cash</option>
                                                        <option value="momo">MoMo</option>
                                                        <option value="credit">Credit</option>
                                                    </select>

                                                    <br>

                                                    <label>
                                                        <h4><b>Amount Paid:</b></h4>
                                                    </label>
                                                    <div class="payment-field">
                                                        <div class="payment-currency">GH¢</div>
                                                        <input
                                                            class="form-control"
                                                            type="number"
                                                            id="paid"
                                                            step="0.01"
                                                            min="0"
                                                            inputmode="decimal"
                                                            autocomplete="off"
                                                            placeholder="0.00"
                                                            oninput="calculateBalance()">
                                                    </div>

                                                    <input type="hidden" name="total" id="total_input">
                                                    <input type="hidden" name="paid_amount" id="paid_input">
                                                    <input type="hidden" name="balance" id="balance_input">

                                                    <br>
                                                    <h3 id="balanceLabel">
                                                        <strong>Balance: GH¢ <span id="balance">0.00</span></strong>
                                                    </h3>


                                                <div id="creditBox" style="display:none;">
                                                    <label>Select Customer:</label><br>
                                                    <select name="customer_id" class="form-control">
                                                        <option value="">Select Customer</option>
                                                        <?php
                                                        $res=mysqli_query($conn,"SELECT * FROM customers");
                                                        while($c=mysqli_fetch_assoc($res)){
                                                        echo "<option value='{$c['id']}'>{$c['name']} ({$c['balance']})</option>";
                                                        }
                                                        ?>
                                                    </select>

                                                    <br><br>
                                                    <label>Or Add New Customer:</label><br>
                                                    <input class="form-control" type="text" name="new_customer"
                                                        placeholder="Customer name">
                                                    <input class="form-control" type="text" name="phone"
                                                        placeholder="Phone">
                                                </div>

                                                <br><br>
                                                <br>
                                                <hr>
                                                <button type="submit"
                                                    class="btn btn-lg btn-success form-control checkout-btn">
                                                    Checkout
                                                </button>
                                            </div>
                                        </form>

                                        <script>
                                        document.addEventListener("click", function(e) {

                                            let item = e.target.closest(".search-item");
                                            if (!item) return;

                                            // 🚫 prevent click if out of stock
                                            if (item.style.cursor === "not-allowed") return;

                                            let id = item.dataset.id;
                                            let name = item.dataset.name;
                                            let price = item.dataset.price;
                                            let bulk = item.dataset.bulk;

                                            addProduct(id, name, price, bulk);
                                        });

                                        // ➕ ADD PRODUCT
                                        function addProduct(id, name, price, bulkprice) {

                                            console.log("ADDING:", id, name); // debug

                                            let table = document.querySelector('#productTable tbody');

                                            let existingRow = document.querySelector(`tr[data-id='${id}']`);

                                            if (existingRow) {
                                                let qtyInput = existingRow.querySelector('.qty');
                                                qtyInput.value = parseFloat(qtyInput.value) + 1;
                                                updateTotal();
                                                return;
                                            }

                                            let row = table.insertRow();
                                            row.setAttribute('data-id', id);

                                            row.innerHTML = `
                                                <td class="cart-product-cell">
                                                    <span class="cart-product-name">${name}</span>
                                                    <input type="hidden"
                                                        name="products[]"
                                                        value="${id}">
                                                    <input type="hidden"
                                                        name="price[]"
                                                        class="price-input"
                                                        value="${price}">
                                                    <input type="hidden"
                                                        name="unit_qty[]"
                                                        class="unit-qty"
                                                        value="1">
                                                </td>

                                                <td class="cart-unit-cell">
                                                    <select class="form-control unit-select"
                                                        onchange="updateRow(this)">
                                                        <option value="1" data-price="${price}">
                                                            Loading...
                                                        </option>
                                                    </select>
                                                </td>

                                                <td class="price cart-price-cell">
                                                    ${price}
                                                </td>

                                                <td class="cart-qty-cell">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value="1"
                                                        min="0.01"
                                                        class="qty"
                                                        name="qty[]"
                                                        inputmode="decimal"
                                                        autocomplete="off"
                                                        oninput="updateTotal()">
                                                </td>

                                                <td class="subtotal cart-subtotal-cell">
                                                    0.00
                                                </td>

                                                <td class="cart-action-cell">
                                                    <span
                                                        class="remove"
                                                        onclick="removeRow(this)">
                                                        Remove
                                                    </span>
                                                </td>
                                            `;

                                            loadUnits(id, row, price, bulkprice); // 🔥 dynamic units

                                            document.getElementById('results').innerHTML = '';
                                            document.getElementById('search').value = '';

                                            updateTotal();
                                        }



                                        function loadUnits(productId, row, defaultPrice, bulkPrice) {
                                            console.log("Fetching units for ID:", productId); // 🔥 DEBUG
                                            fetch('assets/scripts/get_units.php?product_id=' + encodeURIComponent(
                                                    productId))
                                                .then(res => res.json())
                                                .then(units => {

                                                    let select = row.querySelector('.unit-select');
                                                    select.innerHTML = "";

                                                    // fallback default
                                                    if (!units || units.length === 0) {
                                                        select.innerHTML = `
                                                        <option value="1" data-price="${defaultPrice}">Piece</option>
                                                    `;
                                                    } else {

                                                        units.forEach(u => {
                                                            let option = document.createElement("option");
                                                            option.value = u.unit_qty;
                                                            option.setAttribute("data-price", u.price);
                                                            option.text = u.unit_name;
                                                            select.appendChild(option);
                                                        });
                                                    }

                                                    // set first selected values
                                                    let first = select.options[0];

                                                    row.querySelector('.price').innerText =
                                                        first.getAttribute("data-price");

                                                    row.querySelector('.price-input').value =
                                                        first.getAttribute("data-price");

                                                    row.querySelector('.unit-qty').value =
                                                        first.value;

                                                    updateTotal();
                                                })
                                                .catch(err => {
                                                    console.error("UNIT LOAD ERROR:", err);
                                                });
                                        }


                                        // ➖ REMOVE
                                        function removeRow(el) {
                                            el.closest('tr').remove();
                                            updateTotal();
                                        }

                                        // 🧮 TOTAL + SUBTOTALS
                                        function updateTotal() {
                                            let total = 0;

                                            document.querySelectorAll('#productTable tbody tr').forEach(row => {
                                                let price = parseFloat(row.querySelector('.price-input')
                                                    .value) || 0;
                                                let qty = Math.max(0.01, parseFloat(row.querySelector('.qty')
                                                    .value) || 0);

                                                let subtotal = price * qty;
                                                row.querySelector('.subtotal').innerText = subtotal.toFixed(2);

                                                total += subtotal;
                                            });

                                            document.getElementById('total').innerText = total.toFixed(2);


                                            calculateBalance();
                                        }

                                        //UPDATE ROWS
                                        function updateRow(select) {

                                            let row = select.closest('tr');
                                            let option = select.options[select.selectedIndex];

                                            let price = option.getAttribute("data-price");
                                            let unitQty = option.value;

                                            row.querySelector('.price').innerText = price;
                                            row.querySelector('.price-input').value = price;
                                            row.querySelector('.unit-qty').value = unitQty;

                                            updateTotal();
                                        }

                                        // 💰 BALANCE
                                        function calculateBalance() {
                                            let total = parseFloat(document.getElementById('total').innerText) || 0;
                                            let paid = parseFloat(document.getElementById('paid').value) || 0;

                                            let balance = paid - total;

                                            let label = document.getElementById('balanceLabel');

                                            if (balance < 0) {
                                                label.innerHTML =
                                                    `Amount Due: GH¢ <span id="balance">${Math.abs(balance).toFixed(2)}</span>`;
                                                document.getElementById('balance').style.color = 'red';
                                            } else {
                                                label.innerHTML =
                                                    `Change: GH¢ <span id="balance">${balance.toFixed(2)}</span>`;
                                                document.getElementById('balance').style.color = 'green';
                                            }
                                        }

                                        // 💳 PAYMENT TYPE
                                        function toggleCredit() {
                                            let method = document.getElementById('payment_method').value;
                                            document.getElementById('creditBox').style.display = (method === 'credit') ?
                                                'block' : 'none';
                                        }


                                        //UPDATE HIDDEN FIELDS
                                        function updateHiddenFields() {
                                            document.getElementById('total_input').value =
                                                document.getElementById('total').innerText;

                                            document.getElementById('paid_input').value =
                                                document.getElementById('paid').value;

                                            document.getElementById('balance_input').value =
                                                document.getElementById('balance').innerText;
                                        }
                                                                                            // ✅ VALIDATION
                                                                                        function validatePayment() {
                                                        let method = document.getElementById('payment_method').value;
                                                        let paidInput = document.getElementById('paid').value.trim();
                                                        let balance = parseFloat(document.getElementById('balance').innerText) || 0;

                                                        // ❌ Require amount paid for cash/momo
                                                        if (method !== 'credit' && paidInput === "") {
                                                            alert("Please enter amount paid!");
                                                            document.getElementById('paid').focus();
                                                            return false;
                                                        }

                                                        // ❌ Prevent insufficient payment for non-credit
                                                        if (method !== 'credit' && balance < 0) {
                                                            alert("Insufficient payment!");
                                                            return false;
                                                        }

                                                        // ✅ Credit requires customer
                                                        if (method === 'credit') {
                                                            let customer = document.querySelector('[name="customer_id"]').value;
                                                            let newCustomer = document.querySelector('[name="new_customer"]').value.trim();

                                                            if (!customer && !newCustomer) {
                                                                alert("Select or add a customer!");
                                                                return false;
                                                            }
                                                        }

                                                        return true;
                                                    }



                                        let activeInput = null;

                                        // 👆 OPEN CUSTOM NUMPAD ONLY ON DESKTOP
                                        // On phones/tablets the custom keypad is completely disabled.
                                        document.addEventListener("click", function(e) {
                                            if (window.matchMedia("(max-width: 991.98px)").matches) {
                                                return;
                                            }

                                            if (e.target.classList.contains("qty") || e.target.id === "paid") {
                                                activeInput = e.target;
                                                showNumpad();
                                            }
                                        });

                                        function showNumpad() {
                                            if (window.matchMedia("(max-width: 991.98px)").matches) {
                                                return;
                                            }

                                            const numpad = document.getElementById("numpad");
                                            if (numpad) {
                                                numpad.style.display = "block";
                                            }
                                        }

                                        function hideNumpad() {
                                            const numpad = document.getElementById("numpad");
                                            if (numpad) {
                                                numpad.style.display = "none";
                                            }
                                        }



                                        // 🔢 PRESS KEY
                                        function pressKey(value) {
                                            if (!activeInput) return;

                                            if (value === '.' && activeInput.value.includes('.')) return;

                                            if (activeInput.value === "0") {
                                                activeInput.value = value;
                                            } else {
                                                activeInput.value += value;
                                            }

                                            if (activeInput.id === "paid") {
                                                calculateBalance();
                                            } else {
                                                updateTotal();
                                            }
                                        }

                                        // ❌ CLEAR
                                        function clearInput() {
                                            if (activeInput) {
                                                activeInput.value = "";
                                                if (activeInput && activeInput.id === "paid") {
                                                    calculateBalance();
                                                } else {
                                                    updateTotal();
                                                };
                                            }
                                        }

                                        // ❎ CLOSE
                                        function closeNumpad() {
                                            hideNumpad();
                                            activeInput = null;
                                        }

                                        function backspace() {
                                            if (!activeInput) return;

                                            activeInput.value = activeInput.value.slice(0, -1);

                                            if (activeInput.id === "paid") {
                                                calculateBalance();
                                            } else {
                                                if (activeInput && activeInput.id === "paid") {
                                                    calculateBalance();
                                                } else {
                                                    updateTotal();
                                                };
                                            }
                                        }


                                        window.addEventListener("resize", function() {
                                            if (window.matchMedia("(max-width: 991.98px)").matches) {
                                                hideNumpad();
                                                activeInput = null;
                                            }
                                        });

                                        function isMobileLayout() {
                                            return window.matchMedia("(max-width: 991.98px)").matches;
                                        }

                                        function togglePOSMode() {

                                            document.body.classList.toggle("pos-mode");

                                            // Fullscreen is useful on desktop POS, but should not be forced on phones/tablets.
                                            if (isMobileLayout()) {
                                                return;
                                            }

                                            if (!document.fullscreenElement) {
                                                document.documentElement.requestFullscreen().catch(err => {
                                                    console.log(err);
                                                });
                                            } else {
                                                document.exitFullscreen();
                                            }
                                        }

                                        window.addEventListener("load", () => {
                                            if (isMobileLayout()) {
                                                // Keep the compact POS styling on mobile without forcing browser fullscreen.
                                                document.body.classList.add("pos-mode");
                                            } else {
                                                togglePOSMode();
                                            }
                                        });

                                        function exitPOSMode() {

                                            // 🧠 Exit fullscreen browser mode
                                            if (document.fullscreenElement) {
                                                document.exitFullscreen().catch(err => {
                                                    console.log(err);
                                                });
                                            }

                                            // 🎯 Remove POS layout class
                                            document.body.classList.remove("pos-mode");
                                        }

                                        function enterPOSMode() {
                                            document.body.classList.add("pos-mode");

                                            // Do not force fullscreen on phones/tablets.
                                            if (isMobileLayout()) {
                                                return;
                                            }

                                            if (!document.fullscreenElement) {
                                                document.documentElement.requestFullscreen().catch(err => console.log(
                                                    err));
                                            }
                                        }

                                        function exitPOSMode() {

                                            if (document.fullscreenElement) {
                                                document.exitFullscreen().catch(err => console.log(err));
                                            }

                                            // logout
                                            window.location.href = "logout.php";
                                        }

                                        let userRole = "<?php echo $role; ?>";

                                        window.addEventListener("load", () => {
                                            if (userRole === "cashier") {
                                                enterPOSMode();
                                            }
                                        });

                                        function exitPOS() {

                                            // Use the same role value loaded at the top of this page.
                                            // Cashiers must be logged out when they exit the POS.
                                            const role = <?php echo json_encode(strtolower(trim($role))); ?>;

                                            if (role === "cashier") {
                                                // 🔴 Cashier → logout completely
                                                window.location.href = "logout.php";
                                                return;
                                            }

                                            // 🟢 Admin/manager/other roles → leave POS only
                                            if (document.fullscreenElement) {
                                                document.exitFullscreen().catch(err => console.log(err));
                                            }

                                            window.location.href = "index.php";
                                        }
                                        </script>

                                    </div>
                                </div>


                            </div>

                            <div class="col-5 pos-search-panel">
                                <div class="card m-b-30">
                                    <div class="card-body">

                                        <h4>Product Search</h4>

                                        <div class="card-header-form">
                                            <div class="barcode-search-row">
                                                <input type="text" id="search" class="search-box form-control"
                                                    placeholder="Search product / scan barcode..."
                                                    autocomplete="off">
                                                <button type="button"
                                                    class="btn btn-success barcode-scan-btn"
                                                    onclick="openPOSBarcodeScanner()">
                                                    📷 Scan Barcode
                                                </button>

                                                <button type="button"
                                                    class="btn btn-primary mobile-scanner-btn mobile-scanner-desktop-btn"
                                                    onclick="openMobileScannerConnection()">
                                                    📱 Connect Mobile Scanner
                                                </button>

                                                <button type="button"
                                                    class="btn btn-primary mobile-scanner-btn mobile-open-scanner-btn"
                                                    onclick="openMobileScannerFromMobileView()">
                                                    📱 Open Mobile Scanner
                                                </button>
                                            </div>

                                            <div id="results" class="results"></div>

                                            <div id="barcodeFeedback"
                                                class="barcode-feedback"
                                                role="alert"
                                                aria-live="assertive"></div>

                                            <!-- =====================================================
                                                 REMOTE MOBILE SCANNER CONNECTION
                                                 ===================================================== -->
                                            <div id="mobileScannerConnectionBox"
                                                class="mobile-scanner-connection-box"
                                                style="display:none;">

                                                <div class="mobile-scanner-connection-header">
                                                    <strong>📱 Mobile Scanner Connection</strong>
                                                    <button type="button"
                                                        class="mobile-scanner-close"
                                                        onclick="closeMobileScannerConnection()">
                                                        &times;
                                                    </button>
                                                </div>

                                                <div class="mobile-scanner-connection-body">

                                                    <div class="mobile-scanner-status-row">
                                                        <span>Status:</span>
                                                        <strong id="mobileScannerStatus">
                                                            Not connected
                                                        </strong>
                                                    </div>

                                                    <label class="mobile-scanner-label">
                                                        Mobile scanner address
                                                    </label>

                                                    <div class="mobile-scanner-url-row">
                                                        <input type="text"
                                                            id="mobileScannerUrl"
                                                            class="form-control"
                                                            readonly>
                                                        <button type="button"
                                                            class="btn btn-secondary"
                                                            onclick="copyMobileScannerUrl()">
                                                            Copy
                                                        </button>
                                                    </div>

                                                    <div class="mobile-scanner-auto-note">
                                                        No pairing code is required. Open the Mobile Scanner on the phone and it will automatically find this active POS connection.
                                                    </div>

                                                    <div class="mobile-scanner-connection-actions">
                                                        <button type="button"
                                                            class="btn btn-danger"
                                                            onclick="stopMobileScannerConnection()">
                                                            Stop Connection
                                                        </button>
                                                    </div>

                                                </div>
                                            </div>

                                            <div><hr></div>
                                           

                                            <div id="numpad">
                                                <div class="numpad-grid">
                                                    <button style="background-color: #ef54d3;""
                                                        onclick=" pressKey('1')">1</button>
                                                    <button style="background-color: #ef54d3;"
                                                        onclick="pressKey('2')">2</button>
                                                    <button style="background-color: #ef54d3;"
                                                        onclick="pressKey('3')">3</button>

                                                    <button style="background-color: #7a0865;"
                                                        onclick="pressKey('4')">4</button>
                                                    <button style="background-color: #7a0865;"
                                                        onclick="pressKey('5')">5</button>
                                                    <button style="background-color: #7a0865;"
                                                        onclick="pressKey('6')">6</button>

                                                    <button style="background-color: #39022f;"
                                                        onclick="pressKey('7')">7</button>
                                                    <button style="background-color: #39022f;"
                                                        onclick="pressKey('8')">8</button>
                                                    <button style="background-color: #39022f;"
                                                        onclick="pressKey('9')">9</button>

                                                    <button style="background-color: #200b1d;"
                                                        onclick="pressKey('.')">.</button>
                                                    <button style="background-color: #200b1d;"
                                                        onclick="pressKey('0')">0</button>
                                                    <button style="background-color: #057b3e;"
                                                        onclick="clearInput()">C</button>
                                                </div>

                                                <div class="numpad-bottom">
                                                    <button class="btn-backspace" onclick="backspace()">⌫</button>
                                                    <button style="background-color: #d9534f;"
                                                        class="btn btn-danger btn-sm btn-close"
                                                        onclick="closeNumpad()">✖</button>
                                                </div>
                                            </div>
                                        </div>



                                        <script>
                                        // 🔍 PRODUCT SEARCH + BARCODE INPUT
                                        const posSearchInput = document.getElementById('search');
                                        const posResults = document.getElementById('results');
                                        const barcodeFeedback = document.getElementById('barcodeFeedback');
                                        let posBarcodeLookupTimer = null;
                                        let barcodeAudioContext = null;

                                        function showBarcodeFeedback(message, type) {
                                            if (!barcodeFeedback) return;

                                            barcodeFeedback.className = 'barcode-feedback ' + (type || 'error');
                                            barcodeFeedback.textContent = message;
                                        }

                                        function clearBarcodeFeedback() {
                                            if (!barcodeFeedback) return;

                                            barcodeFeedback.className = 'barcode-feedback';
                                            barcodeFeedback.textContent = '';
                                        }

                                        function playBarcodeBeep() {
                                            try {
                                                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                                                if (!AudioCtx) return;

                                                if (!barcodeAudioContext) {
                                                    barcodeAudioContext = new AudioCtx();
                                                }

                                                const ctx = barcodeAudioContext;

                                                const startTone = function() {
                                                    const oscillator = ctx.createOscillator();
                                                    const gain = ctx.createGain();

                                                    oscillator.type = 'sine';
                                                    oscillator.frequency.setValueAtTime(880, ctx.currentTime);

                                                    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                                                    gain.gain.exponentialRampToValueAtTime(0.22, ctx.currentTime + 0.01);
                                                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12);

                                                    oscillator.connect(gain);
                                                    gain.connect(ctx.destination);

                                                    oscillator.start(ctx.currentTime);
                                                    oscillator.stop(ctx.currentTime + 0.13);
                                                };

                                                if (ctx.state === 'suspended') {
                                                    ctx.resume().then(startTone).catch(function() {});
                                                } else {
                                                    startTone();
                                                }
                                            } catch (error) {
                                                console.warn('BARCODE BEEP ERROR:', error);
                                            }
                                        }

                                        // Prime browser audio after the first user interaction so that
                                        // scanner callbacks can still play the success beep later.
                                        document.addEventListener('pointerdown', function() {
                                            try {
                                                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                                                if (!AudioCtx) return;

                                                if (!barcodeAudioContext) {
                                                    barcodeAudioContext = new AudioCtx();
                                                }

                                                if (barcodeAudioContext.state === 'suspended') {
                                                    barcodeAudioContext.resume().catch(function() {});
                                                }
                                            } catch (error) {
                                                // Audio is optional; barcode scanning must continue.
                                            }
                                        }, { once: true, passive: true });

                                        posSearchInput.addEventListener('input', function() {
                                            let q = this.value.trim();

                                            clearBarcodeFeedback();

                                            if (q.length < 1) {
                                                posResults.innerHTML = '';
                                                return;
                                            }

                                            clearTimeout(posBarcodeLookupTimer);

                                            // Keep normal product search working while allowing a
                                            // physical scanner to finish with Enter below.
                                            posBarcodeLookupTimer = setTimeout(function() {
                                                fetch('assets/scripts/search.php?q=' + encodeURIComponent(q))
                                                    .then(res => res.text())
                                                    .then(data => posResults.innerHTML = data)
                                                    .catch(err => console.error('PRODUCT SEARCH ERROR:', err));
                                            }, 180);
                                        });

                                        // Physical USB barcode scanners normally finish with Enter.
                                        posSearchInput.addEventListener('keydown', function(e) {
                                            if (e.key !== 'Enter') return;

                                            e.preventDefault();

                                            let code = this.value.trim();
                                            if (!code) return;

                                            clearTimeout(posBarcodeLookupTimer);
                                            lookupBarcodeAndAdd(code, true);
                                        });

                                        function lookupBarcodeAndAdd(code, force) {
                                            code = String(code || '').trim();
                                            if (!code) return;

                                            clearBarcodeFeedback();

                                            fetch('assets/scripts/barcode_lookup.php?barcode=' + encodeURIComponent(code))
                                                .then(res => {
                                                    if (!res.ok) {
                                                        throw new Error('Barcode lookup request failed with HTTP ' + res.status);
                                                    }
                                                    return res.json();
                                                })
                                                .then(data => {
                                                    if (data.success && data.product) {
                                                        playBarcodeBeep();

                                                        addProduct(
                                                            data.product.productid,
                                                            data.product.pname,
                                                            data.product.sellingprice,
                                                            data.product.sellingprice
                                                        );

                                                        posResults.innerHTML = '';
                                                        posSearchInput.value = '';
                                                        return;
                                                    }

                                                    // Only show an error when this request came from an actual
                                                    // barcode scan / Enter action. Normal typing continues to
                                                    // use the product-search results without an error message.
                                                    if (force) {
                                                        posResults.innerHTML = '';
                                                        showBarcodeFeedback(
                                                            'Barcode not found in the database: ' + code,
                                                            'error'
                                                        );
                                                        posSearchInput.select();
                                                    } else if (code.length > 1) {
                                                        fetch('assets/scripts/search.php?q=' + encodeURIComponent(code))
                                                            .then(res => res.text())
                                                            .then(data => posResults.innerHTML = data)
                                                            .catch(err => console.error('PRODUCT SEARCH ERROR:', err));
                                                    }
                                                })
                                                .catch(err => {
                                                    console.error('BARCODE LOOKUP ERROR:', err);

                                                    if (force) {
                                                        posResults.innerHTML = '';
                                                        showBarcodeFeedback(
                                                            'Unable to check barcode "' + code + '". Please check the POS server/database connection.',
                                                            'error'
                                                        );
                                                    } else {
                                                        fetch('assets/scripts/search.php?q=' + encodeURIComponent(code))
                                                            .then(res => res.text())
                                                            .then(data => posResults.innerHTML = data)
                                                            .catch(searchErr => console.error('PRODUCT SEARCH ERROR:', searchErr));
                                                    }
                                                });
                                        }


                                        // =====================================================
                                        // 📱 REMOTE MOBILE SCANNER
                                        // The phone discovers the active scanner session on this
                                        // same POS automatically. No pairing code is required.
                                        // =====================================================

                                        let mobileScannerPollTimer = null;
                                        let mobileScannerToken = null;
                                        let mobileScannerRunning = false;

                                        function getMobileScannerUrl() {
                                            let protocol = window.location.protocol || 'http:';
                                            let host = window.location.hostname || '';
                                            let port = window.location.port ? ':' + window.location.port : '';
                                            const detectedPosServerIp = <?= json_encode($posServerIp); ?>;

                                            /*
                                             * When this PC opened the POS with localhost,
                                             * localhost would point back to the phone itself.
                                             * Use the PC's detected LAN IP instead.
                                             */
                                            if (
                                                (host === 'localhost' || host === '127.0.0.1' || host === '::1') &&
                                                detectedPosServerIp
                                            ) {
                                                host = detectedPosServerIp;
                                            }

                                            if (!host) {
                                                host = detectedPosServerIp || 'localhost';
                                            }

                                            return protocol + '//' + host + port + '/philynda/mobile_scanner.php?_=' + Date.now();
                                        }

                                        function setMobileScannerStatus(message, connected) {
                                            const el = document.getElementById('mobileScannerStatus');
                                            if (!el) return;

                                            el.textContent = message;
                                            el.style.color = connected ? '#198754' : '#856404';
                                        }

                                        function openMobileScannerConnection() {
                                            const box = document.getElementById('mobileScannerConnectionBox');
                                            const urlInput = document.getElementById('mobileScannerUrl');

                                            if (box) box.style.display = 'block';
                                            if (urlInput) urlInput.value = getMobileScannerUrl();

                                            startMobileScannerConnection();
                                        }

                                        /*
                                         * AUTO-START MOBILE SCANNER ON POS OPEN
                                         *
                                         * The POS desktop creates the scanner session automatically.
                                         * No "Connect Scanner" button is required.
                                         *
                                         * Do this only on desktop-sized screens. A phone/tablet should
                                         * open the mobile scanner page instead of creating a desktop session.
                                         */
                                        let mobileScannerAutoStarted = false;

                                        function autoStartMobileScannerOnPOS() {
                                            if (mobileScannerAutoStarted) return;

                                            if (window.matchMedia('(max-width: 991.98px)').matches) {
                                                return;
                                            }

                                            mobileScannerAutoStarted = true;

                                            const urlInput = document.getElementById('mobileScannerUrl');
                                            if (urlInput) {
                                                urlInput.value = getMobileScannerUrl();
                                            }

                                            startMobileScannerConnection();
                                        }

                                        function closeMobileScannerConnection() {
                                            const box = document.getElementById('mobileScannerConnectionBox');
                                            if (box) box.style.display = 'none';
                                        }

                                        async function startMobileScannerConnection() {
                                            try {
                                                const response = await fetch(
                                                    'assets/scripts/mobile_scanner_api.php?action=start&_=' + Date.now(),
                                                    {
                                                        method: 'POST',
                                                        credentials: 'same-origin',
                                                        cache: 'no-store'
                                                    }
                                                );

                                                const responseText = await response.text();
                                                let data = null;

                                                try {
                                                    data = JSON.parse(responseText);
                                                } catch (parseError) {
                                                    console.error('MOBILE SCANNER START NON-JSON RESPONSE:', response.status, responseText);
                                                    setMobileScannerStatus(
                                                        'Server returned HTTP ' + response.status + ' instead of scanner JSON. Check the API URL/PHP error.',
                                                        false
                                                    );
                                                    return;
                                                }

                                                console.log('MOBILE SCANNER START RESPONSE:', response.status, data);

                                                if (!response.ok || !data.success || !data.token) {
                                                    setMobileScannerStatus(
                                                        data.message || ('Unable to start mobile scanner connection. HTTP ' + response.status),
                                                        false
                                                    );
                                                    return;
                                                }

                                                mobileScannerToken = String(data.token);
                                                mobileScannerRunning = true;

                                                const urlInput = document.getElementById('mobileScannerUrl');
                                                if (urlInput) urlInput.value = getMobileScannerUrl();

                                                setMobileScannerStatus('Waiting for phone to connect automatically...', false);
                                                startMobileScannerPolling();

                                            } catch (error) {
                                                console.error('MOBILE SCANNER START ERROR:', error);
                                                setMobileScannerStatus(
                                                    'Unable to start mobile scanner connection: ' + error.message,
                                                    false
                                                );
                                            }
                                        }

                                        function startMobileScannerPolling() {
                                            stopMobileScannerPolling();

                                            const poll = async function() {
                                                if (!mobileScannerRunning || !mobileScannerToken) return;

                                                try {
                                                    const response = await fetch(
                                                        'assets/scripts/mobile_scanner_api.php?action=poll&token=' +
                                                        encodeURIComponent(mobileScannerToken) + '&_=' + Date.now(),
                                                        {
                                                            method: 'GET',
                                                            cache: 'no-store'
                                                        }
                                                    );

                                                    const data = await response.json();

                                                    if (!response.ok || data.success === false) {
                                                        setMobileScannerStatus(
                                                            data.message || 'Mobile scanner connection ended.',
                                                            false
                                                        );
                                                        mobileScannerRunning = false;
                                                        mobileScannerToken = null;
                                                        return;
                                                    }

                                                    if (data.paired) {
                                                        setMobileScannerStatus(
                                                            data.device ? 'Phone connected: ' + data.device : 'Phone connected',
                                                            true
                                                        );
                                                    } else {
                                                        setMobileScannerStatus(
                                                            'Waiting for phone to connect automatically...',
                                                            false
                                                        );
                                                    }

                                                    if (data.barcode) {
                                                        lookupBarcodeAndAdd(String(data.barcode), true);
                                                    }

                                                } catch (error) {
                                                    console.error('MOBILE SCANNER POLL ERROR:', error);
                                                }

                                                if (mobileScannerRunning) {
                                                    mobileScannerPollTimer = setTimeout(poll, 500);
                                                }
                                            };

                                            poll();
                                        }

                                        function stopMobileScannerPolling() {
                                            if (mobileScannerPollTimer) {
                                                clearTimeout(mobileScannerPollTimer);
                                                mobileScannerPollTimer = null;
                                            }
                                        }

                                        async function stopMobileScannerConnection() {
                                            mobileScannerRunning = false;
                                            stopMobileScannerPolling();

                                            try {
                                                if (mobileScannerToken) {
                                                    await fetch(
                                                        'assets/scripts/mobile_scanner_api.php?action=stop&token=' +
                                                        encodeURIComponent(mobileScannerToken) + '&_=' + Date.now(),
                                                        {
                                                            method: 'POST',
                                                            cache: 'no-store'
                                                        }
                                                    );
                                                }
                                            } catch (error) {
                                                console.error('MOBILE SCANNER STOP ERROR:', error);
                                            }

                                            mobileScannerToken = null;
                                            setMobileScannerStatus('Not connected', false);
                                        }

                                        function copyMobileScannerUrl() {
                                            const input = document.getElementById('mobileScannerUrl');
                                            if (!input) return;

                                            input.select();
                                            input.setSelectionRange(0, input.value.length);

                                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                                navigator.clipboard.writeText(input.value)
                                                    .then(function() {
                                                        alert('Mobile scanner address copied.');
                                                    })
                                                    .catch(function() {
                                                        alert('Copy failed. Please copy the address manually.');
                                                    });
                                            } else {
                                                try {
                                                    document.execCommand('copy');
                                                    alert('Mobile scanner address copied.');
                                                } catch (error) {
                                                    alert('Please copy the address manually.');
                                                }
                                            }
                                        }

                                        window.addEventListener('beforeunload', function() {
                                            if (!mobileScannerToken) return;

                                            try {
                                                navigator.sendBeacon(
                                                    'assets/scripts/mobile_scanner_api.php?action=stop&token=' +
                                                    encodeURIComponent(mobileScannerToken),
                                                    ''
                                                );
                                            } catch (error) {
                                                // Ignore unload cleanup failures.
                                            }
                                        });

                                        // 📱 OPEN REMOTE MOBILE SCANNER FROM MOBILE POS VIEW
                                        // This button is shown only on tablets/phones.
                                        function openMobileScannerFromMobileView() {
                                            const scannerUrl = getMobileScannerUrl();

                                            if (!scannerUrl) {
                                                alert('Mobile scanner address could not be determined.');
                                                return;
                                            }

                                            console.log('OPENING MOBILE SCANNER:', scannerUrl);

                                            // Open the scanner without requiring the user to type the IP.
                                            const newWindow = window.open(
                                                scannerUrl,
                                                '_blank'
                                            );

                                            // If the browser blocks the new tab, navigate the current page.
                                            if (!newWindow) {
                                                window.location.href = scannerUrl;
                                            }
                                        }

                                        // 📷 PHONE CAMERA BARCODE SCANNER
                                        function openPOSBarcodeScanner() {
                                            if (typeof openBarcodeScanner !== 'function') {
                                                alert('Barcode scanner is not loaded.');
                                                return;
                                            }

                                            openBarcodeScanner(function(code) {
                                                lookupBarcodeAndAdd(code, true);
                                            });
                                        }
                                        </script>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                                            </div>
                    <!-- END POS LAYOUT -->


                    <!-- =====================================================
                         SALES HISTORY - STACKED AT THE BOTTOM
                         ===================================================== -->

                    <div class="sales-history-section">

                        <!-- Toggle Button -->
                        <button
                            type="button"
                            id="salesHistoryToggle"
                            class="btn btn-primary sales-history-toggle"
                            onclick="toggleSalesHistory()"
                            aria-expanded="false"
                            aria-controls="salesHistoryPanel">

                            <span id="salesHistoryIcon">+</span>
                            <strong id="salesHistoryButtonText">
                                Show Sales History
                            </strong>

                        </button>


                        <!-- Hidden Sales History -->
                        <div
                            id="salesHistoryPanel"
                            class="sales-history-panel"
                            style="display:none;">

                            <!-- Date Filter -->
                            <div class="sales-history-filter">

                                <div class="row align-items-end">

                                    <!-- FROM DATE -->
                                    <div class="col-md-4">
                                        <label for="salesFromDate">
                                            From Date
                                        </label>

                                        <input
                                            type="date"
                                            id="salesFromDate"
                                            class="form-control">
                                    </div>


                                    <!-- TO DATE -->
                                    <div class="col-md-4">
                                        <label for="salesToDate">
                                            To Date
                                        </label>

                                        <input
                                            type="date"
                                            id="salesToDate"
                                            class="form-control">
                                    </div>


                                    <!-- BUTTONS -->
                                    <div class="col-md-4">

                                        <button
                                            type="button"
                                            class="btn btn-success"
                                            onclick="filterSalesHistory()">

                                            Filter
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            onclick="clearSalesHistoryFilter()">

                                            Clear
                                        </button>

                                    </div>

                                </div>

                            </div>


                            <!-- Sales History -->
                            <div class="sales-history-frame">

                                <iframe
                                    id="salesHistoryFrame"
                                    width="100%"
                                    height="500"
                                    src="about:blank"
                                    title="Sales History">
                                </iframe>

                            </div>

                        </div>

                    </div>


                    <!-- SALES HISTORY JAVASCRIPT -->

                    <script>

                    function toggleSalesHistory() {

                        const panel =
                            document.getElementById('salesHistoryPanel');

                        const toggle =
                            document.getElementById('salesHistoryToggle');

                        const icon =
                            document.getElementById('salesHistoryIcon');

                        const text =
                            document.getElementById('salesHistoryButtonText');

                        if (panel.classList.contains('show')) {

                            // HIDE
                            panel.classList.remove('show');
                            panel.style.display = 'none';

                            icon.innerHTML = '+';

                            text.innerHTML = 'Show Sales History';
                            if (toggle) toggle.setAttribute('aria-expanded', 'false');

                        } else {

                            // SHOW
                            panel.classList.add('show');
                            panel.style.display = 'block';

                            icon.innerHTML = '−';

                            text.innerHTML = 'Hide Sales History';
                            if (toggle) toggle.setAttribute('aria-expanded', 'true');


                            // Load history only when opened
                            const frame =
                                document.getElementById('salesHistoryFrame');

                            if (
                                frame.src === 'about:blank' ||
                                frame.src === ''
                            ) {

                                filterSalesHistory();

                            }

                        }

                    }


                    function filterSalesHistory() {

                        const from =
                            document.getElementById('salesFromDate').value;

                        const to =
                            document.getElementById('salesToDate').value;


                        // Validate dates
                        if (from && to && from > to) {

                            alert('From Date cannot be greater than To Date.');

                            return;
                        }


                        let url = 'saleslog2.php';


                        const params = new URLSearchParams();


                        if (from) {
                            params.append('from_date', from);
                        }

                        if (to) {
                            params.append('to_date', to);
                        }


                        if (params.toString() !== '') {

                            url += '?' + params.toString();

                        }


                        document.getElementById(
                            'salesHistoryFrame'
                        ).src = url;

                    }


                    function clearSalesHistoryFilter() {

                        document.getElementById(
                            'salesFromDate'
                        ).value = '';

                        document.getElementById(
                            'salesToDate'
                        ).value = '';


                        filterSalesHistory();

                    }

                    </script>

                    </div>



                </div>

            </div>
        </div>

    </div><!-- end row -->
    </div><!-- container -->
    </div><!-- Page content Wrapper -->
    </div><!-- content -->
    <footer class="footer"><?php include "assets/sections/footers/footer.php" ?></footer>
    </div><!-- End Right content here -->
    </div><!-- END wrapper -->


    
    <?php include "assets/sections/footers/jqueryscripts.php" ?>

    <!-- Local phone-camera barcode scanner -->
    <?php include "assets/scripts/barcode_scanner.php" ?>

    <script>
        /*
         * Start the remote phone scanner automatically whenever the POS
         * is opened on a desktop. The user does not need to click a
         * scanner connection button.
         */
        document.addEventListener('DOMContentLoaded', function () {
            /*
             * Automatically create the mobile-scanner session when the POS
             * page loads on the desktop. The Connect Mobile Scanner button
             * is no longer needed to start the connection.
             *
             * The connection panel is opened so the cashier can see the
             * connection status and the phone-scanner address immediately.
             * The phone must still open mobile_scanner.php and grant camera
             * permission before it can scan barcodes.
             */
            setTimeout(function () {
                if (window.matchMedia('(max-width: 991.98px)').matches) {
                    return;
                }

                const connectionBox = document.getElementById('mobileScannerConnectionBox');
                const urlInput = document.getElementById('mobileScannerUrl');

                if (connectionBox) {
                    connectionBox.style.display = 'block';
                }

                if (urlInput && typeof getMobileScannerUrl === 'function') {
                    urlInput.value = getMobileScannerUrl();
                }

                if (typeof autoStartMobileScannerOnPOS === 'function') {
                    autoStartMobileScannerOnPOS();
                }
            }, 300);
        });
    </script>
</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>

