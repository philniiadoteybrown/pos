<?php

$pagetitle = "Products Closing Stock";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";


/*
|--------------------------------------------------------------------------
| SERVER IP FOR MOBILE SCANNER
|--------------------------------------------------------------------------
*/

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
        filter_var(
            $serverAddr,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
        )
    ) {
        return $serverAddr;
    }

    return '';
}


$productsServerIp = detectProductsServerIp();


/*
|--------------------------------------------------------------------------
| BARCODE RESOLUTION
|--------------------------------------------------------------------------
|
| The scanner sends the barcode.
|
| We resolve:
|
| barcode column -> productid
|
| OR
|
| productid -> productid
|
| This allows the Smart Closing AJAX file to receive
| the actual product ID.
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'resolve_barcode'
) {

    header('Content-Type: application/json; charset=utf-8');

    $barcode = trim(
        isset($_GET['barcode'])
            ? $_GET['barcode']
            : ''
    );


    if ($barcode === '') {

        echo json_encode([
            'success' => false,
            'message' => 'Barcode is empty.'
        ]);

        exit;
    }


    $barcodeEscaped =
        mysqli_real_escape_string(
            $conn,
            $barcode
        );


    /*
     * Check whether products table has barcode column.
     */

    $hasBarcodeColumn = false;

    $columnResult =
        mysqli_query(
            $conn,
            "SHOW COLUMNS FROM products"
        );


    if ($columnResult) {

        while (
            $column =
                mysqli_fetch_assoc(
                    $columnResult
                )
        ) {

            if (
                isset($column['Field']) &&
                strtolower($column['Field']) === 'barcode'
            ) {

                $hasBarcodeColumn = true;

                break;
            }
        }
    }


    /*
     * Search barcode first when barcode column exists.
     */

    if ($hasBarcodeColumn) {

        $lookupSql = "
            SELECT
                productid,
                pname,
                barcode
            FROM products
            WHERE barcode = '$barcodeEscaped'
               OR productid = '$barcodeEscaped'
            LIMIT 1
        ";

    } else {

        $lookupSql = "
            SELECT
                productid,
                pname
            FROM products
            WHERE productid = '$barcodeEscaped'
            LIMIT 1
        ";
    }


    $lookupResult =
        mysqli_query(
            $conn,
            $lookupSql
        );


    if (!$lookupResult) {

        echo json_encode([
            'success' => false,
            'message' => mysqli_error($conn)
        ]);

        exit;
    }


    $product =
        mysqli_fetch_assoc(
            $lookupResult
        );


    if (!$product) {

        echo json_encode([
            'success' => false,
            'message' =>
                'No product found for barcode: ' .
                $barcode
        ]);

        exit;
    }


    echo json_encode([
        'success' => true,
        'productid' => $product['productid'],
        'pname' => isset($product['pname'])
            ? $product['pname']
            : '',
        'barcode' => isset($product['barcode'])
            ? $product['barcode']
            : $barcode
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| EXISTING SEARCH / PAGING
|--------------------------------------------------------------------------
*/

$where = "";


if ($search != "") {

    $searchEscaped =
        mysqli_real_escape_string(
            $conn,
            $search
        );


    $where = "
        WHERE pname LIKE '%$searchEscaped%'
           OR productid LIKE '%$searchEscaped%'
    ";
}


/*
|--------------------------------------------------------------------------
| TOTAL ROWS
|--------------------------------------------------------------------------
*/

$totalRes =
    mysqli_query(
        $conn,
        "SELECT COUNT(*) as total
         FROM products
         $where"
    );


$totalRow =
    mysqli_fetch_assoc(
        $totalRes
    );


$total =
    isset($totalRow['total'])
        ? $totalRow['total']
        : 0;


$total_pages =
    $limit > 0
        ? ceil($total / $limit)
        : 0;


/*
|--------------------------------------------------------------------------
| FETCH DATA
|--------------------------------------------------------------------------
*/

$res =
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM products
        $where
        ORDER BY pname ASC
        LIMIT $offset, $limit
        "
    );


/*
|--------------------------------------------------------------------------
| SMART CLOSING POST
|--------------------------------------------------------------------------
*/

if (isset($_POST['product_id'])) {

    $ids =
        isset($_POST['product_id'])
            ? $_POST['product_id']
            : [];

    $phys =
        isset($_POST['physical_qty'])
            ? $_POST['physical_qty']
            : [];


    mysqli_begin_transaction($conn);


    try {

        foreach ($ids as $i => $pid) {

            $pid =
                mysqli_real_escape_string(
                    $conn,
                    trim($pid)
                );


            /*
             * Skip empty physical inputs.
             */

            if (
                !isset($phys[$i]) ||
                $phys[$i] === ''
            ) {
                continue;
            }


            $physical =
                floatval(
                    $phys[$i]
                );


            /*
             * Get current product data.
             */

            $productResult =
                mysqli_query(
                    $conn,
                    "
                    SELECT
                        pname,
                        totalstock
                    FROM products
                    WHERE productid='$pid'
                    "
                );


            if (!$productResult) {

                throw new Exception(
                    mysqli_error($conn)
                );
            }


            $row =
                mysqli_fetch_assoc(
                    $productResult
                );


            if (!$row) {
                continue;
            }


            $product_name =
                mysqli_real_escape_string(
                    $conn,
                    $row['pname']
                );


            $system =
                floatval(
                    $row['totalstock']
                );


            $difference =
                $physical - $system;


            /*
             * Skip unchanged stock.
             */

            if ($difference == 0) {
                continue;
            }


            /*
             * Save adjustment history.
             */

            $insert =
                mysqli_query(
                    $conn,
                    "
                    INSERT INTO stock_adjustments
                    (
                        product_id,
                        product_name,
                        system_qty,
                        physical_qty,
                        difference,
                        reason,
                        created_at
                    )
                    VALUES
                    (
                        '$pid',
                        '$product_name',
                        '$system',
                        '$physical',
                        '$difference',
                        'Smart Closing',
                        NOW()
                    )
                    "
                );


            if (!$insert) {

                throw new Exception(
                    mysqli_error($conn)
                );
            }


            /*
             * Update product stock.
             */

            $update =
                mysqli_query(
                    $conn,
                    "
                    UPDATE products
                    SET totalstock='$physical'
                    WHERE productid='$pid'
                    "
                );


            if (!$update) {

                throw new Exception(
                    mysqli_error($conn)
                );
            }
        }


        mysqli_commit($conn);


        $msg =
            "Bulk closing completed successfully";


    } catch (Exception $e) {

        mysqli_rollback($conn);


        $errmsg =
            $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <?php include "assets/sections/headers/header_tag.php"; ?>

    <style>

    /*
    |--------------------------------------------------------------------------
    | GENERAL TABLE
    |--------------------------------------------------------------------------
    */

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        border: 2px solid #000;
        padding: 8px;
        text-align: left;
        vertical-align: middle;
    }

    thead {
        background-color: #f2f2f2;
    }

    tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }


    /*
    |--------------------------------------------------------------------------
    | TWO-COLUMN PAGE LAYOUT
    |--------------------------------------------------------------------------
    | Same layout as Products page.
    |
    | Desktop:
    | Main content | Scanner
    |
    | Mobile:
    | Main content
    | Scanner
    |--------------------------------------------------------------------------
    */

    .products-page-layout {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            minmax(280px, 340px);

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


    /*
    |--------------------------------------------------------------------------
    | MOBILE SCANNER PANEL
    |--------------------------------------------------------------------------
    */

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

        box-shadow:
            0 4px 14px rgba(0,0,0,.08);
    }


    .products-mobile-scanner-box-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 8px;

        padding: 10px 12px;

        background: #f5f7fa;

        border-bottom:
            1px solid #e1e5ea;
    }


    .products-mobile-scanner-box-body {
        padding: 12px;
    }


    .products-mobile-scanner-status {

        display: flex;

        justify-content:
            space-between;

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

        border:
            1px solid #badbcc;
    }


    .products-mobile-scanner-feedback.error {

        display: block;

        background: #f8d7da;

        color: #842029;

        border:
            1px solid #f5c2c7;
    }


    .products-mobile-scanner-box.connected
    .products-mobile-scanner-details {

        display: none;
    }


    .products-mobile-scanner-box.connected
    .products-mobile-scanner-box-header {

        background: #eaf7ef;
    }


    .products-mobile-scanner-status strong {
        font-weight: 700;
    }


    /*
    |--------------------------------------------------------------------------
    | SMART CLOSING CONTROLS
    |--------------------------------------------------------------------------
    */

    .closing-search-toolbar {

        display: flex;

        align-items: center;

        gap: 10px;

        width: 100%;

        flex-wrap: wrap;
    }


    .closing-search-toolbar #searchInput {

        flex: 2;

        min-width: 180px;
    }


    .closing-search-toolbar #category {

        flex: 1;

        min-width: 150px;
    }


    .closing-search-toolbar #limit {

        flex: .7;

        min-width: 80px;
    }


    .closing-table-wrap {

        max-height: 650px;

        overflow-y: auto;

        overflow-x: auto;

        border: 1px solid #ddd;

        padding: 10px;

        background: #fff;
    }


    .closing-table-wrap table {

        min-width: 760px;
    }


    /*
    |--------------------------------------------------------------------------
    | CAMERA BARCODE SCANNER
    |--------------------------------------------------------------------------
    */

    #productsCameraScannerOverlay {

        display: none;

        position: fixed;

        z-index: 99999;

        inset: 0;

        background: rgba(0,0,0,.92);

        padding: 20px;
    }


    .camera-scanner-container {

        max-width: 650px;

        margin: 40px auto;

        background: #fff;

        padding: 15px;

        border-radius: 8px;
    }


    #productsQrReader {
        width: 100%;
    }


    /*
    |--------------------------------------------------------------------------
    | PRINT
    |--------------------------------------------------------------------------
    */

    @media print {

        body * {
            visibility: hidden;
        }


        #closeReport,
        #closeReport * {

            visibility: visible;
        }


        #closeReport {

            position: absolute;

            top: 0;

            left: 0;

            width: 100%;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TABLET / MOBILE
    |--------------------------------------------------------------------------
    */

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

        .closing-search-toolbar {

            flex-direction: column;

            align-items: stretch;

            gap: 7px;
        }


        .closing-search-toolbar > * {

            width: 100% !important;

            min-width: 100% !important;

            margin: 0 !important;
        }


        .closing-search-toolbar span {

            display: none;
        }


        .products-mobile-scanner-url-row {

            flex-direction: column;
        }


        .products-mobile-scanner-url-row .btn {

            width: 100%;
        }


        .products-mobile-scanner-box-body {

            padding: 10px;
        }


        .closing-table-wrap {

            padding: 5px;
        }


        .closing-table-wrap table {

            font-size: 12px;
        }


        th,
        td {

            padding: 6px;
        }


        .camera-scanner-container {

            margin: 20px auto;

            padding: 10px;
        }
    }

    </style>

