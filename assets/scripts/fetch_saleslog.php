<?php
include "dbconn.php";

// ✅ Inputs
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;

// ✅ Default values (prevents warnings)
$total = 0;
$total_pages = 1;

// 🔎 Search condition (FIXED)
$where = "";
if($search != ""){
    $where = "WHERE sales.product_names LIKE '%$search%' 
              OR customers.name LIKE '%$search%'";
}

// ✅ Total rows (WITH JOIN)
$totalRes = mysqli_query($conn,"
SELECT COUNT(*) as total 
FROM sales 
LEFT JOIN customers ON sales.customer_id = customers.id
$where
");

if($totalRes){
    $totalRow = mysqli_fetch_assoc($totalRes);
    $total = $totalRow['total'] ?? 0;
}

// ✅ Calculate pages safely
$total_pages = ($limit > 0) ? ceil($total / $limit) : 1;
if($total_pages < 1) $total_pages = 1;

// ✅ Fetch data (WITH JOIN)
$query = "
SELECT sales.*, customers.name AS customer_name
FROM sales
LEFT JOIN customers ON sales.customer_id = customers.id
$where
LIMIT $offset,$limit
";

$search_result = mysqli_query($conn,$query);
?>

<!-- ✅ Alerts -->
<?php if(isset($msg)){ ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <?php echo $msg ?>
</div>
<?php } ?>

<?php if(isset($errmsg)){ ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <?php echo $errmsg ?>
</div>
<?php } ?>

<?php if(isset($warnmsg)){ ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <?php echo $warnmsg ?>
</div>
<?php } ?>

<!-- ✅ Table -->
<table class="table table-bordered" id="prodList">
    <thead>
        <tr>
            <th>Products</th>
            <th>Total (GH¢)</th>
            <th>Paid (GH¢)</th>
            <th>Balance (GH¢)</th>
            <th>Payment Method</th>
            <th>Creditor</th>
            <th>Date</th>
        </tr>
    </thead>

    <tbody>
        <?php if($search_result && mysqli_num_rows($search_result) > 0){ ?>
        <?php while($fetch = mysqli_fetch_assoc($search_result)){ ?>
        <tr>
            <td><?php echo $fetch['product_names']; ?></td>
            <td><?php echo $fetch['total']; ?></td>
            <td><?php echo $fetch['paid']; ?></td>
            <td><?php echo $fetch['balance']; ?></td>
            <td><?php echo $fetch['payment_method']; ?></td>
            <td><?php echo $fetch['customer_name'] ?? 'Walk-in'; ?></td>
            <td><?php echo $fetch['created_at']; ?></td>
        </tr>
        <?php } ?>
        <?php } else { ?>
        <tr>
            <td colspan="7">No records found</td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<!-- ✅ Pagination -->
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">

    <!-- 📄 Pagination -->
    <nav>
        <ul class="pagination mb-0">

            <!-- Previous -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="#"
                    onclick="event.preventDefault(); <?php if($page > 1){ ?>loadData(<?= $page-1 ?>)<?php } ?>">
                    Previous
                </a>
            </li>

            <!-- Page numbers -->
            <?php
            $start = max(1, $page - 2);
            $end   = min($total_pages, $page + 2);

            for($i = $start; $i <= $end; $i++):
            ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="#" onclick="event.preventDefault(); loadData(<?= $i ?>)">
                    <?= $i ?>
                </a>
            </li>
            <?php endfor; ?>

            <!-- Next -->
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="#"
                    onclick="event.preventDefault(); <?php if($page < $total_pages){ ?>loadData(<?= $page+1 ?>)<?php } ?>">
                    Next
                </a>
            </li>

        </ul>
    </nav>

    <!-- 📊 Range -->
    <div class="text-muted">
        <?php
        if($total > 0){
            $startRow = $offset + 1;
            $endRow   = min($offset + $limit, $total);
            echo "Showing $startRow to $endRow of $total entries";
        } else {
            echo "No entries found";
        }
        ?>
    </div>

</div>