<?php
$pagetitle="Products";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";

/* =========================================================
   AUTO-DETECT THIS PC'S LAN IPv4 ADDRESS
   Used to build the mobile scanner address.
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

function detectProductsServerIp()
{
    $serverAddr = isset($_SERVER['SERVER_ADDR'])
        ? $_SERVER['SERVER_ADDR']
        : '';

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

    if (
        $serverAddr &&
        filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
    ) {
        return $serverAddr;
    }

    return '';
}

$productsServerIp = detectProductsServerIp();

$where = "";

if($search != ""){
    $where = "WHERE pname LIKE '%$search%'
              OR productid LIKE '%$search%'
              OR barcode LIKE '%$search%'";
}

// total rows
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM products $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// fetch data
$res = mysqli_query($conn,"
SELECT * FROM products
$where
ORDER BY pname ASC
LIMIT $offset, $limit
");
?>

<!DOCTYPE html>
<html>

<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>
    /* =========================================================
       DESKTOP LAYOUT - PRESERVED
       ========================================================= */
    .products-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #tableData {
        width: 100%;
    }

    #prodList {
        width: 100%;
        min-width: 1050px;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    #prodList th,
    #prodList td {
        border: 2px solid #000;
        padding: 8px;
        text-align: left;
        vertical-align: middle;
    }

    #prodList thead {
        background-color: #f2f2f2;
    }

    #prodList tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    #prodList td:nth-child(8),
    #prodList td:nth-child(9),
    #prodList th:nth-child(8),
    #prodList th:nth-child(9) {
        white-space: nowrap;
    }

    /* Keep all buttons together and make them touch-friendly */
    #prodList .btn {
        margin: 2px;
        min-width: 36px;
        min-height: 34px;
    }

    .mobile-product-details {
        display: none;
    }

    .desktop-product-name {
        display: block;
    }

    /* =========================================================
       MOBILE TABLE - COMPACT 3-COLUMN VIEW
       Product | Modify | Action
       Product details are nested inside the Product cell.
       ========================================================= */
    @media (max-width: 767.98px) {

        .products-table-wrap {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
        }

        #prodList {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
            font-size: 12px !important;
            margin-bottom: 0 !important;
        }

        #prodList th,
        #prodList td {
            padding: 7px 5px !important;
            white-space: normal !important;
            word-break: break-word;
            vertical-align: top !important;
        }

        /* Only Product, Modify and Action are visible */
        #prodList th:nth-child(1),
        #prodList td:nth-child(1) {
            display: table-cell !important;
            width: 54% !important;
        }

        #prodList th:nth-child(8),
        #prodList td:nth-child(8) {
            display: table-cell !important;
            width: 23% !important;
            text-align: center !important;
        }

        #prodList th:nth-child(9),
        #prodList td:nth-child(9) {
            display: table-cell !important;
            width: 23% !important;
            text-align: center !important;
        }

        #prodList th:nth-child(2),
        #prodList td:nth-child(2),
        #prodList th:nth-child(3),
        #prodList td:nth-child(3),
        #prodList th:nth-child(4),
        #prodList td:nth-child(4),
        #prodList th:nth-child(5),
        #prodList td:nth-child(5),
        #prodList th:nth-child(6),
        #prodList td:nth-child(6),
        #prodList th:nth-child(7),
        #prodList td:nth-child(7) {
            display: none !important;
        }

        #prodList th {
            font-size: 12px !important;
            font-weight: 700;
            white-space: nowrap !important;
            background: #f2f2f2;
            text-align: center;
        }

        #prodList th:first-child {
            text-align: left !important;
        }

        #prodList .btn {
            min-width: 34px !important;
            min-height: 34px !important;
            padding: 6px 7px !important;
            margin: 2px 1px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
        }

        /* Stack Modify and Action buttons vertically on small phones */
        #prodList td:nth-child(8) .btn,
        #prodList td:nth-child(9) .btn {
            display: flex !important;
            width: 100% !important;
            margin: 3px 0 !important;
        }

        /* Nested product information */
        .mobile-product-details {
            display: block;
            margin-top: 7px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            line-height: 1.45;
        }

        .mobile-product-detail {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            padding: 2px 0;
        }

        .mobile-product-detail .detail-label {
            font-weight: 700;
            color: #555;
            flex: 0 0 auto;
        }

        .mobile-product-detail .detail-value {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .desktop-product-name {
            display: none;
        }

        .mobile-product-name {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .mobile-product-description {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
            overflow-wrap: anywhere;
        }

        .mobile-stock-status {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
        }

        /* Nested unit details returned by get_product_units.php */
        #prodList tr[id^="units-"] > td {
            padding: 6px !important;
            width: 100% !important;
        }

        #prodList .unit-box {
            width: 100% !important;
            overflow: visible !important;
        }

        #prodList .unit-box table {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
            margin: 0 !important;
        }

        #prodList .unit-box table thead {
            display: none !important;
        }

        #prodList .unit-box table,
        #prodList .unit-box tbody,
        #prodList .unit-box tr,
        #prodList .unit-box td {
            display: block !important;
            width: 100% !important;
        }

        #prodList .unit-box tr {
            margin-bottom: 8px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
        }

        #prodList .unit-box td {
            border: 0 !important;
            padding: 3px 0 !important;
            text-align: left !important;
        }

        #prodList .unit-box td::before {
            font-weight: 700;
            color: #555;
            margin-right: 6px;
        }

        #prodList .unit-box td:nth-child(1)::before { content: "Unit: "; }
        #prodList .unit-box td:nth-child(2)::before { content: "Quantity: "; }
        #prodList .unit-box td:nth-child(3)::before { content: "Price: "; }
        #prodList .unit-box td:nth-child(4)::before { content: "Action: "; }

        #prodList .unit-box input,
        #prodList .unit-box select {
            max-width: 100%;
        }

        /* Mobile search section: every item gets its own row */
        .products-toolbar {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 7px !important;
            width: 100% !important;
        }

        .products-toolbar > * {
            width: 100% !important;
            min-width: 100% !important;
            flex: 0 0 auto !important;
            margin: 0 !important;
        }

        .products-toolbar .rows-label {
            display: none !important;
        }

        .products-toolbar .btn {
            width: 100% !important;
            min-height: 40px;
        }

        .products-toolbar #search,
        .products-toolbar #category,
        .products-toolbar #limit {
            height: 40px;
        }

        .mobile-table-hint {
            display: block !important;
            font-size: 11px;
            color: #6c757d;
            padding: 5px 0 8px;
        }
    }

    .mobile-table-hint {
        display: none;
        font-size: 12px;
        color: #6c757d;
        padding: 7px 2px 0;
    }

    /* =========================================================
       BARCODE SCANNER OVERLAY
       ========================================================= */
    #productBarcodeScannerOverlay {
        display: none;
        position: fixed;
        z-index: 99999;
        inset: 0;
        background: #000;
        color: #fff;
        flex-direction: column;
    }

    #productBarcodeScannerHeader {
        min-height: 56px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(0,0,0,.92);
        flex-shrink: 0;
    }

    #productBarcodeScannerHeader strong {
        font-size: 16px;
    }

    #productBarcodeScannerClose {
        border: 0;
        background: #dc3545;
        color: #fff;
        border-radius: 5px;
        padding: 8px 14px;
        font-size: 14px;
    }

    #productBarcodeReaderArea {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 12px;
        position: relative;
    }

    #productBarcodeReader {
        width: min(92vw, 850px);
        height: min(52vw, 470px);
        max-height: 58vh;
        background: #111;
        position: relative;
        overflow: hidden;
        border: 2px solid #fff;
        border-radius: 8px;
    }

    #productBarcodeReader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }

    .product-scan-guide {
        position: absolute;
        z-index: 5;
        left: 6%;
        right: 6%;
        top: 34%;
        height: 32%;
        border: 3px solid #00ff66;
        border-radius: 8px;
        pointer-events: none;
        box-shadow: 0 0 0 9999px rgba(0,0,0,.20);
    }

    #productBarcodeScannerStatus {
        text-align: center;
        padding: 10px 14px 16px;
        font-size: 13px;
        color: #ddd;
        flex-shrink: 0;
    }

    @media (max-width: 767.98px) {
        #productBarcodeReader {
            width: 94vw;
            height: 43vw;
            max-height: 40vh;
        }

        .product-scan-guide {
            left: 5%;
            right: 5%;
            top: 30%;
            height: 40%;
        }
    }

    /* =========================================================
       PRODUCTS + MOBILE SCANNER TWO-COLUMN LAYOUT
       Desktop: product list left, scanner panel right.
       Tablet/phone: stack the scanner panel below.
       ========================================================= */
    .products-page-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
        gap: 22px;
        align-items: start;
        width: 100%;
    }

    .products-main-column,
    .products-scanner-column {
        min-width: 0;
    }

    .products-scanner-column {
        position: sticky;
        top: 20px;
    }

    .products-scanner-title {
        margin: 0 0 8px;
        font-size: 18px;
        font-weight: 700;
    }

    .products-scanner-note {
        margin: 0 0 12px;
        font-size: 12px;
        color: #6c757d;
        line-height: 1.45;
    }

    .products-mobile-scanner-open-btn {
        display: none;
        width: 100%;
        min-height: 48px;
    }

    .products-mobile-scanner-connect-btn {
        width: 100%;
        min-height: 44px;
    }

    .products-mobile-scanner-box {
        display: none;
        margin-top: 10px;
        border: 1px solid #cfd7e0;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(0,0,0,.08);
    }

    .products-mobile-scanner-box-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 10px 12px;
        background: #f5f7fa;
        border-bottom: 1px solid #e1e5ea;
    }

    .products-mobile-scanner-box-body {
        padding: 12px;
    }

    .products-mobile-scanner-status {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .products-mobile-scanner-details {
        display: block;
    }

    .products-mobile-scanner-url-row {
        display: flex;
        gap: 6px;
        margin-bottom: 10px;
    }

    .products-mobile-scanner-url-row input {
        flex: 1;
        min-width: 0;
        font-size: 13px;
    }

    .products-mobile-scanner-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 10px;
    }

    .products-mobile-scanner-feedback {
        display: none;
        margin-top: 8px;
        padding: 9px 10px;
        border-radius: 6px;
        font-size: 13px;
        line-height: 1.35;
        font-weight: 600;
    }

    .products-mobile-scanner-feedback.success {
        display: block;
        background: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }

    .products-mobile-scanner-feedback.error {
        display: block;
        background: #f8d7da;
        color: #842029;
        border: 1px solid #f5c2c7;
    }

    .products-mobile-scanner-box.connected .products-mobile-scanner-details {
        display: none;
    }

    .products-mobile-scanner-box.connected .products-mobile-scanner-box-header {
        background: #eaf7ef;
    }

    .products-mobile-scanner-status strong {
        font-weight: 700;
    }

    @media (max-width: 991.98px) {
        .products-page-layout {
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .products-scanner-column {
            position: static;
            order: 2;
        }

        .products-mobile-scanner-connect-btn {
            display: none !important;
        }

        .products-mobile-scanner-open-btn {
            display: block;
        }
    }

    @media (max-width: 767.98px) {
        .products-mobile-scanner-url-row {
            flex-direction: column;
        }

        .products-mobile-scanner-url-row .btn {
            width: 100%;
        }

        .products-mobile-scanner-box-body {
            padding: 10px;
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

    <!-- LEFT SIDEBAR -->

    <?php include "assets/sections/leftside.php"; ?>

    <!-- LEFT SIDEBAR END -->

    <div class="content-page">

        <div class="content">

            <!-- TOP BAR -->

            <?php include "assets/sections/topbar.php"; ?>

            <!-- TOP BAR END -->

            <div class="page-content-wrapper">

                    <div class="container-fluid">

                        <div class="row">
                            <div class="col-sm-12">
                                <br>
                            </div>
                        </div>


                        <div class="products-page-layout">

                            <div class="products-main-column">

                                <div class="card m-b-30">

                                    <div class="card-body">

                                        <h2>Products</h2>

                                        <?php if(isset($msg)){ ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert"
                                                aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                            <?php echo $msg ?>
                                        </div>
                                        <?php } ?>

                                        <?php if(isset($errmsg)){ ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert"
                                                aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                            <?php echo $errmsg ?>
                                        </div>
                                        <?php } ?>

                                        <div class="card-header-form">

                                            <form method="GET">

                                                <div class="products-toolbar"
                                                    style="display:flex;align-items:center;gap:10px;width:100%;">

                                                    <a href="add_products.php"
                                                        title="Add"
                                                        class="btn btn-primary btn-animation">
                                                        <span class="fa fa-plus"></span>
                                                    </a>

                                                    <input type="text"
                                                        id="search"
                                                        class="form-control"
                                                        placeholder="Search product, ID or barcode..."
                                                        style="flex:2;"
                                                        autocomplete="off">

                                                    <button type="button"
                                                        class="btn btn-success barcode-scan-btn"
                                                        onclick="openProductBarcodeScanner()"
                                                        title="Scan product barcode">
                                                        <span class="fa fa-camera"></span>
                                                        <span class="d-none d-md-inline"> Scan</span>
                                                    </button>

                                                    <select id="category"
                                                        class="form-control"
                                                        onchange="loadData(1)"
                                                        style="flex:1;">

                                                        <option value="">All Categories</option>

                                                        <?php
                                                        $catRes = mysqli_query($conn, "
                                                            SELECT category, COUNT(*) as total
                                                            FROM products
                                                            WHERE category IS NOT NULL AND category <> ''
                                                            GROUP BY category
                                                            ORDER BY category ASC
                                                        ");

                                                        while($cat = mysqli_fetch_assoc($catRes)){
                                                            echo "<option value='".htmlspecialchars($cat['category'])."'>"
                                                                .htmlspecialchars($cat['category'])
                                                                ." ({$cat['total']})</option>";
                                                        }
                                                        ?>

                                                    </select>

                                                    <span class="rows-label"
                                                        style="white-space:nowrap;">Showing</span>

                                                    <select id="limit"
                                                        class="form-control"
                                                        style="flex:0.7;">

                                                        <option value="5">5</option>
                                                        <option value="10" selected>10</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>

                                                    </select>

                                                    <span class="rows-label"
                                                        style="white-space:nowrap;">rows per page</span>

                                                </div>

                                            </form>

                                            <br>
                                            <hr>
                                            <br>

                                            <div class="mobile-table-hint">
                                                Product details are grouped together. Modify and Action buttons are stacked for easy mobile use. Remote mobile scanning is available in the scanner panel.
                                            </div>

                                            <div id="tableData"></div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <!-- =====================================================
                                 REMOTE MOBILE SCANNER - RIGHT COLUMN
                                 ===================================================== -->
                            <div class="products-scanner-column">

                                <div class="card border m-b-20">

                                    <div class="card-body">

                                        <h4 class="products-scanner-title">
                                            📱 Mobile Scanner
                                        </h4>

                                        <p class="products-scanner-note">
                                            Connect a phone camera to this Products page.
                                            A barcode scanned on the phone will be entered
                                            into the product search automatically.
                                        </p>

                                        <!-- DESKTOP -->
                                        <button
                                            type="button"
                                            class="btn btn-primary products-mobile-scanner-connect-btn"
                                            onclick="openProductsMobileScannerConnection()">
                                            📱 Connect Mobile Scanner
                                        </button>

                                        <!-- MOBILE / TABLET -->
                                        <button
                                            type="button"
                                            class="btn btn-primary products-mobile-scanner-open-btn"
                                            onclick="openProductsMobileScannerFromMobile()">
                                            📱 Open Mobile Scanner
                                        </button>

                                        <div
                                            id="productsMobileScannerFeedback"
                                            class="products-mobile-scanner-feedback"
                                            role="alert"
                                            aria-live="assertive">
                                        </div>

                                        <div
                                            id="productsMobileScannerBox"
                                            class="products-mobile-scanner-box">

                                            <div class="products-mobile-scanner-box-header">

                                                <strong>
                                                    📱 Remote Mobile Scanner
                                                </strong>

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="closeProductsMobileScannerConnection()"
                                                    aria-label="Close">
                                                    &times;
                                                </button>

                                            </div>

                                            <div class="products-mobile-scanner-box-body">

                                                <div class="products-mobile-scanner-status">
                                                    <span>Status:</span>

                                                    <strong
                                                        id="productsMobileScannerStatus">
                                                        Not connected
                                                    </strong>
                                                </div>

                                                <div class="products-mobile-scanner-details">

                                                    <label class="mb-1">
                                                        <strong>
                                                            Open on the phone:
                                                        </strong>
                                                    </label>

                                                    <div class="products-mobile-scanner-url-row">

                                                        <input
                                                            type="text"
                                                            id="productsMobileScannerUrl"
                                                            class="form-control"
                                                            readonly>

                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary"
                                                            onclick="copyProductsMobileScannerUrl()">
                                                            Copy
                                                        </button>

                                                    </div>

                                                    <p class="products-scanner-note">
                                                        No pairing code is required.
                                                        The phone scanner automatically finds
                                                        the active Products connection on this server.
                                                    </p>

                                                </div>

                                                <div class="products-mobile-scanner-actions">

                                                    <button
                                                        type="button"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="stopProductsMobileScannerConnection()">
                                                        Stop Connection
                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>
                    </div>

                </div>

            </div>

            <footer class="footer">
                <?php include "assets/sections/footers/footer.php" ?>.
            </footer>

        </div>

    </div>

    <!-- =========================================================
         RESTOCK MODAL
         ========================================================= -->
    <div class="modal fade" id="restock" tabindex="-1">

        <div class="modal-dialog">

            <div class="modal-content">

                <form action="assets/scripts/process_addrestock.php" method="post">

                    <div class="modal-header">
                        <h5 class="modal-title">Re-Stock</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <input type="hidden" name="productid" id="modal-id">

                        <p><strong>Product ID:</strong>
                            <span id="modal-id-span"></span>
                        </p>

                        <p><strong>Product Name:</strong>
                            <span id="modal-name"></span>
                        </p>

                        <p><strong>Description:</strong>
                            <span id="modal-description"></span>
                        </p>

                        <p><strong>Quantity Available:</strong>
                            <span id="modal-qty"></span>
                        </p>

                        <p><strong>Unit Cost:</strong>
                            <span id="modal-uc"></span>
                        </p>

                        <div class="form-group">
                            <label>Quantity to Stock</label>
                            <input type="number"
                                class="form-control"
                                name="qpurchase"
                                required>
                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal">
                            Close
                        </button>

                        <button type="submit"
                            name="addstock"
                            class="btn btn-success">
                            Add Stock
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

    <!-- =========================================================
         SINGLE UNIT SALES MODAL
         ========================================================= -->
    <div class="modal fade"
        id="sell"
        tabindex="-1"
        role="dialog"
        aria-labelledby="formModal"
        aria-hidden="true">

        <div class="modal-dialog" role="document">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title" id="formModal">
                        Single Unit Sales
                    </h5>

                    <button type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>

                <div class="modal-body">

                    <form action="assets/scripts/process_addsales.php" method="post">

                        <input type="hidden" name="productid" id="modal-sid">

                        <p><strong>Product ID:</strong>
                            <b><span id="modal-sid-span"></span></b>
                        </p>

                        <p><strong>Product Name:</strong>
                            <b><span id="modal-sname"></span></b>
                        </p>

                        <p><strong>Description:</strong>
                            <b><span id="modal-sdescription"></span></b>
                        </p>

                        <p><strong>Stock Available:</strong>
                            <b><span id="modal-sqty"></span></b>
                        </p>

                        <p><strong>Price:</strong>
                            <b><span id="modal-ssp"></span></b>
                        </p>

                        <hr>

                        <div class="form-group">
                            <label>Quantity Soled</label>
                            <input type="number"
                                class="form-control"
                                name="qtysold"
                                min="1"
                                required>
                        </div>

                        <button type="submit"
                            class="btn btn-success"
                            name="addsale">
                            Add Sales
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <!-- Local barcode library -->
    <script src="assets/scripts/html5-qrcode.min.js"></script>

    <?php include "assets/sections/footers/jqueryscripts.php" ?>

    <!-- =========================================================
         PRODUCT PAGE JAVASCRIPT
         ========================================================= -->
    <script>

    let timer;

    function loadData(page = 1) {

        let search = document.getElementById("search").value;
        let limit = document.getElementById("limit").value;
        let category = document.getElementById("category").value;

        fetch(
            `assets/scripts/fetch_products3.php?search=${encodeURIComponent(search)}&page=${page}&limit=${limit}&category=${encodeURIComponent(category)}`
        )
        .then(res => {
            if (!res.ok) {
                throw new Error(
                    "Product table request failed: " +
                    res.status + " " + res.statusText
                );
            }
            return res.text();
        })
        .then(data => {
            document.getElementById("tableData").innerHTML = data;

            /*
             * After every AJAX refresh, check whether the search value
             * represents a barcode/product result. The returned row
             * already contains ALL Modify and Action buttons.
             */
            highlightBarcodeResult(search);
        })
        .catch(err => {
            console.error("Product loading error:", err);
            document.getElementById("tableData").innerHTML =
                '<div class="alert alert-danger">Unable to load the product list. Please check fetch_products3.php.</div>';
        });
    }

    function highlightBarcodeResult(searchValue) {

        if (!searchValue || searchValue.trim() === "") {
            return;
        }

        const table = document.getElementById("prodList");

        if (!table) {
            return;
        }

        const rows = table.querySelectorAll("tbody > tr");

        rows.forEach(function(row) {

            if (row.id && row.id.indexOf("units-") === 0) {
                return;
            }

            const text = row.textContent.toLowerCase();

            if (text.indexOf(searchValue.trim().toLowerCase()) !== -1) {

                row.classList.add("barcode-selected-row");

                /*
                 * On mobile, automatically scroll the matching row
                 * into view. The complete Modify and Action cells
                 * remain available by horizontal swiping.
                 */
                if (window.innerWidth <= 767) {
                    setTimeout(function() {
                        row.scrollIntoView({
                            behavior: "smooth",
                            block: "center",
                            inline: "nearest"
                        });
                    }, 100);
                }
            }
        });
    }

    /* Search */
    document.getElementById("search").addEventListener("keyup", function(e) {

        clearTimeout(timer);

        /*
         * Physical barcode scanners normally finish with ENTER.
         * Search immediately when ENTER is received.
         */
        if (e.key === "Enter") {
            e.preventDefault();
            loadData(1);
            return;
        }

        timer = setTimeout(() => {
            loadData(1);
        }, 300);
    });

    /* Rows per page */
    document.getElementById("limit").addEventListener("change", function() {
        loadData(1);
    });

    /*
     * Category listener is already connected through onchange,
     * but this also supports dynamically changing the control.
     */
    document.getElementById("category").addEventListener("change", function() {
        loadData(1);
    });

    function toggleUnits(btn) {

        let id = btn.getAttribute("data-id");
        let row = document.getElementById("units-" + id);

        if (!row) {
            return;
        }

        let box = row.querySelector(".unit-box");

        if (row.style.display === "table-row") {
            row.style.display = "none";
            return;
        }

        row.style.display = "table-row";

        if (row.dataset.loaded === "true") {
            return;
        }

        box.innerHTML = "Loading...";

        fetch("assets/scripts/get_product_units.php?product_id=" + encodeURIComponent(id))
            .then(res => res.text())
            .then(data => {

                box.innerHTML = data;

                if (typeof bindLiveUnitInputs === "function") {
                    bindLiveUnitInputs();
                }

                row.dataset.loaded = "true";
            })
            .catch(err => {
                box.innerHTML = "Unable to load units.";
                console.error(err);
            });
    }

    /* Save live unit changes */
    document.addEventListener("click", function(e) {

        if (!e.target.classList.contains("save-btn")) {
            return;
        }

        let btn = e.target;
        let row = btn.closest("tr");

        if (!row) {
            return;
        }

        let id = btn.getAttribute("data-id");
        let unitInput = row.querySelector(".unit_name");
        let qtyInput = row.querySelector(".unit_qty");
        let priceInput = row.querySelector(".price");

        if (!unitInput || !qtyInput || !priceInput) {
            return;
        }

        let unit_name = unitInput.value;
        let unit_qty = qtyInput.value;
        let price = priceInput.value;

        fetch("assets/scripts/update_units.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                id,
                unit_name,
                unit_qty,
                price
            })
        })
        .then(res => res.text())
        .then(data => {

            console.log("SERVER:", data);

            if (data.trim() === "success") {

                btn.textContent = "Saved";
                btn.classList.remove("btn-success");
                btn.classList.add("btn-primary");

            } else {

                alert("Update failed: " + data);

            }
        });
    });

    /* =========================================================
       BARCODE SCANNER
       ========================================================= */

    let productBarcodeScanner = null;
    let productBarcodeScannerRunning = false;
    let lastProductBarcode = "";
    let lastProductBarcodeTime = 0;

    function openProductBarcodeScanner() {

        const overlay = document.getElementById("productBarcodeScannerOverlay");

        if (!overlay) {
            createProductBarcodeScanner();
        }

        document.getElementById("productBarcodeScannerOverlay").style.display = "flex";

        startProductBarcodeScanner();
    }

    function createProductBarcodeScanner() {

        const overlay = document.createElement("div");

        overlay.id = "productBarcodeScannerOverlay";

        overlay.innerHTML = `
            <div id="productBarcodeScannerHeader">
                <strong>Scan Product Barcode</strong>
                <button type="button"
                    id="productBarcodeScannerClose"
                    onclick="closeProductBarcodeScanner()">
                    Close
                </button>
            </div>

            <div id="productBarcodeReaderArea">
                <div id="productBarcodeReader">
                    <div class="product-scan-guide"></div>
                </div>
            </div>

            <div id="productBarcodeScannerStatus">
                Point the camera at the barcode. The wide green box is the capture area.
            </div>
        `;

        document.body.appendChild(overlay);
    }

    async function startProductBarcodeScanner() {

        if (typeof Html5Qrcode === "undefined") {

            document.getElementById("productBarcodeScannerStatus").textContent =
                "Barcode scanner library could not be loaded.";

            return;
        }

        if (productBarcodeScannerRunning) {
            return;
        }

        const status = document.getElementById("productBarcodeScannerStatus");

        try {

            productBarcodeScanner = new Html5Qrcode("productBarcodeReader");

            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                throw new Error("No camera found.");
            }

            let selectedCamera = cameras[0];

            for (let i = 0; i < cameras.length; i++) {

                const label = (cameras[i].label || "").toLowerCase();

                if (
                    label.includes("back") ||
                    label.includes("rear") ||
                    label.includes("environment")
                ) {
                    selectedCamera = cameras[i];
                    break;
                }
            }

            const config = {
                fps: 10,
                qrbox: function(viewfinderWidth, viewfinderHeight) {

                    /*
                     * Wide landscape capture region:
                     * approximately 82% width and 32% height.
                     */
                    const width = Math.floor(viewfinderWidth * 0.82);
                    const height = Math.floor(viewfinderHeight * 0.32);

                    return {
                        width: Math.max(240, width),
                        height: Math.max(90, height)
                    };
                },
                aspectRatio: 1.7777778
            };

            if (typeof Html5QrcodeSupportedFormats !== "undefined") {

                config.formatsToSupport = [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.CODE_93,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.ITF,
                    Html5QrcodeSupportedFormats.CODABAR
                ];
            }

            await productBarcodeScanner.start(
                selectedCamera.id,
                config,
                function(decodedText) {

                    const now = Date.now();

                    if (
                        decodedText === lastProductBarcode &&
                        now - lastProductBarcodeTime < 2000
                    ) {
                        return;
                    }

                    lastProductBarcode = decodedText;
                    lastProductBarcodeTime = now;

                    handleProductBarcode(decodedText);

                },
                function(errorMessage) {
                    // Normal scanner decode failures are intentionally ignored.
                }
            );

            productBarcodeScannerRunning = true;

            status.textContent =
                "Scanning... Place the barcode horizontally inside the green capture area.";

        } catch (error) {

            console.error("Barcode scanner error:", error);

            status.textContent =
                "Unable to start camera. Check camera permission and try again.";

        }
    }

    async function handleProductBarcode(code) {

        const searchInput = document.getElementById("search");

        if (searchInput) {
            searchInput.value = code;
        }

        await stopProductBarcodeScanner();

        const overlay = document.getElementById("productBarcodeScannerOverlay");

        if (overlay) {
            overlay.style.display = "none";
        }

        /*
         * Barcode is now searched against barcode/productid/name/description.
         * The returned row contains the complete Modify + Action columns.
         */
        loadData(1);
    }

    async function stopProductBarcodeScanner() {

        if (!productBarcodeScanner || !productBarcodeScannerRunning) {
            return;
        }

        try {
            await productBarcodeScanner.stop();
        } catch (e) {
            console.warn("Scanner stop:", e);
        }

        try {
            productBarcodeScanner.clear();
        } catch (e) {
            console.warn("Scanner clear:", e);
        }

        productBarcodeScanner = null;
        productBarcodeScannerRunning = false;
    }

    async function closeProductBarcodeScanner() {

        await stopProductBarcodeScanner();

        const overlay = document.getElementById("productBarcodeScannerOverlay");

        if (overlay) {
            overlay.style.display = "none";
        }
    }

    document.addEventListener("keydown", function(e) {

        if (e.key === "Escape") {
            closeProductBarcodeScanner();
        }
    });

    /* =========================================================
       EXISTING MODALS / CONFIRM FUNCTIONS
       ========================================================= */

    function loadProduct(button) {

        let id = button.getAttribute('data-id');
        let name = button.getAttribute('data-name');
        let desc = button.getAttribute('data-description');
        let qty = button.getAttribute('data-qty');
        let uc = button.getAttribute('data-uc');

        document.getElementById('modal-id').value = id;

        document.getElementById('modal-id-span').textContent = id;
        document.getElementById('modal-name').textContent = name;
        document.getElementById('modal-description').textContent = desc;
        document.getElementById('modal-qty').textContent = qty;
        document.getElementById('modal-uc').textContent = uc;
    }

    function sellProduct(button) {

        let id = button.getAttribute('data-sid');
        let name = button.getAttribute('data-sname');
        let desc = button.getAttribute('data-sdescription');
        let qty = button.getAttribute('data-sqty');
        let sp = button.getAttribute('data-ssp');

        document.getElementById('modal-sid').value = id;

        document.getElementById('modal-sid-span').textContent = id;
        document.getElementById('modal-sname').textContent = name;
        document.getElementById('modal-sdescription').textContent = desc;
        document.getElementById('modal-sqty').textContent = qty;
        document.getElementById('modal-ssp').textContent = sp;
    }

    function confirmDelete(name) {
        return confirm(
            "Are you sure you want to delete " + name + " ?"
        );
    }

    function confirmEdit(name) {
        return confirm(
            "Are you sure you want edit " + name + " ?"
        );
    }

    function confirmAddStock(name) {
        return confirm(
            "This action will add more stock to " + name + ". Proceed ?"
        );
    }

    function confirmReStock(name) {
        return confirm(
            "This action will restock stock to " + name +
            " because quantity remaining 0. Proceed ?"
        );
    }


    /* =========================================================
       REMOTE MOBILE SCANNER FOR PRODUCTS
       Reuses the same working mobile_scanner_api.php used by
       POS and Add Product.
       ========================================================= */
    (function () {

        var productsMobileScannerPollTimer = null;
        var productsMobileScannerToken = null;
        var productsMobileScannerRunning = false;

        var productsMobileScannerServerIp =
            <?php echo json_encode($productsServerIp); ?>;

        function setProductsMobileScannerStatus(message, connected) {

            var el =
                document.getElementById(
                    'productsMobileScannerStatus'
                );

            if (!el) {
                return;
            }

            el.textContent = message;

            el.style.color =
                connected
                    ? '#198754'
                    : '#856404';
        }

        function setProductsMobileScannerFeedback(
            message,
            type
        ) {

            var el =
                document.getElementById(
                    'productsMobileScannerFeedback'
                );

            if (!el) {
                return;
            }

            el.className =
                'products-mobile-scanner-feedback ' +
                (type || '');

            el.textContent =
                message || '';
        }

        function getProductsMobileScannerUrl() {

            var protocol =
                window.location.protocol || 'http:';

            var host =
                window.location.hostname || '';

            var port =
                window.location.port
                    ? ':' + window.location.port
                    : '';

            /*
             * When the desktop is opened as localhost,
             * the phone must use the PC's LAN address.
             */
            if (
                (
                    host === 'localhost' ||
                    host === '127.0.0.1' ||
                    host === '::1'
                ) &&
                productsMobileScannerServerIp
            ) {
                host =
                    productsMobileScannerServerIp;
            }

            if (!host) {
                host =
                    productsMobileScannerServerIp ||
                    'localhost';
            }

            return (
                protocol +
                '//' +
                host +
                port +
                '/philynda/mobile_scanner.php'
            );
        }

        window.openProductsMobileScannerConnection =
            function () {

                var box =
                    document.getElementById(
                        'productsMobileScannerBox'
                    );

                var url =
                    document.getElementById(
                        'productsMobileScannerUrl'
                    );

                if (box) {
                    box.style.display = 'block';
                }

                if (url) {
                    url.value =
                        getProductsMobileScannerUrl();
                }

                setProductsMobileScannerFeedback(
                    '',
                    ''
                );

                startProductsMobileScannerConnection();
            };

        window.closeProductsMobileScannerConnection =
            function () {

                var box =
                    document.getElementById(
                        'productsMobileScannerBox'
                    );

                if (box) {
                    box.style.display = 'none';
                }

            };

        window.openProductsMobileScannerFromMobile =
            function () {

                var scannerUrl =
                    getProductsMobileScannerUrl();

                if (!scannerUrl) {

                    alert(
                        'Mobile scanner address could not be determined.'
                    );

                    return;
                }

                window.open(
                    scannerUrl,
                    '_blank'
                );
            };

        function startProductsMobileScannerConnection() {

            if (productsMobileScannerRunning) {
                return;
            }

            fetch(
                'assets/scripts/mobile_scanner_api.php?action=start&_=' +
                Date.now(),
                {
                    method: 'POST',
                    cache: 'no-store'
                }
            )
            .then(function (response) {

                return response.text()
                    .then(function (text) {

                        var data;

                        try {
                            data =
                                JSON.parse(text);
                        } catch (e) {

                            throw new Error(
                                'Scanner API did not return JSON. ' +
                                'Check mobile_scanner_api.php.'
                            );
                        }

                        return {
                            response: response,
                            data: data
                        };

                    });

            })
            .then(function (result) {

                if (
                    !result.response.ok ||
                    !result.data.success ||
                    !result.data.token
                ) {

                    throw new Error(
                        result.data.message ||
                        (
                            'Unable to start scanner (HTTP ' +
                            result.response.status +
                            ').'
                        )
                    );
                }

                productsMobileScannerToken =
                    String(
                        result.data.token
                    );

                productsMobileScannerRunning =
                    true;

                var url =
                    document.getElementById(
                        'productsMobileScannerUrl'
                    );

                if (url) {
                    url.value =
                        getProductsMobileScannerUrl();
                }

                var scannerBox =
                    document.getElementById(
                        'productsMobileScannerBox'
                    );

                if (scannerBox) {
                    scannerBox.classList.remove(
                        'connected'
                    );
                }

                setProductsMobileScannerStatus(
                    'Waiting for phone...',
                    false
                );

                startProductsMobileScannerPolling();

            })
            .catch(function (error) {

                console.error(
                    'PRODUCTS MOBILE SCANNER START ERROR:',
                    error
                );

                productsMobileScannerRunning =
                    false;

                productsMobileScannerToken =
                    null;

                setProductsMobileScannerStatus(
                    'Connection could not be started',
                    false
                );

                setProductsMobileScannerFeedback(
                    error.message ||
                    'Unable to start mobile scanner connection.',
                    'error'
                );

            });
        }

        function startProductsMobileScannerPolling() {

            stopProductsMobileScannerPolling();

            var poll =
                function () {

                    if (
                        !productsMobileScannerRunning ||
                        !productsMobileScannerToken
                    ) {
                        return;
                    }

                    fetch(
                        'assets/scripts/mobile_scanner_api.php?action=poll&token=' +
                        encodeURIComponent(
                            productsMobileScannerToken
                        ) +
                        '&_=' +
                        Date.now(),
                        {
                            method: 'GET',
                            cache: 'no-store'
                        }
                    )
                    .then(function (response) {

                        return response.text()
                            .then(function (text) {

                                var data;

                                try {
                                    data =
                                        JSON.parse(text);
                                } catch (e) {

                                    throw new Error(
                                        'Scanner poll did not return JSON.'
                                    );
                                }

                                return {
                                    response: response,
                                    data: data
                                };

                            });

                    })
                    .then(function (result) {

                        var response =
                            result.response;

                        var data =
                            result.data;

                        if (
                            !response.ok ||
                            data.success === false
                        ) {

                            productsMobileScannerRunning =
                                false;

                            productsMobileScannerToken =
                                null;

                            setProductsMobileScannerStatus(
                                data.message ||
                                'Mobile scanner connection ended.',
                                false
                            );

                            setProductsMobileScannerFeedback(
                                data.message ||
                                'Mobile scanner connection ended.',
                                'error'
                            );

                            return;
                        }

                        var scannerBox =
                            document.getElementById(
                                'productsMobileScannerBox'
                            );

                        if (data.paired) {

                            setProductsMobileScannerStatus(
                                data.device
                                    ? 'Phone connected: ' +
                                      data.device
                                    : 'Phone connected',
                                true
                            );

                            if (scannerBox) {
                                scannerBox.classList.add(
                                    'connected'
                                );
                            }

                        } else {

                            setProductsMobileScannerStatus(
                                'Waiting for phone...',
                                false
                            );

                            if (scannerBox) {
                                scannerBox.classList.remove(
                                    'connected'
                                );
                            }
                        }

                        if (data.barcode) {

                            receiveProductsMobileBarcode(
                                String(
                                    data.barcode
                                )
                            );
                        }

                    })
                    .catch(function (error) {

                        console.error(
                            'PRODUCTS MOBILE SCANNER POLL ERROR:',
                            error
                        );

                        setProductsMobileScannerFeedback(
                            error.message ||
                            'Unable to read mobile scanner queue.',
                            'error'
                        );

                    })
                    .finally(function () {

                        if (productsMobileScannerRunning) {

                            productsMobileScannerPollTimer =
                                setTimeout(
                                    poll,
                                    500
                                );
                        }

                    });
                };

            poll();
        }

        function stopProductsMobileScannerPolling() {

            if (productsMobileScannerPollTimer) {

                clearTimeout(
                    productsMobileScannerPollTimer
                );

                productsMobileScannerPollTimer =
                    null;
            }
        }

        function beepProductsScanner() {

            try {

                var AudioContextClass =
                    window.AudioContext ||
                    window.webkitAudioContext;

                if (!AudioContextClass) {
                    return;
                }

                var ctx =
                    new AudioContextClass();

                var oscillator =
                    ctx.createOscillator();

                var gain =
                    ctx.createGain();

                oscillator.frequency.value =
                    880;

                oscillator.type =
                    'sine';

                gain.gain.value =
                    0.05;

                oscillator.connect(gain);

                gain.connect(
                    ctx.destination
                );

                oscillator.start();

                setTimeout(
                    function () {

                        oscillator.stop();

                        if (ctx.close) {
                            ctx.close();
                        }

                    },
                    100
                );

            } catch (e) {
                /* Audio is only confirmation. */
            }
        }

        function receiveProductsMobileBarcode(code) {

            var searchInput =
                document.getElementById(
                    'search'
                );

            if (!searchInput) {
                return;
            }

            searchInput.value =
                code;

            searchInput.focus();

            setProductsMobileScannerFeedback(
                'Barcode received from mobile scanner: ' +
                code,
                'success'
            );

            beepProductsScanner();

            /*
             * Search immediately so the matching
             * product row appears.
             */
            loadData(1);
        }

        window.stopProductsMobileScannerConnection =
            function () {

                productsMobileScannerRunning =
                    false;

                stopProductsMobileScannerPolling();

                var token =
                    productsMobileScannerToken;

                productsMobileScannerToken =
                    null;

                if (token) {

                    fetch(
                        'assets/scripts/mobile_scanner_api.php?action=stop&token=' +
                        encodeURIComponent(token) +
                        '&_=' +
                        Date.now(),
                        {
                            method: 'POST',
                            cache: 'no-store'
                        }
                    )
                    .catch(function (error) {

                        console.error(
                            'PRODUCTS MOBILE SCANNER STOP ERROR:',
                            error
                        );

                    });
                }

                var scannerBox =
                    document.getElementById(
                        'productsMobileScannerBox'
                    );

                if (scannerBox) {
                    scannerBox.classList.remove(
                        'connected'
                    );
                }

                setProductsMobileScannerStatus(
                    'Not connected',
                    false
                );

                setProductsMobileScannerFeedback(
                    '',
                    ''
                );
            };

        window.copyProductsMobileScannerUrl =
            function () {

                var input =
                    document.getElementById(
                        'productsMobileScannerUrl'
                    );

                if (!input) {
                    return;
                }

                input.select();

                input.setSelectionRange(
                    0,
                    input.value.length
                );

                if (
                    navigator.clipboard &&
                    navigator.clipboard.writeText
                ) {

                    navigator.clipboard.writeText(
                        input.value
                    )
                    .then(function () {

                        alert(
                            'Mobile scanner address copied.'
                        );

                    })
                    .catch(function () {

                        alert(
                            'Copy failed. Please copy the address manually.'
                        );

                    });

                } else {

                    try {

                        document.execCommand(
                            'copy'
                        );

                        alert(
                            'Mobile scanner address copied.'
                        );

                    } catch (e) {

                        alert(
                            'Please copy the address manually.'
                        );
                    }
                }
            };

        window.addEventListener(
            'beforeunload',
            function () {

                if (!productsMobileScannerToken) {
                    return;
                }

                try {

                    navigator.sendBeacon(
                        'assets/scripts/mobile_scanner_api.php?action=stop&token=' +
                        encodeURIComponent(
                            productsMobileScannerToken
                        ),
                        ''
                    );

                } catch (e) {
                    /* Ignore unload cleanup failures. */
                }

            }
        );

    })();

    /*
     * Initial load.
     * Automatically start the remote mobile-scanner session on desktop
     * without requiring the Connect Mobile Scanner button to be clicked.
     * On phones/tablets, keep the existing Open Mobile Scanner workflow.
     */
    loadData();

    window.addEventListener('load', function () {
        setTimeout(function () {
            if (window.innerWidth > 991.98) {
                if (typeof window.openProductsMobileScannerConnection === 'function') {
                    window.openProductsMobileScannerConnection();
                }
            }
        }, 300);
    });

    </script>

</body>

</html>