<?php
$pagetitle="Edit Product Details";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";


// 🔍 GET ID
if(!isset($_GET['id']) || empty($_GET['id'])){
    die("❌ No product selected");
}

$productid = mysqli_real_escape_string($conn, $_GET['id']);


// 🔍 FETCH DATA
$res = mysqli_query(
    $conn,
    "SELECT * FROM products WHERE productid='$productid' LIMIT 1"
);

if(!$res){
    die("❌ Unable to load product: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($res);

if(!$data){
    die("❌ Product not found");
}


// ================= UPDATE =================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    /*
    |--------------------------------------------------------------------------
    | GET FORM VALUES
    |--------------------------------------------------------------------------
    */

    $pname = mysqli_real_escape_string(
        $conn,
        trim($_POST['pname'] ?? '')
    );

    $pdesc = mysqli_real_escape_string(
        $conn,
        trim($_POST['pdesc'] ?? '')
    );

    $unit = mysqli_real_escape_string(
        $conn,
        trim($_POST['unit'] ?? '')
    );

    $qtyperunit = isset($_POST['qpu'])
        ? (float)$_POST['qpu']
        : 0;

    $unitprice = isset($_POST['unitprice'])
        ? (float)$_POST['unitprice']
        : 0;

    $sellingprice = isset($_POST['sellingprice'])
        ? (float)$_POST['sellingprice']
        : 0;

    $qtyalert = isset($_POST['qtyalert'])
        ? (float)$_POST['qtyalert']
        : 0;


    /*
    |--------------------------------------------------------------------------
    | QUANTITY PURCHASED
    |--------------------------------------------------------------------------
    |
    | DO NOT take qty from $_POST.
    |
    | The original quantity purchased is taken directly from the database.
    | Therefore, this form cannot change the purchased quantity.
    |
    */

    $qty = (float)$data['qty'];


    /*
    |--------------------------------------------------------------------------
    | CATEGORY
    |--------------------------------------------------------------------------
    */

    $category_select = trim($_POST['category_select'] ?? '');
    $new_category    = trim($_POST['new_category'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if($pname == ''){

        $errmsg = "Update failed: Product Name is required.";

    }elseif($pdesc == ''){

        $errmsg = "Update failed: Product Description is required.";

    }elseif($unit == ''){

        $errmsg = "Update failed: Unit Measure is required.";

    }elseif($qtyperunit <= 0){

        $errmsg = "Update failed: Quantity per Unit must be greater than zero.";

    }elseif($unitprice < 0){

        $errmsg = "Update failed: Unit Cost cannot be negative.";

    }elseif($sellingprice < 0){

        $errmsg = "Update failed: Selling Price cannot be negative.";

    }elseif($qtyalert < 0){

        $errmsg = "Update failed: Quantity Alert cannot be negative.";

    }elseif($category_select == '' && $new_category == ''){

        $errmsg = "Update failed: Please select a category or add a new category.";

    }


    /*
    |--------------------------------------------------------------------------
    | PROCESS UPDATE
    |--------------------------------------------------------------------------
    */

    if(!isset($errmsg)){

        /*
        |--------------------------------------------------------------------------
        | DETERMINE CATEGORY
        |--------------------------------------------------------------------------
        */

        if($new_category != ''){

            $new_category_db = mysqli_real_escape_string(
                $conn,
                $new_category
            );

            /*
            | Check if category already exists
            */
            $checkCategory = mysqli_query(
                $conn,
                "SELECT catname
                 FROM category
                 WHERE catname='$new_category_db'
                 LIMIT 1"
            );

            if(!$checkCategory){

                $errmsg =
                    "Update failed while checking category: "
                    . mysqli_error($conn);

            }elseif(mysqli_num_rows($checkCategory) > 0){

                /*
                | Existing category
                */
                $categoryRow = mysqli_fetch_assoc($checkCategory);

                $catname = $categoryRow['catname'];

            }else{

                /*
                | Create new category
                */
                $insertCategory = mysqli_query(
                    $conn,
                    "INSERT INTO category(catname)
                     VALUES('$new_category_db')"
                );

                if(!$insertCategory){

                    $errmsg =
                        "Update failed while creating category: "
                        . mysqli_error($conn);

                }else{

                    $catname = $new_category;
                }
            }

        }else{

            $catname = $category_select;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CONTINUE WITH PRODUCT UPDATE
    |--------------------------------------------------------------------------
    */

    if(!isset($errmsg)){

        $catname = mysqli_real_escape_string(
            $conn,
            $catname
        );


        /*
        |--------------------------------------------------------------------------
        | CALCULATIONS
        |--------------------------------------------------------------------------
        |
        | Quantity Purchased remains unchanged.
        |
        */

        $costperunit = $unitprice / $qtyperunit;

        $stock = $qty * $qtyperunit;

        $tp = $qty * $unitprice;


        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */

        mysqli_begin_transaction($conn);


        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCTS
            |--------------------------------------------------------------------------
            */

            $productQuery = "
                UPDATE products SET
                    pname='$pname',
                    pdesc='$pdesc',
                    unit='$unit',
                    unitprice='$unitprice',
                    sellingprice='$sellingprice',
                    qtyalert='$qtyalert',
                    category='$catname',
                    qtyperunit='$qtyperunit',
                    costperunit='$costperunit',
                    totalstock='$stock'
                WHERE productid='$productid'
            ";

            $updateProduct = mysqli_query(
                $conn,
                $productQuery
            );


            /*
            | Check Products update
            */
            if(!$updateProduct){

                throw new Exception(
                    "Products update failed: "
                    . mysqli_error($conn)
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE PURCHASE ITEMS
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | qty is NOT included here.
            | Quantity Purchased therefore remains unchanged.
            |
            */

            $purchaseQuery = "
                UPDATE purchase_items SET
                    pname='$pname',
                    pdesc='$pdesc',
                    unit='$unit',
                    unitprice='$unitprice',
                    sellingprice='$sellingprice',
                    qtyalert='$qtyalert',
                    totalqty='$stock',
                    totalpurchase='$tp'
                WHERE productid='$productid'
            ";

            $updatePurchase = mysqli_query(
                $conn,
                $purchaseQuery
            );


            /*
            | Check Purchase Items update
            */
            if(!$updatePurchase){

                throw new Exception(
                    "Purchase Items update failed: "
                    . mysqli_error($conn)
                );
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            if(!mysqli_commit($conn)){

                throw new Exception(
                    "Database commit failed: "
                    . mysqli_error($conn)
                );
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $msg = "Product details updated successfully.";


            /*
            | Reload updated product data
            */
            $res = mysqli_query(
                $conn,
                "SELECT * FROM products
                 WHERE productid='$productid'
                 LIMIT 1"
            );

            if($res){

                $data = mysqli_fetch_assoc($res);
            }


        } catch(Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            mysqli_rollback($conn);

            $errmsg = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>

<html>

<head>

```
<?php include "assets/sections/headers/header_tag.php" ?>
```

</head>

<body class="fixed-left">

```
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
                                        Edit Product
                                    </h2>


                                    <?php if(isset($msg)){ ?>

                                    <!-- SUCCESS ALERT -->

                                    <div
                                        class="alert alert-success alert-dismissible fade show"
                                        role="alert">

                                        <button
                                            type="button"
                                            class="close"
                                            data-dismiss="alert"
                                            aria-label="Close">

                                            <span aria-hidden="true">
                                                &times;
                                            </span>

                                        </button>

                                        <strong>
                                            Successful!
                                        </strong>

                                        <?php echo htmlspecialchars($msg); ?>

                                        <br>

                                        <small>
                                            Returning to Products...
                                        </small>

                                    </div>


                                    <script>
                                    setTimeout(function() {
                                        window.location.href = "products.php";
                                    }, 2000);
                                    </script>

                                    <?php } ?>


                                    <?php if(isset($errmsg)){ ?>

                                    <!-- ERROR ALERT -->

                                    <div
                                        class="alert alert-danger alert-dismissible fade show"
                                        role="alert">

                                        <button
                                            type="button"
                                            class="close"
                                            data-dismiss="alert"
                                            aria-label="Close">

                                            <span aria-hidden="true">
                                                &times;
                                            </span>

                                        </button>

                                        <strong>
                                            Update Failed!
                                        </strong>

                                        <br>

                                        <?php echo htmlspecialchars($errmsg); ?>

                                    </div>

                                    <?php } ?>


                                    <form
                                        method="post"
                                        action="">


                                        <div class="card-body">


                                            <!-- PRODUCT NAME -->

                                            <div class="form-group">

                                                <label>
                                                    Product Name
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="pname"
                                                    value="<?php echo htmlspecialchars($data['pname']); ?>"
                                                    required>

                                            </div>


                                            <!-- PRODUCT DESCRIPTION -->

                                            <div class="form-group">

                                                <label>
                                                    Product Description
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="pdesc"
                                                    value="<?php echo htmlspecialchars($data['pdesc']); ?>"
                                                    required>

                                            </div>


                                            <!-- UNIT MEASURE -->

                                            <div class="section-title">
                                                Unit Measure
                                            </div>


                                            <div class="form-group">

                                                <label>
                                                    Select Measure
                                                </label>

                                                <select
                                                    class="form-control"
                                                    name="unit">

                                                    <option
                                                        value="<?php echo htmlspecialchars($data['unit']); ?>">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $data['unit']
                                                        );
                                                        ?>

                                                    </option>

                                                    <option value="Crate(s)">
                                                        Crate
                                                    </option>

                                                    <option value="Carton(s)">
                                                        Carton
                                                    </option>

                                                    <option value="Dozen(s)">
                                                        Dozen
                                                    </option>

                                                    <option value="Sack(s)">
                                                        Sack
                                                    </option>

                                                    <option value="Litres">
                                                        Litres
                                                    </option>

                                                    <option value="kg(s)">
                                                        KG
                                                    </option>

                                                    <option value="Piece(s)">
                                                        Pieces
                                                    </option>

                                                    <option value="Box(es)">
                                                        Box
                                                    </option>

                                                    <option value="Packs">
                                                        Packs
                                                    </option>

                                                    <option value="Rows">
                                                        Rows
                                                    </option>

                                                </select>

                                            </div>


                                            <!-- QUANTITY PER UNIT -->

                                            <div class="form-group">

                                                <label>
                                                    Quantity per Unit
                                                </label>

                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0.000001"
                                                    class="form-control"
                                                    name="qpu"
                                                    value="<?php echo htmlspecialchars($data['qtyperunit']); ?>"
                                                    required>

                                            </div>


                                            <!-- CATEGORY -->

                                            <div class="form-group">

                                                <label>
                                                    Select Category
                                                </label>

                                                <select
                                                    name="category_select"
                                                    class="form-control">

                                                    <option value="">
                                                        Select Category
                                                    </option>


                                                    <?php

                                                    $res = mysqli_query(
                                                        $conn,
                                                        "SELECT * FROM category ORDER BY catname ASC"
                                                    );

                                                    if($res){

                                                        while(
                                                            $c =
                                                            mysqli_fetch_assoc($res)
                                                        ){

                                                            $selected =
                                                                ($c['catname'] == $data['category'])
                                                                ? "selected"
                                                                : "";

                                                    ?>

                                                    <option
                                                        value="<?php echo htmlspecialchars($c['catname']); ?>"
                                                        <?php echo $selected; ?>>

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $c['catname']
                                                        );
                                                        ?>

                                                    </option>

                                                    <?php

                                                        }
                                                    }

                                                    ?>

                                                </select>


                                                <br>

                                                <label>
                                                    Or Add New :
                                                </label>

                                                <br>

                                                <input
                                                    class="form-control"
                                                    type="text"
                                                    name="new_category"
                                                    placeholder="Category name">

                                            </div>


                                            <!-- UNIT COST -->

                                            <div class="form-group">

                                                <label>
                                                    Unit Cost
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
                                                        min="0"
                                                        class="form-control"
                                                        name="unitprice"
                                                        value="<?php echo htmlspecialchars($data['unitprice']); ?>"
                                                        required>

                                                </div>

                                            </div>


                                            <!-- QUANTITY PURCHASED -->

                                            <div class="form-group">

                                                <label>
                                                    Quantity Purchased
                                                    <small class="text-muted">
                                                        (Cannot be edited)
                                                    </small>
                                                </label>

                                                <input
                                                    type="number"
                                                    step="any"
                                                    class="form-control"
                                                    value="<?php echo htmlspecialchars($data['qty']); ?>"
                                                    readonly
                                                    style="background-color:#f5f5f5;">

                                                <small class="form-text text-muted">
                                                    Quantity Purchased is protected and will remain unchanged.
                                                </small>

                                            </div>


                                            <!-- SELLING PRICE -->

                                            <div class="form-group">

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
                                                        min="0"
                                                        class="form-control"
                                                        name="sellingprice"
                                                        value="<?php echo htmlspecialchars($data['sellingprice']); ?>"
                                                        required>

                                                </div>

                                            </div>


                                            <!-- QUANTITY ALERT -->

                                            <div class="form-group">

                                                <label>
                                                    Quantity Alert
                                                </label>

                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    class="form-control"
                                                    name="qtyalert"
                                                    value="<?php echo htmlspecialchars($data['qtyalert']); ?>"
                                                    required>

                                            </div>


                                            <!-- CURRENT STOCK -->

                                            <div class="alert alert-info">

                                                <strong>
                                                    Current Quantity Purchased:
                                                </strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $data['qty']
                                                );
                                                ?>

                                                <br>

                                                <strong>
                                                    Current Total Stock:
                                                </strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $data['totalstock']
                                                );
                                                ?>

                                                <br>

                                                <small>
                                                    Quantity Purchased will not be changed.
                                                </small>

                                            </div>


                                        </div>


                                        <div class="card-footer">

                                            <button
                                                class="btn btn-primary"
                                                type="submit">

                                                Update

                                            </button>


                                            <a
                                                href="products.php"
                                                class="btn btn-secondary">

                                                Cancel

                                            </a>

                                        </div>


                                    </form>


                                </div>

                            </div>


                        </div>


                    </div>

                </div>

            </div>

            <!-- end row -->

        </div>

        <!-- container -->

    </div>

    <!-- Page content Wrapper -->

</div>

<!-- content -->


<footer class="footer">

    <?php include "assets/sections/footers/footer.php" ?>

</footer>


</div>

<!-- End Right content here -->

</div>

<!-- END wrapper -->


<!-- jQuery -->

<?php include "assets/sections/footers/jqueryscripts.php" ?>
```

</body>

</html>
