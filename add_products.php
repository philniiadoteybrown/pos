<?php
$pagetitle = "Add Products";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";

/* =========================================================
   AUTO-DETECT THIS PC'S LAN IPv4 ADDRESS
   Used only to build the phone scanner address.
   Compatible with older PHP versions used by XAMPP.
========================================================= */
function isPrivateIpv4($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $parts = explode('.', $ip);
    if (count($parts) !== 4) {
        return false;
    }

    $a = (int)$parts[0];
    $b = (int)$parts[1];

    return (
        $a === 10 ||
        ($a === 172 && $b >= 16 && $b <= 31) ||
        ($a === 192 && $b === 168)
    );
}

function detectAddProductServerIp()
{
    $serverAddr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';

    if (isPrivateIpv4($serverAddr)) {
        return $serverAddr;
    }

    $hostname = gethostname();

    if ($hostname) {
        $ips = @gethostbynamel($hostname);

        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if (isPrivateIpv4($ip)) {
                    return $ip;
                }
            }
        }
    }

    return ($serverAddr && filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        ? $serverAddr
        : '';
}

$addProductServerIp = detectAddProductServerIp();

$msg = '';
$errmsg = '';

/* =========================================================
   ADD PRODUCT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addproduct'])) {

    $barcode       = trim($_POST['barcode'] ?? '');
    $pname         = trim($_POST['pname'] ?? '');
    $pdesc         = trim($_POST['pdesc'] ?? '');
    $category      = trim($_POST['category_select'] ?? '');
    $new_category  = trim($_POST['new_category'] ?? '');
    $unit          = trim($_POST['unit_select'] ?? '');
    $new_unit      = trim($_POST['new_unit'] ?? '');
    $qpu           = (float)($_POST['qpu'] ?? 0);
    $unitprice     = (float)($_POST['unitprice'] ?? 0);
    $qty           = (float)($_POST['qty'] ?? 0);
    $sellingprice  = (float)($_POST['sellingprice'] ?? 0);
    $qtyalert      = (float)($_POST['qtyalert'] ?? 0);

    /* New category takes priority */
    if ($new_category !== '') {
        $category = $new_category;
    }

    /* New unit takes priority */
    if ($new_unit !== '') {
        $unit = $new_unit;
    }

    if ($pname === '') {
        $errmsg = "Product name is required.";
    } elseif ($qpu <= 0) {
        $errmsg = "Quantity per Unit must be greater than zero.";
    } elseif ($unitprice < 0 || $sellingprice < 0 || $qty < 0 || $qtyalert < 0) {
        $errmsg = "Quantity and prices cannot be negative.";
    } elseif ($barcode !== '') {
        /* Prevent duplicate barcode */
        $safeBarcode = mysqli_real_escape_string($conn, $barcode);
        $check = mysqli_query($conn, "SELECT productid, pname FROM products WHERE barcode='$safeBarcode' LIMIT 1");

        if ($check && mysqli_num_rows($check) > 0) {
            $existing = mysqli_fetch_assoc($check);
            $errmsg = "Barcode already belongs to product: " . htmlspecialchars($existing['pname']) . " (" . htmlspecialchars($existing['productid']) . ").";
        }
    }

    if ($errmsg === '') {
        $pnameEsc        = mysqli_real_escape_string($conn, $pname);
        $pdescEsc        = mysqli_real_escape_string($conn, $pdesc);
        $categoryEsc     = mysqli_real_escape_string($conn, $category);
        $unitEsc         = mysqli_real_escape_string($conn, $unit);
        $barcodeEsc      = mysqli_real_escape_string($conn, $barcode);

        $costperunit = $unitprice / $qpu;
        $totalstock  = $qty * $qpu;
        $totalpurchase = $qty * $unitprice;

        mysqli_begin_transaction($conn);

        try {
            /* -------------------------------------------------
               Save category if a new one was entered
               ------------------------------------------------- */
            if ($new_category !== '') {
                $catCheck = mysqli_query($conn, "SELECT catname FROM category WHERE catname='$categoryEsc' LIMIT 1");
                if (!$catCheck || mysqli_num_rows($catCheck) === 0) {
                    if (!mysqli_query($conn, "INSERT INTO category(catname) VALUES('$categoryEsc')")) {
                        throw new Exception("Unable to save category: " . mysqli_error($conn));
                    }
                }
            }

            /* -------------------------------------------------
               Save unit if a new one was entered
               ------------------------------------------------- */
            if ($new_unit !== '') {
                $unitCheck = mysqli_query($conn, "SELECT unit_name FROM units WHERE unit_name='$unitEsc' LIMIT 1");
                if (!$unitCheck || mysqli_num_rows($unitCheck) === 0) {
                    if (!mysqli_query($conn, "INSERT INTO units(unit_name) VALUES('$unitEsc')")) {
                        throw new Exception("Unable to save unit: " . mysqli_error($conn));
                    }
                }
            }

            /* -------------------------------------------------
               Generate next Product ID
               ------------------------------------------------- */
            $lastRes = mysqli_query($conn, "SELECT productid FROM products ORDER BY productid DESC LIMIT 1");
            $lastId = 0;

            if ($lastRes && mysqli_num_rows($lastRes) > 0) {
                $lastRow = mysqli_fetch_assoc($lastRes);
                $lastId = (int)preg_replace('/[^0-9]/', '', $lastRow['productid']);
            }

            $productid = 'PRD' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            /* -------------------------------------------------
               Insert product
               ------------------------------------------------- */
            $insertProduct = "
                INSERT INTO products
                (
                    productid,
                    barcode,
                    pname,
                    pdesc,
                    unit,
                    qty,
                    unitprice,
                    sellingprice,
                    qtyalert,
                    category,
                    qtyperunit,
                    costperunit,
                    totalstock,
                    created_at
                )
                VALUES
                (
                    '$productid',
                    '$barcodeEsc',
                    '$pnameEsc',
                    '$pdescEsc',
                    '$unitEsc',
                    '$qty',
                    '$unitprice',
                    '$sellingprice',
                    '$qtyalert',
                    '$categoryEsc',
                    '$qpu',
                    '$costperunit',
                    '$totalstock',
                    NOW()
                )
            ";

            if (!mysqli_query($conn, $insertProduct)) {
                throw new Exception("Unable to save product: " . mysqli_error($conn));
            }

            /* -------------------------------------------------
               Record initial purchase
               ------------------------------------------------- */
            if ($qty > 0) {
                $insertPurchase = "
                    INSERT INTO purchase_items
                    (
                        pname,
                        pdesc,
                        qty,
                        unitprice,
                        totalqty,
                        unit,
                        type,
                        created_at
                    )
                    VALUES
                    (
                        '$pnameEsc',
                        '$pdescEsc',
                        '$qty',
                        '$unitprice',
                        '$totalstock',
                        '$unitEsc',
                        'purchase',
                        NOW()
                    )
                ";

                if (!mysqli_query($conn, $insertPurchase)) {
                    throw new Exception("Unable to record initial purchase: " . mysqli_error($conn));
                }
            }

            /* -------------------------------------------------
               Create the default Piece unit/price for POS
               ------------------------------------------------- */
            $piecePrice = $sellingprice;

            $pieceCheck = mysqli_query($conn, "
                SELECT id FROM units
                WHERE product_id='$productid' AND unit_name='Piece'
                LIMIT 1
            ");

            if ($pieceCheck && mysqli_num_rows($pieceCheck) === 0) {
                if (!mysqli_query($conn, "
                    INSERT INTO units(product_id, unit_name, unit_qty, price)
                    VALUES('$productid','Piece','1','$piecePrice')
                ")) {
                    throw new Exception("Unable to create Piece selling unit: " . mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            $msg = "Product added successfully. Product ID: $productid" . ($barcode !== '' ? " | Barcode: $barcode" : '');

            /* Clear fields after successful save */
            $_POST = [];

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errmsg = $e->getMessage();
        }
    }
}

/* =========================================================
   DROPDOWN DATA
   ========================================================= */
$categories = mysqli_query($conn, "SELECT catname FROM category ORDER BY catname ASC");

$units = mysqli_query($conn, "SELECT DISTINCT unitname FROM item_units WHERE unitname IS NOT NULL AND unitname <> '' ORDER BY unitname ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>
        .barcode-group {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }

        .barcode-group .barcode-input-wrap {
            flex: 1;
        }

        .barcode-scan-btn {
            min-width: 125px;
            white-space: nowrap;
        }

        .barcode-help {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        @media (max-width: 575.98px) {
            .barcode-group {
                flex-direction: column;
            }

            .barcode-scan-btn,
            .mobile-scanner-connect-btn,
            .mobile-scanner-open-btn {
                width: 100%;
            }
        }

        /* =====================================================
           ADD PRODUCT TWO-COLUMN LAYOUT
           Desktop: form left, mobile scanner right.
           Mobile/tablet: columns stack.
        ===================================================== */
        .add-product-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
            gap: 22px;
            align-items: start;
        }

        .add-product-form-column,
        .add-product-scanner-column {
            min-width: 0;
        }

        .add-product-scanner-column {
            position: sticky;
            top: 20px;
        }

        .mobile-scanner-panel-title {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .mobile-scanner-panel-note {
            margin: 0 0 12px;
            font-size: 12px;
            color: #6c757d;
            line-height: 1.45;
        }

        @media (max-width: 991.98px) {
            .add-product-layout {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .add-product-scanner-column {
                position: static;
                order: 2;
            }
        }

        /* =====================================================
           REMOTE MOBILE SCANNER FOR ADD PRODUCT
        ===================================================== */
        .mobile-scanner-connect-btn {
            min-width: 170px;
            white-space: nowrap;
        }

        .mobile-scanner-open-btn {
            display: none;
            width: 100%;
            margin-top: 8px;
            min-height: 48px;
            white-space: nowrap;
        }

        .mobile-scanner-box {
            display: none;
            margin-top: 10px;
            border: 1px solid #cfd7e0;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,.08);
        }

        .mobile-scanner-box-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 12px;
            background: #f5f7fa;
            border-bottom: 1px solid #e1e5ea;
        }

        .mobile-scanner-box-body {
            padding: 12px;
        }

        .mobile-scanner-status {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .mobile-scanner-url-row {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }

        .mobile-scanner-url-row input {
            flex: 1;
            min-width: 0;
            font-size: 13px;
        }

        .mobile-scanner-note {
            margin: 0;
            padding: 9px 10px;
            background: #f8fbff;
            border: 1px solid #d9e8ff;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.45;
        }

        .mobile-scanner-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 10px;
        }

        .mobile-scanner-feedback {
            display: none;
            margin-top: 8px;
            padding: 9px 10px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.35;
            font-weight: 600;
        }

        .mobile-scanner-feedback.success {
            display: block;
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .mobile-scanner-feedback.error {
            display: block;
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .mobile-scanner-box.connected .mobile-scanner-details {
            display: none;
        }

        .mobile-scanner-box.connected .mobile-scanner-box-header {
            background: #eaf7ef;
        }

        @media (max-width: 991.98px) {
            .mobile-scanner-connect-btn {
                display: none !important;
            }

            .mobile-scanner-open-btn {
                display: block;
            }

            .mobile-scanner-url-row {
                flex-direction: column;
            }

            .mobile-scanner-url-row .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="fixed-left">

<div id="preloader">
    <div id="status">
        <div class="spinner"></div>
    </div>
</div>

<div id="wrapper">

    <?php include "assets/sections/leftside.php" ?>

    <div class="content-page">
        <div class="content">

            <?php include "assets/sections/topbar.php" ?>

            <div class="page-content-wrapper">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col-sm-12">
                            <br>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8 col-md-10 col-12 mx-auto">

                            <div class="card m-b-30">
                                <div class="card-body">

                                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                                        <h2 class="mb-0">Add Product</h2>
                                        <a href="products.php" class="btn btn-secondary btn-sm">
                                            <span class="fa fa-arrow-left"></span> Products
                                        </a>
                                    </div>

                                    <?php if ($msg !== '') { ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                            <?php echo htmlspecialchars($msg); ?>
                                        </div>
                                    <?php } ?>

                                    <?php if ($errmsg !== '') { ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                            <?php echo $errmsg; ?>
                                        </div>
                                    <?php } ?>

                                    <form method="POST" action="">

                                    <!-- Barcode -->
                                        <div class="form-group">
                                            <label><strong>Barcode</strong></label>

                                            <div class="barcode-group">
                                                <div class="barcode-input-wrap">
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="barcode"
                                                        id="barcode"
                                                        value="<?php echo htmlspecialchars($_POST['barcode'] ?? ''); ?>"
                                                        placeholder="Scan or type barcode"
                                                        autocomplete="off"
                                                        inputmode="numeric">
                                                </div>

                                                <!-- EXISTING LOCAL PHONE/PC CAMERA SCANNER -->
                                                <button
                                                    type="button"
                                                    class="btn btn-success barcode-scan-btn"
                                                    onclick="openAddProductScanner()">
                                                    <span class="fa fa-camera"></span>
                                                    Scan Barcode
                                                </button>

                                                <!-- DESKTOP: START REMOTE PHONE SCANNER -->
                                                <button
                                                    type="button"
                                                    class="btn btn-primary mobile-scanner-connect-btn"
                                                    onclick="openAddProductMobileScannerConnection()">
                                                    📱 Connect Mobile Scanner
                                                </button>

                                                <!-- MOBILE/TABLET: OPEN REMOTE SCANNER PAGE -->
                                                <button
                                                    type="button"
                                                    class="btn btn-primary mobile-scanner-open-btn"
                                                    onclick="openAddProductMobileScannerFromMobile()">
                                                    📱 Open Mobile Scanner
                                                </button>
                                            </div>

                                            <div class="barcode-help">
                                                You can scan with the first button, type/use a physical barcode scanner, or connect a separate phone as a remote scanner.
                                            </div>

                                        </div>

                                        <div class="add-product-layout">
                                            <div class="add-product-form-column">

                                        <!-- Product Name -->
                                        <div class="form-group">
                                            <label><strong>Product Name</strong></label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="pname"
                                                value="<?php echo htmlspecialchars($_POST['pname'] ?? ''); ?>"
                                                required
                                                autocomplete="off">
                                        </div>

                                        

                                        <!-- Description -->
                                        <div class="form-group">
                                            <label><strong>Product Description</strong></label>
                                            <textarea
                                                class="form-control"
                                                name="pdesc"
                                                rows="3"><?php echo htmlspecialchars($_POST['pdesc'] ?? ''); ?></textarea>
                                        </div>

                                        <!-- Category -->
                                        <div class="form-group">
                                            <label><strong>Select Category</strong></label>
                                            <select class="form-control" name="category_select">
                                                <option value="">Select Category</option>
                                                <?php
                                                if ($categories) {
                                                    while ($cat = mysqli_fetch_assoc($categories)) {
                                                        $selected = (($_POST['category_select'] ?? '') === $cat['catname']) ? 'selected' : '';
                                                        echo '<option value="' . htmlspecialchars($cat['catname']) . '" ' . $selected . '>' . htmlspecialchars($cat['catname']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label><strong>Or Add New Category</strong></label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="new_category"
                                                value="<?php echo htmlspecialchars($_POST['new_category'] ?? ''); ?>"
                                                placeholder="Enter new category if needed"
                                                autocomplete="off">
                                        </div>

                                        <!-- Unit -->
                                        <div class="form-group">
                                            <label><strong>Unit Measure</strong></label>
                                            <select class="form-control" name="unit_select">
                                                <option value="">Select Unit</option>
                                                <?php
                                                if ($units) {
                                                    while ($u = mysqli_fetch_assoc($units)) {
                                                        $selected = (($_POST['unit_select'] ?? '') === $u['unitname']) ? 'selected' : '';
                                                        echo '<option value="' . htmlspecialchars($u['unitname']) . '" ' . $selected . '>' . htmlspecialchars($u['unitname']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label><strong>Or Add New Unit</strong></label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="new_unit"
                                                value="<?php echo htmlspecialchars($_POST['new_unit'] ?? ''); ?>"
                                                placeholder="Enter new unit if needed"
                                                autocomplete="off">
                                        </div>

                                        <!-- Quantity per Unit -->
                                        <div class="form-group">
                                            <label><strong>Quantity per Unit</strong></label>
                                            <input
                                                type="number"
                                                class="form-control"
                                                name="qpu"
                                                value="<?php echo htmlspecialchars($_POST['qpu'] ?? ''); ?>"
                                                min="1"
                                                required>
                                        </div>

                                        <!-- Unit Cost -->
                                        <div class="form-group">
                                            <label><strong>Unit Cost</strong></label>
                                            <input
                                                type="number"
                                                class="form-control"
                                                name="unitprice"
                                                value="<?php echo htmlspecialchars($_POST['unitprice'] ?? ''); ?>"
                                                min="0"
                                                step="0.01"
                                                required>
                                        </div>

                                        <!-- Quantity Purchased -->
                                        <div class="form-group">
                                            <label><strong>Quantity Purchased</strong></label>
                                            <input
                                                type="number"
                                                class="form-control"
                                                name="qty"
                                                value="<?php echo htmlspecialchars($_POST['qty'] ?? ''); ?>"
                                                min="0"
                                                step="0.01"
                                                required>
                                        </div>

                                        <!-- Selling Price -->
                                        <div class="form-group">
                                            <label><strong>Selling Price (Per Piece)</strong></label>
                                            <input
                                                type="number"
                                                class="form-control"
                                                name="sellingprice"
                                                value="<?php echo htmlspecialchars($_POST['sellingprice'] ?? ''); ?>"
                                                min="0"
                                                step="0.01"
                                                required>
                                        </div>

                                        <!-- Quantity Alert -->
                                        <div class="form-group">
                                            <label><strong>Quantity Alert</strong></label>
                                            <input
                                                type="number"
                                                class="form-control"
                                                name="qtyalert"
                                                value="<?php echo htmlspecialchars($_POST['qtyalert'] ?? ''); ?>"
                                                min="0"
                                                step="0.01"
                                                required>
                                        </div>

                                        <hr>

                                            </div><!-- /.add-product-form-column -->

                                            <div class="add-product-scanner-column">
                                                <div class="card border m-b-20">
                                                    <div class="card-body">
                                                        <h4 class="mobile-scanner-panel-title">📱 Mobile Scanner</h4>
                                                        <p class="mobile-scanner-panel-note">
                                                            Connect a phone camera to this Add Product page.
                                                            The scanned barcode will be placed directly into the Barcode field.
                                                        </p>

                                                        <div id="mobileScannerFeedback"
                                                            class="mobile-scanner-feedback"
                                                            role="alert"
                                                            aria-live="assertive"></div>

                                                        <div id="mobileScannerBox" class="mobile-scanner-box">
                                                            <div class="mobile-scanner-box-header">
                                                                <strong>📱 Remote Mobile Scanner</strong>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-danger"
                                                                    onclick="closeAddProductMobileScannerConnection()">
                                                                    &times;
                                                                </button>
                                                            </div>

                                                            <div class="mobile-scanner-box-body">
                                                                <div class="mobile-scanner-status">
                                                                    <span>Status:</span>
                                                                    <strong id="mobileScannerStatus">Not connected</strong>
                                                                </div>

                                                                <div class="mobile-scanner-details">
                                                                    <label class="mb-1"><strong>Open on the phone:</strong></label>

                                                                    <div class="mobile-scanner-url-row">
                                                                        <input
                                                                            type="text"
                                                                            id="mobileScannerUrl"
                                                                            class="form-control"
                                                                            readonly>
                                                                        <button
                                                                            type="button"
                                                                            class="btn btn-secondary"
                                                                            onclick="copyAddProductMobileScannerUrl()">
                                                                            Copy
                                                                        </button>
                                                                    </div>

                                                                    <p class="mobile-scanner-note">
                                                                        No pairing code is required. The phone scanner automatically finds the active Add Product connection on this server.
                                                                    </p>
                                                                </div>

                                                                <div class="mobile-scanner-actions">
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-danger btn-sm"
                                                                        onclick="stopAddProductMobileScannerConnection()">
                                                                        Stop Connection
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="small text-muted mt-2">
                                                            On mobile, use <strong>Open Mobile Scanner</strong>.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div><!-- /.add-product-scanner-column -->
                                        </div><!-- /.add-product-layout -->

                                        <hr>

                                        <button
                                            type="submit"
                                            name="addproduct"
                                            class="btn btn-success btn-lg btn-block">
                                            <span class="fa fa-save"></span>
                                            Add Product
                                        </button>

                                    </form>

                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include "assets/sections/footers/jqueryscripts.php" ?>

<!-- =========================================================
     LOCAL BARCODE SCANNER
     Landscape capture area - wider and shorter for barcodes
     ========================================================= -->
<style>
    #barcodeScannerOverlay {
        display: none;
        position: fixed;
        z-index: 99999;
        left: 0;
        top: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.96);
        color: #fff;
        overflow: hidden;
    }

    #barcodeScannerHeader {
        height: 58px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 14px;
        background: rgba(0,0,0,0.85);
        position: relative;
        z-index: 2;
    }

    #barcodeScannerTitle {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }

    #barcodeScannerClose {
        border: 0;
        background: transparent;
        color: #fff;
        font-size: 30px;
        line-height: 1;
        padding: 4px 8px;
        cursor: pointer;
    }

    #barcodeScannerCameraArea {
        position: absolute;
        left: 0;
        right: 0;
        top: 58px;
        bottom: 72px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    #barcodeReader {
        width: 100%;
        height: 100%;
        max-width: 1100px;
        position: relative;
    }

    /* Force the visible scanner box to be landscape */
    #barcodeReader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }

    #barcodeReader__scan_region {
        width: 100% !important;
        height: 100% !important;
        border: 0 !important;
    }

    #barcodeReader__scan_region > img {
        display: none !important;
    }

    #barcodeScannerGuide {
        position: absolute;
        left: 5%;
        right: 5%;
        top: 50%;
        transform: translateY(-50%);
        height: clamp(90px, 24vh, 180px);
        border: 3px solid #00ff66;
        border-radius: 10px;
        box-shadow: 0 0 0 9999px rgba(0,0,0,0.42);
        pointer-events: none;
        z-index: 3;
    }

    #barcodeScannerGuide:before,
    #barcodeScannerGuide:after {
        content: "";
        position: absolute;
        top: 50%;
        width: 16px;
        height: 3px;
        background: #00ff66;
    }

    #barcodeScannerGuide:before {
        left: -3px;
    }

    #barcodeScannerGuide:after {
        right: -3px;
    }

    #barcodeScannerStatus {
        position: absolute;
        left: 12px;
        right: 12px;
        bottom: 14px;
        text-align: center;
        font-size: 14px;
        color: #fff;
        z-index: 4;
        text-shadow: 0 1px 3px #000;
    }

    #barcodeScannerHint {
        position: absolute;
        left: 8%;
        right: 8%;
        top: calc(50% + clamp(55px, 13vh, 100px));
        text-align: center;
        color: #fff;
        font-size: 14px;
        z-index: 4;
        text-shadow: 0 1px 3px #000;
        pointer-events: none;
    }

    @media (orientation: landscape) {
        #barcodeScannerHeader {
            height: 52px;
        }

        #barcodeScannerCameraArea {
            top: 52px;
            bottom: 58px;
        }

        #barcodeScannerGuide {
            left: 8%;
            right: 8%;
            height: min(24vh, 150px);
        }

        #barcodeScannerHint {
            top: calc(50% + min(14vh, 80px));
        }
    }

    @media (max-width: 575.98px) {
        #barcodeScannerTitle {
            font-size: 16px;
        }

        #barcodeScannerGuide {
            left: 4%;
            right: 4%;
            height: 105px;
        }
    }
