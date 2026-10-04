<?php
$pagetitle="Edit Units";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";


// 🔍 GET PRODUCT ID
if(!isset($_GET['id'])){
    die("❌ No Product selected");
}

$product_id = mysqli_real_escape_string($conn, $_GET['id']);


// 🔍 FETCH PRODUCT INFO
$productRes = mysqli_query($conn,"
    SELECT * FROM products 
    WHERE productid='$product_id'
");

$product = mysqli_fetch_assoc($productRes);

if(!$product){
    die("❌ Product not found");
}


// 🔍 FETCH ALL UNITS FOR THIS PRODUCT
$unitRes = mysqli_query($conn,"
    SELECT * FROM units 
    WHERE product_id='$product_id'
");


// ================= UPDATE =================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $ids       = $_POST['id'];
    $names     = $_POST['unit_name'];
    $qtys      = $_POST['unit_qty'];
    $prices    = $_POST['price'];

    $ok = true;

    for($i = 0; $i < count($names); $i++){

        $id    = mysqli_real_escape_string($conn, $ids[$i]);
        $name  = mysqli_real_escape_string($conn, $names[$i]);
        $qty   = floatval($qtys[$i]);
        $price = floatval($prices[$i]);

        // UPDATE existing
        if($id != ""){

            $query = "
                UPDATE units SET
                    unit_name='$name',
                    unit_qty='$qty',
                    price='$price'
                WHERE id='$id'
            ";

            if(!mysqli_query($conn,$query)){
                $ok = false;
            }

        } 
        // INSERT new
        else {

            if(!mysqli_query($conn,"
                INSERT INTO units (product_id, unit_name, unit_qty, price)
                VALUES ('$product_id','$name','$qty','$price')
            ")){
                $ok = false;
            }
        }
    }

    if($ok){
        $msg = "Units updated successfully";
    } else {
        $errmsg = "Some updates failed: " . mysqli_error($conn);
    }

    // Refresh the units after saving so the displayed values are current.
    $unitRes = mysqli_query($conn,"
        SELECT * FROM units 
        WHERE product_id='$product_id'
    ");
}
?>

<!DOCTYPE html>
<html>

<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>
    /* =========================================================
       EDIT UNITS - RESPONSIVE TABLE
       Desktop keeps the normal table layout.
       Mobile changes each unit into a compact card/row.
       ========================================================= */

    .edit-units-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #unitsTable {
        width: 100%;
        margin-bottom: 15px;
    }

    #unitsTable th,
    #unitsTable td {
        vertical-align: middle;
    }

    #unitsTable input.form-control {
        min-width: 100px;
    }

    .units-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .units-actions .btn {
        min-height: 42px;
    }

    .add-unit-btn {
        min-height: 42px;
    }

    .update-units-btn {
        min-height: 42px;
    }

    .mobile-unit-title {
        display: none;
    }

    /* =========================================================
       MOBILE
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
            margin-bottom: 15px;
        }

        .edit-units-table-wrap {
            overflow: visible;
        }

        /* Hide the desktop table header on phones */
        #unitsTable thead {
            display: none;
        }

        #unitsTable,
        #unitsTable tbody {
            display: block;
            width: 100%;
        }

        /* Each unit becomes a separate mobile card */
        #unitsTable tbody tr {
            display: block;
            width: 100%;
            margin-bottom: 14px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }

        #unitsTable tbody td {
            display: block;
            width: 100%;
            padding: 6px 0;
            border: 0 !important;
        }

        #unitsTable tbody td::before {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
        }

        #unitsTable tbody td:nth-child(1)::before {
            content: "Unit Name";
        }

        #unitsTable tbody td:nth-child(2)::before {
            content: "Quantity";
        }

        #unitsTable tbody td:nth-child(3)::before {
            content: "Price";
        }

        #unitsTable tbody td:nth-child(4)::before {
            content: "Action";
        }

        #unitsTable input.form-control {
            width: 100%;
            min-width: 0;
            height: 44px;
            font-size: 16px;
            padding: 8px 10px;
        }

        #unitsTable td:nth-child(4) {
            padding-top: 10px;
        }

        #unitsTable td:nth-child(4) .btn {
            width: 100%;
            min-height: 42px;
            font-size: 15px;
        }

        .units-actions {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 8px;
            margin-top: 8px;
        }

        .units-actions .btn {
            width: 100%;
            min-height: 46px;
            font-size: 15px;
        }

        .mobile-unit-title {
            display: block;
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
        }

        /* Prevent Bootstrap or theme styles from creating horizontal overflow */
        .table-responsive,
        .row,
        .col-lg-12 {
            max-width: 100%;
        }

        /* Make alerts comfortable on small screens */
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
                                            Edit Unit for
                                            <?php echo htmlspecialchars($product['pname']." - ".$product['pdesc']); ?>
                                        </h2>

                                        <div class="mobile-unit-title">
                                            Edit the unit name, quantity and selling price below.
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

                                            <div class="edit-units-table-wrap">

                                                <table class="table table-bordered" id="unitsTable">

                                                    <thead>

                                                        <tr>
                                                            <th>Unit Name</th>
                                                            <th>Qty</th>
                                                            <th>Price</th>
                                                            <th>Action</th>
                                                        </tr>

                                                    </thead>

                                                    <tbody>

                                                        <?php while($u = mysqli_fetch_assoc($unitRes)) { ?>

                                                        <tr>

                                                            <td>
                                                                <input type="hidden"
                                                                    name="id[]"
                                                                    value="<?= htmlspecialchars($u['id']) ?>">

                                                                <input type="text"
                                                                    name="unit_name[]"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($u['unit_name']) ?>">
                                                            </td>

                                                            <td>
                                                                <input type="number"
                                                                    name="unit_qty[]"
                                                                    class="form-control"
                                                                    step="any"
                                                                    min="0.0001"
                                                                    value="<?= htmlspecialchars($u['unit_qty']) ?>">
                                                            </td>

                                                            <td>
                                                                <input type="number"
                                                                    name="price[]"
                                                                    class="form-control"
                                                                    step="0.01"
                                                                    min="0"
                                                                    value="<?= htmlspecialchars($u['price']) ?>">
                                                            </td>

                                                            <td>
                                                                <button type="button"
                                                                    class="btn btn-danger btn-sm"
                                                                    onclick="this.closest('tr').remove()">
                                                                    <span class="fa fa-trash"></span>
                                                                    Remove
                                                                </button>
                                                            </td>

                                                        </tr>

                                                        <?php } ?>

                                                    </tbody>

                                                </table>

                                            </div>

                                            <div class="units-actions">

                                                <button type="button"
                                                    class="btn btn-secondary add-unit-btn"
                                                    onclick="addRow()">
                                                    <span class="fa fa-plus"></span>
                                                    Add Unit
                                                </button>

                                                <button type="submit"
                                                    class="btn btn-primary update-units-btn">
                                                    <span class="fa fa-save"></span>
                                                    Update All
                                                </button>

                                            </div>

                                        </form>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div><!-- end row -->

            </div><!-- container -->

        </div><!-- Page content Wrapper -->

    </div><!-- content -->

    <footer class="footer">
        <?php include "assets/sections/footers/footer.php" ?>
    </footer>

    </div><!-- End Right content here -->

    </div><!-- END wrapper -->

    <!-- jQuery -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>

    <script>

    function addRow() {

        let row = `
<tr>

    <td>
        <input type="hidden" name="id[]" value="">

        <input type="text"
            name="unit_name[]"
            class="form-control"
            placeholder="Unit name"
            required>
    </td>

    <td>
        <input type="number"
            name="unit_qty[]"
            class="form-control"
            step="any"
            min="0.0001"
            placeholder="Quantity"
            required>
    </td>

    <td>
        <input type="number"
            name="price[]"
            class="form-control"
            step="0.01"
            min="0"
            placeholder="Price"
            required>
    </td>

    <td>
        <button type="button"
            class="btn btn-danger btn-sm"
            onclick="this.closest('tr').remove()">
            <span class="fa fa-trash"></span>
            Remove
        </button>
    </td>

</tr>
`;

        document.querySelector("#unitsTable tbody")
            .insertAdjacentHTML("beforeend", row);

    }

    </script>

</body>

<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>
