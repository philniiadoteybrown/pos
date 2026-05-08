<?php
$pagetitle="Products";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";

$where = "";

if($search != ""){
    $where = "WHERE pname LIKE '%$search%' 
              OR productid LIKE '%$search%'";
}

// total rows
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM purchase_items $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// fetch data (NOT used in AJAX view but kept for fallback)
$res = mysqli_query($conn,"
SELECT * FROM purchase_items 
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
    </style>
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
                        <div class="row">
                            <div class="col-sm-12">
                                <!-- <div class="page-title-box">
                                    <h4 class="page-title">Datatable</h4>
                                </div> -->
                                <br>
                            </div>
                        </div><!-- end page title end breadcrumb -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <h2>Purchased Items Log</h2>
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
                                        <div class="card-header-form">
                                            <form method="GET">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="text" id="search" class="form-control"
                                                        placeholder="Search product..." style="flex: 1;">
                                                    <div
                                                        style="display:flex; gap:10px; align-items:center; margin:10px 0;">
                                                        <select id="filter" class="form-control"
                                                            onchange="loadData(1, this.value)" id="filter">
                                                            <option value="">All</option>
                                                            <option value="today">Today</option>
                                                            <option value="week">This Week</option>
                                                        </select>
                                                    </div>
                                                    <div
                                                        style="display:flex; gap:10px; align-items:center; margin:10px 0;">

                                                        <input type="date" id="start_date" class="form-control"
                                                            style="width:auto;">
                                                        <input type="date" id="end_date" class="form-control"
                                                            style="width:auto;">

                                                        <button class="btn btn-primary btn-sm"
                                                            onclick="applyDateFilter()">
                                                            Filter
                                                        </button>

                                                        <button class="btn btn-secondary btn-sm"
                                                            onclick="clearDateFilter()">
                                                            Reset
                                                        </button>

                                                    </div>
                                                    showing
                                                    <select id="limit" class="form-control" style="width: auto;">
                                                        <option value="5">5</option>
                                                        <option value="10" selected>10</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>
                                                    </select>
                                                    rows per page
                                                </div>
                                            </form>
                                            <br>
                                            <hr>
                                            <br>
                                            <div id="tableData"></div>
                                            <div class="d-flex justify-content-between mt-3">

                                                <button class="btn btn-primary btn-sm"
                                                    onclick="loadData(<?= $page-1 ?>)"
                                                    <?= ($page <= 1 ? 'disabled' : '') ?>>
                                                    Previous
                                                </button>

                                                <span>
                                                    Page <?= $page ?> of <?= $total_pages ?>
                                                </span>

                                                <button class="btn btn-primary btn-sm"
                                                    onclick="loadData(<?= $page+1 ?>)"
                                                    <?= ($page >= $total_pages ? 'disabled' : '') ?>>
                                                    Next
                                                </button>

                                            </div>

                                            </form>
                                        </div>

                                        <script>
                                        let timer;
                                        let currentFilter = "";

                                        // ✅ MAIN LOADER
                                        function loadData(page = 1, filter = null) {

                                            if (filter !== null) {
                                                currentFilter = filter;
                                            }

                                            let search = document.getElementById('search').value;
                                            let limit = document.getElementById('limit').value;
                                            let start_date = document.getElementById('start_date').value;
                                            let end_date = document.getElementById('end_date').value;

                                            fetch(
                                                    `assets/scripts/fetch_purchaselog.php?page=${page}&search=${search}&filter=${currentFilter}&limit=${limit}&start_date=${start_date}&end_date=${end_date}`
                                                    )
                                                .then(res => res.text())
                                                .then(data => {
                                                    document.getElementById('tableData').innerHTML = data;
                                                });
                                        }

                                        // SEARCH (AJAX)
                                        document.getElementById("search").addEventListener("keyup", function() {
                                            clearTimeout(timer);
                                            timer = setTimeout(() => loadData(1), 300);
                                        });

                                        // LIMIT CHANGE
                                        document.getElementById("limit").addEventListener("change", function() {
                                            loadData(1);
                                        });

                                        // FILTER CHANGE
                                        document.getElementById("filter").addEventListener("change", function() {
                                            loadData(1, this.value);
                                        });

                                        // DATE FILTER
                                        function applyDateFilter() {
                                            loadData(1);
                                        }

                                        function clearDateFilter() {
                                            document.getElementById('start_date').value = "";
                                            document.getElementById('end_date').value = "";
                                            loadData(1);
                                        }

                                        // AUTO LOAD
                                        loadData();
                                        </script>
                                    </div>
                                </div>
                            </div><!-- end col -->
                        </div><!-- end row -->

                    </div><!-- container -->
                </div><!-- Page content Wrapper -->
            </div><!-- content -->
            <footer class="footer"> <?php include "assets/sections/footers/footer.php" ?>.</footer>
        </div><!-- End Right content here -->
    </div><!-- END wrapper -->




    <!-- jQuery  -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>




</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/tables-datatable.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:37 GMT -->

</html>