</style>

<div id="barcodeScannerOverlay">
    <div id="barcodeScannerHeader">
        <div id="barcodeScannerTitle">
            <span class="fa fa-barcode"></span> Scan Barcode
        </div>
        <button type="button" id="barcodeScannerClose" aria-label="Close">&times;</button>
    </div>

    <div id="barcodeScannerCameraArea">
        <div id="barcodeReader"></div>
        <div id="barcodeScannerGuide"></div>
        <div id="barcodeScannerHint">Place the barcode inside the wide box</div>
        <div id="barcodeScannerStatus">Starting camera...</div>
    </div>
</div>

<script src="assets/scripts/html5-qrcode.min.js"></script>

<script>
(function () {
    let barcodeScanner = null;
    let scannerRunning = false;
    let lastScannedCode = '';
    let lastScanTime = 0;

    const overlay = document.getElementById('barcodeScannerOverlay');
    const closeButton = document.getElementById('barcodeScannerClose');
    const status = document.getElementById('barcodeScannerStatus');

    function setStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function getLandscapeBox() {
        const area = document.getElementById('barcodeScannerCameraArea');

        if (!area) {
            return { width: 800, height: 150 };
        }

        const areaWidth = Math.max(320, area.clientWidth);
        const areaHeight = Math.max(180, area.clientHeight);

        /*
         * Deliberately make the barcode target LANDSCAPE:
         * - approximately 88% of available width
         * - approximately 22% of available height
         * - never allow height to exceed width
         */
        let width = Math.floor(areaWidth * 0.88);
        let height = Math.floor(areaHeight * 0.22);

        width = Math.min(width, 1050);
        height = Math.max(height, 95);
        height = Math.min(height, 190);

        if (height >= width) {
            height = Math.max(90, Math.floor(width * 0.28));
        }

        return {
            width: width,
            height: height
        };
    }

    function preferredCamera(cameras) {
        if (!cameras || !cameras.length) {
            return null;
        }

        const preferred = cameras.find(function (camera) {
            const label = (camera.label || '').toLowerCase();

            return (
                label.indexOf('back') !== -1 ||
                label.indexOf('rear') !== -1 ||
                label.indexOf('environment') !== -1
            );
        });

        return preferred || cameras[cameras.length - 1];
    }

    window.openAddProductScanner = function () {
        if (typeof Html5Qrcode === 'undefined') {
            alert('Barcode scanner library could not be loaded.');
            return;
        }

        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
        setStatus('Starting camera...');

        if (scannerRunning) {
            return;
        }

        barcodeScanner = new Html5Qrcode('barcodeReader', {
            verbose: false
        });

        Html5Qrcode.getCameras()
            .then(function (cameras) {
                if (!cameras || cameras.length === 0) {
                    throw new Error('No camera was found on this device.');
                }

                const camera = preferredCamera(cameras);
                const box = getLandscapeBox();

                setStatus('Point the camera at the barcode');

                return barcodeScanner.start(
                    camera.id,
                    {
                        fps: 10,
                        aspectRatio: 16 / 9,
                        qrbox: {
                            width: box.width,
                            height: box.height
                        },
                        formatsToSupport: [
                            Html5QrcodeSupportedFormats.CODE_128,
                            Html5QrcodeSupportedFormats.CODE_39,
                            Html5QrcodeSupportedFormats.CODE_93,
                            Html5QrcodeSupportedFormats.EAN_13,
                            Html5QrcodeSupportedFormats.EAN_8,
                            Html5QrcodeSupportedFormats.UPC_A,
                            Html5QrcodeSupportedFormats.UPC_E,
                            Html5QrcodeSupportedFormats.ITF,
                            Html5QrcodeSupportedFormats.CODABAR
                        ]
                    },
                    function (decodedText) {
                        const now = Date.now();

                        if (
                            decodedText === lastScannedCode &&
                            now - lastScanTime < 2000
                        ) {
                            return;
                        }

                        lastScannedCode = decodedText;
                        lastScanTime = now;

                        handleAddProductBarcode(decodedText);
                    },
                    function () {
                        /* Ignore normal scan-frame failures */
                    }
                );
            })
            .then(function () {
                scannerRunning = true;
                setStatus('Scanning... Place the barcode inside the wide box.');
            })
            .catch(function (error) {
                scannerRunning = false;
                console.error(error);
                setStatus('Unable to start camera.');

                setTimeout(function () {
                    if (overlay.style.display !== 'none') {
                        alert(
                            'Unable to start the camera. Please allow camera access and try again.'
                        );
                    }
                }, 100);
            });
    };

    function handleAddProductBarcode(code) {
        const input = document.getElementById('barcode');

        if (input) {
            input.value = code;
            input.focus();
        }

        setStatus('Barcode captured: ' + code);

        setTimeout(function () {
            closeAddProductScanner();
        }, 250);
    }

    window.closeAddProductScanner = function () {
        if (barcodeScanner && scannerRunning) {
            barcodeScanner.stop()
                .then(function () {
                    return barcodeScanner.clear();
                })
                .catch(function (error) {
                    console.error(error);

                    try {
                        barcodeScanner.clear();
                    } catch (e) {}
                })
                .finally(function () {
                    scannerRunning = false;
                    barcodeScanner = null;
                });
        }

        overlay.style.display = 'none';
        document.body.style.overflow = '';
        setStatus('Camera stopped');
    };

    closeButton.addEventListener('click', function () {
        closeAddProductScanner();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay.style.display !== 'none') {
            closeAddProductScanner();
        }
    });

    /*
     * Keep physical barcode scanners working.
     * Prevent Enter from submitting the whole Add Product form
     * while the barcode field is active.
     */
    const barcodeInput = document.getElementById('barcode');

    if (barcodeInput) {
        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    }

    window.addEventListener('resize', function () {
        if (overlay.style.display !== 'none' && !scannerRunning) {
            const guide = document.getElementById('barcodeScannerGuide');

            if (guide) {
                guide.style.height = '';
            }
        }
    });
})();
</script>

