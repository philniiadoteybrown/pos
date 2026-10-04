<?php
$pagetitle="Add Products";
include "assets/scripts/auth.php";

include "assets/scripts/dbconn.php";

// 🔍 GET ID
if(!isset($_GET['id']) || empty($_GET['id'])){
    die("❌ No product selected");
}

$productid = mysqli_real_escape_string($conn, $_GET['id']);

// 🔍 FETCH DATA
$res = mysqli_query($conn,"SELECT * FROM products WHERE productid='$productid'");

if(!$res){
    die("❌ Database error: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($res);

if(!$data){
    die("❌ Product not found");
}


// ================= UPDATE =================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // ONLY THESE TWO VALUES CAN BE EDITED
    $qty = filter_var($_POST['qty'] ?? null, FILTER_VALIDATE_FLOAT);
    $qtyalert = filter_var($_POST['qtyalert'] ?? null, FILTER_VALIDATE_FLOAT);

    if($qty === false || $qty < 0){
        $errmsg = "Quantity Purchased must be a valid number.";
    }
    elseif($qtyalert === false || $qtyalert < 0){
        $errmsg = "Quantity Alert must be a valid number.";
    }

    if(!isset($errmsg)){

        // 🔒 ALL OTHER VALUES COME FROM DATABASE
        $qtyperunit = (float)$data['qtyperunit'];
        $unitprice  = (float)$data['unitprice'];

        // 🧮 CALCULATIONS
        $stock = $qty * $qtyperunit;
        $tp = $qty * $unitprice;

        mysqli_begin_transaction($conn);

        try {

            // 🔄 UPDATE PRODUCTS
            mysqli_query($conn,"
                UPDATE products SET
                    qty='$qty',
                    qtyalert='$qtyalert',
                    totalstock='$stock'
                WHERE productid='$productid'
            ");

            if(mysqli_affected_rows($conn) < 0){
                throw new Exception(mysqli_error($conn));
            }

            // 🔄 UPDATE PURCHASE ITEMS
            $purchaseUpdate = mysqli_query($conn,"
                UPDATE purchase_items SET
                    qty='$qty',
                    qtyalert='$qtyalert',
                    totalqty='$stock',
                    totalpurchase='$tp'
                WHERE productid='$productid'
            ");

            if(!$purchaseUpdate){
                throw new Exception(mysqli_error($conn));
            }

            mysqli_commit($conn);

            $msg = "Successfully Updated.";

            // Reload data
            $reload = mysqli_query(
                $conn,
                "SELECT * FROM products WHERE productid='$productid' LIMIT 1"
            );

            if($reload){
                $data = mysqli_fetch_assoc($reload);
            }

            header('refresh:2; url=products.php');

        } catch(Exception $e){

            mysqli_rollback($conn);

            $errmsg = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>

<html>

<head>


<?php include "assets/sections/headers/header_tag.php" ?>


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
                        <br>
                        <div class="row">
                            <div class="col-lg-12">


                            <div class="card m-b-30">
                                <div class="card-body bootstrap-select-1">

                                    <h2>Edit Product</h2>

                                    <?php if(isset($msg)){ ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <button type="button" class="close" data-dismiss="alert"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <?php } ?>

                                    <?php if(isset($errmsg)){ ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <button type="button" class="close" data-dismiss="alert"
                                            aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <?php echo htmlspecialchars($errmsg, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <?php } ?>

                                    <form method="post" action="">

                                        <div class="card-body">

                                            <!-- PRODUCT NAME -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Product Name</h5>
                                                <p class="text-muted mb-3">
                                                    <?php echo htmlspecialchars($data['pname'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>

                                            <!-- DESCRIPTION -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Product Description</h5>
                                                <p class="text-muted mb-3">
                                                    <?php echo htmlspecialchars($data['pdesc'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>

                                            <div class="section-title">Unit Measure</div>

                                            <!-- UNIT -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Unit Measure</h5>
                                                <p class="text-muted mb-3">
                                                    <?php echo htmlspecialchars($data['unit'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>

                                            <!-- QPU -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Quantity per Unit</h5>
                                                <p class="text-muted mb-3">
                                                    <?php echo htmlspecialchars($data['qtyperunit'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>

                                            <!-- CATEGORY -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Category</h5>
                                                <p class="text-muted mb-3">
                                                    <?php echo htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>

                                            <!-- UNIT COST -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Unit Cost</h5>
                                                <p class="text-muted mb-3">
                                                    GH¢ <?php echo number_format((float)$data['unitprice'], 2); ?>
                                                </p>
                                            </div>

                                             <!-- SELLING PRICE -->
                                            <div class="form-group">
                                                <h5 class="mb-1">Selling Price</h5>
                                                <p class="text-muted mb-3">
                                                    GH¢ <?php echo number_format((float)$data['sellingprice'], 2); ?>
                                                </p>
                                            </div>
                                            <!-- EDITABLE QUANTITY -->
                                            <div class="form-group">
                                                <label>Quantity Purchased</label>
                                                <input type="number"
                                                    class="form-control"
                                                    name="qty"
                                                    value="<?php echo htmlspecialchars($data['qty'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    min="0"
                                                    step="any"
                                                    required>
                                            </div>

                                           

                                            <!-- EDITABLE QUANTITY ALERT -->
                                            <div class="form-group">
                                                <label>Quantity Alert</label>
                                                <input type="number"
                                                    class="form-control"
                                                    name="qtyalert"
                                                    value="<?php echo htmlspecialchars($data['qtyalert'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    min="0"
                                                    step="any"
                                                    required>
                                            </div>

                                        </div>

                                        <div class="card-footer">
                                            <button class="btn btn-primary" type="submit">
                                                Update
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


</body>

</html>