</head>


<body class="fixed-left">


<!-- PRELOADER -->

<div id="preloader">

    <div id="status">

        <div class="spinner"></div>

    </div>

</div>


<div id="wrapper">


    <!-- LEFT SIDEBAR -->

    <?php include "assets/sections/leftside.php"; ?>


    <!-- CONTENT PAGE -->

    <div class="content-page">

        <div class="content">


            <!-- TOP BAR -->

            <?php include "assets/sections/topbar.php"; ?>


            <div class="page-content-wrapper">

                <div class="container-fluid">


                    <div class="row">

                        <div class="col-sm-12">

                            <br>

                        </div>

                    </div>


                    <!-- =====================================================
                         TWO COLUMN LAYOUT
                         ===================================================== -->

                    <div class="products-page-layout">


                        <!-- =================================================
                             MAIN SMART CLOSING COLUMN
                             ================================================= -->

                        <div class="products-main-column">


                            <div class="card m-b-30">

                                <div class="card-body">


                                    <h2>
                                        Products Closing Stock
                                    </h2>


                                    <?php if (isset($msg)) { ?>

                                        <div
                                            class="alert alert-success alert-dismissible fade show"
                                            role="alert"
                                        >

                                            <button
                                                type="button"
                                                class="close"
                                                data-dismiss="alert"
                                            >

                                                <span>
                                                    &times;
                                                </span>

                                            </button>

                                            <?php echo htmlspecialchars($msg); ?>

                                        </div>

                                    <?php } ?>


                                    <?php if (isset($errmsg)) { ?>

                                        <div
                                            class="alert alert-danger alert-dismissible fade show"
                                            role="alert"
                                        >

                                            <button
                                                type="button"
                                                class="close"
                                                data-dismiss="alert"
                                            >

                                                <span>
                                                    &times;
                                                </span>

                                            </button>

                                            <?php echo htmlspecialchars($errmsg); ?>

                                        </div>

                                    <?php } ?>


                                    <!-- =================================================
                                         SEARCH
                                         ================================================= -->

                                    <div class="card-header-form">

                                        <form method="GET">

                                            <div class="closing-search-toolbar">


                                                <input
                                                    type="text"
                                                    id="searchInput"
                                                    class="form-control"
                                                    placeholder="Search product, ID or barcode..."
                                                    autocomplete="off"
                                                >


                                                <select
                                                    id="category"
                                                    class="form-control"
                                                >

                                                    <option value="">
                                                        All Categories
                                                    </option>


                                                    <?php

                                                    $catRes =
                                                        mysqli_query(
                                                            $conn,
                                                            "
                                                            SELECT DISTINCT category
                                                            FROM products
                                                            WHERE category IS NOT NULL
                                                            AND category <> ''
                                                            ORDER BY category ASC
                                                            "
                                                        );


                                                    while (
                                                        $cat =
                                                            mysqli_fetch_assoc(
                                                                $catRes
                                                            )
                                                    ) {

                                                        echo
                                                            "<option value='" .
                                                            htmlspecialchars(
                                                                $cat['category'],
                                                                ENT_QUOTES
                                                            ) .
                                                            "'>" .
                                                            htmlspecialchars(
                                                                $cat['category']
                                                            ) .
                                                            "</option>";
                                                    }

                                                    ?>

                                                </select>


                                                <span>
                                                    Showing
                                                </span>


                                                <select
                                                    id="limit"
                                                    class="form-control"
                                                >

                                                    <option value="5">
                                                        5
                                                    </option>

                                                    <option
                                                        value="10"
                                                        selected
                                                    >
                                                        10
                                                    </option>

                                                    <option value="25">
                                                        25
                                                    </option>

                                                    <option value="50">
                                                        50
                                                    </option>

                                                </select>


                                                <span>
                                                    rows per page
                                                </span>


                                            </div>

                                        </form>


                                        <br>

                                        <hr>

                                        <br>


                                        <!-- =================================================
                                             SMART CLOSING
                                             ================================================= -->

                                        <h3>
                                            🧾 Smart Closing System
                                        </h3>


                                        <form method="POST">


                                            <div class="closing-table-wrap">


                                                <!-- ACTION BUTTONS -->

                                                <div
                                                    class="mb-3"
                                                    style="
                                                        display:flex;
                                                        gap:8px;
                                                        flex-wrap:wrap;
                                                    "
                                                >

                                                    <button
                                                        type="button"
                                                        class="btn btn-primary btn-sm"
                                                        onclick="copyAllSystem()"
                                                    >

                                                        📋 Copy All System Stock

                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="btn btn-dark btn-sm"
                                                        onclick="generateCloseReport()"
                                                    >

                                                        📄 Generate Close Report

                                                    </button>

                                                </div>


                                                <!-- CLOSING TABLE -->

                                                <table
                                                    class="table table-bordered"
                                                >

                                                    <thead
                                                        style="
                                                            position:sticky;
                                                            top:0;
                                                            background:#fff;
                                                            z-index:2;
                                                        "
                                                    >

                                                        <tr>

                                                            <th>
                                                                Product
                                                            </th>

                                                            <th>
                                                                System Stock
                                                            </th>

                                                            <th>
                                                                Physical Count
                                                            </th>

                                                            <th>
                                                                Difference
                                                            </th>

                                                            <th>
                                                                Status
                                                            </th>

                                                        </tr>

                                                    </thead>


                                                    <tbody
                                                        id="closingTable"
                                                    ></tbody>

                                                </table>


                                                <br>


                                                <!-- REPORT -->

                                                <div
                                                    id="closeReport"
                                                    style="
                                                        display:none;
                                                        border:1px solid #000;
                                                        padding:15px;
                                                        background:#fff;
                                                    "
                                                >

                                                    <h3>
                                                        🧾 Daily Closing Report
                                                    </h3>


                                                    <button
                                                        type="button"
                                                        class="btn btn-success btn-sm"
                                                        onclick="window.print()"
                                                    >

                                                        🖨 Print Report

                                                    </button>


                                                    <hr>


                                                    <div
                                                        id="reportContent"
                                                    ></div>

                                                </div>


                                            </div>


                                            <br>


                                            <button
                                                type="submit"
                                                class="btn btn-success btn-lg"
                                            >

                                                Save Closing Stock

                                            </button>


                                        </form>


                                    </div>


                                </div>

                            </div>


                        </div>


                        <!-- =================================================
                             MOBILE SCANNER RIGHT COLUMN
                             ================================================= -->

                        <div class="products-scanner-column">


                            <div class="card border m-b-20">

                                <div class="card-body">


                                    <h4 class="products-scanner-title">

                                        📱 Mobile Scanner

                                    </h4>


                                    <p class="products-scanner-note">

                                        Connect a phone camera to this
                                        Smart Closing page. A barcode
                                        scanned on the phone will be
                                        resolved against the product
                                        database and loaded automatically.

                                    </p>


                                    <!-- DESKTOP -->

                                    <button
                                        type="button"
                                        class="btn btn-primary products-mobile-scanner-connect-btn"
                                        onclick="openProductsMobileScannerConnection()"
                                    >

                                        📱 Connect Mobile Scanner

                                    </button>


                                    <!-- MOBILE / TABLET -->

                                    <button
                                        type="button"
                                        class="btn btn-primary products-mobile-scanner-open-btn"
                                        onclick="openProductsMobileScannerFromMobile()"
                                    >

                                        📱 Open Mobile Scanner

                                    </button>


                                    <!-- FEEDBACK -->

                                    <div
                                        id="productsMobileScannerFeedback"
                                        class="products-mobile-scanner-feedback"
                                        role="alert"
                                        aria-live="assertive"
                                    ></div>


                                    <!-- CONNECTION BOX -->

                                    <div
                                        id="productsMobileScannerBox"
                                        class="products-mobile-scanner-box"
                                    >


                                        <div
                                            class="products-mobile-scanner-box-header"
                                        >

                                            <strong>
                                                📱 Remote Mobile Scanner
                                            </strong>


                                            <button
                                                type="button"
                                                class="btn btn-sm btn-danger"
                                                onclick="closeProductsMobileScannerConnection()"
                                                aria-label="Close"
                                            >

                                                &times;

                                            </button>

                                        </div>


                                        <div
                                            class="products-mobile-scanner-box-body"
                                        >


                                            <div
                                                class="products-mobile-scanner-status"
                                            >

                                                <span>
                                                    Status:
                                                </span>


                                                <strong
                                                    id="productsMobileScannerStatus"
                                                >

                                                    Not connected

                                                </strong>

                                            </div>


                                            <div
                                                class="products-mobile-scanner-details"
                                            >


                                                <label class="mb-1">

                                                    <strong>
                                                        Open on the phone:
                                                    </strong>

                                                </label>


                                                <div
                                                    class="products-mobile-scanner-url-row"
                                                >

                                                    <input
                                                        type="text"
                                                        id="productsMobileScannerUrl"
                                                        class="form-control"
                                                        readonly
                                                    >


                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary"
                                                        onclick="copyProductsMobileScannerUrl()"
                                                    >

                                                        Copy

                                                    </button>

                                                </div>


                                                <p
                                                    class="products-scanner-note"
                                                >

                                                    No pairing code is
                                                    required. Open the
                                                    address on the phone
                                                    and scan the barcode.

                                                </p>


                                            </div>


                                            <div
                                                class="products-mobile-scanner-actions"
                                            >

                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="stopProductsMobileScannerConnection()"
                                                >

                                                    Stop Connection

                                                </button>

                                            </div>


                                        </div>

                                    </div>


                                    <hr>


                                    <!-- LOCAL CAMERA -->

                                    <button
                                        type="button"
                                        class="btn btn-dark btn-block"
                                        onclick="startProductsLocalScanner()"
                                    >

                                        📷 Scan With Camera

                                    </button>


                                </div>

                            </div>


                        </div>


                    </div>


                </div>

            </div>


        </div>


        <!-- FOOTER -->

        <footer class="footer">

            <?php include "assets/sections/footers/footer.php"; ?>

        </footer>


    </div>