<script>
/* =========================================================
   REMOTE MOBILE SCANNER FOR ADD PRODUCT
   Reuses the same mobile_scanner_api.php used by POS.
   Scanned barcode is written into #barcode.
========================================================= */
(function () {
    var mobileScannerPollTimer = null;
    var mobileScannerToken = null;
    var mobileScannerRunning = false;
    var addProductServerIp = <?php echo json_encode($addProductServerIp); ?>;

    function setMobileScannerStatus(message, connected) {
        var el = document.getElementById('mobileScannerStatus');
        if (!el) return;

        el.textContent = message;
        el.style.color = connected ? '#198754' : '#856404';
    }

    function setMobileScannerFeedback(message, type) {
        var el = document.getElementById('mobileScannerFeedback');
        if (!el) return;

        el.className = 'mobile-scanner-feedback ' + (type || '');
        el.textContent = message || '';
    }

    function getAddProductMobileScannerUrl() {
        var protocol = window.location.protocol || 'http:';
        var host = window.location.hostname || '';
        var port = window.location.port ? ':' + window.location.port : '';

        if (
            (host === 'localhost' || host === '127.0.0.1' || host === '::1') &&
            addProductServerIp
        ) {
            host = addProductServerIp;
        }

        if (!host) {
            host = addProductServerIp || 'localhost';
        }

        return protocol + '//' + host + port + '/philynda/mobile_scanner.php';
    }

    window.openAddProductMobileScannerConnection = function () {
        var box = document.getElementById('mobileScannerBox');
        var url = document.getElementById('mobileScannerUrl');

        if (box) box.style.display = 'block';
        if (url) url.value = getAddProductMobileScannerUrl();

        setMobileScannerFeedback('', '');
        startAddProductMobileScannerConnection();
    };

    window.closeAddProductMobileScannerConnection = function () {
        var box = document.getElementById('mobileScannerBox');
        if (box) box.style.display = 'none';
    };

    window.openAddProductMobileScannerFromMobile = function () {
        var scannerUrl = getAddProductMobileScannerUrl();

        if (!scannerUrl) {
            alert('Mobile scanner address could not be determined.');
            return;
        }

        window.open(scannerUrl, '_blank');
    };

    function startAddProductMobileScannerConnection() {
        if (mobileScannerRunning) {
            return;
        }

        fetch('assets/scripts/mobile_scanner_api.php?action=start&_=' + Date.now(), {
            method: 'POST',
            cache: 'no-store'
        })
        .then(function (response) {
            return response.text().then(function (text) {
                var data;

                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Scanner API did not return JSON. Check mobile_scanner_api.php.');
                }

                return { response: response, data: data };
            });
        })
        .then(function (result) {
            if (!result.response.ok || !result.data.success || !result.data.token) {
                throw new Error(result.data.message || ('Unable to start scanner (HTTP ' + result.response.status + ').'));
            }

            mobileScannerToken = String(result.data.token);
            mobileScannerRunning = true;

            var url = document.getElementById('mobileScannerUrl');
            if (url) url.value = getAddProductMobileScannerUrl();

            var scannerBox = document.getElementById('mobileScannerBox');
            if (scannerBox) scannerBox.classList.remove('connected');

            setMobileScannerStatus('Waiting for phone...', false);
            startAddProductMobileScannerPolling();
        })
        .catch(function (error) {
            console.error('ADD PRODUCT MOBILE SCANNER START ERROR:', error);
            mobileScannerRunning = false;
            mobileScannerToken = null;
            setMobileScannerStatus('Connection could not be started', false);
            setMobileScannerFeedback(error.message || 'Unable to start mobile scanner connection.', 'error');
        });
    }

    function startAddProductMobileScannerPolling() {
        stopAddProductMobileScannerPolling();

        var poll = function () {
            if (!mobileScannerRunning || !mobileScannerToken) {
                return;
            }

            fetch(
                'assets/scripts/mobile_scanner_api.php?action=poll&token=' +
                encodeURIComponent(mobileScannerToken) + '&_=' + Date.now(),
                {
                    method: 'GET',
                    cache: 'no-store'
                }
            )
            .then(function (response) {
                return response.text().then(function (text) {
                    var data;

                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Scanner poll did not return JSON.');
                    }

                    return { response: response, data: data };
                });
            })
            .then(function (result) {
                var response = result.response;
                var data = result.data;

                if (!response.ok || data.success === false) {
                    mobileScannerRunning = false;
                    mobileScannerToken = null;
                    setMobileScannerStatus(data.message || 'Mobile scanner connection ended.', false);
                    setMobileScannerFeedback(data.message || 'Mobile scanner connection ended.', 'error');
                    return;
                }

                var scannerBox = document.getElementById('mobileScannerBox');

                if (data.paired) {
                    setMobileScannerStatus(
                        data.device ? 'Phone connected: ' + data.device : 'Phone connected',
                        true
                    );

                    if (scannerBox) {
                        scannerBox.classList.add('connected');
                    }
                } else {
                    setMobileScannerStatus('Waiting for phone...', false);

                    if (scannerBox) {
                        scannerBox.classList.remove('connected');
                    }
                }

                if (data.barcode) {
                    receiveAddProductMobileBarcode(String(data.barcode));
                }
            })
            .catch(function (error) {
                console.error('ADD PRODUCT MOBILE SCANNER POLL ERROR:', error);
                setMobileScannerFeedback(error.message || 'Unable to read mobile scanner queue.', 'error');
            })
            .finally(function () {
                if (mobileScannerRunning) {
                    mobileScannerPollTimer = setTimeout(poll, 500);
                }
            });
        };

        poll();
    }

    function stopAddProductMobileScannerPolling() {
        if (mobileScannerPollTimer) {
            clearTimeout(mobileScannerPollTimer);
            mobileScannerPollTimer = null;
        }
    }

    function receiveAddProductMobileBarcode(code) {
        var input = document.getElementById('barcode');
        if (!input) return;

        input.value = code;
        input.focus();

        setMobileScannerFeedback('Barcode received from mobile scanner: ' + code, 'success');

        // Short beep confirms receipt on the desktop.
        try {
            var AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                var ctx = new AudioContextClass();
                var oscillator = ctx.createOscillator();
                var gain = ctx.createGain();
                oscillator.frequency.value = 880;
                oscillator.type = 'sine';
                gain.gain.value = 0.05;
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start();
                setTimeout(function () {
                    oscillator.stop();
                    if (ctx.close) ctx.close();
                }, 100);
            }
        } catch (e) {
            // Audio is only confirmation; barcode delivery still works without it.
        }
    }

    window.stopAddProductMobileScannerConnection = function () {
        mobileScannerRunning = false;
        stopAddProductMobileScannerPolling();

        var token = mobileScannerToken;
        mobileScannerToken = null;

        if (token) {
            fetch(
                'assets/scripts/mobile_scanner_api.php?action=stop&token=' +
                encodeURIComponent(token) + '&_=' + Date.now(),
                { method: 'POST', cache: 'no-store' }
            ).catch(function (error) {
                console.error('ADD PRODUCT MOBILE SCANNER STOP ERROR:', error);
            });
        }

        var scannerBox = document.getElementById('mobileScannerBox');
        if (scannerBox) scannerBox.classList.remove('connected');

        setMobileScannerStatus('Not connected', false);
        setMobileScannerFeedback('', '');
    };

    window.copyAddProductMobileScannerUrl = function () {
        var input = document.getElementById('mobileScannerUrl');
        if (!input) return;

        input.select();
        input.setSelectionRange(0, input.value.length);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value)
                .then(function () { alert('Mobile scanner address copied.'); })
                .catch(function () { alert('Copy failed. Please copy the address manually.'); });
        } else {
            try {
                document.execCommand('copy');
                alert('Mobile scanner address copied.');
            } catch (e) {
                alert('Please copy the address manually.');
            }
        }
    };

    window.addEventListener('beforeunload', function () {
        if (!mobileScannerToken) return;

        try {
            navigator.sendBeacon(
                'assets/scripts/mobile_scanner_api.php?action=stop&token=' +
                encodeURIComponent(mobileScannerToken),
                ''
            );
        } catch (e) {
            // Ignore unload cleanup failures.
        }
    });
})();
/*
 * Automatically open/start the remote mobile-scanner connection on desktop,
 * matching the products.php behavior. Keep the existing Add Product layout
 * and scanner panel unchanged; phones/tablets retain the existing workflow.
 */
window.addEventListener('load', function () {
    setTimeout(function () {
        if (window.innerWidth > 991.98) {
            if (typeof window.openAddProductMobileScannerConnection === 'function') {
                window.openAddProductMobileScannerConnection();
            }
        }
    }, 300);
});

</script>

</body>
</html>
