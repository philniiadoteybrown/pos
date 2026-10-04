<?php

$pagetitle = "Edit Units";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";


// ======================================================
// GET UNIT ID
// ======================================================

if (!isset($_GET['id']) || trim($_GET['id']) == '') {
    die("❌ No Unit selected");
}

$unit_id = (int)$_GET['id'];

if ($unit_id <= 0) {
    die("❌ Invalid Unit ID");
}


// ======================================================
// FIND THE UNIT AND ITS PRODUCT
// ======================================================

$unitInfoRes = mysqli_query(
    $conn,
    "
    SELECT
        units.id AS unit_id,
        units.product_id,

        products.productid,
        products.pname,
        products.pdesc

    FROM units

    INNER JOIN products
        ON units.product_id = products.productid

    WHERE units.id = $unit_id

    LIMIT 1
    "
);


if (!$unitInfoRes) {
    die(
        "❌ Database error: " .
        htmlspecialchars(mysqli_error($conn))
    );
}


$unitInfo = mysqli_fetch_assoc($unitInfoRes);


if (!$unitInfo) {
    die("❌ Unit not found");
}


// ======================================================
// CREATE PRODUCT ARRAY
// ======================================================
//
// This fixes the Undefined variable: product error.
// The HTML below uses $product['productid'],
// $product['pname'] and $product['pdesc'].
//

$product = [
    'productid' => $unitInfo['productid'],
    'pname'     => $unitInfo['pname'],
    'pdesc'     => $unitInfo['pdesc']
];


$product_id = $unitInfo['product_id'];


// ======================================================
// UPDATE UNITS
// ======================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $ids = isset($_POST['id'])
        ? $_POST['id']
        : [];

    $names = isset($_POST['unit_name'])
        ? $_POST['unit_name']
        : [];

    $qtys = isset($_POST['unit_qty'])
        ? $_POST['unit_qty']
        : [];

    $prices = isset($_POST['price'])
        ? $_POST['price']
        : [];


    $ok = true;
    $errorMessage = "";


    if (!is_array($names)) {

        $ok = false;

        $errorMessage = "Invalid unit data submitted.";

    }


    // ==================================================
    // PROCESS UNITS
    // ==================================================

    if ($ok) {

        for ($i = 0; $i < count($names); $i++) {

            $id = isset($ids[$i])
                ? (int)$ids[$i]
                : 0;

            $name = isset($names[$i])
                ? trim($names[$i])
                : '';

            $qty = isset($qtys[$i])
                ? (float)$qtys[$i]
                : 0;

            $price = isset($prices[$i])
                ? (float)$prices[$i]
                : 0;


            // ==========================================
            // VALIDATE
            // ==========================================

            if ($name == '') {

                $ok = false;

                $errorMessage =
                    "Unit name cannot be empty.";

                break;
            }


            if ($qty <= 0) {

                $ok = false;

                $errorMessage =
                    "Quantity for unit '$name' must be greater than zero.";

                break;
            }


            if ($price < 0) {

                $ok = false;

                $errorMessage =
                    "Price for unit '$name' cannot be negative.";

                break;
            }


            $name = mysqli_real_escape_string(
                $conn,
                $name
            );


            $product_id_safe =
                mysqli_real_escape_string(
                    $conn,
                    $product_id
                );


            // ==========================================
            // UPDATE EXISTING UNIT
            // ==========================================

            if ($id > 0) {

                $query = "
                    UPDATE units

                    SET
                        unit_name = '$name',
                        unit_qty = '$qty',
                        price = '$price'

                    WHERE id = $id

                    AND product_id = '$product_id_safe'
                ";


                if (!mysqli_query($conn, $query)) {

                    $ok = false;

                    $errorMessage =
                        mysqli_error($conn);

                    break;
                }

            }


            // ==========================================
            // INSERT NEW UNIT
            // ==========================================

            else {

                $query = "
                    INSERT INTO units
                    (
                        product_id,
                        unit_name,
                        unit_qty,
                        price
                    )

                    VALUES
                    (
                        '$product_id_safe',
                        '$name',
                        '$qty',
                        '$price'
                    )
                ";


                if (!mysqli_query($conn, $query)) {

                    $ok = false;

                    $errorMessage =
                        mysqli_error($conn);

                    break;
                }
            }
        }
    }


    // ==================================================
    // MESSAGE
    // ==================================================

    if ($ok) {

        $msg = "Units updated successfully.";

    } else {

        $errmsg =
            "Some updates failed: " . $errorMessage;
    }
}


// ======================================================
// FETCH ALL UNITS FOR THIS PRODUCT
// ======================================================

$product_id_safe =
    mysqli_real_escape_string(
        $conn,
        $product_id
    );


$unitRes = mysqli_query(
    $conn,
    "
    SELECT *

    FROM units

    WHERE product_id = '$product_id_safe'

    ORDER BY id ASC
    "
);


