<?php
$pagetitle = "Out of Stock";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, trim($_GET['category'])) : '';

$where = "WHERE totalstock < 3";

if ($search != "") {
    $where .= " AND (
        pname LIKE '%$search%'
        OR productid LIKE '%$search%'
        OR pdesc LIKE '%$search%'
        OR category LIKE '%$search%'
    )";
}

if ($category != "") {
    $where .= " AND category = '$category'";
}

$categoryRes = mysqli_query($conn, "
    SELECT DISTINCT category
    FROM products
    WHERE category IS NOT NULL
      AND TRIM(category) <> ''
    ORDER BY category ASC
");

$totalRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = (int)$totalRow['total'];

$total_pages = ($limit > 0) ? ceil($total / $limit) : 1;

$res = mysqli_query($conn, "
    SELECT productid, pname, pdesc, qty, unitprice,
           qtyperunit, unit, category, totalstock
    FROM products
    $where
    ORDER BY pname ASC
    LIMIT $offset, $limit
");

if (!$res) {
    die("Database Error: " . mysqli_error($conn));
}

function pageUrl($pageNumber, $limit, $search, $category) {
    return '?page=' . (int)$pageNumber
        . '&limit=' . (int)$limit
        . '&search=' . urlencode($search)
        . '&category=' . urlencode($category);
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
        }

        thead {
            background-color: #f2f2f2;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .out-stock-row {
            background-color: #ffe6e6 !important;
        }

        .stock-zero {
            color: #dc3545;
            font-weight: bold;
        }

        @media (min-width: 992px) {
            .fixed-left .content-page {
                margin-left: 250px !important;
                width: calc(100% - 250px) !important;
            }
        }

        .outstock-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            flex-wrap: wrap;
        }

        .outstock-filter .search-control {
            flex: 2 1 280px;
            min-width: 220px;
        }

        .outstock-filter .category-control {
            flex: 1 1 190px;
            min-width: 170px;
        }

        .outstock-filter .limit-control {
            flex: 0 0 85px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #prodList {
            min-width: 950px;
        }

        #prodList th,
        #prodList td {
            vertical-align: middle;
        }

        #prodList th {
            white-space: nowrap;
        }

        #prodList tfoot td {
            background: #f2f2f2;
            font-weight: 700;
            border-top: 3px solid #000;
        }

        .unit-price-cell,
        .subtotal-cell {
            white-space: nowrap;
            font-weight: 600;
        }

        .qty-cell {
            width: 75px;
            max-width: 75px;
        }

        .qty-input {
            width: 68px;
            min-width: 68px;
            max-width: 68px;
            text-align: right;
            display: inline-block;
            padding-left: 4px;
            padding-right: 4px;
        }

        .qty-input:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, .15);
        }

        .grand-total-box {
            border: 2px solid #198754;
            background: #f3fff8;
            border-radius: 5px;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 12px 0;
        }

        .grand-total-label {
            font-weight: 700;
            font-size: 16px;
        }

        .grand-total-value {
            color: #198754;
            font-weight: 800;
            font-size: 20px;
            white-space: nowrap;
        }

        .pagination-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0;
        }

        .pagination-info {
            font-weight: 600;
            margin: 0 6px;
        }

        .pdf-controls {
            margin-top: 18px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .pdf-frame {
            width: 100%;
            height: 650px;
            border: 1px solid #aaa;
            display: none;
            margin-top: 15px;
            background: #f5f5f5;
        }

        .pdf-download-wrap {
            display: none;
            margin-top: 10px;
        }

        @media (max-width: 991.98px) {
            .fixed-left .content-page {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .outstock-filter {
                align-items: stretch;
                flex-direction: column;
            }

            .outstock-filter .search-control,
            .outstock-filter .category-control,
            .outstock-filter .limit-control {
                width: 100%;
                min-width: 100%;
                flex: 1 1 auto;
            }

            .filter-label {
                display: none;
            }

            .back-button {
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
                            <div class="col-12">
                                <div class="card m-b-30">
                                    <div class="card-body">

                                        <h2>
                                            Out of Stock
                                            <span class="badge badge-danger"><?php echo $total; ?></span>
                                        </h2>

                                        <p class="text-muted">
                                            Products with <strong>stock below 3</strong>.
                                        </p>

                                        <div class="card-header-form">
                                            <form method="GET">
                                                <div class="outstock-filter">
                                                    <a href="products.php"
                                                        title="Back to Products"
                                                        class="btn btn-secondary btn-animation back-button">
                                                        <span class="fa fa-arrow-left"></span>
                                                    </a>

                                                    <input type="text"
                                                        name="search"
                                                        id="search"
                                                        class="form-control search-control"
                                                        placeholder="Search product ID, name or description..."
                                                        value="<?php echo htmlspecialchars($search); ?>">

                                                    <select name="category"
                                                        id="category"
                                                        class="form-control category-control"
                                                        onchange="changeFilter()">
                                                        <option value="">All Categories</option>
                                                        <?php
                                                        if ($categoryRes && mysqli_num_rows($categoryRes) > 0) {
                                                            while ($catRow = mysqli_fetch_assoc($categoryRes)) {
                                                                $catValue = $catRow['category'];
                                                        ?>
                                                                <option value="<?php echo htmlspecialchars($catValue); ?>"
                                                                    <?php echo ($category === $catValue) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($catValue); ?>
                                                                </option>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </select>

                                                    <span class="filter-label" style="white-space:nowrap;">Showing</span>

                                                    <select name="limit"
                                                        id="limit"
                                                        class="form-control limit-control"
                                                        onchange="changeLimit()">
                                                        <option value="5" <?php echo ($limit == 5 ? 'selected' : ''); ?>>5</option>
                                                        <option value="10" <?php echo ($limit == 10 ? 'selected' : ''); ?>>10</option>
                                                        <option value="25" <?php echo ($limit == 25 ? 'selected' : ''); ?>>25</option>
                                                        <option value="50" <?php echo ($limit == 50 ? 'selected' : ''); ?>>50</option>
                                                    </select>

                                                    <span class="filter-label" style="white-space:nowrap;">rows per page</span>
                                                </div>
                                            </form>

                                            <div class="grand-total-box">
                                                <span class="grand-total-label">Grand Total</span>
                                                <span class="grand-total-value">GH¢ <span id="grandTotalTop">0.00</span></span>
                                            </div>

                                            <div class="pagination-row">
                                                <?php if ($total_pages > 1) { ?>
                                                    <?php if ($page > 1) { ?>
                                                        <a class="btn btn-light btn-sm"
                                                            href="<?php echo pageUrl($page - 1, $limit, $search, $category); ?>">
                                                            <span class="fa fa-chevron-left"></span> Previous
                                                        </a>
                                                    <?php } ?>

                                                    <span class="pagination-info">
                                                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                                    </span>

                                                    <?php if ($page < $total_pages) { ?>
                                                        <a class="btn btn-light btn-sm"
                                                            href="<?php echo pageUrl($page + 1, $limit, $search, $category); ?>">
                                                            Next <span class="fa fa-chevron-right"></span>
                                                        </a>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <span class="pagination-info">Page 1 of 1</span>
                                                <?php } ?>
                                            </div>

                                            <hr>

                                            <div class="table-responsive">
                                                <table id="prodList">
                                                    <thead>
                                                        <tr>
                                                            <th>Product ID</th>
                                                            <th>Product Name (Description)</th>
                                                            <th>Stock</th>
                                                            <th>Unit Price</th>
                                                            <th class="qty-cell">Qty</th>
                                                            <th>Quantity per Unit</th>
                                                            <th>Unit</th>
                                                            <th>Subtotal</th>
                                                        </tr>
                                                    </thead>

                                                    <tbody>
                                                        <?php if (mysqli_num_rows($res) > 0) { ?>
                                                            <?php while ($fetch = mysqli_fetch_assoc($res)) { ?>
                                                                <tr class="out-stock-row">
                                                                    <td>
                                                                        <strong><?php echo htmlspecialchars($fetch['productid']); ?></strong>
                                                                    </td>

                                                                    <td>
                                                                        <strong><?php echo htmlspecialchars($fetch['pname']); ?></strong>
                                                                        <?php if (!empty($fetch['pdesc'])) { ?>
                                                                            <br><small class="text-muted"><?php echo htmlspecialchars($fetch['pdesc']); ?></small>
                                                                        <?php } ?>
                                                                    </td>

                                                                    <td class="stock-zero">
                                                                        <?php echo number_format((float)$fetch['totalstock'], 2); ?>
                                                                    </td>

                                                                    <td class="unit-price-cell">
                                                                        GH¢ <?php echo number_format((float)$fetch['unitprice'], 2); ?>
                                                                    </td>

                                                                    <td class="qty-cell">
                                                                        <input type="number"
                                                                            class="form-control form-control-sm qty-input"
                                                                            min="0"
                                                                            step="0.01"
                                                                            value="0"
                                                                            data-productid="<?php echo htmlspecialchars($fetch['productid']); ?>"
                                                                            data-unitprice="<?php echo htmlspecialchars($fetch['unitprice']); ?>"
                                                                            aria-label="Quantity for <?php echo htmlspecialchars($fetch['pname']); ?>">
                                                                    </td>

                                                                    <td>
                                                                        <?php echo number_format((float)$fetch['qtyperunit'], 2); ?>
                                                                    </td>

                                                                    <td>
                                                                        <?php echo !empty($fetch['unit']) ? htmlspecialchars($fetch['unit']) : '<span class="text-muted">—</span>'; ?>
                                                                    </td>

                                                                    <td class="subtotal-cell">GH¢ 0.00</td>
                                                                </tr>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                            <tr>
                                                                <td colspan="8" class="text-center">
                                                                    <div class="alert alert-success mb-0">
                                                                        <span class="fa fa-check-circle"></span>
                                                                        No products with stock below 3 found.
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>

                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="7" class="table-total-label">Grand Total</td>
                                                            <td class="table-total-value">GH¢ <span id="grandTotalBottom">0.00</span></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>

                                            <div class="grand-total-box">
                                                <span class="grand-total-label">Grand Total</span>
                                                <span class="grand-total-value">GH¢ <span id="grandTotalAfterTable">0.00</span></span>
                                            </div>

                                            <div class="pagination-row">
                                                <?php if ($total_pages > 1) { ?>
                                                    <?php if ($page > 1) { ?>
                                                        <a class="btn btn-light btn-sm"
                                                            href="<?php echo pageUrl($page - 1, $limit, $search, $category); ?>">
                                                            <span class="fa fa-chevron-left"></span> Previous
                                                        </a>
                                                    <?php } ?>

                                                    <span class="pagination-info">
                                                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                                    </span>

                                                    <?php if ($page < $total_pages) { ?>
                                                        <a class="btn btn-light btn-sm"
                                                            href="<?php echo pageUrl($page + 1, $limit, $search, $category); ?>">
                                                            Next <span class="fa fa-chevron-right"></span>
                                                        </a>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <span class="pagination-info">Page 1 of 1</span>
                                                <?php } ?>
                                            </div>

                                            <div class="pdf-controls">
                                                <button type="button"
                                                    class="btn btn-danger"
                                                    id="generatePdfButton"
                                                    onclick="generateOutOfStockPDF()">
                                                    <span class="fa fa-file-pdf-o"></span>
                                                    Generate PDF
                                                </button>

                                                <span id="pdfStatus" class="text-muted ml-2"></span>

                                                <div id="pdfDownloadWrap" class="pdf-download-wrap">
                                                    <a id="downloadPdfButton"
                                                        class="btn btn-success btn-sm"
                                                        href="#"
                                                        download="out_of_stock.pdf">
                                                        <span class="fa fa-download"></span>
                                                        Download PDF
                                                    </a>
                                                </div>

                                                <iframe id="pdfPreview"
                                                    class="pdf-frame"
                                                    title="Out of Stock PDF Preview"></iframe>
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
        function loadProduct(button) {
            let id = button.getAttribute('data-id');
            let name = button.getAttribute('data-name');
            let desc = button.getAttribute('data-description');
            let qty = button.getAttribute('data-qty');
            let uc = button.getAttribute('data-uc');

            if (document.getElementById('modal-id')) {
                document.getElementById('modal-id').value = id;
                document.getElementById('modal-id-span').textContent = id;
                document.getElementById('modal-name').textContent = name;
                document.getElementById('modal-description').textContent = desc;
                document.getElementById('modal-qty').textContent = qty;
                document.getElementById('modal-uc').textContent = uc;
            }
        }

        function applyFilters() {
            let search = document.getElementById('search').value;
            let category = document.getElementById('category').value;
            let limit = document.getElementById('limit').value;

            window.location.href =
                "?page=1&limit=" +
                encodeURIComponent(limit) +
                "&search=" +
                encodeURIComponent(search) +
                "&category=" +
                encodeURIComponent(category);
        }

        function changeLimit() {
            applyFilters();
        }

        function changeFilter() {
            applyFilters();
        }

        let timer;
        document.getElementById("search").addEventListener("keyup", function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                applyFilters();
            }, 500);
        });
    </script>

    <script>
        let latestPdfUrl = '';

        function formatMoney(value) {
            return Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getEnteredQuantities() {
            const quantities = {};

            document.querySelectorAll('.qty-input').forEach(function(input) {
                const productId = input.getAttribute('data-productid');
                let qty = parseFloat(input.value);

                if (!Number.isFinite(qty) || qty < 0) {
                    qty = 0;
                }

                quantities[productId] = qty;
            });

            return quantities;
        }

        function encodeQuantities(quantities) {
            const json = JSON.stringify(quantities);
            const bytes = new TextEncoder().encode(json);
            let binary = '';

            bytes.forEach(function(byte) {
                binary += String.fromCharCode(byte);
            });

            return btoa(binary)
                .replace(/\+/g, '-')
                .replace(/\//g, '_')
                .replace(/=+$/, '');
        }

        function calculateSubTotals() {
            let grandTotal = 0;

            document.querySelectorAll('.qty-input').forEach(function(input) {
                let qty = parseFloat(input.value);
                let unitPrice = parseFloat(input.getAttribute('data-unitprice'));

                if (!Number.isFinite(qty) || qty < 0) qty = 0;
                if (!Number.isFinite(unitPrice) || unitPrice < 0) unitPrice = 0;

                const subtotal = qty * unitPrice;
                const row = input.closest('tr');
                const subtotalCell = row ? row.querySelector('.subtotal-cell') : null;

                if (subtotalCell) {
                    subtotalCell.textContent = 'GH¢ ' + formatMoney(subtotal);
                }

                grandTotal += subtotal;
            });

            const formatted = formatMoney(grandTotal);
            const ids = ['grandTotalTop', 'grandTotalBottom', 'grandTotalAfterTable'];

            ids.forEach(function(id) {
                const element = document.getElementById(id);
                if (element) element.textContent = formatted;
            });
        }

        function buildPdfUrl(download) {
            const search = document.getElementById('search').value;
            const category = document.getElementById('category').value;
            const quantities = encodeQuantities(getEnteredQuantities());

            return 'assets/scripts/export_outofstock_pdf.php'
                + '?search=' + encodeURIComponent(search)
                + '&category=' + encodeURIComponent(category)
                + '&quantities=' + encodeURIComponent(quantities)
                + '&mode=' + (download ? 'download' : 'preview');
        }

        function generateOutOfStockPDF() {
            calculateSubTotals();

            const button = document.getElementById('generatePdfButton');
            const status = document.getElementById('pdfStatus');
            const iframe = document.getElementById('pdfPreview');
            const downloadWrap = document.getElementById('pdfDownloadWrap');
            const downloadButton = document.getElementById('downloadPdfButton');

            const previewUrl = buildPdfUrl(false);
            const downloadUrl = buildPdfUrl(true);

            latestPdfUrl = downloadUrl;
            iframe.src = previewUrl;
            iframe.style.display = 'block';
            downloadWrap.style.display = 'block';
            downloadButton.href = downloadUrl;

            button.disabled = true;
            button.innerHTML = '<span class="fa fa-spinner fa-spin"></span> Generating...';
            status.textContent = 'Preparing PDF...';

            iframe.onload = function() {
                button.disabled = false;
                button.innerHTML = '<span class="fa fa-file-pdf-o"></span> Generate PDF';
                status.textContent = 'PDF generated. Preview it below or download it.';
            };

            iframe.onerror = function() {
                button.disabled = false;
                button.innerHTML = '<span class="fa fa-file-pdf-o"></span> Generate PDF';
                status.textContent = 'Unable to generate the PDF. Please try again.';
            };
        }

        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('qty-input')) {
                calculateSubTotals();
            }
        });

        document.addEventListener('DOMContentLoaded', calculateSubTotals);
    </script>
</body>
</html>
