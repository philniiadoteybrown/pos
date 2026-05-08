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
                                            <?php echo $msg ?>
                                        </div>
                                        <?php } ?>
                                        <div class="card-header-form">
                                            <form method="GET">
                                                <div
                                                    style="display: flex; align-items: center; gap: 10px; width: 100%;">

                                                    <input type="text" id="search" class="form-control"
                                                        placeholder="Search product..." style="flex: 2;">

                                                    <select id="category" class="form-control" onchange="loadData(1)"
                                                        style="flex: 1;">
                                                        <option value="">All Categories</option>

                                                        <?php
        $catRes = mysqli_query($conn, "
            SELECT category, COUNT(*) as total 
            FROM products 
            WHERE category IS NOT NULL AND category <> '' 
            GROUP BY category 
            ORDER BY category ASC
        ");

        while($cat = mysqli_fetch_assoc($catRes)){
            echo "<option value='".htmlspecialchars($cat['category'])."'>
                    ".htmlspecialchars($cat['category'])." ({$cat['total']})
                  </option>";
        }
        ?>
                                                    </select>

                                                    <span style="white-space: nowrap;">Showing</span>

                                                    <select id="limit" class="form-control" style="flex: 0.7;">
                                                        <option value="5">5</option>
                                                        <option value="10" selected>10</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>
                                                    </select>

                                                    <span style="white-space: nowrap;">rows per page</span>

                                                </div>

                                            </form>
                                            <br>
                                            <hr>
                                            <br>
                                            <div id="tableData"></div>

                                        </div>

                                        <script>
                                        let timer;

                                        function loadData(page = 1) {

                                            let search = document.getElementById("search").value;
                                            let limit = document.getElementById("limit").value;
                                            let category = document.getElementById("category").value;

                                            fetch(
                                                    `assets/scripts/fetch_products.php?search=${encodeURIComponent(search)}&page=${page}&limit=${limit}&category=${encodeURIComponent(category)}`
                                                )
                                                .then(res => res.text())
                                                .then(data => {
                                                    document.getElementById("tableData").innerHTML = data;
                                                });
                                        }

                                        loadData(); // initial load

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
                                            document.getElementById("category").addEventListener("change",
                                                function() {
                                                    loadData(1);
                                                });
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

                                        function toggleUnits(btn) {

                                            let id = btn.getAttribute("data-id");
                                            let row = document.getElementById("units-" + id);
                                            let box = row.querySelector(".unit-box");

                                            // toggle close
                                            if (row.style.display === "table-row") {
                                                row.style.display = "none";
                                                return;
                                            }

                                            row.style.display = "table-row";

                                            // 🚀 load only once
                                            if (row.dataset.loaded === "true") return;

                                            box.innerHTML = "Loading...";

                                            fetch("assets/scripts/get_product_units.php?product_id=" + id)
                                                .then(res => res.text())
                                                .then(data => {
                                                    box.innerHTML = data;

                                                    // 🔥 activate live editing after load
                                                    bindLiveUnitInputs();

                                                    row.dataset.loaded = "true";
                                                });
                                        }

                                        document.addEventListener("click", function(e) {
                                            if (!e.target.classList.contains("save-btn")) return;

                                            let btn = e.target;
                                            let row = btn.closest("tr");

                                            let id = btn.getAttribute("data-id");
                                            let unit_name = row.querySelector(".unit_name").value;
                                            let unit_qty = row.querySelector(".unit_qty").value;
                                            let price = row.querySelector(".price").value;

                                            fetch("assets/scripts/update_units.php", {
                                                    method: "POST",
                                                    headers: {
                                                        "Content-Type": "application/x-www-form-urlencoded"
                                                    },
                                                    body: new URLSearchParams({
                                                        id,
                                                        unit_name,
                                                        unit_qty,
                                                        price
                                                    })
                                                })
                                                .then(res => res.text())
                                                .then(data => {
                                                    console.log("SERVER:", data);

                                                    if (data.trim() === "success") {
                                                        btn.textContent = "Saved";
                                                        btn.classList.remove("btn-success");
                                                        btn.classList.add("btn-primary");
                                                    } else {
                                                        alert("Update failed: " + data);
                                                    }
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