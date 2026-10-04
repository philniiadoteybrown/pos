<?php
include "dbconn.php";

$filter = $_GET['filter'] ?? '';
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 10);

if ($page <= 0) $page = 1;
if ($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;

$where = "WHERE 1";

// SEARCH
if ($search != "") {
    $where .= " AND (pname LIKE '%$search%' 
                OR pdesc LIKE '%$search%' 
                OR productid LIKE '%$search%')";
}

// FILTER (today/week)
if ($filter == "today") {
    $where .= " AND DATE(created_at) = CURDATE()";
}

if ($filter == "week") {
    $where .= " AND YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)";
}

// DATE RANGE
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($start_date != "" && $end_date != "") {
    $start_date = mysqli_real_escape_string($conn, $start_date);
    $end_date = mysqli_real_escape_string($conn, $end_date);

    $where .= " AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
}

// QUERY
// Expected Profit = total quantity in pieces × (selling price per piece - cost per piece).
$query = "SELECT purchase_items.*,
                 products.sellingprice,
                 (purchase_items.totalqty * (
                     products.sellingprice -
                     (purchase_items.unitprice / NULLIF((purchase_items.totalqty / NULLIF(purchase_items.qty, 0)), 0))
                 )) AS expected_profit
          FROM purchase_items
          LEFT JOIN products ON purchase_items.productid = products.productid
          $where
          ORDER BY purchase_items.created_at DESC, purchase_items.id DESC
          LIMIT $offset,$limit";
$result = mysqli_query($conn, $query);

// Total rows for AJAX pagination
$countQuery = "SELECT COUNT(*) AS total FROM purchase_items $where";
$countResult = mysqli_query($conn, $countQuery);
$countRow = $countResult ? mysqli_fetch_assoc($countResult) : ['total' => 0];
$total = (int)($countRow['total'] ?? 0);
$total_pages = ($limit > 0) ? (int)ceil($total / $limit) : 1;
if ($total_pages < 1) $total_pages = 1;
 ?>



<table class="table table-bordered" id="prodList">
    <thead>
        <tr>
            <th>Product Name</th>

            <th>Qty Purchased</th>
            <th>Cost (GH¢)</th>
            <th>Stock</th>
            <th>Total Cost (GH¢)</th>
            <th>Expected Profit (GH¢)</th>
            <th>Type</th>
            <th>Date</th>
            <!-- <th>Action</th> -->
        </tr>
    </thead>

    <tbody>
        <?php
if(mysqli_num_rows($result)>0){
while($fetch = mysqli_fetch_assoc($result)){
    $measure=$fetch['unit'];


 // 📅 Detect today
  
$isPriceChange = ($fetch['type'] == 'price_change') ? 'table-warning' : '';
$today = date('Y-m-d');
$rowDate = date('Y-m-d', strtotime($fetch['created_at']));

$isToday = ($rowDate == $today) ? 'table-success' : '';
$isPriceChange = ($fetch['type'] == 'price_change') ? 'table-warning' : '';

$rowClass = !empty($isToday) ? $isToday : $isPriceChange;
?>


        <tr class="<?php echo $rowClass; ?>">

            <td><?php echo $fetch['pname']." (".$fetch['pdesc'].")"; ?></td>

            <td><?php echo $fetch['qty'] ?></td>
            <td><?php echo $fetch['unitprice'] ?></td>
            <td>
                <?php
                $qpu=$fetch['totalqty']/$fetch['qty'];
$rows = floor($fetch['totalqty'] / $qpu);
$pcs  = $fetch['totalqty'] % $qpu;


    echo $fetch['totalqty']. "pcs [$rows $measure / $pcs pc(s)]";

?>
            </td>

            <td>
                <?php echo number_format((float)$fetch['totalpurchase'], 2); ?>
            </td>

            <td>
                <?php echo number_format((float)($fetch['expected_profit'] ?? 0), 2); ?>
            </td>

            <td>
                <?php
$type = strtolower($fetch['type']);

if($type == 'restock'){
    echo "<span class='badge badge-success'>Restock</span>";
}
elseif($type == 'price_change'){
    echo "<span class='badge badge-warning'>Price Change</span>";
}
else{
    echo "<span class='badge badge-secondary'>".$fetch['type']."</span>";
}
?>
            </td>
            <td><?php echo $fetch['created_at'] ?></td>

            <!-- <td>
                <a href="edit_products.php?id=<?php echo $fetch['productid']; ?>" title="Edit"
                    class="btn btn-warning btn-animation btn-sm"> <span class="fa fa-edit"></span>
                </a>
                <?php if($fetch['totalstock']<>0){?>

                <a href="restockproducts.php?id=<?php echo $fetch['productid']; ?>" title="Sell Bulk"
                    class="btn btn-success btn-animation btn-sm"> <span class="fa fa-shopping-basket"></span> </a>

                <?php } else { echo "Re-stock to sell"; }?>

                <?php if($fetch['totalstock'] <= $fetch['qtyalert']){ ?>
                <a href="restockproducts.php?id=<?php echo $fetch['productid']; ?>" title="Re-stock"
                    class="btn btn-danger btn-animation btn-sm"> <span class="fa fa-cart-arrow-down"></span> </a>
                <?php } else { ?>
                <a href="restockproducts.php?id=<?php echo $fetch['productid']; ?>" title="Re-stock"
                    class="btn btn-primary btn-animation btn-sm"> <span class="fa fa-cart-plus"></span> </a>
                <?php } ?>

            </td> -->

        </tr>

        <?php } } else { ?>

        <tr>
            <td colspan="8">No records found</td>
        </tr>

        <?php } ?>
    </tbody>
</table>

<!-- AJAX Pagination -->
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
    <nav>
        <ul class="pagination mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="#"
                   onclick="event.preventDefault(); <?php if($page > 1){ ?>loadData(<?= $page-1 ?>)<?php } ?>">
                    Previous
                </a>
            </li>

            <?php
            $start = max(1, $page - 2);
            $end   = min($total_pages, $page + 2);
            for($i = $start; $i <= $end; $i++):
            ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="#"
                   onclick="event.preventDefault(); loadData(<?= $i ?>)">
                    <?= $i ?>
                </a>
            </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="#"
                   onclick="event.preventDefault(); <?php if($page < $total_pages){ ?>loadData(<?= $page+1 ?>)<?php } ?>">
                    Next
                </a>
            </li>
        </ul>
    </nav>

    <div class="text-muted">
        <?php
        if($total > 0){
            $startRow = $offset + 1;
            $endRow = min($offset + $limit, $total);
            echo "Showing $startRow to $endRow of $total entries";
        } else {
            echo "No entries found";
        }
        ?>
    </div>
</div>