</div>


<!-- =========================================================
     CAMERA SCANNER
     ========================================================= -->

<div
    id="productsCameraScannerOverlay"
    class="camera-scanner-overlay"
>


    <div class="camera-scanner-container">


        <div
            class="d-flex justify-content-between align-items-center mb-2"
        >

            <h5 class="mb-0">
                Scan Barcode
            </h5>


            <button
                type="button"
                class="btn btn-danger btn-sm"
                onclick="stopProductsLocalScanner()"
            >

                Close

            </button>

        </div>


        <div id="productsQrReader"></div>


        <div
            id="productsCameraFeedback"
            class="mt-2 text-center font-weight-bold"
        ></div>


    </div>

</div>


<!-- =========================================================
     RESTOCK MODAL
     ========================================================= -->

<div
    class="modal fade"
    id="restock"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">


            <form
                action="assets/scripts/process_addrestock.php"
                method="post"
            >


                <div class="modal-header">

                    <h5 class="modal-title">
                        Re-Stock
                    </h5>


                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                    >

                        &times;

                    </button>

                </div>


                <div class="modal-body">


                    <input
                        type="hidden"
                        name="productid"
                        id="modal-id"
                    >


                    <p>

                        <strong>
                            Product ID:
                        </strong>

                        <span id="modal-id-span"></span>

                    </p>


                    <p>

                        <strong>
                            Product Name:
                        </strong>

                        <span id="modal-name"></span>

                    </p>


                    <p>

                        <strong>
                            Description:
                        </strong>

                        <span id="modal-description"></span>

                    </p>


                    <p>

                        <strong>
                            Quantity Available:
                        </strong>

                        <span id="modal-qty"></span>

                    </p>


                    <p>

                        <strong>
                            Unit Cost:
                        </strong>

                        <span id="modal-uc"></span>

                    </p>


                    <div class="form-group">

                        <label>
                            Quantity to Stock
                        </label>


                        <input
                            type="number"
                            class="form-control"
                            name="qpurchase"
                            required
                        >

                    </div>


                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-dismiss="modal"
                    >

                        Close

                    </button>


                    <button
                        type="submit"
                        name="addstock"
                        class="btn btn-success"
                    >

                        Add Stock

                    </button>


                </div>


            </form>


        </div>

    </div>

