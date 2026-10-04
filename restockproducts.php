<?php
$pagetitle = "Add Products";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";


// =====================================================
// GET PRODUCT ID
// =====================================================

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ No product selected");
}

$productid = mysqli_real_escape_string($conn, $_GET['id']);


// =====================================================
// FETCH PRODUCT
// =====================================================

$res = mysqli_query(
    $conn,
    "SELECT * FROM products WHERE productid='$productid'"
);

if (!$res) {
    die("❌ Database error: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($res);

if (!$data) {
    die("❌ Product not found");
}


// =====================================================
// UPDATE / RESTOCK
// =====================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    mysqli_begin_transaction($conn);

    try {

        // Product information
        $pname = mysqli_real_escape_string(
            $conn,
            $_POST['pname']
        );

        $pdesc = mysqli_real_escape_string(
            $conn,
            $_POST['pdesc']
        );

        $unit = mysqli_real_escape_string(
            $conn,
            $_POST['unit']
        );


        // Quantities
        $qty = floatval($_POST['qty']);

        $qtyperunit = floatval($_POST['qpu']);


        // Previous unit price
        $oldunitprice = floatval($_POST['oldunitprice']);


        // Selling price
        $sellingprice = floatval($_POST['sellingprice']);


        // Quantity alert
        $qtyalert = intval($_POST['qtyalert']);


        // Price change
        $price_change = $_POST['price_change'] ?? 'no';


        // =================================================
        // DEFAULT VALUES
        // =================================================

        $final_unitprice = $oldunitprice;

        $costperunit = $data['costperunit'];


        // =================================================
        // IF PRICE HAS CHANGED
        // =================================================

        if ($price_change == "yes") {

            if (empty($_POST['unitprice'])) {
                throw new Exception("Enter new cost");
            }

            $new_unitprice = floatval($_POST['unitprice']);

            $final_unitprice = $new_unitprice;

            $costperunit = $new_unitprice / $qtyperunit;
        }


        // =================================================
        // STOCK CALCULATION
        // =================================================

        $newqty = $qty + $data['qty'];

        $newstock =
            ($qty * $qtyperunit)
            + $data['totalstock'];


        // =================================================
        // PURCHASE VALUES
        // =================================================

        $totalpurchase =
            $qty * $final_unitprice;

        $totalqty =
            $qty * $qtyperunit;


        // =================================================
        // UPDATE PRODUCTS
        // =================================================

        if ($price_change == "yes") {

            // Update selling price + cost

            $update = mysqli_query(
                $conn,
                "
                UPDATE products SET

                    qty='$newqty',

                    totalstock='$newstock',

                    unitprice='$final_unitprice',

                    costperunit='$costperunit',

                    sellingprice='$sellingprice'

                WHERE productid='$productid'
                "
            );

        } else {

            // Keep old selling price

            $sellingprice = $data['sellingprice'];

            $update = mysqli_query(
                $conn,
                "
                UPDATE products SET

                    qty='$newqty',

                    totalstock='$newstock',

                    unitprice='$final_unitprice',

                    costperunit='$costperunit'

                WHERE productid='$productid'
                "
            );
        }


        if (!$update) {
            throw new Exception(mysqli_error($conn));
        }


        // =================================================
        // INSERT PURCHASE HISTORY
        // =================================================

        $insert = mysqli_query(
            $conn,
            "
            INSERT INTO purchase_items
            (
                productid,
                pname,
                pdesc,
                unit,
                qty,
                unitprice,
                sellingprice,
                qtyalert,
                type,
                totalqty,
                totalpurchase
            )

            VALUES
            (
                '$productid',
                '$pname',
                '$pdesc',
                '$unit',
                '$qty',
                '$final_unitprice',
                '$sellingprice',
                '$qtyalert',
                'restock',
                '$totalqty',
                '$totalpurchase'
            )
            "
        );


        if (!$insert) {
            throw new Exception(mysqli_error($conn));
        }


        // =================================================
        // COMMIT
        // =================================================

        mysqli_commit($conn);

        $msg = "Successfully Updated.";

        header("refresh:2; url=products.php");


    } catch (Exception $e) {

        mysqli_rollback($conn);

        $errmsg = $e->getMessage();
    }
}

?>


<!DOCTYPE html>

<html>

<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>

        /* =====================================================
           PAGE STYLING
        ===================================================== */

        .product-info-section {
            padding: 5px 0;
        }

        .product-info-section .form-group {
            margin-bottom: 20px;
        }

        .product-info-section h5 {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .product-info-section p {
            margin-bottom: 0;
            color: #6c757d;
            font-size: 15px;
        }

        .section-title {
            font-size: 17px;
            font-weight: 600;
            padding-bottom: 10px;
            margin-top: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }

        .restock-section {
            margin-top: 25px;
            padding-top: 10px;
        }

        .restock-section label {
            font-weight: 500;
        }

        .card-footer {
            padding: 15px 20px;
        }

        .btn-primary {
            min-width: 100px;
        }

        .alert {
            margin-top: 15px;
        }

        @media (max-width: 767px) {

            .card-body {
                padding: 15px;
            }

            .section-title {
                margin-top: 15px;
            }

            .product-info-section h5 {
                font-size: 15px;
            }

            .product-info-section p {
                font-size: 14px;
            }

        }

    </style>

</head>


<body class="fixed-left">


    <!-- =====================================================
         LOADER
    ====================================================== -->

    <div id="preloader">

        <div id="status">

            <div class="spinner"></div>

        </div>

    </div>


    <!-- =====================================================
         BEGIN PAGE
    ====================================================== -->

    <div id="wrapper">


        <!-- =================================================
             LEFT SIDEBAR
        ================================================== -->

        <?php include "assets/sections/leftside.php" ?>


        <!-- =================================================
             RIGHT CONTENT
        ================================================== -->

        <div class="content-page">

            <div class="content">


                <!-- =================================================
                     TOP BAR
                ================================================== -->

                <?php include "assets/sections/topbar.php" ?>


                <!-- =================================================
                     PAGE CONTENT
                ================================================== -->

                <div class="page-content-wrapper">

                    <div class="container-fluid">

                        <br>


                        <div class="row">

                            <div class="col-lg-12">


                                <!-- =================================================
                                     MAIN CARD
                                ================================================== -->

                                <div class="card m-b-30">


                                    <div class="card-body bootstrap-select-1">


                                        <!-- =================================================
                                             PAGE TITLE
                                        ================================================== -->

                                        <h2>Restock Item</h2>


                                        <!-- =================================================
                                             SUCCESS MESSAGE
                                        ================================================== -->

                                        <?php if (isset($msg)) { ?>

                                            <div
                                                class="alert alert-success alert-dismissible fade show"
                                                role="alert"
                                            >

                                                <button
                                                    type="button"
                                                    class="close"
                                                    data-dismiss="alert"
                                                    aria-label="Close"
                                                >

                                                    <span aria-hidden="true">
                                                        &times;
                                                    </span>

                                                </button>

                                                <?php echo htmlspecialchars(
                                                    $msg,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>

                                            </div>

                                        <?php } ?>


                                        <!-- =================================================
                                             ERROR MESSAGE
                                        ================================================== -->

                                        <?php if (isset($errmsg)) { ?>

                                            <div
                                                class="alert alert-danger alert-dismissible fade show"
                                                role="alert"
                                            >

                                                <button
                                                    type="button"
                                                    class="close"
                                                    data-dismiss="alert"
                                                    aria-label="Close"
                                                >

                                                    <span aria-hidden="true">
                                                        &times;
                                                    </span>

                                                </button>

                                                <?php echo htmlspecialchars(
                                                    $errmsg,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>

                                            </div>

                                        <?php } ?>


                                        <!-- =================================================
                                             FORM
                                        ================================================== -->

                                        <form
                                            method="post"
                                            action=""
                                        >


                                            <!-- =================================================
                                                 HIDDEN VALUES
                                            ================================================== -->

                                            <input
                                                type="hidden"
                                                name="pname"
                                                value="<?php echo htmlspecialchars(
                                                    $data['pname'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                required
                                                readonly
                                            >


                                            <input
                                                type="hidden"
                                                name="pdesc"
                                                value="<?php echo htmlspecialchars(
                                                    $data['pdesc'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                required
                                                readonly
                                            >


                                            <input
                                                type="hidden"
                                                name="unit"
                                                value="<?php echo htmlspecialchars(
                                                    $data['unit'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                required
                                                readonly
                                            >


                                            <input
                                                type="hidden"
                                                name="qpu"
                                                value="<?php echo htmlspecialchars(
                                                    $data['qtyperunit'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                min="1"
                                                required
                                            >


                                            <input
                                                type="hidden"
                                                name="qtyalert"
                                                value="<?php echo htmlspecialchars(
                                                    $data['qtyalert'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                min="1"
                                                required
                                            >


                                            <input
                                                type="hidden"
                                                name="oldunitprice"
                                                value="<?php echo htmlspecialchars(
                                                    $data['unitprice'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                min="1"
                                                required
                                            >


                                            <input
                                                type="hidden"
                                                name="sellingprice"
                                                value="<?php echo htmlspecialchars(
                                                    $data['sellingprice'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                required
                                            >


                                            <!-- =================================================
                                                 PRODUCT INFORMATION
                                            ================================================== -->

                                            <div class="card-body product-info-section">


                                                <div class="section-title">
                                                    Product Information
                                                </div>


                                                <!-- PRODUCT NAME -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Product Name
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        <?php echo htmlspecialchars(
                                                            $data['pname'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </p>

                                                </div>


                                                <!-- DESCRIPTION -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Product Description
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        <?php echo htmlspecialchars(
                                                            $data['pdesc'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </p>

                                                </div>


                                                <!-- CATEGORY -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Category
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        <?php echo htmlspecialchars(
                                                            $data['category'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </p>

                                                </div>


                                                <!-- UNIT -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Unit Measure
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        <?php echo htmlspecialchars(
                                                            $data['unit'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    </p>

                                                </div>


                                                <!-- QUANTITY PER UNIT -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Quantity per Unit
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        <?php echo htmlspecialchars(
                                                            $data['qtyperunit'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                        pieces

                                                    </p>

                                                </div>


                                                <!-- PREVIOUS COST -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Previous Cost
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        GH¢

                                                        <?php echo number_format(
                                                            (float)$data['unitprice'],
                                                            2
                                                        ); ?>

                                                    </p>

                                                </div>


                                                <!-- SELLING PRICE -->

                                                <div class="form-group">

                                                    <h5 class="mb-1">
                                                        Selling Price
                                                    </h5>

                                                    <p class="text-muted mb-3">

                                                        GH¢

                                                        <?php echo number_format(
                                                            (float)$data['sellingprice'],
                                                            2
                                                        ); ?>

                                                    </p>

                                                </div>


                                            </div>


                                            <!-- =================================================
                                                 RESTOCK INFORMATION
                                            ================================================== -->

                                            <div class="card-body restock-section">


                                                <div class="section-title">
                                                    Restock Information
                                                </div>


                                                <!-- QUANTITY -->

                                                <div class="form-group">

                                                    <label>
                                                        Quantity Purchased
                                                    </label>

                                                    <input
                                                        type="number"
                                                        min="0.25"
                                                        step="0.01"
                                                        class="form-control"
                                                        name="qty"
                                                        required
                                                    >

                                                </div>


                                                <!-- PRICE CHANGE -->

                                                <div class="form-group">

                                                    <label>
                                                        Price Change?
                                                    </label>

                                                    <select
                                                        class="form-control"
                                                        name="price_change"
                                                        id="price_change"
                                                        onchange="togglePrice()"
                                                    >

                                                        <option value="no">
                                                            No
                                                        </option>

                                                        <option value="yes">
                                                            Yes
                                                        </option>

                                                    </select>

                                                </div>


                                                <!-- =================================================
                                                     NEW COST
                                                ================================================== -->

                                                <div
                                                    class="form-group"
                                                    id="costBox"
                                                    style="display:none;"
                                                >

                                                    <label>
                                                        New Cost
                                                    </label>


                                                    <div class="input-group">

                                                        <div class="input-group-prepend">

                                                            <div class="input-group-text">
                                                                GH¢
                                                            </div>

                                                        </div>


                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            class="form-control currency"
                                                            name="unitprice"
                                                        >

                                                    </div>


                                                    <!-- =================================================
                                                         SELLING PRICE
                                                    ================================================== -->

                                                    <div class="form-group mt-3">

                                                        <label>
                                                            Selling Price
                                                        </label>


                                                        <div class="input-group">

                                                            <div class="input-group-prepend">

                                                                <div class="input-group-text">
                                                                    GH¢
                                                                </div>

                                                            </div>


                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                class="form-control currency"
                                                                name="sellingprice"
                                                            >

                                                        </div>

                                                    </div>


                                                </div>


                                            </div>


                                            <!-- =================================================
                                                 FOOTER / UPDATE BUTTON
                                            ================================================== -->

                                            <div class="card-footer">

                                                <button
                                                    class="btn btn-primary"
                                                    type="submit"
                                                >

                                                    Update

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


            <!-- =================================================
                 FOOTER
            ================================================== -->

            <footer class="footer">

                <?php include "assets/sections/footers/footer.php" ?>

            </footer>


        </div>

    </div>


    <!-- =====================================================
         JAVASCRIPT
    ====================================================== -->

    <?php include "assets/sections/footers/jqueryscripts.php" ?>


    <script>

        function togglePrice() {

            let val =
                document.getElementById('price_change').value;

            let box =
                document.getElementById('costBox');


            if (val === "yes") {

                box.style.display = "block";

            } else {

                box.style.display = "none";

            }

        }

    </script>


</body>

</html>