if (!$unitRes) {

    die(
        "❌ Unable to load units: " .
        htmlspecialchars(mysqli_error($conn))
    );
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

</div>


<!-- Begin page -->

<div id="wrapper">


    <!-- Left Sidebar -->

    <?php include "assets/sections/leftside.php" ?>


    <!-- Start right Content -->

    <div class="content-page">


        <!-- Start content -->

        <div class="content">


            <!-- Top Bar -->

            <?php include "assets/sections/topbar.php" ?>


            <!-- Page content -->

            <div class="page-content-wrapper">


                <div class="container-fluid">

                    <br>


                    <div class="row">

                        <div class="col-lg-12">


                            <div class="card m-b-30">


                                <div class="card-body">


                                    <!-- ==========================================
                                         PRODUCT TITLE
                                    ========================================== -->

                                    <h2>

                                        Edit Units

                                    </h2>


                                    <div class="mb-3">

                                        <strong>
                                            Product:
                                        </strong>

                                        <?= htmlspecialchars(
                                            $product['productid']
                                        ) ?>

                                        -

                                        <?= htmlspecialchars(
                                            $product['pname']
                                        ) ?>


                                        <?php if (!empty($product['pdesc'])) { ?>

                                            <span class="text-muted">

                                                (
                                                <?= htmlspecialchars(
                                                    $product['pdesc']
                                                ) ?>
                                                )

                                            </span>

                                        <?php } ?>

                                    </div>


                                    <!-- ==========================================
                                         SUCCESS MESSAGE
                                    ========================================== -->

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

                                            <?= htmlspecialchars($msg) ?>

                                        </div>

                                    <?php } ?>


                                    <!-- ==========================================
                                         ERROR MESSAGE
                                    ========================================== -->

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

                                            <?= htmlspecialchars($errmsg) ?>

                                        </div>

                                    <?php } ?>


                                    <!-- ==========================================
                                         FORM
                                    ========================================== -->

                                    <form method="POST">


                                        <div class="table-responsive">


                                            <table
                                                class="table table-bordered table-hover"
                                                id="unitsTable"
                                            >


                                                <thead>

                                                    <tr>

                                                        <th>
                                                            Unit Name
                                                        </th>

                                                        <th>
                                                            Qty Per Unit
                                                        </th>

                                                        <th>
                                                            Price (GH¢)
                                                        </th>

                                                        <th width="100">
                                                            Action
                                                        </th>

                                                    </tr>

                                                </thead>


                                                <tbody>


                                                <?php while (
                                                    $u = mysqli_fetch_assoc($unitRes)
                                                ) { ?>


                                                    <tr>


                                                        <!-- UNIT ID -->

                                                        <input
                                                            type="hidden"
                                                            name="id[]"
                                                            value="<?= htmlspecialchars(
                                                                $u['id']
                                                            ) ?>"
                                                        >


                                                        <!-- UNIT NAME -->

                                                        <td>

                                                            <input
                                                                type="text"
                                                                name="unit_name[]"
                                                                class="form-control"
                                                                value="<?= htmlspecialchars(
                                                                    $u['unit_name']
                                                                ) ?>"
                                                                required
                                                            >

                                                        </td>


                                                        <!-- QTY -->

                                                        <td>

                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0.01"
                                                                name="unit_qty[]"
                                                                class="form-control"
                                                                value="<?= htmlspecialchars(
                                                                    $u['unit_qty']
                                                                ) ?>"
                                                                required
                                                            >

                                                        </td>


                                                        <!-- PRICE -->

                                                        <td>

                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                name="price[]"
                                                                class="form-control"
                                                                value="<?= htmlspecialchars(
                                                                    $u['price']
                                                                ) ?>"
                                                                required
                                                            >

                                                        </td>


                                                        <!-- DELETE FROM FORM -->

                                                        <td>

                                                            <button
                                                                type="button"
                                                                class="btn btn-danger btn-sm"
                                                                onclick="removeRow(this)"
                                                            >

                                                                <span class="fa fa-remove"></span>

                                                            </button>

                                                        </td>


                                                    </tr>


                                                <?php } ?>


                                                </tbody>

                                            </table>

                                        </div>


                                        <!-- ==========================================
                                             BUTTONS
                                        ========================================== -->

                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            onclick="addRow()"
                                        >

                                            <span class="fa fa-plus"></span>

                                            Add Unit

                                        </button>


                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >

                                            <span class="fa fa-save"></span>

                                            Update All

                                        </button>


                                        <a
                                            href="products.php"
                                            class="btn btn-light"
                                        >

                                            Cancel

                                        </a>


                                    </form>


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


</div>


<!-- jQuery -->

<?php include "assets/sections/footers/jqueryscripts.php" ?>


<script>


// ==================================================
// ADD UNIT
// ==================================================

function addRow() {

    let row = `

        <tr>

            <input
                type="hidden"
                name="id[]"
                value=""
            >


            <td>

                <input
                    type="text"
                    name="unit_name[]"
                    class="form-control"
                    placeholder="e.g. Piece, Box, Bag"
                    required
                >

            </td>


            <td>

                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="unit_qty[]"
                    class="form-control"
                    placeholder="Qty"
                    required
                >

            </td>


            <td>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="price[]"
                    class="form-control"
                    placeholder="Price"
                    required
                >

            </td>


            <td>

                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    onclick="removeRow(this)"
                >

                    <span class="fa fa-remove"></span>

                </button>

            </td>

        </tr>

    `;


    document
        .querySelector("#unitsTable tbody")
        .insertAdjacentHTML(
            "beforeend",
            row
        );

}


// ==================================================
// REMOVE ROW FROM FORM
// ==================================================

function removeRow(button) {

    button
        .closest("tr")
        .remove();

}

</script>


</body>

</html>