</div>


<!-- =========================================================
     SELL MODAL
     ========================================================= -->

<div
    class="modal fade"
    id="sell"
    tabindex="-1"
    role="dialog"
    aria-labelledby="formModal"
    aria-hidden="true"
>


    <div
        class="modal-dialog"
        role="document"
    >

        <div class="modal-content">


            <div class="modal-header">


                <h5
                    class="modal-title"
                    id="formModal"
                >

                    Single Unit Sales

                </h5>


                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                >

                    <span>
                        &times;
                    </span>

                </button>


            </div>


            <div class="modal-body">


                <form
                    action="assets/scripts/process_addsales.php"
                    method="post"
                >


                    <input
                        type="hidden"
                        name="productid"
                        id="modal-sid"
                    >


                    <p>

                        <strong>
                            Product ID:
                        </strong>

                        <b>
                            <span id="modal-sid-span"></span>
                        </b>

                    </p>


                    <p>

                        <strong>
                            Product Name:
                        </strong>

                        <b>
                            <span id="modal-sname"></span>
                        </b>

                    </p>


                    <p>

                        <strong>
                            Description:
                        </strong>

                        <b>
                            <span id="modal-sdescription"></span>
                        </b>

                    </p>


                    <p>

                        <strong>
                            Stock Available:
                        </strong>

                        <b>
                            <span id="modal-sqty"></span>
                        </b>

                    </p>


                    <p>

                        <strong>
                            Price:
                        </strong>

                        <b>
                            <span id="modal-ssp"></span>
                        </b>

                    </p>


                    <hr>


                    <div class="form-group">

                        <label>
                            Quantity Sold
                        </label>


                        <input
                            type="number"
                            class="form-control"
                            name="qtysold"
                            min="1"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-success"
                        name="addsale"
                    >

                        Add Sales

                    </button>


                </form>


            </div>


        </div>

    </div>

</div>


<!-- jQuery / Bootstrap scripts -->

<?php include "assets/sections/footers/jqueryscripts.php"; ?>


<?php include "assets/js/stop_save.js"; ?>


<!-- html5-qrcode -->

<script src="assets/scripts/html5-qrcode.min.js"></script>


<script>

/*
|--------------------------------------------------------------------------
| SERVER IP
|--------------------------------------------------------------------------
*/

const productsMobileScannerServerIp =
    <?php echo json_encode($productsServerIp); ?>;


/*
|--------------------------------------------------------------------------
| MOBILE SCANNER VARIABLES
|--------------------------------------------------------------------------
*/

let productsMobileScannerPollTimer = null;

let productsMobileScannerToken = '';

let productsMobileScannerRunning = false;


/*
|--------------------------------------------------------------------------
| LOCAL CAMERA SCANNER
|--------------------------------------------------------------------------
*/

let productsLocalScanner = null;

let productsLocalScannerRunning = false;

let lastProductsLocalBarcode = '';

let lastProductsLocalBarcodeTime = 0;


/*
|--------------------------------------------------------------------------
| SMART CLOSING SEARCH TIMER
|--------------------------------------------------------------------------
*/

let closingTimer = null;


/*
|--------------------------------------------------------------------------
| MOBILE SCANNER URL
|--------------------------------------------------------------------------
*/

function getProductsMobileScannerUrl()
{
    const protocol =
        window.location.protocol === 'https:'
            ? 'https:'
            : 'http:';


    let host =
        window.location.hostname;


    const port =
        window.location.port
            ? ':' + window.location.port
            : '';


    if (
        host === 'localhost' ||
        host === '127.0.0.1' ||
        host === '::1'
    ) {

        if (
            productsMobileScannerServerIp &&
            productsMobileScannerServerIp !== '127.0.0.1' &&
            productsMobileScannerServerIp !== '::1'
        ) {

            host =
                productsMobileScannerServerIp;
        }
    }


    return (
        protocol +
        '//' +
        host +
        port +
        '/philynda/mobile_scanner.php'
    );
}


/*
|--------------------------------------------------------------------------
| OPEN DESKTOP MOBILE SCANNER
|--------------------------------------------------------------------------
*/

