<?php

$pagetitle="Add Products";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";

// 🔍 GET ID
if(!isset($_GET['id'])){
    die("❌ No product selected");
}

$productid = mysqli_real_escape_string($conn, $_GET['id']);

// 🔍 FETCH DATA
$res = mysqli_query($conn,"SELECT * FROM products WHERE productid='$productid'");

$data = mysqli_fetch_assoc($res);

if(!$data){
    die("❌ Product not found");
}


// ================= SAVE UNITS =================
if(isset($_POST['save_units'])) {

    $names  = $_POST['unit_name'] ?? [];
    $qtys   = $_POST['unit_qty'] ?? [];
    $prices = $_POST['price'] ?? [];

    $ok = true;

    foreach($names as $i => $unit_name){

        $name  = mysqli_real_escape_string($conn, trim($unit_name));
        $qty   = floatval($qtys[$i] ?? 0);
        $price = floatval($prices[$i] ?? 0);

        if($name == "") continue;

        // INSERT INTO units
        if(!mysqli_query($conn,"
            INSERT INTO units(product_id, unit_name, unit_qty, price)
            VALUES('$productid','$name','$qty','$price')
        ")){
            $ok = false;
        }

        // ALSO INSERT INTO item_units if not exists
        $check = mysqli_query($conn,"
            SELECT id FROM item_units WHERE unitname='$name'
        ");

        if($check && mysqli_num_rows($check) == 0){

            if(!mysqli_query($conn,"
                INSERT INTO item_units(unitname) VALUES('$name')
            ")){
                $ok = false;
            }
        }
    }

    if($ok){
        $msg = "All units saved successfully";
        header('refresh:2; url=products.php');
    } else {
        $errmsg = "Some units could not be saved.";
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>

    /* =========================================================
       ADD UNITS - DESKTOP
       ========================================================= */

    .units-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #unitTable {
        width: 100%;
        margin-bottom: 15px;
        border-collapse: collapse;
    }

    #unitTable th,
    #unitTable td {
        padding: 8px;
        vertical-align: middle;
    }

    #unitTable th {
        white-space: nowrap;
        background: #f2f2f2;
    }

    #unitTable .form-control {
        min-width: 100px;
    }

    .unit-select {
        margin-bottom: 6px;
    }

    .unit-input {
        margin-top: 5px;
    }

    .unit-page-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .unit-page-actions .btn {
        min-height: 44px;
    }

    .mobile-unit-instruction {
        display: none;
    }


    /* =========================================================
       MOBILE
       Each unit becomes a separate card.
       ========================================================= */

    @media (max-width: 767.98px) {

        html,
        body {
            max-width: 100%;
            overflow-x: hidden;
        }

        #wrapper,
        .content-page,
        .content,
        .page-content-wrapper,
        .container-fluid,
        .card,
        .card-body {
            max-width: 100%;
        }

        .container-fluid {
            padding-left: 10px;
            padding-right: 10px;
        }

        .card-body {
            padding: 15px 10px !important;
        }

        h2 {
            font-size: 20px;
            line-height: 1.35;
            word-break: break-word;
            margin-bottom: 8px;
        }

        .mobile-unit-instruction {
            display: block;
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .units-table-wrap {
            width: 100%;
            overflow: visible;
        }

        /* Hide desktop table header */
        #unitTable thead {
            display: none;
        }

        #unitTable,
        #unitTable tbody {
            display: block;
            width: 100%;
        }

        /* Each unit is a mobile card */
        #unitTable tbody tr {
            display: block;
            width: 100%;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 5px rgba(0,0,0,.08);
        }

        #unitTable tbody td {
            display: block;
            width: 100%;
            padding: 6px 0;
            border: 0 !important;
        }

        #unitTable tbody td::before {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }

        #unitTable tbody td:nth-child(1)::before {
            content: "Unit Name";
        }

        #unitTable tbody td:nth-child(2)::before {
            content: "Quantity";
        }

        #unitTable tbody td:nth-child(3)::before {
            content: "Price";
        }

        #unitTable tbody td:nth-child(4)::before {
            content: "Action";
        }

        /* Larger touch-friendly controls */
        #unitTable .form-control,
        #unitTable select {
            width: 100%;
            min-width: 0;
            height: 44px;
            font-size: 16px;
        }

        #unitTable .unit-input {
            margin-top: 6px;
        }

        #unitTable td:nth-child(4) {
            padding-top: 10px;
        }

        #unitTable td:nth-child(4) .btn {
            width: 100%;
            min-height: 44px;
            font-size: 15px;
        }

        /* Add/Save buttons */
        .unit-page-actions {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 9px;
            margin-top: 5px;
        }

        .unit-page-actions .btn {
            width: 100%;
            min-height: 48px;
            font-size: 16px;
        }

        /* Alerts fit phone width */
        .alert {
            word-break: break-word;
        }
    }

    </style>

</head>


