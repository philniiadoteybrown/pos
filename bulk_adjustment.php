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

if(isset($_POST['product_id'])){


    $ids  = $_POST['product_id'];
    $phys = $_POST['physical_qty'];

    mysqli_begin_transaction($conn);

    try {

        foreach($ids as $i => $pid){

            $pid = mysqli_real_escape_string($conn, trim($pid));

            // skip empty physical inputs
            if(!isset($phys[$i]) || $phys[$i] === ''){
                continue;
            }

            $physical = floatval($phys[$i]);

            // get current product data
            $res = mysqli_query($conn,"
                SELECT pname, totalstock
                FROM products
                WHERE productid='$pid'
            ");

            if(!$res){
                throw new Exception(mysqli_error($conn));
            }

            $row = mysqli_fetch_assoc($res);

            if(!$row){
                continue;
            }

            $product_name = mysqli_real_escape_string($conn, $row['pname']);

            $system = floatval($row['totalstock']);

            $difference = $physical - $system;

            // skip unchanged stock
            if($difference == 0){
                continue;
            }

            // save adjustment history
            $insert = mysqli_query($conn,"
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
            ");

            if(!$insert){
                throw new Exception(mysqli_error($conn));
            }

            // update product stock
            $update = mysqli_query($conn,"
                UPDATE products
                SET totalstock='$physical'
                WHERE productid='$pid'
            ");

            if(!$update){
                throw new Exception(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);

        $msg = "Bulk closing completed successfully";

    } catch(Exception $e){

        mysqli_rollback($conn);

        $errmsg = $e->getMessage();
    }
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
                                        <h2>Products</h2>
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
                                            <?php echo $errmsg ?>
                                        </div>
                                        <?php } ?>
                                        <div class="card-header-form">
                                            <form method="GET">
                                                <div
                                                    style="display: flex; align-items: center; gap: 10px; width: 100%;">

                                                    <input type="text" id="searchInput" class="form-control"
                                                        placeholder="Search product..." style="flex: 2;">

                                                    <select id="category" class="form-control" style="flex: 1;">
                                                        <option value="">All Categories</option>
                                                        <?php
    $catRes = mysqli_query($conn,"
        SELECT DISTINCT category FROM products 
        WHERE category IS NOT NULL AND category <> '' 
        ORDER BY category ASC
    ");
    while($cat = mysqli_fetch_assoc($catRes)){
        echo "<option value='".htmlspecialchars($cat['category'])."'>
                ".htmlspecialchars($cat['category'])."
              </option>";
    }
    ?>
                                                    </select>

                                                    <span>Showing</span>

                                                    <select id="limit" class="form-control" style="flex: 0.7;">
                                                        <option value="5">5</option>
                                                        <option value="10" selected>10</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>
                                                    </select>

                                                    <span>rows per page</span>
                                                </div>

                                            </form>
                                            <br>
                                            <hr>
                                            <br>
                                            <h3>🧾 Smart Closing System</h3>

                                            <form method="POST">

                                                <div
                                                    style="max-height:auto; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                        onclick="copyAllSystem()">
                                                        📋 Copy All System Stock
                                                    </button>
                                                    <button type="button" class="btn btn-dark btn-sm"
                                                        onclick="generateCloseReport()">
                                                        📄 Generate Close Report
                                                    </button>

                                                    <table class="table table-bordered">
                                                        <thead style="position:sticky; top:0; background:#fff;">
                                                            <tr>
                                                                <th>Product</th>
                                                                <th>System Stock</th>
                                                                <th>Physical Count</th>
                                                                <th>Difference</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>

                                                        <tbody id="closingTable"></tbody>
                                                    </table>
                                                    <br>
                                                    <div id="closeReport"
                                                        style="display:none; border:1px solid #000; padding:15px;">
                                                        <h3>🧾 Daily Closing Report</h3> <button
                                                            class="btn btn-success btn-sm" onclick="window.print()">
                                                            🖨 Print Report
                                                        </button>
                                                        <hr>
                                                        <div id="reportContent"></div>
                                                    </div>
                                                </div>

                                                <br>

                                                <button type="submit" class="btn btn-success btn-lg">
                                                    Save Closing Stock
                                                </button>

                                            </form>

                                        </div>

                                        <script>
                                        let timer;


                                        function loadClosingData() {

                                            let search = document.getElementById("searchInput").value;
                                            let category = document.getElementById("category").value;

                                            fetch(
                                                    `assets/scripts/fetch_closing.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`
                                                )
                                                .then(res => res.text())
                                                .then(data => {
                                                    document.getElementById("closingTable").innerHTML = data;
                                                });
                                        }

                                        // 🔎 Typing (no reload)
                                        document.getElementById("searchInput").addEventListener("keyup", function() {
                                            clearTimeout(timer);
                                            timer = setTimeout(loadClosingData, 300);
                                        });

                                        // 📂 Category change
                                        document.getElementById("category").addEventListener("change", loadClosingData);

                                        // 🚀 Initial load → ALL products
                                        window.onload = loadClosingData;

                                        // 🔎 Typing (no reload)
                                        document.getElementById("searchInput").addEventListener("keyup", function() {
                                            clearTimeout(timer);
                                            timer = setTimeout(loadClosingData, 300);
                                        });

                                        // 📂 Category change
                                        document.getElementById("category").addEventListener("change", function() {
                                            loadClosingData();
                                        });

                                        // 🚀 Initial load → ALL products
                                        loadClosingData();

                                        // // 🔢 change rows per page
                                        // document.getElementById("limit").addEventListener("change", function() {
                                        //     loadData(1);
                                        //     document.getElementById("category").addEventListener("change",
                                        //         function() {
                                        //             loadData(1);
                                        //         });
                                        // });

                                        // // first load
                                        // loadData();

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

                                        function calcSmart(input) {

                                            let row = input.closest('tr');

                                            let system = parseFloat(row.querySelector('.system').value) || 0;
                                            let physical = parseFloat(input.value) || 0;

                                            let diff = physical - system;

                                            row.querySelector('.diff').value = diff;

                                            let status = row.querySelector('.status');

                                            if (diff < 0) {
                                                status.innerHTML = "🔴 LOSS";
                                                status.style.color = "red";
                                            } else if (diff > 0) {
                                                status.innerHTML = "🟡 GAIN";
                                                status.style.color = "orange";
                                            } else {
                                                status.innerHTML = "🟢 OK";
                                                status.style.color = "green";
                                            }
                                        }

                                        function copySystem(btn) {
                                            let row = btn.closest('tr');

                                            let system = row.querySelector('.system').value;
                                            let physical = row.querySelector('.physical');

                                            physical.value = system;

                                            calcSmart(physical);
                                        }

                                        function copyAllSystem() {

                                            let rows = document.querySelectorAll("tbody tr");

                                            rows.forEach(row => {

                                                let system = row.querySelector(".system").value;
                                                let physicalInput = row.querySelector(".physical");

                                                physicalInput.value = system;

                                                // recalc each row
                                                calcSmart(physicalInput);
                                            });

                                            alert("All system stock copied successfully!");
                                        }

                                        function generateCloseReport() {

                                            let rows = document.querySelectorAll("tbody tr");

                                            let totalLoss = 0;
                                            let totalGain = 0;
                                            let totalItems = 0;

                                            let reportHTML = `
        <div id="printArea">
            <h2 style="text-align:center;">🧾 Daily Closing Report</h2>
            <p><strong>Date:</strong> ${new Date().toLocaleString()}</p>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>System</th>
                        <th>Physical</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;

                                            rows.forEach(row => {

                                                let product = row.querySelector("td").innerText.trim();
                                                let system = parseFloat(row.querySelector(".system").value) ||
                                                    0;
                                                let physical = parseFloat(row.querySelector(".physical")
                                                    .value) || 0;

                                                // skip empty rows (not counted)
                                                if (physical === 0 && system === 0) return;

                                                let diff = physical - system;

                                                let status = "";
                                                if (diff < 0) {
                                                    status = "LOSS";
                                                    totalLoss += Math.abs(diff);
                                                } else if (diff > 0) {
                                                    status = "GAIN";
                                                    totalGain += diff;
                                                } else {
                                                    status = "OK";
                                                }

                                                totalItems++;

                                                reportHTML += `
            <tr>
                <td>${product}</td>
                <td>${system}</td>
                <td>${physical}</td>
                <td>${diff}</td>
                <td>${status}</td>
            </tr>
        `;
                                            });

                                            reportHTML += `
                </tbody>
            </table>

            <hr>

            <h4>Summary</h4>
            <p><strong>Total Items Counted:</strong> ${totalItems}</p>
            <p><strong>Total Loss:</strong> ${totalLoss}</p>
            <p><strong>Total Gain:</strong> ${totalGain}</p>
        </div>
    `;

                                            // inject report
                                            let container = document.getElementById("reportContent");
                                            container.innerHTML = reportHTML;

                                            document.getElementById("closeReport").style.display = "block";
                                        }
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
    <!-- Restock Modal -->
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

                        <p><strong>Product ID:</strong> <span id="modal-id-span"></span></p>
                        <p><strong>Product Name:</strong> <span id="modal-name"></span></p>
                        <p><strong>Description:</strong> <span id="modal-description"></span></p>
                        <p><strong>Quantity Available:</strong> <span id="modal-qty"></span></p>
                        <p><strong>Unit Cost:</strong> <span id="modal-uc"></span></p>

                        <div class="form-group">
                            <label>Quantity to Stock</label>
                            <input type="number" class="form-control" name="qpurchase" required>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="addstock" class="btn btn-success">Add Stock</button>
                    </div>

                </form>

            </div>
        </div>
    </div>

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


    <!-- Modal with form sell -->
    <div class="modal fade" id="sell" tabindex="-1" role="dialog" aria-labelledby="formModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formModal">Single Unit Sales</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="assets/scripts/process_addsales.php" method="post">
                        <!-- ID goes here so it submits to DB -->
                        <input type="hidden" name="productid" id="modal-sid">

                        <!-- These are just for display -->
                        <p><strong>Product ID:</strong><b> <span id="modal-sid-span"></span></b></p>
                        <p><strong>Product Name:</strong> <b><span id="modal-sname"></span></b></p>
                        <p><strong>Description:</strong> <b><span id="modal-sdescription"></span></b></p>
                        <p><strong>Stock Available:</strong> <b><span id="modal-sqty"></span></b></p>
                        <p><strong>Price:</strong> <b><span id="modal-ssp"></span></b></p>
                        <hr>
                        <div class="form-group">
                            <label>Quantity Soled</label>
                            <input type="number" class="form-control" name="qtysold" min="1" required>
                        </div>

                        <button type="submit" class="btn btn-success" name="addsale">Add Sales</button>
                    </form>

                </div>
            </div>
        </div>
    </div>




    <!-- jQuery  -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>
    <?php include "assets/js/stop_save.js" ?>


    <script>
    function sellProduct(button) {
        // Grab data from button
        let id = button.getAttribute('data-sid');
        let name = button.getAttribute('data-sname');
        let desc = button.getAttribute('data-sdescription');
        let qty = button.getAttribute('data-sqty');
        let sp = button.getAttribute('data-ssp');

        // ID -> input .value so it submits
        document.getElementById('modal-sid').value = id;

        // Name & Description -> span .textContent for display
        document.getElementById('modal-sid-span').textContent = id;
        document.getElementById('modal-sname').textContent = name;
        document.getElementById('modal-sdescription').textContent = desc;
        document.getElementById('modal-sqty').textContent = qty;
        document.getElementById('modal-ssp').textContent = sp;
    }
    </script>


</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/tables-datatable.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:37 GMT -->

</html>