function openProductsMobileScannerConnection()
{
    const box =
        document.getElementById(
            'productsMobileScannerBox'
        );


    const url =
        document.getElementById(
            'productsMobileScannerUrl'
        );


    const status =
        document.getElementById(
            'productsMobileScannerStatus'
        );


    const feedback =
        document.getElementById(
            'productsMobileScannerFeedback'
        );


    if (box) {
        box.style.display = 'block';
    }


    if (url) {
        url.value =
            getProductsMobileScannerUrl();
    }


    if (status) {

        status.textContent =
            'Connecting to scanner...';

        status.className =
            'products-mobile-scanner-status';
    }


    if (feedback) {

        feedback.className =
            'products-mobile-scanner-feedback';

        feedback.textContent =
            '';
    }


    startProductsMobileScannerConnection();
}


/*
|--------------------------------------------------------------------------
| CLOSE SCANNER CONNECTION PANEL
|--------------------------------------------------------------------------
*/

function closeProductsMobileScannerConnection()
{
    const box =
        document.getElementById(
            'productsMobileScannerBox'
        );


    if (box) {
        box.style.display = 'none';
    }
}


/*
|--------------------------------------------------------------------------
| OPEN MOBILE SCANNER ON PHONE
|--------------------------------------------------------------------------
*/

function openProductsMobileScannerFromMobile()
{
    const url =
        getProductsMobileScannerUrl();


    if (!url) {

        alert(
            'Mobile scanner address could not be determined.'
        );

        return;
    }


    window.open(
        url,
        '_blank'
    );
}


/*
|--------------------------------------------------------------------------
| START MOBILE SCANNER CONNECTION
|--------------------------------------------------------------------------
*/

function startProductsMobileScannerConnection()
{
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

    .then(function(response) {

        return response.text()
            .then(function(text) {

                let data;

                try {

                    data =
                        JSON.parse(text);

                } catch (e) {

                    throw new Error(
                        'Scanner API did not return valid JSON.'
                    );
                }


                return {
                    response: response,
                    data: data
                };

            });
    })


    .then(function(result) {

        if (
            !result.response.ok ||
            !result.data.success ||
            !result.data.token
        ) {

            throw new Error(
                result.data.message ||
                'Unable to start mobile scanner.'
            );
        }


        productsMobileScannerToken =
            String(
                result.data.token
            );


        productsMobileScannerRunning =
            true;


        const status =
            document.getElementById(
                'productsMobileScannerStatus'
            );


        if (status) {

            status.textContent =
                'Waiting for phone...';

            status.style.color =
                '#856404';
        }


        pollProductsMobileScanner();

    })


    .catch(function(error) {

        console.error(
            'PRODUCTS MOBILE SCANNER START ERROR:',
            error
        );


        productsMobileScannerRunning =
            false;


        productsMobileScannerToken =
            '';


        const status =
            document.getElementById(
                'productsMobileScannerStatus'
            );


        if (status) {

            status.textContent =
                'Connection failed';

            status.style.color =
                '#842029';
        }


        showProductsScannerFeedback(
            error.message ||
            'Unable to start mobile scanner.',
            false
        );

    });
}


/*
|--------------------------------------------------------------------------
| POLL MOBILE SCANNER
|--------------------------------------------------------------------------
*/

function pollProductsMobileScanner()
{
    if (
        !productsMobileScannerRunning ||
        !productsMobileScannerToken
    ) {
        return;
    }


    const url =
        'assets/scripts/mobile_scanner_api.php' +
        '?action=poll' +
        '&token=' +
        encodeURIComponent(
            productsMobileScannerToken
        ) +
        '&_=' +
        Date.now();


    fetch(
        url,
        {
            method: 'GET',
            cache: 'no-store'
        }
    )


    .then(function(response) {

        return response.text()
            .then(function(text) {

                let data;

                try {

                    data =
                        JSON.parse(text);

                } catch (e) {

                    throw new Error(
                        'Scanner poll returned invalid JSON.'
                    );
                }


                return {
                    response: response,
                    data: data
                };

            });

    })


    .then(function(result) {

        if (!productsMobileScannerRunning) {
            return;
        }


        const response =
            result.response;


        const data =
            result.data;


        if (
            !response.ok ||
            data.success === false
        ) {

            throw new Error(
                data.message ||
                'Mobile scanner connection ended.'
            );
        }


        const status =
            document.getElementById(
                'productsMobileScannerStatus'
            );


        const box =
            document.getElementById(
                'productsMobileScannerBox'
            );


        if (data.paired) {

            if (status) {

                status.textContent =
                    data.device
                        ? '📱 Phone connected: ' +
                          data.device
                        : '📱 Mobile scanner connected';

                status.style.color =
                    '#198754';
            }


            if (box) {

                box.classList.add(
                    'connected'
                );
            }

        } else {

            if (status) {

                status.textContent =
                    'Waiting for phone...';

                status.style.color =
                    '#856404';
            }


            if (box) {

                box.classList.remove(
                    'connected'
                );
            }
        }


        /*
         * Barcode received.
         */

        if (data.barcode) {

            receiveProductsMobileBarcode(
                String(
                    data.barcode
                )
            );
        }

    })


    .catch(function(error) {

        if (!productsMobileScannerRunning) {
            return;
        }


        console.error(
            'PRODUCTS MOBILE SCANNER POLL ERROR:',
            error
        );


        showProductsScannerFeedback(
            error.message ||
            'Unable to read mobile scanner queue.',
            false
        );

    })


    .finally(function() {

        if (
            productsMobileScannerRunning
        ) {

            productsMobileScannerPollTimer =
                setTimeout(
                    pollProductsMobileScanner,
                    500
                );
        }
    });
}


/*
|--------------------------------------------------------------------------
| STOP MOBILE SCANNER
|--------------------------------------------------------------------------
*/

function stopProductsMobileScannerConnection()
{
    productsMobileScannerRunning =
        false;


    if (
        productsMobileScannerPollTimer
    ) {

        clearTimeout(
            productsMobileScannerPollTimer
        );


        productsMobileScannerPollTimer =
            null;
    }


    const token =
        productsMobileScannerToken;


    productsMobileScannerToken =
        '';


    if (token) {

        fetch(
            'assets/scripts/mobile_scanner_api.php' +
            '?action=stop&token=' +
            encodeURIComponent(token) +
            '&_=' +
            Date.now(),
            {
                method: 'POST',
                cache: 'no-store'
            }
        )
        .catch(function(error) {

            console.log(
                'Scanner stop error:',
                error
            );

        });
    }


    const status =
        document.getElementById(
            'productsMobileScannerStatus'
        );


    if (status) {

        status.textContent =
            'Not connected';

        status.style.color =
            '#856404';
    }


    const box =
        document.getElementById(
            'productsMobileScannerBox'
        );


    if (box) {

        box.classList.remove(
            'connected'
        );
    }
}


/*
|--------------------------------------------------------------------------
| BEFORE UNLOAD
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'beforeunload',
    function() {

        if (
            !productsMobileScannerToken
        ) {
            return;
        }


        try {

            navigator.sendBeacon(
                'assets/scripts/mobile_scanner_api.php' +
                '?action=stop&token=' +
                encodeURIComponent(
                    productsMobileScannerToken
                ),
                ''
            );

        } catch (e) {

            /*
             * Ignore unload cleanup errors.
             */
        }
    }
);


