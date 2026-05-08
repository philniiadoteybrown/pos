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
    $sellingprice = $_POST['sellingprice'];
    $sellingpricebulk = $_POST['sellingpricebulk'];
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
    $costperunit = $unitprice / $qtyperunit;
    $stock = $qty * $qtyperunit;
    $tp = $qty * $unitprice;

    mysqli_begin_transaction($conn);

    try {

        // 🔄 UPDATE PRODUCTS
        mysqli_query($conn,"
            UPDATE products SET
            pname='$pname',
            pdesc='$pdesc',
            unit='$unit',
            qty='$qty',
            unitprice='$unitprice',
            sellingprice='$sellingprice',
            bulkprice='$sellingpricebulk',
            qtyalert='$qtyalert',
            category='$catname',
            qtyperunit='$qtyperunit',
            costperunit='$costperunit',
            totalstock='$stock'
            WHERE productid='$productid'
        ");

        // 🔄 UPDATE PURCHASE ITEMS
        mysqli_query($conn,"
            UPDATE purchase_items SET
            pname='$pname',
            pdesc='$pdesc',
            unit='$unit',
            qty='$qty',
            unitprice='$unitprice',
            sellingprice='$sellingprice',
            qtyalert='$qtyalert',
            totalqty='$stock',
            totalpurchase='$tp',
            bulkprice='$sellingpricebulk'
            WHERE productid='$productid'
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
                                        <h2>Edit Product</h2>
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

                                            <div class="card-body">

                                                <div class="form-group">
                                                    <label>Product Name</label>
                                                    <input type="text" class="form-control" name="pname"
                                                        value="<?php echo $data['pname']; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Product Description</label>
                                                    <input type="text" class="form-control" name="pdesc"
                                                        value="<?php echo $data['pdesc']; ?>" required>
                                                </div>

                                                <div class="section-title">Unit Measure</div>

                                                <div class="form-group">
                                                    <label>Select Measure</label>
                                                    <select class="form-control" name="unit">
                                                        <option value="<?php echo $data['unit']; ?>">
                                                            <?php echo $data['unit']; ?></option>
                                                        <option value="Crate(s)">Crate</option>
                                                        <option value="Carton(s)">Carton</option>
                                                        <option value="Dozen(s)">Dozen</option>
                                                        <option value="Sack(s)">Sack</option>
                                                        <option value="Litres">Litres</option>
                                                        <option value="kg(s)">KG</option>
                                                        <option value="Piece(s)">Pieces</option>
                                                        <option value="Box(es)">Box</option>
                                                        <option value="Packs">Packs</option>
                                                        <option value="Rows">Rows</option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label>Quantity per Unit</label>
                                                    <input type="number" class="form-control" name="qpu"
                                                        value="<?php echo $data['qtyperunit']; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Select Category</label>
                                                    <select name="category_select" class="form-control">
                                                        <option value="">Select Category</option>
                                                        <?php
$res=mysqli_query($conn,"SELECT * FROM category");
while($c=mysqli_fetch_assoc($res)){
$selected = ($c['catname'] == $data['category']) ? "selected" : "";
echo "<option value='{$c['catname']}' $selected>{$c['catname']}</option>";
}
?>
                                                    </select>

                                                    <br>
                                                    <label>Or Add New :</label><br>
                                                    <input class="form-control" type="text" name="new_category"
                                                        placeholder="Category name">
                                                </div>

                                                <div class="form-group">
                                                    <label>Unit Cost</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">GH¢</div>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control"
                                                            name="unitprice" value="<?php echo $data['unitprice']; ?>"
                                                            required>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Quantity Purchased</label>
                                                    <input type="number" class="form-control" name="qty"
                                                        value="<?php echo $data['qty']; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Selling Price (Single)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">GH¢</div>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control"
                                                            name="sellingprice"
                                                            value="<?php echo $data['sellingprice']; ?>" required>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Selling Price (Bulk)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">GH¢</div>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control"
                                                            name="sellingpricebulk"
                                                            value="<?php echo $data['bulkprice']; ?>" required>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Quantity Alert</label>
                                                    <input type="number" class="form-control" name="qtyalert"
                                                        value="<?php echo $data['qtyalert']; ?>" required>
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
</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>