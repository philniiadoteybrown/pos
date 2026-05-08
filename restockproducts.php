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


// ================= UPDATE =================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $pname = mysqli_real_escape_string($conn, $_POST['pname']);
    $pdesc = mysqli_real_escape_string($conn, $_POST['pdesc']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $qty = $_POST['qty'];
    $qtyperunit = $_POST['qpu'];
    $unitprice = $_POST['unitprice'];
    $oldunitprice = $_POST['oldunitprice'];
    $sellingprice = $_POST['sellingprice'];
    //$sellingpricebulk = $_POST['sellingpricebulk'];
    $qtyalert = $_POST['qtyalert'];

    // ✅ CATEGORY FIX (same logic as your working version)
    $category_select = $_POST['category_select'] ?? '';
    $new_category    = $_POST['new_category'] ?? '';

    if(!empty($new_category)){
        $catname = mysqli_real_escape_string($conn, $new_category);
        mysqli_query($conn,"INSERT INTO category(catname) VALUES('$catname')");
    } else {
        $catname = mysqli_real_escape_string($conn, $category_select);
    }

    // 🧮 CALCULATIONS (same logic as add product)
    // 🔥 NEW FIELD
$price_change = $_POST['price_change'] ?? 'no';

if($price_change == "yes" && empty($_POST['unitprice'])){
    throw new Exception("Enter new cost when price change is YES");
}

$new_unitprice = floatval($_POST['unitprice']);
$old_unitprice = floatval($_POST['oldunitprice']);

$qty = floatval($_POST['qty']);
$qtyperunit = floatval($_POST['qpu']);

// 🧮 STOCK CALCULATION
$newqty   = $qty + $data['qty'];
$newstock = ($qty * $qtyperunit) + $data['totalstock'];

// 💰 PURCHASE VALUES
$totalpurchase = $qty * $new_unitprice;
$totalqty      = $qty * $qtyperunit;

// 🔥 PRICE LOGIC
if($price_change == "yes"){
    $final_unitprice = $new_unitprice;
    $costperunit = $new_unitprice / $qtyperunit;
} else {
    $final_unitprice = $old_unitprice;
    $costperunit = $data['costperunit'];
}

    try {

        // 🔄 UPDATE PRODUCTS
        mysqli_query($conn,"
    UPDATE products SET
    qty='$newqty',
    sellingprice=$sellingprice,
    totalstock='$newstock',
    unitprice='$final_unitprice',
    costperunit='$costperunit'
    WHERE productid='$productid'
");

        // 🔄 UPDATE PURCHASE ITEMS
  mysqli_query($conn,"
INSERT INTO purchase_items 
(productid, pname, pdesc, unit, qty, unitprice, sellingprice, qtyalert, type, totalqty, totalpurchase)
VALUES
('$productid','$pname','$pdesc','$unit','$qty','$final_unitprice','$sellingprice','$qtyalert','restock','$totalqty','$totalpurchase')
");

        mysqli_commit($conn);

        $msg="Successfully Updated.";
        header('refresh:2; url=products.php');

    } catch(Exception $e){

        mysqli_rollback($conn);
        $errmsg="Update failed.";
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
                                        <h1><?php echo "Restock Item" ?></h1>
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
                                        <form method="post" action="">
                                            <input type="hidden" class="form-control" name="pname"
                                                value="<?php echo $data['pname']; ?>" required readonly>

                                            <input type="hidden" class="form-control" name="pdesc"
                                                value="<?php echo $data['pdesc']; ?>" required readonly>

                                            <input type="hidden" class="form-control" name="unit"
                                                value="<?php echo $data['unit']; ?>" required readonly>

                                            <input type="hidden" class="form-control"
                                                value="<?php echo $data['qtyperunit']; ?>" name="qpu" min="1" required>

                                            <input type="hidden" class="form-control"
                                                value="<?php echo $data['qtyalert']; ?>" name="qtyalert" min="1"
                                                required>

                                            <input type="hidden" class="form-control"
                                                value="<?php echo $data['unitprice']; ?>" name="oldunitprice" min="1"
                                                required>
                                            <input type="hidden" step="0.01" class="form-control currency"
                                                name="sellingprice" value="<?php echo $data['sellingprice']; ?>"
                                                required>



                                            <div class=" card-body">

                                                <div class="form-group">
                                                    <label>
                                                        <h3 style="font-weight: bold;">Product Name
                                                            : <?php echo $data['pname']; ?></h3>
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <h3 style="font-weight: bold;">Description
                                                            : <?php echo $data['pdesc']; ?></h3>
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <h3 style="font-weight: bold;">Previous Cost
                                                            : <?php echo "GH¢ ". $data['unitprice']; ?></h3>
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <h3 style="font-weight: bold;">Unit of Measure
                                                            : <?php echo $data['unit']; ?></h3>
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <h3 style="font-weight: bold;">Quantity per Unit
                                                            : <?php echo $data['qtyperunit'] ." pieces"; ?></h3>
                                                    </label>
                                                </div>
                                                <div class="form-group">
                                                    <label>
                                                        <h3 style="font-weight: bold;">Category
                                                            : <?php echo $data['category']; ?></h3>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Quantity Purchased</label>
                                                <input type="number" min="0.25" step="0.01" class="form-control"
                                                    name="qty" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Price Change?</label>
                                                <select class="form-control" name="price_change" id="price_change"
                                                    onchange="togglePrice()">
                                                    <option value="no">No</option>
                                                    <option value="yes">Yes</option>
                                                </select>
                                            </div>
                                            <div class="form-group" id="costBox" style="display:none;">
                                                <label>New Cost</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">GH¢</div>
                                                    </div>
                                                    <input type="number" step="0.01" class="form-control currency"
                                                        name="unitprice">
                                                </div>

                                                <div class="form-group">
                                                    <label>Selling Price (Single)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                GH¢
                                                            </div>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control currency"
                                                            name="sellingprice" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Selling Price (Bulk)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                GH¢
                                                            </div>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control currency"
                                                            name="sellingpricebulk" required>
                                                    </div>
                                                </div>
                                            </div>





                                    </div>

                                    <div class="card-footer">
                                        <button class="btn btn-primary" type="submit">Update</button>
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
    <footer class="footer"><?php include "assets/sections/footers/footer.php" ?></footer>
    </div><!-- End Right content here -->
    </div><!-- END wrapper -->
    <!-- jQuery  -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>
    <script>
    function togglePrice() {
        let val = document.getElementById('price_change').value;
        let box = document.getElementById('costBox');

        if (val === "yes") {
            box.style.display = "block";
        } else {
            box.style.display = "none";
        }
    }
    </script>
</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>