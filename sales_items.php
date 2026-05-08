<?php
$pagetitle="Sales Items Report";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
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

                                        <h2>Sales Items Report</h2>
                                        <div style="
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:nowrap;
    overflow-x:auto;
    white-space:nowrap;
">

                                            <input type="text" id="search" class="form-control" placeholder="Search..."
                                                style="min-width:180px;">

                                            <select id="category" class="form-control" style="min-width:160px;">
                                                <option value="">All Categories</option>
                                                <?php
    $res=mysqli_query($conn,"SELECT DISTINCT category FROM products ORDER BY category");
    while($r=mysqli_fetch_assoc($res)){
        echo "<option value='{$r['category']}'>{$r['category']}</option>";
    }
    ?>
                                            </select>

                                            <div style="display:flex;align-items:center;gap:5px;">
                                                <label style="margin:0;">From:</label>
                                                <input type="date" id="from" class="form-control"
                                                    style="min-width:140px;">
                                            </div>

                                            <div style="display:flex;align-items:center;gap:5px;">
                                                <label style="margin:0;">To:</label>
                                                <input type="date" id="to" class="form-control"
                                                    style="min-width:140px;">
                                            </div>

                                            <select id="limit" class="form-control" style="min-width:90px;">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                            </select>

                                        </div>

                                        <br>
                                        <hr><br>

                                        <div id="tableData"></div>

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
            let timer;

            function loadData(page = 1) {

                let search = document.getElementById("search").value;
                let category = document.getElementById("category").value;
                let from = document.getElementById("from").value;
                let to = document.getElementById("to").value;
                let limit = document.getElementById("limit").value;

                fetch(
                        `assets/scripts/fetch_sales_items.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&from=${from}&to=${to}&page=${page}&limit=${limit}`
                    )
                    .then(res => res.text())
                    .then(data => {
                        document.getElementById("tableData").innerHTML = data;
                    });
            }

            // events
            document.getElementById("search").addEventListener("keyup", () => {
                clearTimeout(timer);
                timer = setTimeout(() => loadData(1), 300);
            });

            document.getElementById("category").addEventListener("change", () => loadData(1));
            document.getElementById("from").addEventListener("change", () => loadData(1));
            document.getElementById("to").addEventListener("change", () => loadData(1));
            document.getElementById("limit").addEventListener("change", () => loadData(1));

            loadData();
            </script>

</body>

</html>