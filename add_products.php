<?php
$pagetitle="Add Products";
include "assets/scripts/auth.php";

include "assets/scripts/dbconn.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get form data
    $pname = mysqli_real_escape_string($conn, $_POST['pname']);
    $pdesc = mysqli_real_escape_string($conn, $_POST['pdesc']);
    //$unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $qty = $_POST['qty'];
    $qtyperunit = $_POST['qpu'];
    $unitprice = $_POST['unitprice'];
    $sellingprice = $_POST['sellingprice'];
    $qtyalert = $_POST['qtyalert'];

    

    // 👤 Create category if new
        $category_select = $_POST['category_select'] ?? '';
$new_category    = $_POST['new_category'] ?? '';

if(!empty($new_category)){

    $catname = mysqli_real_escape_string($conn, $new_category);

    // insert new category
    mysqli_query($conn,"INSERT INTO category(catname) VALUES('$catname')");

} else {

    $catname = mysqli_real_escape_string($conn, $category_select);
}


    // 👤 Create unit if new
        $unit_select = $_POST['unit_select'] ?? '';
$new_unit    = $_POST['new_unit'] ?? '';

if(!empty($new_unit)){

    $unitname = mysqli_real_escape_string($conn, $new_unit);

    // insert new unit
    mysqli_query($conn,"INSERT INTO units(unit_name) VALUES('$unitname')");

} else {

    $unitname = mysqli_real_escape_string($conn, $unit_select);
}


$res=mysqli_query($conn,"SELECT productid FROM products ORDER BY productid DESC LIMIT 1");

if(mysqli_num_rows($res)){
$row=mysqli_fetch_assoc($res);
$num=(int)substr($row['productid'],3)+1;
$productid="PRD".str_pad($num,4,"0",STR_PAD_LEFT);
}else{
$productid="PRD0001";
}

$costperunit=$unitprice/$qtyperunit;
$stock=$qty*$qtyperunit;
$tp=$qty*$unitprice;
    // Insert query
    $sql = "INSERT INTO products (productid, pname, pdesc, unit, qty, unitprice, sellingprice, qtyalert,category,qtyperunit,costperunit, totalstock, created_at)
            VALUES ('$productid','$pname', '$pdesc', '$unitname', '$qty', '$unitprice', '$sellingprice', '$qtyalert','$catname','$qtyperunit','$costperunit','$stock', NOW())";

    if (mysqli_query($conn, $sql)) {
        $msg="Successfully Saved.";
header('refresh:2; url=add_products.php');

mysqli_query($conn,"
INSERT INTO purchase_items 
(productid, pname, pdesc, unit, qty, unitprice, sellingprice, qtyalert, type, totalqty, totalpurchase)
VALUES
('$productid','$pname','$pdesc','$unitname','$qty','$unitprice','$sellingprice','$qtyalert','initial','$stock','$tp')
");


mysqli_query($conn,"
            INSERT INTO units(product_id, unit_name, unit_qty, price)
            VALUES('$productid','Piece','1','$sellingprice')
        ");

    } else {
         $errmsg="Data not Saved.";
header('refresh:2; url=add_products.php');
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
                                        <h2>Add Product</h2>
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
                                                    <input type="text" class="form-control" name="pname" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Product Description</label>
                                                    <input type="text" class="form-control" name="pdesc" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Select Category</label>
                                                    <select name="category_select" class="form-control">
                                                        <option value="">Select Category</option>
                                                        <?php
                                                        $res=mysqli_query($conn,"SELECT * FROM category");
                                                        while($c=mysqli_fetch_assoc($res)){
                                                        echo "<option value='{$c['catname']}'>{$c['catname']}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                    <br>
                                                    <label>Or Add New :</label><br>
                                                    <input class="form-control" type="text" name="new_category"
                                                        placeholder="Category name">
                                                </div>
                                                <div class="section-title">Unit Measure</div>
                                                <div class="form-group">
                                                    <label>Select Measure</label>

                                                    <select name="unit_select" class="form-control">
                                                        <option value="">Select Unit</option>
                                                        <?php
                                                        $res = mysqli_query($conn,"SELECT DISTINCT unitname FROM item_units ");
                                                        while($c=mysqli_fetch_assoc($res)){
                                                        echo "<option value='{$c['unitname']}'>{$c['unitname']}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                    <br>
                                                    <label>Or Add New :</label><br>
                                                    <input class="form-control" type="text" name="new_unit"
                                                        placeholder="Unit name">
                                                </div>
                                                <div class="form-group">
                                                    <label>Quantity per Unit</label>
                                                    <input type="number" class="form-control" name="qpu" min="1"
                                                        required>
                                                </div>


                                                <div class="form-group">
                                                    <label>Unit Cost</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                GH¢
                                                            </div>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control currency"
                                                            name="unitprice" required>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Quantity Purchased</label>
                                                    <input type="number" class="form-control" name="qty" min="0"
                                                        step="0.01" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Selling Price (Per Piece)</label>
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

                                                <!-- <div class="section-title">Upload Image</div>
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="customFile">
                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                            </div> -->
                                                <div class="form-group">
                                                    <label>Quantity Alert</label>
                                                    <input type="number" class="form-control" name="qtyalert" min="1"
                                                        required>
                                                </div>

                                            </div>
                                            <div class="card-footer">
                                                <button class="btn btn-primary" type="submit"
                                                    name="addproducts">Submit</button>
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