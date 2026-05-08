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
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM customer_payments $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// fetch data
$res = mysqli_query($conn,"
SELECT * FROM customer_payments 
$where
ORDER BY amount ASC
LIMIT $offset, $limit
");


?>

<!DOCTYPE html>
<html>


<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>
    .bp-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        z-index: 1000;
    }

    .bp-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 400px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: none;
        z-index: 1001;
    }

    .bp-header,
    .bp-footer {
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #eee;
    }

    .bp-body {
        padding: 15px;
    }

    .bp-input {
        width: 100%;
        padding: 8px;
        margin-top: 8px;
    }

    .bp-btn {
        width: 100%;
        padding: 10px;
        background: #007bff;
        color: #fff;
        border: none;
        cursor: pointer;
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
                                        <h2>Payments Log</h2>

                                        <!-- <div class="card-header-form">
                                            <form>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="searchInput"
                                                        placeholder="Search..." />

                                                </div>
                                            </form>
                                        </div> -->




                                        <!-- <p class="text-muted m-b-30 font-14">DataTables has most features enabled by
                                            default, so all you need to do to use it with your own tables is to call the
                                            construction function: <code>$().DataTable();</code>.</p> -->
                                        <div class="card-header-form">
                                            <form method="GET">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <input type="text" id="search" class="form-control"
                                                        placeholder="Search customer..." style="flex: 1;">

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


                                            </form>
                                        </div>

                                        <script>
                                        let timer;

                                        function loadData(page = 1) {

                                            let search = document.getElementById("search").value;
                                            let limit = document.getElementById("limit").value;

                                            fetch(
                                                    `assets/scripts/fetch_payments.php?search=${encodeURIComponent(search)}&page=${page}&limit=${limit}`
                                                )
                                                .then(res => res.text())
                                                .then(data => {
                                                    document.getElementById("tableData").innerHTML = data;
                                                });
                                        }

                                        // 🔎 key press search (debounced)
                                        document.getElementById("search").addEventListener("keyup", function() {

                                            clearTimeout(timer);

                                            timer = setTimeout(() => {
                                                loadData(1); // reset to page 1
                                            }, 300); // delay for performance
                                        });

                                        // 🔢 change rows per page
                                        document.getElementById("limit").addEventListener("change", function() {
                                            loadData(1);
                                        });

                                        // first load
                                        loadData();

                                        document.getElementById("searchInput").addEventListener("keyup",
                                            function() {
                                                let filter = this.value.toLowerCase();
                                                let rows = document.querySelectorAll("#prodList tr");

                                                rows.forEach(row => {
                                                    let text = row.textContent.toLowerCase();
                                                    row.style.display = text.includes(filter) ? "" :
                                                        "none";
                                                });
                                            });
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



    <script>
    function sellProduct(button) {
        // Grab data from button
        let id = button.getAttribute('data-cid');
        let name = button.getAttribute('data-cname');
        let phone = button.getAttribute('data-cphone');
        let bal = button.getAttribute('data-cbalance');

        // ID -> input .value so it submits
        document.getElementById('modal-cid').value = id;

        // Name & Description -> span .textContent for display
        document.getElementById('modal-cid-span').textContent = id;
        document.getElementById('modal-cname').textContent = name;
        document.getElementById('modal-cphone').textContent = phone;
        document.getElementById('modal-cbalance').textContent = bal;
    }
    </script>


</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/tables-datatable.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:37 GMT -->

</html>