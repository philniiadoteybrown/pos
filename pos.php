<?php
$pagetitle="Add Products";
include "assets/scripts/auth.php";

include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";

$role = $_SESSION['role'] ?? '';
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

        $errsg="Sales transaction failed.";
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
    </style>
</head>

<body class="fixed-left">
    <!-- Loader -->
    <div id="preloader">
        <div id="status">
            <div class="spinner"></div>
        </div>
    </div><!-- Begin page -->
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
                        <div class="row">
                            <div class="col-8">
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
                                            <?php echo $msg ?>
                                        </div>
                                        <?php } ?>
                                        <form method="POST" action=""
                                            onsubmit="updateHiddenFields(); return validatePayment();">
                                            <div class="card-body">
                                                <div style="
        height: 350px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
    ">
                                                    <table id="productTable" class="table table-bordered"
                                                        style="margin-bottom:0;">
                                                        <thead
                                                            style="position: sticky; top: 0; background: #fff; z-index: 2;"
                                                            <thead
                                                            style="position: sticky; top: 0; background: #fff; z-index: 2;">
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

                                                <label>
                                                    <h4><b>Amount Paid:</b></h4>
                                                </label><br>
                                                <input style="height:80px; font-size:40px;" class="form-control"
                                                    type="number" id="paid" step="0.01" placeholder="Enter amount paid"
                                                    oninput="calculateBalance()">
                                                <input type="hidden" name="total" id="total_input">
                                                <input type="hidden" name="paid_amount" id="paid_input">
                                                <input type="hidden" name="balance" id="balance_input">
                                                <br>
                                                <h3 id="balanceLabel"><strong>Balance: GH¢ <span
                                                            id="balance">0.00</span></strong></h3>
                                                <label>Payment Method:</label><br>
                                                <select class="form-control" name="payment_method" id="payment_method"
                                                    onchange="toggleCredit()">
                                                    <option value="cash">Cash</option>
                                                    <option value="momo">MoMo</option>
                                                    <option value="credit">Credit</option>
                                                </select>

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
        <td>
            ${name}
            <input type="hidden" name="products[]" value="${id}">
            <input type="hidden" name="price[]" class="price-input" value="${price}">
            <input type="hidden" name="unit_qty[]" class="unit-qty" value="1">
        </td>

        <td>
            <select class="form-control unit-select" onchange="updateRow(this)">
                <option value="1" data-price="${price}">Loading...</option>
            </select>
        </td>

        <td class="price">${price}</td>

        <td>
            <input type="number" step="0.01" value="1" min="0.01"
                class="qty" name="qty[]" oninput="updateTotal()">
        </td>

        <td class="subtotal">0.00</td>

        <td><span class="remove" onclick="removeRow(this)">Remove</span></td>
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
                                            let balance = parseFloat(document.getElementById('balance').innerText);

                                            if (method !== 'credit' && balance < 0) {
                                                alert("Insufficient payment!");
                                                return false;
                                            }

                                            if (method === 'credit') {
                                                let customer = document.querySelector('[name="customer_id"]').value;
                                                let newCustomer = document.querySelector('[name="new_customer"]').value;

                                                if (!customer && !newCustomer) {
                                                    alert("Select or add a customer!");
                                                    return false;
                                                }
                                            }

                                            return true;
                                        }



                                        let activeInput = null;

                                        // 👆 OPEN NUMPAD when clicking qty
                                        document.addEventListener("click", function(e) {
                                            if (e.target.classList.contains("qty") || e.target.id === "paid") {
                                                activeInput = e.target;
                                                showNumpad();
                                            }
                                        });

                                        function showNumpad() {
                                            document.getElementById("numpad").style.display = "block";
                                        }

                                        function hideNumpad() {
                                            document.getElementById("numpad").style.display = "none";
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

                                        function togglePOSMode() {

                                            document.body.classList.toggle("pos-mode");

                                            // 🔥 Enter true fullscreen
                                            if (!document.fullscreenElement) {
                                                document.documentElement.requestFullscreen().catch(err => {
                                                    console.log(err);
                                                });
                                            } else {
                                                document.exitFullscreen();
                                            }
                                        }

                                        window.addEventListener("load", () => {
                                            togglePOSMode();
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

                                            let role = "<?= $_SESSION['user']['role'] ?>";

                                            if (role === "cashier") {
                                                // 🔴 cashier → logout completely
                                                window.location.href = "logout.php";
                                            } else {
                                                // 🟢 admin/manager → only exit fullscreen
                                                if (document.fullscreenElement) {
                                                    document.exitFullscreen();
                                                }

                                                // optional: redirect to dashboard
                                                window.location.href = "index.php";
                                            }
                                        }
                                        </script>

                                    </div>
                                </div>


                            </div>

                            <div class="col-4">
                                <div class="card m-b-30">
                                    <div class="card-body">

                                        <h4>Product Search</h4>

                                        <div class="card-header-form">
                                            <input type="text" id="search" class="search-box form-control"
                                                placeholder="Search product...">

                                            <div id="results" class="results"></div>

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
                                        // 🔍 SEARCH
                                        document.getElementById('search').addEventListener('keyup', function() {
                                            let q = this.value;

                                            if (q.length < 1) {
                                                document.getElementById('results').innerHTML = '';
                                                return;
                                            }

                                            fetch('assets/scripts/search.php?q=' + q)
                                                .then(res => res.text())
                                                .then(data => document.getElementById('results').innerHTML =
                                                    data);
                                        });
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>

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


    <!-- 🔢 NUMPAD -->
    <!-- <div id="numpad" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #222;
    padding: 10px;
    border-radius: 10px;
    display: none;
    z-index: 9999;
">
        <div style="display:grid; grid-template-columns:repeat(3,72px); gap:6px;">
            <button onclick="pressKey('1')">1</button>
            <button onclick="pressKey('2')">2</button>
            <button onclick="pressKey('3')">3</button>

            <button onclick="pressKey('4')">4</button>
            <button onclick="pressKey('5')">5</button>
            <button onclick="pressKey('6')">6</button>

            <button onclick="pressKey('7')">7</button>
            <button onclick="pressKey('8')">8</button>
            <button onclick="pressKey('9')">9</button>

            <button onclick="pressKey('.')">.</button>
            <button onclick="pressKey('0')">0</button>
            <button onclick="clearInput()">C</button>

        </div>
        <button style="margin-top:5px; width:100%;" onclick="backspace()">⌫</button>
        <button style="margin-top:5px; width:100%;" onclick="closeNumpad()">Close</button>
    </div> -->
    <!-- jQuery  -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>
</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>