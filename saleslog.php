<?php

$pagetitle = "Sales Log";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";


// ---------------------------------------------------------
// OPTIONAL SERVER-SIDE VALUES
// ---------------------------------------------------------

$where = "";

if ($search != "") {
    $where = "WHERE pname LIKE '%$search%'
              OR productid LIKE '%$search%'";
}


// ---------------------------------------------------------
// TOTAL ROWS
// ---------------------------------------------------------

$totalRes = mysqli_query(
    $conn,
    "SELECT COUNT(*) as total FROM sales $where"
);

$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);


// ---------------------------------------------------------
// FETCH DATA
// ---------------------------------------------------------

$res = mysqli_query(
    $conn,
    "
    SELECT *
    FROM sales
    $where
    ORDER BY created_at DESC, id DESC
    LIMIT $offset, $limit
    "
);

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

        /* Date filter area */
        .filter-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-item {
            flex: 1;
            min-width: 180px;
        }

        .filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .filter-buttons {
            display: flex;
            gap: 5px;
        }

        @media (max-width: 768px) {

            .filter-row {
                display: block;
            }

            .filter-item {
                width: 100%;
                margin-bottom: 10px;
            }

            .filter-buttons {
                width: 100%;
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
    <!-- End Loader -->


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


                        <!-- Page title -->

                        <div class="row">

                            <div class="col-sm-12">

                                <br>

                            </div>

                        </div>

                        <!-- end page title -->


                        <div class="row">

                            <div class="col-12">

                                <div class="card m-b-30">

                                    <div class="card-body">


                                        <h2>Sales Log</h2>


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

                                                <?php echo $msg ?>

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
                                                    aria-label="Close"
                                                >

                                                    <span aria-hidden="true">
                                                        &times;
                                                    </span>

                                                </button>

                                                <?php echo $errmsg ?>

                                            </div>

                                        <?php } ?>


                                        <!-- =====================================================
                                             SEARCH + DATE FILTERS
                                        ====================================================== -->

                                        <div class="card-header-form">

                                            <div class="filter-row">


                                                <!-- Search -->

                                                <div class="filter-item">

                                                    <label for="search">
                                                        Search Product
                                                    </label>

                                                    <input
                                                        type="text"
                                                        id="search"
                                                        class="form-control"
                                                        placeholder="Search product..."
                                                    >

                                                </div>


                                                <!-- Start Date -->

                                                <div class="filter-item">

                                                    <label for="start_date">
                                                        Start Date
                                                    </label>

                                                    <input
                                                        type="date"
                                                        id="start_date"
                                                        class="form-control"
                                                    >

                                                </div>


                                                <!-- End Date -->

                                                <div class="filter-item">

                                                    <label for="end_date">
                                                        End Date
                                                    </label>

                                                    <input
                                                        type="date"
                                                        id="end_date"
                                                        class="form-control"
                                                    >

                                                </div>


                                                <!-- Date buttons -->

                                                <div class="filter-buttons">

                                                    <button
                                                        type="button"
                                                        id="filterDate"
                                                        class="btn btn-primary"
                                                    >
                                                        Filter
                                                    </button>

                                                    <button
                                                        type="button"
                                                        id="clearDate"
                                                        class="btn btn-secondary"
                                                    >
                                                        Clear
                                                    </button>

                                                </div>


                                            </div>


                                            <br>


                                            <!-- =====================================================
                                                 ROW LIMIT
                                            ====================================================== -->

                                            <div
                                                style="
                                                    display:flex;
                                                    align-items:center;
                                                    gap:8px;
                                                    flex-wrap:wrap;
                                                "
                                            >

                                                <span>
                                                    Showing
                                                </span>

                                                <select
                                                    id="limit"
                                                    class="form-control"
                                                    style="width:auto;"
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


                                            <br>

                                            <hr>

                                            <br>


                                            <!-- =====================================================
                                                 AJAX TABLE
                                            ====================================================== -->

                                            <div id="tableData"></div>


                                        </div>


                                    </div>

                                </div>

                            </div>

                        </div>


                    </div>
                    <!-- container -->

                </div>
                <!-- Page content Wrapper -->

            </div>
            <!-- content -->


            <footer class="footer">

                <?php include "assets/sections/footers/footer.php" ?>.

            </footer>


        </div>
        <!-- End Right content here -->


    </div>
    <!-- END wrapper -->



    <!-- =====================================================
         RESTOCK MODAL
    ====================================================== -->

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
                            <strong>Product ID:</strong>
                            <span id="modal-id-span"></span>
                        </p>


                        <p>
                            <strong>Product Name:</strong>
                            <span id="modal-name"></span>
                        </p>


                        <p>
                            <strong>Description:</strong>
                            <span id="modal-description"></span>
                        </p>


                        <p>
                            <strong>Quantity Available:</strong>
                            <span id="modal-qty"></span>
                        </p>


                        <p>
                            <strong>Unit Cost:</strong>
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
                                min="1"
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



    <!-- =====================================================
         RESTOCK JAVASCRIPT
    ====================================================== -->

    <script>

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

    </script>



    <!-- =====================================================
         SINGLE UNIT SALES MODAL
    ====================================================== -->

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
                        aria-label="Close"
                    >

                        <span aria-hidden="true">
                            &times;
                        </span>

                    </button>

                </div>


                <div class="modal-body">


                    <form
                        action="assets/scripts/process_addsales.php"
                        method="post"
                    >


                        <!-- ID goes here so it submits to DB -->

                        <input
                            type="hidden"
                            name="productid"
                            id="modal-sid"
                        >


                        <!-- Display information -->

                        <p>
                            <strong>Product ID:</strong>
                            <b>
                                <span id="modal-sid-span"></span>
                            </b>
                        </p>


                        <p>
                            <strong>Product Name:</strong>
                            <b>
                                <span id="modal-sname"></span>
                            </b>
                        </p>


                        <p>
                            <strong>Description:</strong>
                            <b>
                                <span id="modal-sdescription"></span>
                            </b>
                        </p>


                        <p>
                            <strong>Stock Available:</strong>
                            <b>
                                <span id="modal-sqty"></span>
                            </b>
                        </p>


                        <p>
                            <strong>Price:</strong>
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



    <!-- =====================================================
         jQuery
    ====================================================== -->

    <?php include "assets/sections/footers/jqueryscripts.php" ?>



    <!-- =====================================================
         SALES LOG JAVASCRIPT
    ====================================================== -->

    <script>

        let timer;


        // =====================================================
        // LOAD SALES DATA
        // =====================================================

        function loadData(page = 1) {

            let search = document
                .getElementById("search")
                .value;

            let limit = document
                .getElementById("limit")
                .value;

            let startDate = document
                .getElementById("start_date")
                .value;

            let endDate = document
                .getElementById("end_date")
                .value;


            // -------------------------------------------------
            // Validate date range
            // -------------------------------------------------

            if (startDate !== "" &&
                endDate !== "" &&
                startDate > endDate) {

                alert("Start date cannot be later than end date.");

                return;
            }


            // -------------------------------------------------
            // Build AJAX URL
            // -------------------------------------------------

            let url =
                "assets/scripts/fetch_saleslog.php" +
                "?search=" + encodeURIComponent(search) +
                "&page=" + encodeURIComponent(page) +
                "&limit=" + encodeURIComponent(limit) +
                "&start_date=" + encodeURIComponent(startDate) +
                "&end_date=" + encodeURIComponent(endDate);


            // -------------------------------------------------
            // Load table
            // -------------------------------------------------

            fetch(url)

                .then(function(res) {

                    if (!res.ok) {
                        throw new Error(
                            "Unable to load sales data."
                        );
                    }

                    return res.text();

                })

                .then(function(data) {

                    document.getElementById(
                        "tableData"
                    ).innerHTML = data;

                })

                .catch(function(error) {

                    console.error(error);

                    document.getElementById(
                        "tableData"
                    ).innerHTML =
                        '<div class="alert alert-danger">' +
                        'Unable to load sales data.' +
                        '</div>';

                });

        }



        // =====================================================
        // SEARCH - DEBOUNCED
        // =====================================================

        document
            .getElementById("search")
            .addEventListener(
                "keyup",
                function() {

                    clearTimeout(timer);


                    timer = setTimeout(
                        function() {

                            loadData(1);

                        },
                        300
                    );

                }
            );



        // =====================================================
        // ROW LIMIT
        // =====================================================

        document
            .getElementById("limit")
            .addEventListener(
                "change",
                function() {

                    loadData(1);

                }
            );



        // =====================================================
        // FILTER BUTTON
        // =====================================================

        document
            .getElementById("filterDate")
            .addEventListener(
                "click",
                function() {

                    loadData(1);

                }
            );



        // =====================================================
        // CLEAR DATE FILTER
        // =====================================================

        document
            .getElementById("clearDate")
            .addEventListener(
                "click",
                function() {

                    document.getElementById(
                        "start_date"
                    ).value = "";


                    document.getElementById(
                        "end_date"
                    ).value = "";


                    loadData(1);

                }
            );



        // =====================================================
        // PRESS ENTER IN DATE FIELDS
        // =====================================================

        document
            .getElementById("start_date")
            .addEventListener(
                "change",
                function() {

                    let startDate = this.value;

                    let endDate = document
                        .getElementById("end_date")
                        .value;


                    if (
                        startDate !== "" &&
                        endDate !== "" &&
                        startDate > endDate
                    ) {

                        document.getElementById(
                            "end_date"
                        ).value = startDate;

                    }

                }
            );



        document
            .getElementById("end_date")
            .addEventListener(
                "change",
                function() {

                    let startDate = document
                        .getElementById("start_date")
                        .value;

                    let endDate = this.value;


                    if (
                        startDate !== "" &&
                        endDate !== "" &&
                        endDate < startDate
                    ) {

                        document.getElementById(
                            "start_date"
                        ).value = endDate;

                    }

                }
            );



        // =====================================================
        // INITIAL LOAD
        // =====================================================

        loadData(1);

    </script>



    <!-- =====================================================
         SELL PRODUCT JAVASCRIPT
    ====================================================== -->

    <script>

        function sellProduct(button) {

            // Grab data from button

            let id =
                button.getAttribute(
                    'data-sid'
                );

            let name =
                button.getAttribute(
                    'data-sname'
                );

            let desc =
                button.getAttribute(
                    'data-sdescription'
                );

            let qty =
                button.getAttribute(
                    'data-sqty'
                );

            let sp =
                button.getAttribute(
                    'data-ssp'
                );


            // ID -> input .value

            document.getElementById(
                'modal-sid'
            ).value = id;


            // Display values

            document.getElementById(
                'modal-sid-span'
            ).textContent = id;


            document.getElementById(
                'modal-sname'
            ).textContent = name;


            document.getElementById(
                'modal-sdescription'
            ).textContent = desc;


            document.getElementById(
                'modal-sqty'
            ).textContent = qty;


            document.getElementById(
                'modal-ssp'
            ).textContent = sp;

        }

    </script>


</body>

</html>
