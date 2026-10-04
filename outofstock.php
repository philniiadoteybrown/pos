<?php

$pagetitle = "Product Budgeting";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";

$productRes = mysqli_query($conn, "
    SELECT productid, pname, pdesc, qtyperunit, unitprice, unit, category, totalstock, qtyalert
    FROM products
    WHERE totalstock < qtyalert
    ORDER BY category ASC, pname ASC
");

if (!$productRes) die("Database Error: " . mysqli_error($conn));

$products = [];

while ($p = mysqli_fetch_assoc($productRes)) {
    $products[] = $p;
}

?>

<!DOCTYPE html>

<html>

<head>

<?php include "assets/sections/headers/header_tag.php" ?>

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

    .budget-table th:nth-child(1), .budget-table td:nth-child(1) { width: 30%; }
    .budget-table th:nth-child(2), .budget-table td:nth-child(2) { width: 10%; text-align: center; }
    .budget-table th:nth-child(3), .budget-table td:nth-child(3) { width: 14%; }
    .budget-table th:nth-child(4), .budget-table td:nth-child(4) { width: 16%; }
    .budget-table th:nth-child(5), .budget-table td:nth-child(5) { width: 12%; }
    .budget-table th:nth-child(6), .budget-table td:nth-child(6) { width: 12%; }
    .budget-table th:nth-child(7), .budget-table td:nth-child(7) { width: 6%; text-align: center; }

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

                    <div class="row">

                        <div class="col-sm-12">
                            <br>
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-12">

                            <div class="card m-b-30">

                                <div class="card-body">

                                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">

                                        <h2 class="mb-0">
                                            Budget
                                        </h2>

                                        <a href="outofstock.php"
                                           class="btn btn-secondary btn-sm">

                                            <span class="fa fa-arrow-left"></span>
                                            Out of Stock

                                        </a>

                                    </div>

                                    <p class="text-muted">
                                        Add products to build a budget. Quantity is entered manually;
                                        product information is loaded from the database.
                                    </p>


                                    <!-- GRAND TOTAL / PDF -->

                                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

                                        <div class="budget-grand-total">

                                            GRAND TOTAL:

                                            <span class="text-success">

                                                GH¢
                                                <span id="grandTotalTop">0.00</span>

                                            </span>

                                        </div>

                                        <button type="button"
                                                class="btn btn-primary mt-2 mt-md-0"
                                                onclick="generateBudgetPdf()">

                                            <span class="fa fa-file-pdf-o"></span>
                                            Generate PDF

                                        </button>

                                    </div>


                                    <!-- PRODUCT CATEGORY CONTROLS -->

                                    <div class="budget-controls mb-3">

                                        <div class="row">

                                            <div class="col-md-5">

                                                <label for="productCategory"
                                                       class="font-weight-bold">

                                                    Product Category

                                                </label>

                                                <select id="productCategory"
                                                        class="form-control"
                                                        onchange="addCategoryProducts(this.value)">

                                                    <option value="">
                                                        Select category...
                                                    </option>

                                                </select>

                                            </div>


                                            <div class="col-md-7">

                                                <label class="d-block font-weight-bold">
                                                    Product Selection
                                                </label>

                                                <button type="button"
                                                        class="btn btn-primary mr-2 mb-2"
                                                        onclick="selectAllProducts()">

                                                    <span class="fa fa-check-square-o"></span>
                                                    Select All Low-Stock Products

                                                </button>


                                                <button type="button"
                                                        class="btn btn-secondary mb-2"
                                                        onclick="clearAllProducts()">

                                                    <span class="fa fa-times"></span>
                                                    Clear All

                                                </button>

                                            </div>

                                        </div>


                                        <div class="mt-2 text-muted">

                                            Only products with <strong>Available Stock less than Quantity Alert</strong> are loaded from the database.

                                        </div>


                                        <div class="mt-2 budget-selected-count">

                                            <span id="selectedProductCount">0</span>
                                            low-stock products selected.

                                        </div>

                                    </div>


                                    <!-- BUDGET TABLE -->

                                    <div class="table-responsive">

                                        <table class="budget-table"
                                               id="budgetTable">

                                            <thead>

                                                <tr>

                                                    <th>
                                                        Product Name (Description)
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


                                            <tbody id="budgetBody"></tbody>


                                            <tfoot>

                                                <tr>

                                                    <td colspan="6"
                                                        style="text-align:right;font-weight:700;">

                                                        GRAND TOTAL

                                                    </td>

                                                    <td class="budget-grand-total">

                                                        GH¢
                                                        <span id="grandTotalBottom">
                                                            0.00
                                                        </span>

                                                    </td>

                                                </tr>

                                            </tfoot>

                                        </table>

                                    </div>


                                    <!-- ADD ROW -->

                                    <div class="mt-3">

                                        <button type="button"
                                                class="btn btn-success"
                                                onclick="addBudgetRow()">

                                            <span class="fa fa-plus"></span>
                                            Add Row

                                        </button>

                                    </div>


                                    <!-- PDF PREVIEW -->

                                    <div id="budgetPdfWrap"
                                         style="display:none;margin-top:25px;">

                                        <div class="d-flex justify-content-between align-items-center mb-2">

                                            <h5 class="mb-0">
                                                PDF Preview
                                            </h5>

                                            <a id="budgetDownloadPdf"
                                               class="btn btn-success btn-sm"
                                               href="#"
                                               target="_blank"
                                               download>

                                                <span class="fa fa-download"></span>
                                                Download PDF

                                            </a>

                                        </div>


                                        <iframe id="budgetPdfPreview"
                                                title="Budget PDF Preview"
                                                style="width:100%;
                                                       height:650px;
                                                       border:1px solid #ddd;
                                                       border-radius:4px;">
                                        </iframe>

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

        <?php include "assets/sections/footers/footer.php" ?>

    </footer>

</div>

</div>

<?php include "assets/sections/footers/jqueryscripts.php" ?>

<script>

/* =========================================================
   PRODUCTS FROM DATABASE
========================================================= */

const budgetProducts = <?php echo json_encode(
    $products,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
); ?>;


let budgetRowCounter = 0;


/* =========================================================
   FORMAT MONEY
========================================================= */

function formatMoney(value) {

    return Number(value || 0).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }
    );

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value) {

    return String(value ?? '').replace(
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


/* =========================================================
   PRODUCT OPTIONS
========================================================= */

function productOptions(selectedId = '') {

    let html =
        '<option value="">Select product...</option>';

    budgetProducts.forEach(function(p) {

        const id =
            String(p.productid);

        const label =
            String(p.pname || '') +
            (
                p.pdesc
                    ? ' - ' + String(p.pdesc)
                    : ''
            );

        html +=
            '<option value="' +
            escapeHtml(id) +
            '" ' +
            (
                id === String(selectedId)
                    ? 'selected'
                    : ''
            ) +
            '>' +
            escapeHtml(label) +
            '</option>';

    });

    return html;

}


/* =========================================================
   POPULATE CATEGORY DROPDOWN
========================================================= */

function populateCategoryFilter() {

    const select =
        document.getElementById(
            'productCategory'
        );

    if (!select) {
        return;
    }


    const categories = [];


    budgetProducts.forEach(function(product) {

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

    });


    categories.sort(function(a, b) {

        return a.localeCompare(b);

    });


    categories.forEach(function(category) {

        const option =
            document.createElement('option');

        option.value =
            category;

        option.textContent =
            category;

        select.appendChild(option);

    });

}


/* =========================================================
   CHECK IF PRODUCT ALREADY EXISTS IN TABLE
========================================================= */

function isProductSelected(productId) {

    let found = false;


    document.querySelectorAll(
        '#budgetBody .budget-product'
    ).forEach(function(select) {

        if (
            String(select.value) ===
            String(productId)
        ) {

            found = true;

        }

    });


    return found;

}


/* =========================================================
   ADD BUDGET ROW
========================================================= */

function addBudgetRow(selectedId = '') {

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

        <td class="budget-stock">—</td>


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
                class="form-control form-control-sm budget-qty"
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
                class="btn btn-danger btn-sm"
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


/* =========================================================
   ADD ALL PRODUCTS IN A CATEGORY
========================================================= */

function addCategoryProducts(category) {

    if (!category) {
        return;
    }


    budgetProducts.forEach(function(product) {

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

    });


    updateSelectedProductCount();


    /*
     * Reset the category dropdown.
     * This allows the user to select another
     * category immediately.
     */

    const categorySelect =
        document.getElementById(
            'productCategory'
        );


    if (categorySelect) {

        categorySelect.value = '';

    }

}


/* =========================================================
   SELECT ALL PRODUCTS
========================================================= */

function selectAllProducts() {

    budgetProducts.forEach(function(product) {

        if (
            !isProductSelected(
                product.productid
            )
        ) {

            addBudgetRow(
                String(product.productid)
            );

        }

    });


    updateSelectedProductCount();

}


/* =========================================================
   CLEAR ALL PRODUCTS
========================================================= */

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


/* =========================================================
   UPDATE SELECTED PRODUCT COUNTER
========================================================= */

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


/* =========================================================
   UPDATE BUDGET ROW
========================================================= */

function updateBudgetRow(select) {

    const row =
        select.closest('tr');


    const product =
        budgetProducts.find(function(p) {

            return String(p.productid) ===
                   String(select.value);

        });


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

    }

    else {

        const availableStock =
            product.totalstock === null ||
            product.totalstock === ''
                ? 0
                : product.totalstock;

        const stockNumber = parseFloat(availableStock);
        const stockDisplay = Number.isInteger(stockNumber)
            ? String(stockNumber)
            : String(stockNumber);
        const stockUnit = stockNumber === 1 ? 'pc' : 'pcs';

        row.querySelector(
            '.budget-stock'
        ).textContent = stockDisplay + ' ' + stockUnit;

        const qpu =
            product.qtyperunit === null ||
            product.qtyperunit === ''
                ? '—'
                : product.qtyperunit;


        const unitPrice =
            parseFloat(
                product.unitprice || 0
            );


        const qpuNumber = parseFloat(qpu);
        const qpuDisplay = Number.isNaN(qpuNumber)
            ? String(qpu)
            : (Number.isInteger(qpuNumber) ? String(qpuNumber) : String(qpuNumber));
        const qpuUnit = product.unit ? String(product.unit).trim() : 'unit';

        row.querySelector(
            '.budget-qpu'
        ).textContent =
            qpuDisplay + ' per ' + qpuUnit;


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


/* =========================================================
   REMOVE BUDGET ROW
========================================================= */

function removeBudgetRow(button) {

    const row =
        button.closest('tr');


    if (row) {

        row.remove();

    }


    calculateBudget();

    updateSelectedProductCount();

}


/* =========================================================
   CALCULATE BUDGET
========================================================= */

function calculateBudget() {

    let total = 0;


    document.querySelectorAll(
        '#budgetBody tr'
    ).forEach(function(row) {


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

    });


    document.getElementById(
        'grandTotalTop'
    ).textContent =
        formatMoney(total);


    document.getElementById(
        'grandTotalBottom'
    ).textContent =
        formatMoney(total);


    updateSelectedProductCount();

}


/* =========================================================
   ENCODE BUDGET ROWS FOR PDF
========================================================= */

function encodeBudgetRows() {

    const rows = [];


    document.querySelectorAll(
        '#budgetBody tr'
    ).forEach(function(row) {


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
                productid: productId,
                quantity: qty
            });

        }

    });


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


/* =========================================================
   GENERATE BUDGET PDF
========================================================= */

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


/* =========================================================
   PAGE INITIALIZATION
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function() {

        /*
         * Populate category dropdown
         */
        populateCategoryFilter();


        /*
         * Keep the original behaviour:
         * start with one empty row.
         */
        addBudgetRow();


        /*
         * Initialize counter.
         */
        updateSelectedProductCount();

    }
);

</script>

</body>

</html> . 