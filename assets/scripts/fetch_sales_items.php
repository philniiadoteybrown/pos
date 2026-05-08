<?php
include "dbconn.php";
include "paging.php";

$search   = $_GET['search'] ?? "";
$category = $_GET['category'] ?? "";
$from     = $_GET['from'] ?? "";
$to       = $_GET['to'] ?? "";

// WHERE
$where="WHERE 1=1";

if($search!=""){
$where.=" AND (p.pname LIKE '%$search%' OR p.category LIKE '%$search%')";
}

if($category!=""){
$where.=" AND p.category='$category'";
}

if($from!="" && $to!=""){
$where.=" AND DATE(s.created_at) BETWEEN '$from' AND '$to'";
}

// TOTAL ROWS
$totalRes=mysqli_query($conn,"
SELECT COUNT(*) as total
FROM sales_items si
JOIN products p ON si.product_id=p.productid
JOIN sales s ON si.sale_id=s.id
$where
");

$totalRow=mysqli_fetch_assoc($totalRes);
$total=$totalRow['total'] ?? 0;
$total_pages=($total>0)?ceil($total/$limit):1;

// DATA
$res=mysqli_query($conn,"
SELECT si.*,p.pname,p.category,p.sellingprice,s.created_at
FROM sales_items si
JOIN products p ON si.product_id=p.productid
JOIN sales s ON si.sale_id=s.id
$where
ORDER BY p.category ASC
LIMIT $offset,$limit
");

// GRAND TOTAL
$totalSales=mysqli_query($conn,"
SELECT SUM(si.qty*p.sellingprice) as total
FROM sales_items si
JOIN products p ON si.product_id=p.productid
JOIN sales s ON si.sale_id=s.id
$where
");

$grand=mysqli_fetch_assoc($totalSales)['total'] ?? 0;

// CATEGORY DAILY SUMMARY
$summary=mysqli_query($conn,"
SELECT DATE(s.created_at) as d,p.category,
SUM(si.qty*p.sellingprice) as total
FROM sales_items si
JOIN products p ON si.product_id=p.productid
JOIN sales s ON si.sale_id=s.id
$where
GROUP BY d,p.category
ORDER BY d ASC
");

// prepare chart data
$chart=[];
while($s=mysqli_fetch_assoc($summary)){
$chart[]=$s;
}
?>

<!-- SUMMARY TABLE -->
<h4>Category Totals (Per Day)</h4>
<table class="table table-bordered">
    <tr>
        <th>Date</th>
        <th>Category</th>
        <th>Total</th>
    </tr>

    <?php foreach($chart as $s): ?>
    <tr>
        <td><?= $s['d'] ?></td>
        <td><?= $s['category'] ?></td>
        <td>GHS <?= number_format($s['total'],2) ?></td>
    </tr>
    <?php endforeach; ?>

</table>

<!-- MAIN TABLE -->
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Category</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
            <th>Date</th>
        </tr>
    </thead>

    <tbody>

        <?php if(mysqli_num_rows($res)>0): ?>
        <?php while($row=mysqli_fetch_assoc($res)): ?>
        <tr>
            <td><?= $row['category'] ?></td>
            <td><?= $row['pname'] ?></td>
            <td><?= $row['qty'] ?></td>
            <td>GHS <?= number_format($row['sellingprice'],2) ?></td>
            <td>GHS <?= number_format($row['qty']*$row['sellingprice'],2) ?></td>
            <td><?= date("Y-m-d",strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
        <?php else: ?>
        <tr>
            <td colspan="6" style="text-align:center;">No data</td>
        </tr>
        <?php endif; ?>

    </tbody>
</table>

<!-- TOTAL -->
<div style="text-align:right;margin-top:10px;">
    <h4>Total Sales: GHS <?= number_format($grand,2) ?></h4>
</div>

<!-- PAGINATION -->
<div style="display:flex;justify-content:space-between;align-items:center;">

    <nav>
        <ul class="pagination">

            <?php if($page>1): ?>
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadData(<?= $page-1 ?>)">Previous</a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><a class="page-link">Previous</a></li>
            <?php endif; ?>

            <?php for($i=1;$i<=$total_pages;$i++): ?>
            <li class="page-item <?=($i==$page)?'active':''?>">
                <a class="page-link" href="#" onclick="loadData(<?= $i ?>)"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if($page<$total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadData(<?= $page+1 ?>)">Next</a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><a class="page-link">Next</a></li>
            <?php endif; ?>

        </ul>
    </nav>

    <div>
        Showing <?=($total>0?$offset+1:0)?> to <?=min($offset+$limit,$total)?> of <?= $total ?>
    </div>

</div>

<!-- CHART DATA -->
<script>
let chartData = <?= json_encode($chart) ?>;
</script>