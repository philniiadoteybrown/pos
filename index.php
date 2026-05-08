<?php
$pagetitle="Dashboard";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";


$filter = $_GET['filter'] ?? 'month';

$filter = $_GET['filter'] ?? 'today';
$monthyear = $_GET['monthyear'] ?? '';

if($monthyear != ''){

    $parts = explode('-', $monthyear);

    $year  = intval($parts[0]);
    $month = intval($parts[1]);

    $dateCondition = "
        MONTH(created_at)='$month'
        AND YEAR(created_at)='$year'
    ";

    $label = date("F Y", strtotime($monthyear."-01"));
}
else{

    if($filter == 'today'){

        $dateCondition = "DATE(created_at)=CURDATE()";
        $label = "Today's";

    }elseif($filter == 'week'){

        $dateCondition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $label = "Weekly";

    }else{

        $dateCondition = "
            MONTH(created_at)=MONTH(CURDATE()) 
            AND YEAR(created_at)=YEAR(CURDATE())
        ";

        $label = "Monthly";
    }
}

//$label = "Today's";

if($filter == 'week'){
    $label = "Weekly";
}
elseif($filter == 'month'){
    $label = "Monthly";
}

function percentChange($today, $yesterday){
    if($yesterday == 0){
        return 100;
    }
    return round((($today - $yesterday) / $yesterday) * 100, 2);
}

/* SALES */
$salesToday = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COALESCE(SUM(total),0) as total FROM sales 
WHERE $dateCondition
"))['total'];

$salesYesterday = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COALESCE(SUM(total),0) as total FROM sales 
WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)
"))['total'];

/* PURCHASES */
$purchaseToday = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COALESCE(SUM(qty * unitprice),0) as total FROM purchase_items 
WHERE $dateCondition
"))['total'];

$purchaseYesterday = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COALESCE(SUM(qty * unitprice),0) as total FROM purchase_items 
WHERE DATE(created_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)
"))['total'];

/* CREDIT */
$creditToday = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(DISTINCT customer_id) as total FROM sales 
WHERE payment_method='credit'
AND DATE(created_at)=CURDATE()
"))['total'];

/* MONTH */
if($monthyear != ''){

    $monthSales = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COALESCE(SUM(total),0) as total 
        FROM sales 
        WHERE MONTH(created_at)='$month'
        AND YEAR(created_at)='$year'
    "))['total'];

}else{

    $monthSales = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COALESCE(SUM(total),0) as total 
        FROM sales 
        WHERE MONTH(created_at)=MONTH(CURDATE())
        AND YEAR(created_at)=YEAR(CURDATE())
    "))['total'];
}

$lastMonthSales = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COALESCE(SUM(total),0) as total FROM sales 
WHERE MONTH(created_at)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
"))['total'];

/* PERCENTAGES */
$salesPercent = percentChange($salesToday, $salesYesterday);
$purchasePercent = percentChange($purchaseToday, $purchaseYesterday);
$monthPercent = percentChange($monthSales, $lastMonthSales);

$profitToday = $salesToday - $purchaseToday;
$profitYesterday = $salesYesterday - $purchaseYesterday;
$profitPercent = percentChange($profitToday, $profitYesterday);

/* ===================== CHART DATA ===================== */