<body class="fixed-left">

    <!-- Loader -->
    <div id="preloader">
        <div id="status">
            <div class="spinner"></div>
        </div>
    </div>

    <!-- Begin page -->
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

                        <br>

                        <div class="row">

                            <div class="col-lg-12">

                                <div class="card m-b-30">

                                    <div class="card-body bootstrap-select-1">

                                        <h2>
                                            <?php
                                            echo "Add More Units for "
                                                . htmlspecialchars($data['pname'])
                                                . " - "
                                                . htmlspecialchars($data['productid']);
                                            ?>
                                        </h2>

                                        <div class="mobile-unit-instruction">
                                            Select a unit or type a custom unit, then enter the quantity and price.
                                        </div>


                                        <?php if(isset($msg)){ ?>

                                        <div class="alert alert-success alert-dismissible fade show" role="alert">

                                            <button type="button"
                                                class="close"
                                                data-dismiss="alert"
                                                aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>

                                            <?php echo $msg ?>

                                        </div>

                                        <?php } ?>


                                        <?php if(isset($errmsg)){ ?>

                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">

                                            <button type="button"
                                                class="close"
                                                data-dismiss="alert"
                                                aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>

                                            <?php echo $errmsg ?>

                                        </div>

                                        <?php } ?>


                                        <form method="POST">

                                            <div class="units-table-wrap">

                                                <table id="unitTable"
                                                    class="table table-bordered">

                                                    <thead>

                                                        <tr>
                                                            <th>Unit Name</th>
                                                            <th>Quantity</th>
                                                            <th>Price</th>
                                                            <th>Action</th>
                                                        </tr>

                                                    </thead>


                                                    <tbody>

                                                        <tr>

                                                            <td>

                                                                <!-- Dropdown -->
                                                                <select class="form-control unit-select"
                                                                    onchange="syncUnit(this)">

                                                                    <option value="">
                                                                        Select Unit
                                                                    </option>

                                                                    <?php

                                                                    $res = mysqli_query(
                                                                        $conn,
                                                                        "SELECT unitname
                                                                         FROM item_units
                                                                         ORDER BY unitname ASC"
                                                                    );

                                                                    while($u = mysqli_fetch_assoc($res)){

                                                                        echo "<option value='"
                                                                            . htmlspecialchars($u['unitname'], ENT_QUOTES)
                                                                            . "'>"
                                                                            . htmlspecialchars($u['unitname'])
                                                                            . "</option>";
                                                                    }

                                                                    ?>

                                                                </select>

                                                                <!-- Actual value submitted -->
                                                                <input type="text"
                                                                    name="unit_name[]"
                                                                    class="form-control mt-1 unit-input"
                                                                    placeholder="Or type custom unit"
                                                                    required>

                                                            </td>


                                                            <td>

                                                                <input
                                                                    class="form-control"
                                                                    type="number"
                                                                    step="any"
                                                                    min="0.0001"
                                                                    name="unit_qty[]"
                                                                    placeholder="Quantity"
                                                                    required>

                                                            </td>


                                                            <td>

                                                                <input
                                                                    class="form-control"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    name="price[]"
                                                                    placeholder="Price"
                                                                    required>

                                                            </td>


                                                            <td>

                                                                <button
                                                                    class="btn btn-danger btn-sm"
                                                                    type="button"
                                                                    onclick="removeRow(this)">

                                                                    <span class="fa fa-trash"></span>
                                                                    Remove

                                                                </button>

                                                            </td>

                                                        </tr>

                                                    </tbody>

                                                </table>

                                            </div>


                                            <div class="unit-page-actions">

                                                <button
                                                    class="btn btn-success btn-lg"
                                                    type="button"
                                                    onclick="addRow()">

                                                    <span class="fa fa-plus"></span>
                                                    Add More

                                                </button>


                                                <button
                                                    class="btn btn-primary btn-lg"
                                                    type="submit"
                                                    name="save_units">

                                                    <span class="fa fa-save"></span>
                                                    Save All

                                                </button>

                                            </div>

                                        </form>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <footer class="footer">
        <?php include "assets/sections/footers/footer.php" ?>
    </footer>

    </div>

    <!-- jQuery -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>


    <script>

    /*
     * When a unit is selected from the dropdown,
     * copy it into the actual input that gets submitted.
     */
    function syncUnit(select) {

        let input = select
            .closest('td')
            .querySelector('.unit-input');

        if(input){
            input.value = select.value;
        }
    }


    /*
     * Add a completely fresh unit row.
     */
    function addRow() {

        let tbody = document.querySelector("#unitTable tbody");

        if(!tbody){
            return;
        }

        let templateRow = tbody.querySelector("tr");

        if(!templateRow){
            return;
        }

        let newRow = templateRow.cloneNode(true);

        // Reset dropdown
        let select = newRow.querySelector(".unit-select");

        if(select){
            select.selectedIndex = 0;
        }

        // Reset all inputs
        let inputs = newRow.querySelectorAll("input");

        inputs.forEach(function(input){
            input.value = "";
        });

        tbody.appendChild(newRow);
    }


    /*
     * Remove a row.
     * Keep at least one row on the page.
     */
    function removeRow(btn) {

        let row = btn.closest("tr");
        let tbody = document.querySelector("#unitTable tbody");

        if(!row || !tbody){
            return;
        }

        if(tbody.rows.length > 1){
            row.remove();
        } else {

            // Clear the last remaining row instead of removing it.
            let select = row.querySelector(".unit-select");
            let inputs = row.querySelectorAll("input");

            if(select){
                select.selectedIndex = 0;
            }

            inputs.forEach(function(input){
                input.value = "";
            });
        }
    }

    </script>

</body>

</html>