/*
|--------------------------------------------------------------------------
| COPY SCANNER URL
|--------------------------------------------------------------------------
*/

function copyProductsMobileScannerUrl()
{
    const input =
        document.getElementById(
            'productsMobileScannerUrl'
        );


    if (!input) {
        return;
    }


    const value =
        input.value;


    if (
        navigator.clipboard &&
        navigator.clipboard.writeText
    ) {

        navigator.clipboard.writeText(
            value
        )

        .then(function() {

            showProductsScannerFeedback(
                'Mobile scanner address copied.',
                true
            );

        })

        .catch(function() {

            fallbackCopyScannerUrl(
                input
            );
        });

    } else {

        fallbackCopyScannerUrl(
            input
        );
    }
}


/*
|--------------------------------------------------------------------------
| FALLBACK COPY
|--------------------------------------------------------------------------
*/

function fallbackCopyScannerUrl(input)
{
    try {

        input.select();

        input.setSelectionRange(
            0,
            input.value.length
        );


        document.execCommand(
            'copy'
        );


        showProductsScannerFeedback(
            'Mobile scanner address copied.',
            true
        );

    } catch (e) {

        alert(
            'Please copy the scanner address manually.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| SCANNER FEEDBACK
|--------------------------------------------------------------------------
*/

function showProductsScannerFeedback(
    message,
    success
)
{
    const feedback =
        document.getElementById(
            'productsMobileScannerFeedback'
        );


    if (!feedback) {
        return;
    }


    feedback.textContent =
        message || '';


    feedback.className =
        'products-mobile-scanner-feedback ' +
        (
            success
                ? 'success'
                : 'error'
        );
}


/*
|--------------------------------------------------------------------------
| SCANNER BEEP
|--------------------------------------------------------------------------
*/

function productsScannerBeep()
{
    try {

        const AudioContextClass =
            window.AudioContext ||
            window.webkitAudioContext;


        if (!AudioContextClass) {
            return;
        }


        const context =
            new AudioContextClass();


        const oscillator =
            context.createOscillator();


        const gain =
            context.createGain();


        oscillator.frequency.value =
            900;


        oscillator.type =
            'sine';


        gain.gain.value =
            0.08;


        oscillator.connect(
            gain
        );


        gain.connect(
            context.destination
        );


        oscillator.start();


        setTimeout(
            function() {

                try {
                    oscillator.stop();
                } catch (e) {}


                if (context.close) {
                    context.close();
                }

            },
            120
        );

    } catch (e) {

        /*
         * Audio is only confirmation.
         */
    }
}


/*
|--------------------------------------------------------------------------
| RECEIVE MOBILE BARCODE
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Do NOT immediately send the barcode to fetch_closing.php.
|
| First resolve it against products.
|--------------------------------------------------------------------------
*/

function receiveProductsMobileBarcode(code)
{
    code =
        String(
            code || ''
        ).trim();


    if (!code) {
        return;
    }


    productsScannerBeep();


    const searchInput =
        document.getElementById(
            'searchInput'
        );


    if (searchInput) {

        searchInput.value =
            code;

        searchInput.focus();
    }


    showProductsScannerFeedback(
        'Scanned barcode: ' +
        code +
        ' — looking up product...',
        true
    );


    resolveClosingBarcode(
        code
    );
}


/*
|--------------------------------------------------------------------------
| RESOLVE BARCODE
|--------------------------------------------------------------------------
*/

function resolveClosingBarcode(barcode)
{
    barcode =
        String(
            barcode || ''
        ).trim();


    if (!barcode) {
        return;
    }


    const resolverUrl =
        '<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>' +
        '?action=resolve_barcode' +
        '&barcode=' +
        encodeURIComponent(barcode) +
        '&_=' +
        Date.now();


    fetch(
        resolverUrl,
        {
            method: 'GET',
            cache: 'no-store'
        }
    )


    .then(function(response) {

        return response.text()
            .then(function(text) {

                let data;

                try {

                    data =
                        JSON.parse(text);

                } catch (e) {

                    throw new Error(
                        'Barcode resolver returned invalid JSON.'
                    );
                }


                return {
                    response: response,
                    data: data
                };

            });

    })


    .then(function(result) {

        const response =
            result.response;


        const data =
            result.data;


        if (
            !response.ok ||
            !data
        ) {

            throw new Error(
                'Unable to resolve barcode.'
            );
        }


        if (
            data.success &&
            data.productid
        ) {

            /*
             * IMPORTANT:
             *
             * Replace the barcode with the actual
             * product ID before calling fetch_closing.php.
             */

            const searchInput =
                document.getElementById(
                    'searchInput'
                );


            if (searchInput) {

                searchInput.value =
                    data.productid;
            }


            showProductsScannerFeedback(
                'Product loaded: ' +
                (
                    data.pname ||
                    data.productid
                ),
                true
            );


            /*
             * Now fetch_closing.php receives
             * the actual product ID.
             */

            loadClosingData();

        } else {

            /*
             * Keep original barcode visible.
             */

            const searchInput =
                document.getElementById(
                    'searchInput'
                );


            if (searchInput) {

                searchInput.value =
                    barcode;
            }


            loadClosingData();


            showProductsScannerFeedback(
                'No product found for barcode: ' +
                barcode,
                false
            );
        }

    })


    .catch(function(error) {

        console.error(
            'Barcode resolution error:',
            error
        );


        const searchInput =
            document.getElementById(
                'searchInput'
            );


        if (searchInput) {

            searchInput.value =
                barcode;
        }


        loadClosingData();


        showProductsScannerFeedback(
            error.message ||
            'Unable to resolve barcode.',
            false
        );
    });
}


/*
|--------------------------------------------------------------------------
| LOCAL CAMERA SCANNER
|--------------------------------------------------------------------------
*/

function startProductsLocalScanner()
{
    const overlay =
        document.getElementById(
            'productsCameraScannerOverlay'
        );


    const reader =
        document.getElementById(
            'productsQrReader'
        );


    const feedback =
        document.getElementById(
            'productsCameraFeedback'
        );


    if (
        typeof Html5Qrcode ===
        'undefined'
    ) {

        alert(
            'Barcode scanner library could not be loaded.'
        );

        return;
    }


    if (productsLocalScannerRunning) {
        return;
    }


    overlay.style.display =
        'block';


    reader.innerHTML =
        '';


    feedback.textContent =
        'Starting camera...';


    productsLocalScanner =
        new Html5Qrcode(
            'productsQrReader'
        );


    Html5Qrcode.getCameras()


    .then(function(cameras) {

        if (
            !cameras ||
            cameras.length === 0
        ) {

            throw new Error(
                'No camera found.'
            );
        }


        let cameraId =
            cameras[0].id;


        /*
         * Prefer rear camera.
         */

        for (
            let i = 0;
            i < cameras.length;
            i++
        ) {

            const label =
                String(
                    cameras[i].label || ''
                ).toLowerCase();


            if (
                label.includes('back') ||
                label.includes('rear') ||
                label.includes('environment')
            ) {

                cameraId =
                    cameras[i].id;

                break;
            }
        }


        const config = {

            fps: 10,


            qrbox:
                function(
                    width,
                    height
                ) {

                    return {

                        width:
                            Math.max(
                                240,
                                Math.floor(
                                    width * 0.82
                                )
                            ),

                        height:
                            Math.max(
                                90,
                                Math.floor(
                                    height * 0.32
                                )
                            )
                    };
                },


            aspectRatio:
                1.7777778
        };


        if (
            typeof Html5QrcodeSupportedFormats !==
            'undefined'
        ) {

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


        return productsLocalScanner.start(

            cameraId,

            config,


            function(decodedText) {

                const now =
                    Date.now();


                /*
                 * Prevent duplicate scans.
                 */

                if (
                    decodedText ===
                    lastProductsLocalBarcode &&
                    now -
                    lastProductsLocalBarcodeTime <
                    2000
                ) {

                    return;
                }


                lastProductsLocalBarcode =
                    decodedText;


                lastProductsLocalBarcodeTime =
                    now;


                handleProductsCameraBarcode(
                    decodedText
                );
            },


            function() {

                /*
                 * Normal decode failures
                 * are ignored.
                 */
            }

        );

    })


    .then(function() {

        productsLocalScannerRunning =
            true;


        feedback.textContent =
            'Point the camera at a barcode.';

    })


    .catch(function(error) {

        console.error(
            'Camera scanner error:',
            error
        );


        feedback.textContent =
            'Camera error: ' +
            error.message;


        productsLocalScanner =
            null;

        productsLocalScannerRunning =
            false;
    });
}


/*
|--------------------------------------------------------------------------
| HANDLE CAMERA BARCODE
|--------------------------------------------------------------------------
*/

function handleProductsCameraBarcode(
    decodedText
)
{
    const code =
        String(
            decodedText || ''
        ).trim();


    if (!code) {
        return;
    }


    productsScannerBeep();


    stopProductsLocalScanner();


    const searchInput =
        document.getElementById(
            'searchInput'
        );


    if (searchInput) {

        searchInput.value =
            code;
    }


    showProductsScannerFeedback(
        'Scanned barcode: ' +
        code +
        ' — looking up product...',
        true
    );


    /*
     * Resolve barcode before loading closing data.
     */

    resolveClosingBarcode(
        code
    );
}


/*
|--------------------------------------------------------------------------
| STOP CAMERA SCANNER
|--------------------------------------------------------------------------
*/

function stopProductsLocalScanner()
{
    const overlay =
        document.getElementById(
            'productsCameraScannerOverlay'
        );


    if (
        productsLocalScanner &&
        productsLocalScannerRunning
    ) {

        productsLocalScanner.stop()


        .then(function() {

            try {

                productsLocalScanner.clear();

            } catch (e) {}


            productsLocalScanner =
                null;


            productsLocalScannerRunning =
                false;


            overlay.style.display =
                'none';

        })


        .catch(function(error) {

            console.warn(
                'Camera stop error:',
                error
            );


            productsLocalScanner =
                null;


            productsLocalScannerRunning =
                false;


            overlay.style.display =
                'none';
        });

    } else {

        productsLocalScanner =
            null;


        productsLocalScannerRunning =
            false;


        overlay.style.display =
            'none';
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPE CLOSES CAMERA
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(e) {

        if (
            e.key === 'Escape'
        ) {

            stopProductsLocalScanner();
        }
    }
);


/*
|--------------------------------------------------------------------------
| SMART CLOSING
|--------------------------------------------------------------------------
*/

function loadClosingData()
{
    const searchInput =
        document.getElementById(
            'searchInput'
        );


    const categoryInput =
        document.getElementById(
            'category'
        );


    const closingTable =
        document.getElementById(
            'closingTable'
        );


    if (!closingTable) {
        return;
    }


    const search =
        searchInput
            ? searchInput.value
            : '';


    const category =
        categoryInput
            ? categoryInput.value
            : '';


    /*
     * Show loading feedback.
     */

    closingTable.innerHTML = `
        <tr>
            <td colspan="5"
                style="text-align:center;padding:20px;">
                Loading closing stock...
            </td>
        </tr>
    `;


    fetch(
        'assets/scripts/fetch_closing.php' +
        '?search=' +
        encodeURIComponent(search) +
        '&category=' +
        encodeURIComponent(category) +
        '&_=' +
        Date.now(),
        {
            method: 'GET',
            cache: 'no-store'
        }
    )


    .then(function(response) {

        if (!response.ok) {

            throw new Error(
                'Closing request failed: HTTP ' +
                response.status
            );
        }


        return response.text();
    })


    .then(function(data) {

        closingTable.innerHTML =
            data;

    })


    .catch(function(error) {

        console.error(
            'Closing data error:',
            error
        );


        closingTable.innerHTML = `
            <tr>
                <td colspan="5"
                    class="text-danger"
                    style="text-align:center;padding:20px;">
                    Unable to load closing stock.
                    Please check fetch_closing.php.
                </td>
            </tr>
        `;
    });
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function() {

        const searchInput =
            document.getElementById(
                'searchInput'
            );


        const category =
            document.getElementById(
                'category'
            );


        const limit =
            document.getElementById(
                'limit'
            );


        if (searchInput) {

            searchInput.addEventListener(
                'keyup',
                function(e) {

                    clearTimeout(
                        closingTimer
                    );


                    /*
                     * Physical barcode scanners
                     * commonly finish with ENTER.
                     */

                    if (
                        e.key ===
                        'Enter'
                    ) {

                        e.preventDefault();


                        const value =
                            searchInput.value.trim();


                        if (value !== '') {

                            resolveClosingBarcode(
                                value
                            );

                        } else {

                            loadClosingData();
                        }


                        return;
                    }


                    closingTimer =
                        setTimeout(
                            function() {

                                loadClosingData();

                            },
                            300
                        );
                }
            );
        }


        if (category) {

            category.addEventListener(
                'change',
                function() {

                    loadClosingData();

                }
            );
        }


        if (limit) {

            limit.addEventListener(
                'change',
                function() {

                    /*
                     * fetch_closing.php currently controls
                     * its own returned rows.
                     *
                     * Keep this listener so changing the
                     * page control immediately refreshes
                     * the table.
                     */

                    loadClosingData();
                }
            );
        }


        /*
         * Initial load.
         */

        loadClosingData();

    }
);


/*
|--------------------------------------------------------------------------
| CALCULATE SMART CLOSING
|--------------------------------------------------------------------------
*/

function calcSmart(input)
{
    const row =
        input.closest('tr');


    if (!row) {
        return;
    }


    const systemInput =
        row.querySelector(
            '.system'
        );


    const physical =
        parseFloat(
            input.value
        ) || 0;


    const system =
        systemInput
            ? parseFloat(
                systemInput.value
              ) || 0
            : 0;


    const difference =
        physical - system;


    const diffInput =
        row.querySelector(
            '.diff'
        );


    if (diffInput) {

        diffInput.value =
            difference;
    }


    const status =
        row.querySelector(
            '.status'
        );


    if (!status) {
        return;
    }


    if (difference < 0) {

        status.innerHTML =
            '🔴 LOSS';

        status.style.color =
            'red';

    } else if (difference > 0) {

        status.innerHTML =
            '🟡 GAIN';

        status.style.color =
            'orange';

    } else {

        status.innerHTML =
            '🟢 OK';

        status.style.color =
            'green';
    }
}


/*
|--------------------------------------------------------------------------
| COPY SYSTEM STOCK
|--------------------------------------------------------------------------
*/

function copySystem(btn)
{
    const row =
        btn.closest('tr');


    if (!row) {
        return;
    }


    const system =
        row.querySelector(
            '.system'
        );


    const physical =
        row.querySelector(
            '.physical'
        );


    if (
        system &&
        physical
    ) {

        physical.value =
            system.value;


        calcSmart(
            physical
        );
    }
}


/*
|--------------------------------------------------------------------------
| COPY ALL SYSTEM STOCK
|--------------------------------------------------------------------------
*/

function copyAllSystem()
{
    const rows =
        document.querySelectorAll(
            '#closingTable tr'
        );


    rows.forEach(
        function(row) {

            const system =
                row.querySelector(
                    '.system'
                );


            const physical =
                row.querySelector(
                    '.physical'
                );


            if (
                system &&
                physical
            ) {

                physical.value =
                    system.value;


                calcSmart(
                    physical
                );
            }

        }
    );


    alert(
        'All system stock copied successfully!'
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE CLOSE REPORT
|--------------------------------------------------------------------------
*/

function generateCloseReport()
{
    const rows =
        document.querySelectorAll(
            '#closingTable tr'
        );


    let totalLoss = 0;

    let totalGain = 0;

    let totalItems = 0;


    let reportHTML = `

        <div id="printArea">

            <h2 style="text-align:center;">
                🧾 Daily Closing Report
            </h2>

            <p>
                <strong>Date:</strong>
                ${new Date().toLocaleString()}
            </p>

            <table class="table table-bordered">

                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            System
                        </th>

                        <th>
                            Physical
                        </th>

                        <th>
                            Difference
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>

                <tbody>
    `;


    rows.forEach(
        function(row) {

            const productCell =
                row.querySelector(
                    'td'
                );


            const systemInput =
                row.querySelector(
                    '.system'
                );


            const physicalInput =
                row.querySelector(
                    '.physical'
                );


            if (
                !productCell ||
                !systemInput ||
                !physicalInput
            ) {

                return;
            }


            const product =
                productCell.innerText.trim();


            const system =
                parseFloat(
                    systemInput.value
                ) || 0;


            const physical =
                parseFloat(
                    physicalInput.value
                ) || 0;


            if (
                physical === 0 &&
                system === 0
            ) {

                return;
            }


            const difference =
                physical - system;


            let status = 'OK';


            if (
                difference < 0
            ) {

                status =
                    'LOSS';


                totalLoss +=
                    Math.abs(
                        difference
                    );

            } else if (
                difference > 0
            ) {

                status =
                    'GAIN';


                totalGain +=
                    difference;
            }


            totalItems++;


            reportHTML += `

                <tr>

                    <td>
                        ${product}
                    </td>

                    <td>
                        ${system}
                    </td>

                    <td>
                        ${physical}
                    </td>

                    <td>
                        ${difference}
                    </td>

                    <td>
                        ${status}
                    </td>

                </tr>

            `;

        }
    );


    reportHTML += `

                </tbody>

            </table>

            <hr>

            <h4>
                Summary
            </h4>

            <p>

                <strong>
                    Total Items Counted:
                </strong>

                ${totalItems}

            </p>

            <p>

                <strong>
                    Total Loss:
                </strong>

                ${totalLoss}

            </p>

            <p>

                <strong>
                    Total Gain:
                </strong>

                ${totalGain}

            </p>

        </div>
    `;


    const reportContent =
        document.getElementById(
            'reportContent'
        );


    const closeReport =
        document.getElementById(
            'closeReport'
        );


    if (reportContent) {

        reportContent.innerHTML =
            reportHTML;
    }


    if (closeReport) {

        closeReport.style.display =
            'block';
    }
}


/*
|--------------------------------------------------------------------------
| RESTOCK MODAL
|--------------------------------------------------------------------------
*/

function loadProduct(button)
{
    const id =
        button.getAttribute(
            'data-id'
        );


    const name =
        button.getAttribute(
            'data-name'
        );


    const desc =
        button.getAttribute(
            'data-description'
        );


    const qty =
        button.getAttribute(
            'data-qty'
        );


    const uc =
        button.getAttribute(
            'data-uc'
        );


    document.getElementById(
        'modal-id'
    ).value =
        id;


    document.getElementById(
        'modal-id-span'
    ).textContent =
        id;


    document.getElementById(
        'modal-name'
    ).textContent =
        name;


    document.getElementById(
        'modal-description'
    ).textContent =
        desc;


    document.getElementById(
        'modal-qty'
    ).textContent =
        qty;


    document.getElementById(
        'modal-uc'
    ).textContent =
        uc;
}



/*
|--------------------------------------------------------------------------
| SELL MODAL
|--------------------------------------------------------------------------
*/

function sellProduct(button)
{
    const id =
        button.getAttribute('data-sid');

    const name =
        button.getAttribute('data-sname');

    const desc =
        button.getAttribute('data-sdescription');

    const qty =
        button.getAttribute('data-sqty');

    const sp =
        button.getAttribute('data-ssp');

    document.getElementById('modal-sid').value = id;
    document.getElementById('modal-sid-span').textContent = id;
    document.getElementById('modal-sname').textContent = name;
    document.getElementById('modal-sdescription').textContent = desc;
    document.getElementById('modal-sqty').textContent = qty;
    document.getElementById('modal-ssp').textContent = sp;
}


/*
|--------------------------------------------------------------------------
| AUTOMATICALLY OPEN MOBILE SCANNER ON DESKTOP
|--------------------------------------------------------------------------
|
| Opens the scanner connection panel automatically when this page loads.
| Starts the connection and leaves the panel visible.
| Mobile and tablet devices continue using their existing scanner button.
|--------------------------------------------------------------------------
*/

window.addEventListener('load', function () {

    if (window.innerWidth > 991.98) {

        const scannerBox = document.getElementById(
            'productsMobileScannerBox'
        );

        if (scannerBox) {
            scannerBox.style.display = 'block';
        }

        setTimeout(function () {

            if (
                typeof openProductsMobileScannerConnection === 'function'
            ) {
                openProductsMobileScannerConnection();
            }

        }, 500);
    }

});

</script>

</body>

</html>