// Monthly Sales
$monthlyData=[];
$res=mysqli_query($conn,"
SELECT DATE_FORMAT(created_at,'%b') as month, SUM(total) as total
FROM sales
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY YEAR(created_at), MONTH(created_at)
");
while($r=mysqli_fetch_assoc($res)) $monthlyData[]=$r;

// Daily Sales
$dailyData=[];
$res=mysqli_query($conn,"
SELECT DATE_FORMAT(created_at,'%d %b') as day, SUM(total) as total
FROM sales
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY DATE(created_at)
");
while($r=mysqli_fetch_assoc($res)) $dailyData[]=$r;

// Monthly SP
$monthlySP=[];
$res=mysqli_query($conn,"
SELECT 
DATE_FORMAT(s.created_at,'%b') as month,
SUM(s.total) as sales,
COALESCE((SELECT SUM(p.qty*p.unitprice) FROM purchase_items p 
WHERE MONTH(p.created_at)=MONTH(s.created_at) 
AND YEAR(p.created_at)=YEAR(s.created_at)),0) as purchases
FROM sales s
WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY YEAR(s.created_at), MONTH(s.created_at)
ORDER BY YEAR(s.created_at), MONTH(s.created_at)
");
while($r=mysqli_fetch_assoc($res)) $monthlySP[]=$r;

// Daily SP
$dailySP=[];
$res=mysqli_query($conn,"
SELECT 
DATE_FORMAT(s.created_at,'%d %b') as label,
SUM(s.total) as sales,
COALESCE((SELECT SUM(p.qty*p.unitprice) FROM purchase_items p 
WHERE DATE(p.created_at)=DATE(s.created_at)),0) as purchases
FROM sales s
WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(s.created_at)
ORDER BY DATE(s.created_at)
");
while($r=mysqli_fetch_assoc($res)) $dailySP[]=$r;
?>
<!DOCTYPE html>
<html>
<!-- Mirrored from mannatthemes.com/annex/vertical/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:12:56 GMT -->

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
                                <div class="page-title-box">
                                    <!-- <div class="btn-group float-right">
                                        <ol class="breadcrumb hide-phone p-0 m-0">
                                            <li class="breadcrumb-item"><a href="#">Annex</a></li>
                                            <li class="breadcrumb-item active">Dashboard</li>
                                        </ol>
                                    </div> -->
                                    <h4 class="page-title">Dashboard</h4>
                                </div>
                            </div>
                        </div><!-- end page title end breadcrumb -->
                        <div class="row">
                            <div class="col-md-6 col-lg-6 col-xl-12">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">

                                            <button class="btn btn-sm <?= $filter=='today'?'btn-primary':'btn-light' ?>"
                                                onclick="setFilter('today')">
                                                Today
                                            </button>

                                            <button class="btn btn-sm <?= $filter=='week'?'btn-info':'btn-light' ?>"
                                                onclick="setFilter('week')">
                                                Week
                                            </button>

                                            <button class="btn btn-sm <?= $filter=='month'?'btn-success':'btn-light' ?>"
                                                onclick="setFilter('month')">
                                                Month
                                            </button>

                                            <select id="monthYearFilter" class="form-control" style="width:200px;"
                                                onchange="filterMonthYear(this.value)">
                                                <option value="">Select Month</option>

                                                <?php for($i=0; $i<12; $i++){
            $time = strtotime("-$i month");
            $value = date("Y-m", $time);
            $text  = date("F Y", $time);
        ?>
                                                <option value="<?= $value ?>"
                                                    <?= (($_GET['monthyear'] ?? '') == $value) ? 'selected' : '' ?>>
                                                    <?= $text ?>
                                                </option>
                                                <?php } ?>
                                            </select>

                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">

                            <!-- LOW STOCK POPUP -->
                            <div id="lowStockPopup" style="
                                        display:none;
                                        position:fixed;
                                        bottom:20px;
                                        right:20px;
                                        width:350px;
                                        background:#fff;
                                        border:2px solid #dc3545;
                                        box-shadow:0 0 15px rgba(0,0,0,0.3);
                                        z-index:99999;
                                        border-radius:10px;
                                        ">

                                <div style="background:#dc3545;color:#fff;padding:10px;font-weight:bold;">
                                    🚨 Low Stock Alert
                                    <button onclick="closeLowStock()"
                                        style="float:right;background:none;border:none;color:#fff;font-size:18px;">×</button>
                                </div>

                                <div id="lowStockBody" style="padding:10px; max-height:250px; overflow:auto;">
                                    Loading...
                                </div>

                            </div>
                            <!-- LOW STOCK POPUP -->

                            <!-- Column -->


                            <!-- SALES TODAY -->
                            <div class="col-md-6 col-lg-6 col-xl-4">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div class="d-flex flex-row">

                                            <div class="col-3 align-self-center">
                                                <div class="round"><i class="mdi mdi-cash"></i></div>
                                            </div>

                                            <div class="col-6 text-center align-self-center">
                                                <h5 class="mt-0 round-inner">GHS <?= number_format($salesToday,2) ?>
                                                </h5>
                                                <p class="mb-0 text-muted"><?= $label ?> Sales</p>
                                            </div>

                                            <div class="col-3 align-self-center">
                                                <h6
                                                    class="m-0 text-center <?= ($salesPercent>=0)?'text-success':'text-danger' ?>">
                                                    <i
                                                        class="mdi <?= ($salesPercent>=0)?'mdi-arrow-up':'mdi-arrow-down' ?>"></i>
                                                    <?= $salesPercent ?>%
                                                </h6>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PURCHASES TODAY -->
                            <div class="col-md-6 col-lg-6 col-xl-4">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div class="d-flex flex-row">

                                            <div class="col-3 align-self-center">
                                                <div class="round"><i class="mdi mdi-truck"></i></div>
                                            </div>

                                            <div class="col-6 text-center align-self-center">
                                                <h5 class="mt-0 round-inner">GHS <?= number_format($purchaseToday,2) ?>
                                                </h5>
                                                <p class="mb-0 text-muted"><?= $label ?> Purchases</p>
                                            </div>

                                            <div class="col-3 align-self-center">
                                                <h6
                                                    class="m-0 text-center <?= ($purchasePercent>=0)?'text-success':'text-danger' ?>">
                                                    <i
                                                        class="mdi <?= ($purchasePercent>=0)?'mdi-arrow-up':'mdi-arrow-down' ?>"></i>
                                                    <?= $purchasePercent ?>%
                                                </h6>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CREDIT CUSTOMERS -->
                            <div class="col-md-6 col-lg-6 col-xl-4">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div class="d-flex flex-row">

                                            <div class="col-3 align-self-center">
                                                <div class="round"><i class="mdi mdi-account-alert"></i></div>
                                            </div>

                                            <div class="col-6 text-center align-self-center">
                                                <h5 class="mt-0 round-inner"><?= $creditToday ?></h5>
                                                <p class="mb-0 text-muted">Credit Sales Today</p>
                                            </div>

                                            <div class="col-3 align-self-center">
                                                <h6 class="text-warning text-center">
                                                    <i class="mdi mdi-alert"></i> Active
                                                </h6>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="row">

                            <!-- MONTHLY SALES -->
                            <div class="col-md-6 col-lg-6 col-xl-6">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div class="d-flex flex-row">

                                            <div class="col-3 align-self-center">
                                                <div class="round"><i class="mdi mdi-chart-line"></i></div>
                                            </div>

                                            <div class="col-6 text-center align-self-center">
                                                <h5 class="mt-0 round-inner">GHS <?= number_format($monthSales,2) ?>
                                                </h5>
                                                <p class="mb-0 text-muted"><?= $label ?> Sales</p>
                                            </div>

                                            <div class="col-3 align-self-center">
                                                <h6
                                                    class="m-0 text-center <?= ($monthPercent>=0)?'text-success':'text-danger' ?>">
                                                    <i
                                                        class="mdi <?= ($monthPercent>=0)?'mdi-arrow-up':'mdi-arrow-down' ?>"></i>
                                                    <?= $monthPercent ?>%
                                                </h6>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-6 col-xl-6">
                                <div class="card m-b-30">
                                    <div class="card-body">
                                        <div class="d-flex flex-row">

                                            <div class="col-3 align-self-center">
                                                <div class="round"><i class="mdi mdi-chart-line"></i></div>
                                            </div>

                                            <div class="col-6 text-center align-self-center">
                                                <h5 class="<?= ($profitToday >= 0)?'text-success':'text-danger' ?>">
                                                    GHS <?= number_format($profitToday,2) ?>
                                                </h5>

                                                <p class="mb-0 text-muted"><?= $label ?> Profit</p>
                                            </div>

                                            <div class="col-3 align-self-center">
                                                <span class="<?= ($profitPercent>=0)?'text-success':'text-danger' ?>">
                                                    <?= $profitPercent ?>%
                                                </span>
                                            </div>


                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Column -->
                    </div>

                    <hr>
                    <!-- CHARTS ROW -->

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5>Daily Sales</h5>
                                    <canvas id="dailyChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5>Monthly Sales</h5>
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5>Daily Sales vs Purchases (Profit Included)</h5>
                                    <canvas id="dailySPChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5>Montly Sales vs Purchases (Profit Included)</h5>
                                    <canvas id="monthlySPChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-12 col-lg-12 col-xl-4">
                            <div class="card bg-white m-b-30">
                                <div class="card-body">
                                    <h5 class="header-title mb-4 mt-0">Customers with Outstanding Balance
                                    </h5>
                                    <?php
                                        $debtors = mysqli_query($conn,"
                                        SELECT name, phone, balance, last_payment_date
                                        FROM customers
                                        WHERE balance > 0
                                        ORDER BY balance DESC
                                        ");
                                        ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Phone</th>
                                                    <th>Balance (GH¢)</th>
                                                    <th>Last Payment</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                                <?php if(mysqli_num_rows($debtors) > 0){ ?>
                                                <?php while($c = mysqli_fetch_assoc($debtors)){ ?>
                                                <tr>
                                                    <td><?php echo $c['name']; ?></td>
                                                    <td><?php echo $c['phone']; ?></td>
                                                    <td style="color:red;font-weight:bold;">
                                                        <?php echo number_format($c['balance'],2); ?>
                                                    </td>
                                                    <td><?php echo $c['last_payment_date'] ?? 'N/A'; ?></td>
                                                </tr>
                                                <?php } ?>
                                                <?php } else { ?>
                                                <tr>
                                                    <td colspan="4">No customers with outstanding balance
                                                    </td>
                                                </tr>
                                                <?php } ?>

                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>


                        <div class="col-md-12 col-lg-12 col-xl-4">
                            <div class="card bg-white m-b-30">
                                <div class="card-body">
                                    <?php
                                        $topItems = mysqli_query($conn,"
                                        SELECT product_names, COUNT(*) as total_sales
                                        FROM sales
                                        GROUP BY product_names
                                        ORDER BY total_sales DESC
                                        LIMIT 5
                                        ");
                                        ?>
                                    <h5 class="header-title mt-0 mb-4">Most Sold Items</h5>

                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Sales Count</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($item = mysqli_fetch_assoc($topItems)){ ?>
                                                <tr>
                                                    <td><?php echo $item['product_names']; ?></td>
                                                    <td><?php echo $item['total_sales']; ?></td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-xl-4">
                            <div class="card bg-white m-b-30">
                                <div class="card-body">
                                    <?php
$topProfit = mysqli_query($conn,"
SELECT 
    product_names as pname,
    SUM(total) as sales
FROM sales
GROUP BY product_names
ORDER BY sales DESC
LIMIT 5
");
?>
                                    <h5 class="header-title mt-0 mb-4">Top Profit Products</h5>

                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php while($p = mysqli_fetch_assoc($topProfit)){ ?>
                                            <tr>
                                                <td><?= $p['pname'] ?></td>
                                                <td><?= number_format($p['sales'],2) ?></td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>




            </div><!-- container -->
        </div><!-- Page content Wrapper -->
    </div><!-- content -->
    <footer class="footer"><?php include "assets/sections/footers/footer.php"?></footer>
    </div><!-- End Right content here -->
    </div><!-- END wrapper -->
    <!-- jQuery  -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/modernizr.min.js"></script>
    <script src="assets/js/detect.js"></script>
    <script src="assets/js/fastclick.js"></script>
    <script src="assets/js/jquery.slimscroll.js"></script>
    <script src="assets/js/jquery.blockUI.js"></script>
    <script src="assets/js/waves.js"></script>
    <script src="assets/js/jquery.nicescroll.js"></script>
    <script src="assets/js/jquery.scrollTo.min.js"></script>
    <script src="assets/plugins/skycons/skycons.min.js"></script>
    <script src="assets/plugins/raphael/raphael-min.js"></script>
    <script src="assets/plugins/morris/morris.min.js"></script>
    <script src="assets/pages/dashborad.js"></script><!-- App js -->
    <script src="assets/js/app.js"></script>
    <script>
    /* BEGIN SVG WEATHER ICON */
    if (typeof Skycons !== 'undefined') {
        var icons = new Skycons({
                "color": "#fff"
            }, {
                "resizeClear": true
            }),
            list = [
                "clear-day", "clear-night", "partly-cloudy-day",
                "partly-cloudy-night", "cloudy", "rain", "sleet", "snow", "wind",
                "fog"
            ],
            i;

        for (i = list.length; i--;)
            icons.set(list[i], list[i]);
        icons.play();
    };

    // scroll

    $(document).ready(function() {

        $("#boxscroll").niceScroll({
            cursorborder: "",
            cursorcolor: "#cecece",
            boxzoom: true
        });
        $("#boxscroll2").niceScroll({
            cursorborder: "",
            cursorcolor: "#cecece",
            boxzoom: true
        });

    });
    </script>

    <script>
    function loadLowStock() {

        fetch('assets/scripts/low_stock_check.php')
            .then(res => res.json())
            .then(data => {

                if (data.length > 0) {

                    let html = "";

                    data.forEach(item => {
                        html += `
                <div style="border-bottom:1px solid #eee;padding:8px;">
                    <b>${item.pname}</b><br>
                    Stock: <span style="color:red;">${item.totalstock}</span> / Alert: ${item.qtyalert}
                    <br>
                    <button onclick="openRestock('${item.productid}')" 
                        style="margin-top:5px;padding:5px 10px;background:#28a745;color:#fff;border:none;">
                        Restock
                    </button>
                </div>
                `;
                    });

                    document.getElementById("lowStockBody").innerHTML = html;
                    document.getElementById("lowStockPopup").style.display = "block";

                } else {
                    document.getElementById("lowStockPopup").style.display = "none";
                }
            });
    }

    // close popup
    function closeLowStock() {
        document.getElementById("lowStockPopup").style.display = "none";
    }

    // open restock modal from popup
    function openRestock(id) {
        document.getElementById("lowStockPopup").style.display = "none";

        // trigger your existing modal button
        let btn = document.createElement("button");
        btn.setAttribute("data-id", id);
        btn.setAttribute("data-toggle", "modal");
        btn.setAttribute("data-target", "#restock");

        document.body.appendChild(btn);
        btn.click();
        document.body.removeChild(btn);
    }

    // run on page load
    window.onload = function() {
        loadLowStock();
        setInterval(loadLowStock, 60000);
    };
    </script>

    <script src="assets/js/chart.js"></script>

    <script>
    function setFilter(type) {
        window.location.href = '?filter=' + type;
    }

    function filterMonthYear(value) {

        if (value == '') {
            window.location.href = '?filter=today';
            return;
        }

        window.location.href = '?monthyear=' + value;
    }

    // SALES
    const monthlySales = <?php echo json_encode($monthlyData); ?>;
    const dailySales = <?php echo json_encode($dailyData); ?>;

    new Chart(monthlyChart, {
        type: 'line',
        data: {
            labels: monthlySales.map(i => i.month),
            datasets: [{
                label: 'Sales',
                data: monthlySales.map(i => i.total)
            }]
        }
    });

    new Chart(dailyChart, {
        type: 'line',
        data: {
            labels: dailySales.map(i => i.day),
            datasets: [{
                label: 'Sales',
                data: dailySales.map(i => i.total)
            }]
        }
    });

    // DATA
    const mSP = <?php echo json_encode($monthlySP); ?>;
    const dSP = <?php echo json_encode($dailySP); ?>;

    // MONTHLY
    new Chart(document.getElementById('monthlySPChart'), {
        type: 'line',
        data: {
            labels: mSP.map(i => i.month),
            datasets: [{
                    label: 'Sales',
                    data: mSP.map(i => i.sales)
                },
                {
                    label: 'Purchases',
                    data: mSP.map(i => i.purchases)
                },
                {
                    label: 'Profit',
                    data: mSP.map(i => i.sales - i.purchases),
                    borderColor: mSP.map(i => (i.sales - i.purchases) < 0 ? 'red' : 'green')
                }
            ]
        }
    });

    // DAILY
    new Chart(document.getElementById('dailySPChart'), {
        type: 'line',
        data: {
            labels: dSP.map(i => i.label),
            datasets: [{
                    label: 'Sales',
                    data: dSP.map(i => i.sales)
                },
                {
                    label: 'Purchases',
                    data: dSP.map(i => i.purchases)
                },
                {
                    label: 'Profit',
                    data: dSP.map(i => i.sales - i.purchases),
                    borderColor: dSP.map(i => (i.sales - i.purchases) < 0 ? 'red' : 'green')
                }
            ]
        }
    });
    </script>
</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:13:31 GMT -->

</html>