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


if(isset($_POST['save_units'])) {

   foreach($_POST['unit_name'] as $i => $unit_name){

    $name  = mysqli_real_escape_string($conn, trim($unit_name));
    $qty   = floatval($_POST['unit_qty'][$i]);
    $price = floatval($_POST['price'][$i]);

    if($name == "") continue;

    // 🔥 INSERT INTO product_units
    mysqli_query($conn,"
        INSERT INTO units(product_id, unit_name, unit_qty, price)
        VALUES('$productid','$name','$qty','$price')
    ");

    // 🔥 ALSO INSERT INTO item_units (if not exists)
    $check = mysqli_query($conn,"
        SELECT id FROM item_units WHERE unitname='$name'
    ");

    if(mysqli_num_rows($check) == 0){
        mysqli_query($conn,"
            INSERT INTO item_units(unitname) VALUES('$name')
        ");
    }
}
   
     $msg="All units saved successfully";
        header('refresh:2; url=products.php');
}

// ================= UPDATE =================
// if ($_SERVER['REQUEST_METHOD'] == 'POST') {

//     $pname = mysqli_real_escape_string($conn, $_POST['pname']);
//     $pdesc = mysqli_real_escape_string($conn, $_POST['pdesc']);
//     $unit = mysqli_real_escape_string($conn, $_POST['unit']);
//     $qty = $_POST['qty'];
//     $qtyperunit = $_POST['qpu'];
//     $unitprice = $_POST['unitprice'];
//     $sellingprice = $_POST['sellingprice'];
//     $sellingpricebulk = $_POST['sellingpricebulk'];
//     $qtyalert = $_POST['qtyalert'];

//     // ✅ CATEGORY FIX (same logic as your working version)
//     $category_select = $_POST['category_select'] ?? '';
//     $new_category    = $_POST['new_category'] ?? '';

//     if(!empty($new_category)){
//         $catname = mysqli_real_escape_string($conn, $new_category);
//         mysqli_query($conn,"INSERT INTO category(catname) VALUES('$catname')");
//     } else {
//         $catname = mysqli_real_escape_string($conn, $category_select);
//     }

//     // 🧮 CALCULATIONS (same logic as add product)
//     $costperunit = $unitprice / $qtyperunit;
//     $stock = $qty * $qtyperunit;
//     $tp = $qty * $unitprice;

//     mysqli_begin_transaction($conn);

//     try {

//         // 🔄 UPDATE PRODUCTS
//         mysqli_query($conn,"
//             UPDATE products SET
//             pname='$pname',
//             pdesc='$pdesc',
//             unit='$unit',
//             qty='$qty',
//             unitprice='$unitprice',
//             sellingprice='$sellingprice',
//             bulkprice='$sellingpricebulk',
//             qtyalert='$qtyalert',
//             category='$catname',
//             qtyperunit='$qtyperunit',
//             costperunit='$costperunit',
//             totalstock='$stock'
//             WHERE productid='$productid'
//         ");

//         // 🔄 UPDATE PURCHASE ITEMS
//         mysqli_query($conn,"
//             UPDATE purchase_items SET
//             pname='$pname',
//             pdesc='$pdesc',
//             unit='$unit',
//             qty='$qty',
//             unitprice='$unitprice',
//             sellingprice='$sellingprice',
//             qtyalert='$qtyalert',
//             totalqty='$stock',
//             totalpurchase='$tp',
//             bulkprice='$sellingpricebulk'
//             WHERE productid='$productid'
//         ");

//         mysqli_commit($conn);

//         $msg="Successfully Updated.";
//         header('refresh:2; url=products.php');

//     } catch(Exception $e){

//         mysqli_rollback($conn);
//         $errmsg="Update failed.";
//     }
// }
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
                                        <h2><?php echo "Add More Units for ". $data['pname']." - ". $data['productid']?>
                                        </h2>
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
                                        <form method="POST">
                                            <div class="card-body">
                                                <table id="unitTable" border="1" cellpadding="5">
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
                                                                    <option value="">Select Unit</option>
                                                                    <?php
        $res = mysqli_query($conn, "SELECT unitname FROM item_units ORDER BY unitname ASC");
        while($u = mysqli_fetch_assoc($res)){
            echo "<option value='{$u['unitname']}'>{$u['unitname']}</option>";
        }
        ?>
                                                                </select>

                                                                <!-- Hidden actual value to submit -->
                                                                <input type="text" name="unit_name[]"
                                                                    class="form-control mt-1 unit-input"
                                                                    placeholder="Or type custom unit" required>
                                                            </td>
                                                            <td><input class="form-control" type="number" step="0.01"
                                                                    name="unit_qty[]" required>
                                                            </td>
                                                            <td><input class="form-control" type="number" step="0.01"
                                                                    name="price[]" required>
                                                            </td>
                                                            <td><button class="btn btn-danger btn-sm" type="button"
                                                                    onclick="removeRow(this)">X</button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>

                                                <br>

                                                <button class="btn btn-success btn-lg" type="button"
                                                    onclick="addRow()">+ Add More</button>
                                                <button class="btn btn-primary btn-lg" type="submit"
                                                    name="save_units">Save All</button>
                                            </div>
                                        </form>

                                        <script>
                                        function syncUnit(select) {
                                            let input = select.closest('td').querySelector('.unit-input');
                                            input.value = select.value;
                                        }

                                        function addRow() {
                                            let table = document.getElementById("unitTable").getElementsByTagName(
                                                'tbody')[0];

                                            let newRow = table.rows[0].cloneNode(true);

                                            // clear inputs
                                            let inputs = newRow.getElementsByTagName('input');
                                            for (let i = 0; i < inputs.length; i++) {
                                                inputs[i].value = "";
                                            }

                                            table.appendChild(newRow);
                                        }

                                        function removeRow(btn) {
                                            let row = btn.parentNode.parentNode;
                                            let table = document.getElementById("unitTable").getElementsByTagName(
                                                'tbody')[0];

                                            if (table.rows.length > 1) {
                                                row.remove();
                                            }
                                        }
                                        </script>

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