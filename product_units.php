<?php
$pagetitle = "Product Units";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";
include "assets/scripts/paging.php";
?>

<!DOCTYPE html>
<html>

<head>

    <?php include "assets/sections/headers/header_tag.php" ?>

    <style>
        .unit-search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .unit-search-bar input {
            flex: 1;
        }

        @media(max-width: 576px) {
            .unit-search-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .unit-search-bar select {
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


                        <!-- Product Units -->
                        <div class="row">

                            <div class="col-12">

                                <div class="card m-b-30">

                                    <div class="card-body">

                                        <h2>Product Units</h2>

                                        <p class="text-muted">
                                            Manage the different selling units for your products.
                                        </p>

                                        <hr>


                                        <!-- SEARCH BAR -->
                                        <div class="unit-search-bar">

                                            <input
                                                type="text"
                                                id="search"
                                                class="form-control"
                                                placeholder="Search product or unit..."
                                                autocomplete="off"
                                            >


                                            <select
                                                id="limit"
                                                class="form-control"
                                                style="width:auto;"
                                            >

                                                <option value="5">5</option>

                                                <option value="10" selected>
                                                    10
                                                </option>

                                                <option value="25">
                                                    25
                                                </option>

                                                <option value="50">
                                                    50
                                                </option>

                                            </select>

                                        </div>


                                        <hr>


                                        <!-- TABLE -->
                                        <div id="tableData">

                                            <div class="text-center p-4">

                                                <div class="spinner-border text-primary"
                                                    role="status">

                                                    <span class="sr-only">
                                                        Loading...
                                                    </span>

                                                </div>

                                            </div>

                                        </div>


                                    </div>

                                </div>

                            </div>

                        </div>
                        <!-- End Product Units -->


                    </div>
                    <!-- container -->

                </div>
                <!-- Page content Wrapper -->

            </div>
            <!-- content -->


            <footer class="footer">
                <?php include "assets/sections/footers/footer.php" ?>
            </footer>


        </div>
        <!-- End Right content here -->

    </div>
    <!-- END wrapper -->


    <!-- jQuery -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>


    <script>

        let timer;


        // ==========================================
        // LOAD PRODUCT UNITS
        // ==========================================

        function loadData(page = 1) {

            let search = document.getElementById("search").value;
            let limit  = document.getElementById("limit").value;


            let url =
                "assets/scripts/fetch_units.php" +
                "?search=" + encodeURIComponent(search) +
                "&page=" + page +
                "&limit=" + limit;


            fetch(url)

                .then(response => {

                    if (!response.ok) {
                        throw new Error("Failed to load product units");
                    }

                    return response.text();

                })

                .then(data => {

                    document.getElementById("tableData").innerHTML = data;

                })

                .catch(error => {

                    console.error(error);

                    document.getElementById("tableData").innerHTML = `
                        <div class="alert alert-danger">
                            Unable to load product units.
                        </div>
                    `;

                });

        }


        // ==========================================
        // SEARCH
        // ==========================================

        document.getElementById("search").addEventListener(
            "keyup",
            function() {

                clearTimeout(timer);

                timer = setTimeout(function() {

                    loadData(1);

                }, 300);

            }
        );


        // ==========================================
        // CHANGE LIMIT
        // ==========================================

        document.getElementById("limit").addEventListener(
            "change",
            function() {

                loadData(1);

            }
        );


        // ==========================================
        // FIRST LOAD
        // ==========================================

        loadData();


    </script>


</body>

</html>