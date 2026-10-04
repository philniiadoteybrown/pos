<?php

$pagetitle = "Product Budgeting/Out of Stock";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";


/*
|--------------------------------------------------------------------------
| SERVER IP FOR MOBILE SCANNER
|--------------------------------------------------------------------------
*/

$budgetServerIp = $_SERVER['SERVER_ADDR'] ?? '';

if (
    empty($budgetServerIp) ||
    $budgetServerIp === '0.0.0.0' ||
    $budgetServerIp === '::'
) {
    $budgetServerIp = gethostbyname(gethostname());
}


/*
|--------------------------------------------------------------------------
| RESOLVE BARCODE
|--------------------------------------------------------------------------
|
| This allows the scanner to send an actual barcode.
| If the products table has a barcode column, it is checked first.
| Product ID is also accepted as a fallback.
|
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'resolve_barcode'
) {

    header('Content-Type: application/json; charset=utf-8');

    $barcode = trim(
        $_GET['barcode'] ?? ''
    );

    if ($barcode === '') {

        echo json_encode([
            'success' => false,
            'message' => 'Barcode is empty.'
        ]);

        exit;
    }


    /*
     * Check whether the products table actually
     * contains a barcode column.
     */
    $hasBarcodeColumn = false;

    $columnCheck = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM products LIKE 'barcode'"
    );


    if (
        $columnCheck &&
        mysqli_num_rows($columnCheck) > 0
    ) {

        $hasBarcodeColumn = true;
    }


    /*
     * If barcode exists, search barcode OR productid.
     */
    if ($hasBarcodeColumn) {

        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT
                productid,
                pname,
                barcode
            FROM products
            WHERE barcode = ?
               OR productid = ?
            LIMIT 1
            "
        );

    } else {

        /*
         * If no barcode column exists,
         * safely fall back to productid.
         */
        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT
                productid,
                pname
            FROM products
            WHERE productid = ?
            LIMIT 1
            "
        );
    }


    if (!$stmt) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to prepare barcode lookup.'
        ]);

        exit;
    }


    if ($hasBarcodeColumn) {

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $barcode,
            $barcode
        );

    } else {

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $barcode
        );
    }


    mysqli_stmt_execute($stmt);


    $result =
        mysqli_stmt_get_result($stmt);


    $product =
        $result
            ? mysqli_fetch_assoc($result)
            : null;


    mysqli_stmt_close($stmt);


    if (!$product) {

        echo json_encode([
            'success' => false,
            'message' => 'Product not found.',
            'barcode' => $barcode
        ]);

        exit;
    }


    echo json_encode([

        'success'   => true,

        'productid' =>
            (string)$product['productid'],

        'pname' =>
            (string)$product['pname'],

        'barcode' =>
            $hasBarcodeColumn &&
            isset($product['barcode'])
                ? (string)$product['barcode']
                : $barcode

    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD LOW STOCK PRODUCTS
|--------------------------------------------------------------------------
*/

$productRes = mysqli_query(
    $conn,
    "
    SELECT
        productid,
        pname,
        pdesc,
        qtyperunit,
        unitprice,
        unit,
        category,
        totalstock,
        qtyalert
    FROM products
    WHERE totalstock < qtyalert
    ORDER BY category ASC, pname ASC
    "
);


if (!$productRes) {

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


$products = [];


while (
    $p =
    mysqli_fetch_assoc($productRes)
) {

    $products[] = $p;
}

?>

<!DOCTYPE html>

<html>

<head>

    <?php include "assets/sections/headers/header_tag.php"; ?>

    <style>

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
            background: #f2f2f2;
        }

        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .budget-layout {
            width: 100%;
        }

        .budget-table {
            min-width: 900px;
            table-layout: fixed;
        }

        .budget-table th:nth-child(1),
        .budget-table td:nth-child(1) {
            width: 30%;
        }

        .budget-table th:nth-child(2),
        .budget-table td:nth-child(2) {
            width: 10%;
            text-align: center;
        }

        .budget-table th:nth-child(3),
        .budget-table td:nth-child(3) {
            width: 14%;
        }

        .budget-table th:nth-child(4),
        .budget-table td:nth-child(4) {
            width: 16%;
        }

        .budget-table th:nth-child(5),
        .budget-table td:nth-child(5) {
            width: 12%;
        }

        .budget-table th:nth-child(6),
        .budget-table td:nth-child(6) {
            width: 12%;
        }

        .budget-table th:nth-child(7),
        .budget-table td:nth-child(7) {
            width: 6%;
            text-align: center;
        }

        .budget-stock {
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .budget-unit-price-display,
        .budget-subtotal {
            font-weight: 600;
            white-space: nowrap;
        }

        .budget-product {
            width: 100%;
        }

        .budget-qty {
            width: 85px;
            text-align: right;
        }

        .budget-grand-total {
            font-size: 18px;
            font-weight: 700;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .budget-controls {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .budget-selected-count {
            font-size: 15px;
            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | SCANNER TOOLBAR
        |--------------------------------------------------------------------------
        */

        .budget-scanner-toolbar {

            width: 100%;

            display: flex;

            justify-content: flex-end;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;

            margin-bottom: 15px;
        }


        .budget-scanner-desktop-button {
            display: inline-block;
        }


        .budget-scanner-mobile-button {
            display: none;
        }


        .budget-scanner-connection-box {

            display: none;

            width: 100%;

            margin-top: 10px;

            margin-bottom: 15px;

            padding: 15px;

            border: 1px solid #ddd;

            border-radius: 5px;

            background: #f8f9fa;
        }


        .budget-scanner-url {

            width: 100%;

            padding: 10px;

            border: 1px solid #ccc;

            background: #fff;

            border-radius: 4px;

            word-break: break-all;

            font-size: 14px;
        }


        .budget-scanner-status {

            font-weight: 600;

            margin-top: 8px;
        }


        .budget-scanner-feedback {

            margin-top: 8px;

            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | CAMERA
        |--------------------------------------------------------------------------
        */

        .budget-camera-overlay {

            display: none;

            position: fixed;

            z-index: 99999;

            left: 0;

            top: 0;

            width: 100%;

            height: 100%;

            background: rgba(0, 0, 0, .92);

            padding: 20px;
        }


        .budget-camera-container {

            max-width: 650px;

            margin: 40px auto;

            background: #fff;

            padding: 15px;

            border-radius: 8px;
        }


        #budgetQrReader {
            width: 100%;
        }


        @media (min-width: 992px) {

            .fixed-left .content-page {

                margin-left: 250px !important;

                width: calc(100% - 250px) !important;
            }

        }


        @media (max-width: 991.98px) {

            .fixed-left .content-page {

                margin-left: 0 !important;

                width: 100% !important;
            }


            .budget-scanner-desktop-button {
                display: none;
            }


            .budget-scanner-mobile-button {
                display: inline-block;
            }

        }

    </style>

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

        <?php include "assets/sections/leftside.php"; ?>


        <!-- Right Content -->

        <div class="content-page">

            <div class="content">


                <!-- Top Bar -->

                <?php include "assets/sections/topbar.php"; ?>


                <div class="page-content-wrapper">

                    <div class="container-fluid">


                        <div class="row">

                            <div class="col-sm-12">

                                <br>

                            </div>

                        </div>


                        <div class="row">

                            <div class="col-12">

                                <div class="card m-b-30">

                                    <div class="card-body">


                                        <div
                                            class="
                                                d-flex
                                                justify-content-between
                                                align-items-center
                                                flex-wrap
                                                mb-2
                                            "
                                        >

                                            <h2 class="mb-0">
                                                Budget
                                            </h2>


                                            <a
                                                href="outofstock.php"
                                                class="btn btn-secondary btn-sm"
                                            >

                                                <span
                                                    class="fa fa-arrow-left"
                                                ></span>

                                                Out of Stock

                                            </a>

                                        </div>


                                        <p class="text-muted">

                                            Add products to build a budget.
                                            Quantity is entered manually;
                                            product information is loaded
                                            from the database.

                                        </p>


                                        <!--
                                        ============================================================
                                        SCANNER
                                        ============================================================
                                        -->

                                        <div
                                            class="budget-scanner-toolbar"
                                        >


                                            <!-- Desktop -->

                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-info
                                                    budget-scanner-desktop-button
                                                "
                                                onclick="
                                                    openBudgetMobileScannerConnection()
                                                "
                                            >

                                                📱 Connect Mobile Scanner

                                            </button>


                                            <!-- Mobile -->

                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-info
                                                    budget-scanner-mobile-button
                                                "
                                                onclick="
                                                    openBudgetMobileScannerFromMobile()
                                                "
                                            >

                                                📱 Open Mobile Scanner

                                            </button>


                                            <!-- Camera -->

                                            <button
                                                type="button"
                                                class="btn btn-dark"
                                                onclick="
                                                    startBudgetLocalScanner()
                                                "
                                            >

                                                📷 Scan With Camera

                                            </button>

                                        </div>


                                        <!-- Scanner Connection -->

                                        <div
                                            id="budgetMobileScannerBox"
                                            class="
                                                budget-scanner-connection-box
                                            "
                                        >

                                            <strong>
                                                Mobile Scanner Connection
                                            </strong>


                                            <div class="mt-2">

                                                Open this address on your phone:

                                            </div>


                                            <div
                                                id="budgetMobileScannerUrl"
                                                class="
                                                    budget-scanner-url
                                                    mt-2
                                                "
                                            ></div>


                                            <div
                                                id="budgetMobileScannerStatus"
                                                class="
                                                    budget-scanner-status
                                                    text-primary
                                                "
                                            >

                                                Connecting...

                                            </div>


                                            <div
                                                id="budgetMobileScannerFeedback"
                                                class="
                                                    budget-scanner-feedback
                                                "
                                            ></div>


                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-danger
                                                    btn-sm
                                                    mt-2
                                                "
                                                onclick="
                                                    stopBudgetMobileScannerConnection()
                                                "
                                            >

                                                Stop Scanner

                                            </button>

                                        </div>


                                        <!-- GRAND TOTAL -->

                                        <div
                                            class="
                                                d-flex
                                                justify-content-between
                                                align-items-center
                                                flex-wrap
                                                mb-3
                                            "
                                        >

                                            <div
                                                class="budget-grand-total"
                                            >

                                                GRAND TOTAL:

                                                <span class="text-success">

                                                    GH¢

                                                    <span
                                                        id="grandTotalTop"
                                                    >
                                                        0.00
                                                    </span>

                                                </span>

                                            </div>


                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-primary
                                                    mt-2
                                                    mt-md-0
                                                "
                                                onclick="
                                                    generateBudgetPdf()
                                                "
                                            >

                                                <span
                                                    class="fa fa-file-pdf-o"
                                                ></span>

                                                Generate PDF

                                            </button>

                                        </div>


                                        <!-- CATEGORY CONTROLS -->

                                        <div
                                            class="
                                                budget-controls
                                                mb-3
                                            "
                                        >

                                            <div class="row">


                                                <div class="col-md-5">

                                                    <label
                                                        for="productCategory"
                                                        class="font-weight-bold"
                                                    >

                                                        Product Category

                                                    </label>


                                                    <select
                                                        id="productCategory"
                                                        class="form-control"
                                                        onchange="
                                                            addCategoryProducts(this.value)
                                                        "
                                                    >

                                                        <option value="">

                                                            Select category...

                                                        </option>

                                                    </select>

                                                </div>


                                                <div class="col-md-7">

                                                    <label
                                                        class="
                                                            d-block
                                                            font-weight-bold
                                                        "
                                                    >

                                                        Product Selection

                                                    </label>


                                                    <button
                                                        type="button"
                                                        class="
                                                            btn
                                                            btn-primary
                                                            mr-2
                                                            mb-2
                                                        "
                                                        onclick="
                                                            selectAllProducts()
                                                        "
                                                    >

                                                        <span
                                                            class="
                                                                fa
                                                                fa-check-square-o
                                                            "
                                                        ></span>

                                                        Select All
                                                        Low-Stock Products

                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="
                                                            btn
                                                            btn-secondary
                                                            mb-2
                                                        "
                                                        onclick="
                                                            clearAllProducts()
                                                        "
                                                    >

                                                        <span
                                                            class="fa fa-times"
                                                        ></span>

                                                        Clear All

                                                    </button>

                                                </div>

                                            </div>


                                            <div
                                                class="
                                                    mt-2
                                                    text-muted
                                                "
                                            >

                                                Only products with
                                                <strong>
                                                    Available Stock less
                                                    than Quantity Alert
                                                </strong>
                                                are loaded from the database.

                                            </div>


                                            <div
                                                class="
                                                    mt-2
                                                    budget-selected-count
                                                "
                                            >

                                                <span
                                                    id="selectedProductCount"
                                                >
                                                    0
                                                </span>

                                                low-stock products selected.

                                            </div>

                                        </div>


                                        <!-- BUDGET TABLE -->

                                        <div class="table-responsive">

                                            <table
                                                class="budget-table"
                                                id="budgetTable"
                                            >

                                                <thead>

                                                    <tr>

                                                        <th>
                                                            Product Name
                                                            (Description)
                                                        </th>

                                                        <th>
                                                            Available Stock
                                                        </th>

                                                        <th>
                                                            Unit Price
                                                        </th>

                                                        <th>
                                                            Quantity per Unit
                                                        </th>

                                                        <th>
                                                            Quantity
                                                        </th>

                                                        <th>
                                                            Sub Total
                                                        </th>

                                                        <th>
                                                            Remove
                                                        </th>

                                                    </tr>

                                                </thead>


                                                <tbody
                                                    id="budgetBody"
                                                ></tbody>


                                                <tfoot>

                                                    <tr>

                                                        <td
                                                            colspan="6"
                                                            style="
                                                                text-align:right;
                                                                font-weight:700;
                                                            "
                                                        >

                                                            GRAND TOTAL

                                                        </td>


                                                        <td
                                                            class="
                                                                budget-grand-total
                                                            "
                                                        >

                                                            GH¢

                                                            <span
                                                                id="grandTotalBottom"
                                                            >
                                                                0.00
                                                            </span>

                                                        </td>

                                                    </tr>

                                                </tfoot>

                                            </table>

                                        </div>


                                        <!-- ADD ROW -->

                                        <div class="mt-3">

                                            <button
                                                type="button"
                                                class="btn btn-success"
                                                onclick="
                                                    addBudgetRow()
                                                "
                                            >

                                                <span
                                                    class="fa fa-plus"
                                                ></span>

                                                Add Row

                                            </button>

                                        </div>


                                        <!-- PDF PREVIEW -->

                                        <div
                                            id="budgetPdfWrap"
                                            style="
                                                display:none;
                                                margin-top:25px;
                                            "
                                        >

                                            <div
                                                class="
                                                    d-flex
                                                    justify-content-between
                                                    align-items-center
                                                    mb-2
                                                "
                                            >

                                                <h5 class="mb-0">

                                                    PDF Preview

                                                </h5>


                                                <a
                                                    id="budgetDownloadPdf"
                                                    class="
                                                        btn
                                                        btn-success
                                                        btn-sm
                                                    "
                                                    href="#"
                                                    target="_blank"
                                                    download
                                                >

                                                    <span
                                                        class="
                                                            fa
                                                            fa-download
                                                        "
                                                    ></span>

                                                    Download PDF

                                                </a>

                                            </div>


                                            <iframe
                                                id="budgetPdfPreview"
                                                title="Budget PDF Preview"
                                                style="
                                                    width:100%;
                                                    height:650px;
                                                    border:1px solid #ddd;
                                                    border-radius:4px;
                                                "
                                            ></iframe>

                                        </div>


                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <footer class="footer">

                <?php include "assets/sections/footers/footer.php"; ?>

            </footer>

        </div>

    </div>


    <!-- CAMERA SCANNER -->

    <div
        id="budgetCameraScannerOverlay"
        class="budget-camera-overlay"
    >

        <div class="budget-camera-container">

            <div
                class="
                    d-flex
                    justify-content-between
                    align-items-center
                    mb-2
                "
            >

                <h5 class="mb-0">
                    Scan Product Barcode
                </h5>


                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    onclick="stopBudgetLocalScanner()"
                >

                    Close

                </button>

            </div>


            <div id="budgetQrReader"></div>


            <div
                id="budgetCameraFeedback"
                class="
                    mt-2
                    text-center
                    font-weight-bold
                "
            ></div>

        </div>

    </div>


    <?php include "assets/sections/footers/jqueryscripts.php"; ?>


    <script src="assets/scripts/html5-qrcode.min.js"></script>


    <script>

    /*
    |--------------------------------------------------------------------------
    | PRODUCTS FROM DATABASE
    |--------------------------------------------------------------------------
    */

    const budgetProducts =
        <?php echo json_encode(
            $products,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ); ?>;


    /*
    |--------------------------------------------------------------------------
    | SERVER IP
    |--------------------------------------------------------------------------
    */

    const budgetMobileScannerServerIp =
        <?php echo json_encode($budgetServerIp); ?>;


    /*
    |--------------------------------------------------------------------------
    | VARIABLES
    |--------------------------------------------------------------------------
    */

    let budgetRowCounter = 0;

    let budgetMobileScannerPollTimer = null;

    let budgetMobileScannerToken = '';

    let budgetMobileScannerRunning = false;

    let budgetLocalScanner = null;

    let budgetLocalScannerRunning = false;

    let budgetBarcodeProcessing = false;


    /*
    |--------------------------------------------------------------------------
    | FORMAT MONEY
    |--------------------------------------------------------------------------
    */

    function formatMoney(value) {

        return Number(
            value || 0
        ).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(
            value ?? ''
        ).replace(
            /[&<>'"]/g,
            function(c) {

                return {

                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#039;',
                    '"': '&quot;'

                }[c];

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT OPTIONS
    |--------------------------------------------------------------------------
    */

    function productOptions(
        selectedId = ''
    ) {

        let html =
            '<option value="">Select product...</option>';


        budgetProducts.forEach(
            function(p) {

                const id =
                    String(
                        p.productid
                    );


                const label =
                    String(
                        p.pname || ''
                    ) +
                    (
                        p.pdesc
                            ? ' - ' +
                              String(p.pdesc)
                            : ''
                    );


                html +=
                    '<option value="' +
                    escapeHtml(id) +
                    '" ' +
                    (
                        id ===
                        String(selectedId)
                            ? 'selected'
                            : ''
                    ) +
                    '>' +
                    escapeHtml(label) +
                    '</option>';
            }
        );


        return html;
    }


    /*
    |--------------------------------------------------------------------------
    | POPULATE CATEGORY
    |--------------------------------------------------------------------------
    */

    function populateCategoryFilter() {

        const select =
            document.getElementById(
                'productCategory'
            );


        if (!select) {
            return;
        }


        const categories = [];


        budgetProducts.forEach(
            function(product) {

                const category =
                    String(
                        product.category || ''
                    ).trim();


                if (
                    category &&
                    !categories.includes(category)
                ) {

                    categories.push(category);
                }
            }
        );


        categories.sort(
            function(a, b) {

                return a.localeCompare(b);

            }
        );


        categories.forEach(
            function(category) {

                const option =
                    document.createElement(
                        'option'
                    );


                option.value =
                    category;


                option.textContent =
                    category;


                select.appendChild(
                    option
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK IF PRODUCT ALREADY SELECTED
    |--------------------------------------------------------------------------
    */

    function isProductSelected(productId) {

        let found = false;


        document.querySelectorAll(
            '#budgetBody .budget-product'
        ).forEach(
            function(select) {

                if (
                    String(select.value) ===
                    String(productId)
                ) {

                    found = true;
                }
            }
        );


        return found;
    }


    /*
    |--------------------------------------------------------------------------
    | ADD BUDGET ROW
    |--------------------------------------------------------------------------
    */

    function addBudgetRow(
        selectedId = ''
    ) {

        budgetRowCounter++;


        const tr =
            document.createElement('tr');


        tr.dataset.rowId =
            budgetRowCounter;


        tr.innerHTML = `

            <td>

                <select
                    class="form-control budget-product"
                    onchange="updateBudgetRow(this)"
                >

                    ${productOptions(selectedId)}

                </select>

                <input
                    type="hidden"
                    class="budget-unit-price"
                    value="0"
                >

            </td>

            <td class="budget-stock">
                —
            </td>

            <td class="budget-unit-price-display">
                GH¢ 0.00
            </td>

            <td>

                <span class="budget-qpu text-muted">
                    —
                </span>

            </td>

            <td>

                <input
                    type="number"
                    class="
                        form-control
                        form-control-sm
                        budget-qty
                    "
                    min="0"
                    step="0.01"
                    value="0"
                    oninput="calculateBudget()"
                >

            </td>

            <td class="budget-subtotal">
                GH¢ 0.00
            </td>

            <td>

                <button
                    type="button"
                    class="
                        btn
                        btn-danger
                        btn-sm
                    "
                    onclick="removeBudgetRow(this)"
                    title="Remove row"
                >

                    <span class="fa fa-trash"></span>

                </button>

            </td>
        `;


        document
            .getElementById('budgetBody')
            .appendChild(tr);


        if (selectedId) {

            updateBudgetRow(
                tr.querySelector(
                    '.budget-product'
                )
            );
        }


        calculateBudget();

        updateSelectedProductCount();
    }


    /*
    |--------------------------------------------------------------------------
    | ADD CATEGORY PRODUCTS
    |--------------------------------------------------------------------------
    */

    function addCategoryProducts(
        category
    ) {

        if (!category) {
            return;
        }


        budgetProducts.forEach(
            function(product) {

                const productCategory =
                    String(
                        product.category || ''
                    ).trim();


                if (
                    productCategory === category &&
                    !isProductSelected(
                        product.productid
                    )
                ) {

                    addBudgetRow(
                        String(product.productid)
                    );
                }
            }
        );


        updateSelectedProductCount();


        const categorySelect =
            document.getElementById(
                'productCategory'
            );


        if (categorySelect) {

            categorySelect.value = '';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SELECT ALL
    |--------------------------------------------------------------------------
    */

    function selectAllProducts() {

        budgetProducts.forEach(
            function(product) {

                if (
                    !isProductSelected(
                        product.productid
                    )
                ) {

                    addBudgetRow(
                        String(product.productid)
                    );
                }
            }
        );


        updateSelectedProductCount();
    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR ALL
    |--------------------------------------------------------------------------
    */

    function clearAllProducts() {

        const body =
            document.getElementById(
                'budgetBody'
            );


        if (body) {

            body.innerHTML = '';
        }


        calculateBudget();

        updateSelectedProductCount();
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE COUNT
    |--------------------------------------------------------------------------
    */

    function updateSelectedProductCount() {

        const rows =
            document.querySelectorAll(
                '#budgetBody .budget-product'
            );


        const countElement =
            document.getElementById(
                'selectedProductCount'
            );


        if (countElement) {

            countElement.textContent =
                rows.length;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE ROW
    |--------------------------------------------------------------------------
    */

    function updateBudgetRow(select) {

        const row =
            select.closest('tr');


        if (!row) {
            return;
        }


        const product =
            budgetProducts.find(
                function(p) {

                    return String(
                        p.productid
                    ) ===
                    String(
                        select.value
                    );

                }
            );


        if (!product) {

            row.querySelector(
                '.budget-stock'
            ).textContent = '—';


            row.querySelector(
                '.budget-qpu'
            ).textContent = '—';


            row.querySelector(
                '.budget-unit-price'
            ).value = '0';


            row.querySelector(
                '.budget-unit-price-display'
            ).textContent =
                'GH¢ 0.00';

        } else {

            const availableStock =
                product.totalstock === null ||
                product.totalstock === ''
                    ? 0
                    : product.totalstock;


            const stockNumber =
                parseFloat(
                    availableStock
                ) || 0;


            const stockDisplay =
                String(stockNumber);


            const stockUnit =
                stockNumber === 1
                    ? 'pc'
                    : 'pcs';


            row.querySelector(
                '.budget-stock'
            ).textContent =
                stockDisplay +
                ' ' +
                stockUnit;


            const qpu =
                product.qtyperunit === null ||
                product.qtyperunit === ''
                    ? '—'
                    : product.qtyperunit;


            const unitPrice =
                parseFloat(
                    product.unitprice || 0
                );


            const qpuNumber =
                parseFloat(qpu);


            const qpuDisplay =
                Number.isNaN(qpuNumber)
                    ? String(qpu)
                    : String(qpuNumber);


            const qpuUnit =
                product.unit
                    ? String(product.unit).trim()
                    : 'unit';


            row.querySelector(
                '.budget-qpu'
            ).textContent =
                qpuDisplay +
                ' per ' +
                qpuUnit;


            row.querySelector(
                '.budget-unit-price'
            ).value =
                unitPrice;


            row.querySelector(
                '.budget-unit-price-display'
            ).textContent =
                'GH¢ ' +
                formatMoney(unitPrice);
        }


        calculateBudget();

        updateSelectedProductCount();
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE ROW
    |--------------------------------------------------------------------------
    */

    function removeBudgetRow(button) {

        const row =
            button.closest('tr');


        if (row) {

            row.remove();
        }


        calculateBudget();

        updateSelectedProductCount();
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE BUDGET
    |--------------------------------------------------------------------------
    */

    function calculateBudget() {

        let total = 0;


        document.querySelectorAll(
            '#budgetBody tr'
        ).forEach(
            function(row) {

                const qty =
                    parseFloat(
                        row.querySelector(
                            '.budget-qty'
                        )?.value
                    ) || 0;


                const price =
                    parseFloat(
                        row.querySelector(
                            '.budget-unit-price'
                        )?.value
                    ) || 0;


                const subtotal =
                    qty * price;


                const cell =
                    row.querySelector(
                        '.budget-subtotal'
                    );


                if (cell) {

                    cell.textContent =
                        'GH¢ ' +
                        formatMoney(subtotal);
                }


                total += subtotal;
            }
        );


        const top =
            document.getElementById(
                'grandTotalTop'
            );


        const bottom =
            document.getElementById(
                'grandTotalBottom'
            );


        if (top) {

            top.textContent =
                formatMoney(total);
        }


        if (bottom) {

            bottom.textContent =
                formatMoney(total);
        }


        updateSelectedProductCount();
    }


    /*
    |--------------------------------------------------------------------------
    | ENCODE BUDGET ROWS
    |--------------------------------------------------------------------------
    */

    function encodeBudgetRows() {

        const rows = [];


        document.querySelectorAll(
            '#budgetBody tr'
        ).forEach(
            function(row) {

                const productId =
                    row.querySelector(
                        '.budget-product'
                    )?.value || '';


                const qty =
                    parseFloat(
                        row.querySelector(
                            '.budget-qty'
                        )?.value
                    ) || 0;


                if (
                    productId &&
                    qty >= 0
                ) {

                    rows.push({

                        productid:
                            productId,

                        quantity:
                            qty
                    });
                }
            }
        );


        const json =
            JSON.stringify(rows);


        return btoa(
            unescape(
                encodeURIComponent(json)
            )
        )
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE PDF
    |--------------------------------------------------------------------------
    */

    function generateBudgetPdf() {

        const encoded =
            encodeBudgetRows();


        const url =
            'assets/scripts/export_budget_pdf.php' +
            '?mode=preview' +
            '&rows=' +
            encodeURIComponent(encoded) +
            '&_=' +
            Date.now();


        const downloadUrl =
            url.replace(
                'mode=preview',
                'mode=download'
            );


        document.getElementById(
            'budgetPdfPreview'
        ).src = url;


        document.getElementById(
            'budgetDownloadPdf'
        ).href = downloadUrl;


        document.getElementById(
            'budgetPdfWrap'
        ).style.display = 'block';


        document.getElementById(
            'budgetPdfWrap'
        ).scrollIntoView({

            behavior: 'smooth',

            block: 'start'

        });
    }


    /*
    |--------------------------------------------------------------------------
    | MOBILE SCANNER URL
    |--------------------------------------------------------------------------
    */

    function getBudgetMobileScannerUrl() {

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
                budgetMobileScannerServerIp &&
                budgetMobileScannerServerIp !== '127.0.0.1' &&
                budgetMobileScannerServerIp !== '::1'
            ) {

                host =
                    budgetMobileScannerServerIp;
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

    function openBudgetMobileScannerConnection() {

        const box =
            document.getElementById(
                'budgetMobileScannerBox'
            );


        const url =
            document.getElementById(
                'budgetMobileScannerUrl'
            );


        const status =
            document.getElementById(
                'budgetMobileScannerStatus'
            );


        const feedback =
            document.getElementById(
                'budgetMobileScannerFeedback'
            );


        if (!box || !url || !status) {
            return;
        }


        box.style.display =
            'block';


        url.textContent =
            getBudgetMobileScannerUrl();


        status.textContent =
            'Connecting to scanner...';


        status.className =
            'budget-scanner-status text-primary';


        if (feedback) {

            feedback.textContent = '';
        }


        startBudgetMobileScannerConnection();
    }


    /*
    |--------------------------------------------------------------------------
    | OPEN MOBILE SCANNER FROM PHONE
    |--------------------------------------------------------------------------
    */

    function openBudgetMobileScannerFromMobile() {

        window.open(
            getBudgetMobileScannerUrl(),
            '_blank'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | START MOBILE SCANNER CONNECTION
    |--------------------------------------------------------------------------
    */

    function startBudgetMobileScannerConnection() {

        if (
            budgetMobileScannerRunning
        ) {

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

        .then(
            function(response) {

                return response.json();

            }
        )

        .then(
            function(data) {

                if (
                    !data ||
                    !data.success ||
                    !data.token
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : 'Unable to start scanner.'
                    );
                }


                budgetMobileScannerToken =
                    String(data.token);


                budgetMobileScannerRunning =
                    true;


                const status =
                    document.getElementById(
                        'budgetMobileScannerStatus'
                    );


                if (status) {

                    status.textContent =
                        'Waiting for mobile scanner...';


                    status.className =
                        'budget-scanner-status text-warning';
                }


                pollBudgetMobileScanner();

            }
        )

        .catch(
            function(error) {

                const status =
                    document.getElementById(
                        'budgetMobileScannerStatus'
                    );


                if (status) {

                    status.textContent =
                        'Scanner connection failed: ' +
                        error.message;


                    status.className =
                        'budget-scanner-status text-danger';
                }

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | POLL MOBILE SCANNER
    |--------------------------------------------------------------------------
    */

    function pollBudgetMobileScanner() {

        if (
            !budgetMobileScannerRunning ||
            !budgetMobileScannerToken
        ) {

            return;
        }


        const url =
            'assets/scripts/mobile_scanner_api.php' +
            '?action=poll' +
            '&token=' +
            encodeURIComponent(
                budgetMobileScannerToken
            ) +
            '&_=' +
            Date.now();


        fetch(
            url,
            {
                cache: 'no-store'
            }
        )

        .then(
            function(response) {

                return response.json();

            }
        )

        .then(
            function(data) {

                if (
                    !budgetMobileScannerRunning
                ) {

                    return;
                }


                const status =
                    document.getElementById(
                        'budgetMobileScannerStatus'
                    );


                if (
                    data &&
                    data.paired &&
                    status
                ) {

                    status.textContent =
                        '📱 Mobile scanner connected';


                    status.className =
                        'budget-scanner-status text-success';
                }


                if (
                    data &&
                    data.barcode
                ) {

                    receiveBudgetMobileBarcode(
                        String(data.barcode)
                    );
                }

            }
        )

        .catch(
            function(error) {

                console.log(
                    'Budget scanner poll error:',
                    error
                );

            }
        )

        .finally(
            function() {

                if (
                    budgetMobileScannerRunning
                ) {

                    budgetMobileScannerPollTimer =
                        setTimeout(
                            pollBudgetMobileScanner,
                            500
                        );
                }

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RECEIVE MOBILE BARCODE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Do not directly assume the scanned value is productid.
    | Resolve it through the database first.
    |
    */

    function receiveBudgetMobileBarcode(code) {

        code =
            String(code || '').trim();


        if (!code) {
            return;
        }


        if (budgetBarcodeProcessing) {
            return;
        }


        budgetBarcodeProcessing =
            true;


        resolveBudgetBarcode(code)

        .finally(
            function() {

                setTimeout(
                    function() {

                        budgetBarcodeProcessing =
                            false;

                    },
                    250
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESOLVE BARCODE THROUGH PHP
    |--------------------------------------------------------------------------
    */

    function resolveBudgetBarcode(code) {

        const feedback =
            document.getElementById(
                'budgetMobileScannerFeedback'
            );


        if (feedback) {

            feedback.textContent =
                'Looking up barcode ' +
                code +
                '...';


            feedback.className =
                'budget-scanner-feedback text-primary';
        }


        const url =
            window.location.pathname +
            '?action=resolve_barcode' +
            '&barcode=' +
            encodeURIComponent(code) +
            '&_=' +
            Date.now();


        return fetch(
            url,
            {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'Accept':
                        'application/json'
                }
            }
        )

        .then(
            function(response) {

                if (!response.ok) {

                    throw new Error(
                        'Barcode lookup failed.'
                    );
                }


                return response.json();

            }
        )

        .then(
            function(data) {

                if (
                    !data ||
                    !data.success ||
                    !data.productid
                ) {

                    showBudgetScannerFeedback(
                        'Product not found for barcode: ' +
                        code,
                        false
                    );


                    budgetScannerBeep(false);

                    return;
                }


                const productId =
                    String(
                        data.productid
                    ).trim();


                /*
                 * The product must be part of the
                 * low-stock products loaded on this page.
                 */

                const product =
                    budgetProducts.find(
                        function(p) {

                            return String(
                                p.productid
                            ).trim() ===
                            productId;

                        }
                    );


                if (!product) {

                    showBudgetScannerFeedback(
                        'Product found (' +
                        (
                            data.pname ||
                            productId
                        ) +
                        ') but it is not currently low-stock.',
                        false
                    );


                    budgetScannerBeep(false);

                    return;
                }


                /*
                 * Do not add duplicates.
                 */

                if (
                    isProductSelected(
                        productId
                    )
                ) {

                    showBudgetScannerFeedback(
                        'Product already selected: ' +
                        product.pname,
                        true
                    );


                    budgetScannerBeep(true);


                    focusBudgetProduct(
                        productId
                    );


                    return;
                }


                /*
                 * Add the product automatically.
                 */

                addBudgetRow(
                    productId
                );


                showBudgetScannerFeedback(
                    'Product added: ' +
                    product.pname,
                    true
                );


                budgetScannerBeep(true);


                focusBudgetProduct(
                    productId
                );

            }
        )

        .catch(
            function(error) {

                console.error(
                    'Budget barcode lookup error:',
                    error
                );


                showBudgetScannerFeedback(
                    'Unable to look up barcode: ' +
                    code,
                    false
                );


                budgetScannerBeep(false);

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FOCUS QUANTITY FIELD
    |--------------------------------------------------------------------------
    */

    function focusBudgetProduct(
        productId
    ) {

        const selects =
            document.querySelectorAll(
                '#budgetBody .budget-product'
            );


        for (
            let i = 0;
            i < selects.length;
            i++
        ) {

            if (
                String(
                    selects[i].value
                ) ===
                String(productId)
            ) {

                const row =
                    selects[i].closest('tr');


                const quantity =
                    row.querySelector(
                        '.budget-qty'
                    );


                if (quantity) {

                    setTimeout(
                        function() {

                            quantity.focus();

                            quantity.select();

                        },
                        100
                    );
                }


                break;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STOP MOBILE SCANNER
    |--------------------------------------------------------------------------
    */

    function stopBudgetMobileScannerConnection() {

        budgetMobileScannerRunning =
            false;


        if (
            budgetMobileScannerPollTimer
        ) {

            clearTimeout(
                budgetMobileScannerPollTimer
            );


            budgetMobileScannerPollTimer =
                null;
        }


        const token =
            budgetMobileScannerToken;


        budgetMobileScannerToken =
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
            .catch(
                function() {}
            );
        }


        const status =
            document.getElementById(
                'budgetMobileScannerStatus'
            );


        if (status) {

            status.textContent =
                'Scanner stopped';


            status.className =
                'budget-scanner-status text-secondary';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SCANNER FEEDBACK
    |--------------------------------------------------------------------------
    */

    function showBudgetScannerFeedback(
        message,
        success
    ) {

        const feedback =
            document.getElementById(
                'budgetMobileScannerFeedback'
            );


        if (!feedback) {
            return;
        }


        feedback.textContent =
            message;


        feedback.className =
            success
                ? 'budget-scanner-feedback text-success'
                : 'budget-scanner-feedback text-danger';
    }


    /*
    |--------------------------------------------------------------------------
    | BEEP
    |--------------------------------------------------------------------------
    */

    function budgetScannerBeep(
        success = true
    ) {

        try {

            const AudioContext =
                window.AudioContext ||
                window.webkitAudioContext;


            if (!AudioContext) {
                return;
            }


            const context =
                new AudioContext();


            const oscillator =
                context.createOscillator();


            const gain =
                context.createGain();


            oscillator.frequency.value =
                success
                    ? 900
                    : 250;


            gain.gain.value =
                0.08;


            oscillator.connect(gain);

            gain.connect(
                context.destination
            );


            oscillator.start();


            oscillator.stop(
                context.currentTime +
                (
                    success
                        ? 0.12
                        : 0.25
                )
            );

        } catch (e) {}
    }


    /*
    |--------------------------------------------------------------------------
    | START LOCAL CAMERA SCANNER
    |--------------------------------------------------------------------------
    */

    function startBudgetLocalScanner() {

        const overlay =
            document.getElementById(
                'budgetCameraScannerOverlay'
            );


        const reader =
            document.getElementById(
                'budgetQrReader'
            );


        const feedback =
            document.getElementById(
                'budgetCameraFeedback'
            );


        if (
            typeof Html5Qrcode ===
            'undefined'
        ) {

            if (feedback) {

                feedback.textContent =
                    'Barcode scanner library could not be loaded.';
            }

            return;
        }


        overlay.style.display =
            'block';


        reader.innerHTML =
            '';


        feedback.textContent =
            'Starting camera...';


        budgetLocalScanner =
            new Html5Qrcode(
                'budgetQrReader'
            );


        Html5Qrcode.getCameras()

        .then(
            function(cameras) {

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
                                    Math.floor(
                                        width * 0.82
                                    ),

                                height:
                                    Math.floor(
                                        height * 0.32
                                    )

                            };
                        },

                    aspectRatio:
                        1.7777778

                };


                /*
                 * Some versions of html5-qrcode expose
                 * Html5QrcodeSupportedFormats while others
                 * may not. Do not allow that to break
                 * the scanner.
                 */

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


                budgetLocalScannerRunning =
                    true;


                return budgetLocalScanner.start(

                    cameraId,

                    config,

                    function(decodedText) {

                        handleBudgetCameraBarcode(
                            decodedText
                        );

                    },

                    function() {}

                );

            }
        )

        .then(
            function() {

                if (
                    budgetLocalScannerRunning
                ) {

                    feedback.textContent =
                        'Point the camera at a barcode.';
                }

            }
        )

        .catch(
            function(error) {

                budgetLocalScannerRunning =
                    false;


                feedback.textContent =
                    'Camera error: ' +
                    (
                        error &&
                        error.message
                            ? error.message
                            : error
                    );

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CAMERA BARCODE
    |--------------------------------------------------------------------------
    */

    function handleBudgetCameraBarcode(
        decodedText
    ) {

        const code =
            String(
                decodedText || ''
            ).trim();


        if (!code) {
            return;
        }


        if (budgetBarcodeProcessing) {
            return;
        }


        budgetScannerBeep(true);


        stopBudgetLocalScanner();


        /*
         * Use the exact same barcode resolver
         * used by the mobile scanner.
         */

        receiveBudgetMobileBarcode(
            code
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STOP LOCAL CAMERA
    |--------------------------------------------------------------------------
    */

    function stopBudgetLocalScanner() {

        const overlay =
            document.getElementById(
                'budgetCameraScannerOverlay'
            );


        const scanner =
            budgetLocalScanner;


        budgetLocalScanner =
            null;


        budgetLocalScannerRunning =
            false;


        if (!scanner) {

            if (overlay) {

                overlay.style.display =
                    'none';
            }

            return;
        }


        scanner.stop()

        .catch(
            function() {}
        )

        .finally(
            function() {

                try {

                    scanner.clear();

                } catch (e) {}


                if (overlay) {

                    overlay.style.display =
                        'none';
                }

            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STOP SCANNER WHEN LEAVING PAGE
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'beforeunload',
        function() {

            if (
                budgetMobileScannerRunning &&
                budgetMobileScannerToken
            ) {

                const url =
                    'assets/scripts/mobile_scanner_api.php' +
                    '?action=stop&token=' +
                    encodeURIComponent(
                        budgetMobileScannerToken
                    );


                try {

                    navigator.sendBeacon(
                        url,
                        new Blob(
                            [],
                            {
                                type:
                                    'application/x-www-form-urlencoded'
                            }
                        )
                    );

                } catch (e) {}
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | INITIALIZE
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            populateCategoryFilter();


            /*
             * Preserve original behaviour:
             * create one empty row.
             */

            addBudgetRow();


            updateSelectedProductCount();

        }
    );

    </script>

</body>

</html>