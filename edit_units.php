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

            mysqli_query($conn,"
                INSERT INTO units (product_id, unit_name, unit_qty, price)
                VALUES ('$product_id','$name','$qty','$price')
            ");
        }
    }

    if($ok){
        $msg = "Units updated successfully";
    } else {
        $errmsg = "Some updates failed: " . mysqli_error($conn);
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
                                        <h2>Edit Unit for <?php echo $product['pname']." - ".$product['pdesc'] ?></h2>
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
                                        <form method="POST">

                                            <table class="table table-bordered">

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

                                                        <input type="hidden" name="id[]" value="<?= $u['id'] ?>">

                                                        <td>
                                                            <input type="text" name="unit_name[]" class="form-control"
                                                                value="<?= $u['unit_name'] ?>">
                                                        </td>

                                                        <td>
                                                            <input type="number" name="unit_qty[]" class="form-control"
                                                                value="<?= $u['unit_qty'] ?>">
                                                        </td>

                                                        <td>
                                                            <input type="number" name="price[]" class="form-control"
                                                                value="<?= $u['price'] ?>">
                                                        </td>

                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm"
                                                                onclick="this.closest('tr').remove()">
                                                                X
                                                            </button>
                                                        </td>

                                                    </tr>

                                                    <?php } ?>

                                                </tbody>

                                            </table>

                                            <button type="button" class="btn btn-secondary" onclick="addRow()">
                                                + Add Unit
                                            </button>

                                            <button type="submit" class="btn btn-primary">
                                                Update All
                                            </button>

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
    function addRow() {

        let row = `
<tr>

<input type="hidden" name="id[]" value="">

<td><input type="text" name="unit_name[]" class="form-control"></td>
<td><input type="number" name="unit_qty[]" class="form-control"></td>
<td><input type="number" name="price[]" class="form-control"></td>

<td>
<button type="button" class="btn btn-danger btn-sm"
onclick="this.closest('tr').remove()">X</button>
</td>

</tr>
`;

        document.querySelector("tbody").insertAdjacentHTML("beforeend", row);

    }
    </script>

</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>