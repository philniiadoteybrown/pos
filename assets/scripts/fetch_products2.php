<?php
include "dbconn.php";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;

// 🔎 search condition
$where = "";
if($search != ""){
    $where = "WHERE pname LIKE '%$search%' 
              OR pdesc LIKE '%$search%' 
              OR productid LIKE '%$search%'";
}

// total rows
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM products $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// fetch data
$query = "SELECT * FROM products $where ORDER BY productid DESC LIMIT $offset,$limit";
$search_result = mysqli_query($conn,$query);
?>
<?php if(isset($msg)){ ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <?php echo $msg ?>
</div>
<?php } ?>
<?php if(isset($errmsg)){ ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <?php echo $msg ?>
</div>
<?php } ?>
<?php if(isset($warnmsg)){ ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <?php echo $msg ?>
</div>
<?php } ?>
<table class="table table-bordered" id="prodList">
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Bulk Price</th>
            <th>Single Price</th>
            <th>Qty</th>
        </tr>
    </thead>

    <tbody>
        <?php
if(mysqli_num_rows($search_result)>0){
while($fetch = mysqli_fetch_assoc($search_result)){
    $unitqty=$fetch['qtyperunit'];
    $measure=$fetch['unit'];
    $stock=$fetch['totalstock']/$unitqty;
    $stockpc=$stock/$unitqty;
    $rows = floor($fetch['totalstock'] / $fetch['qtyperunit']);
$pcs  = $fetch['totalstock'] % $fetch['qtyperunit'];
?>

        <tr>

            <td><?php echo $fetch['pname']." (".$fetch['pdesc'].")"; ?></td>
            <td><?php echo $fetch['bulkprice'] ?></td>
            <td><?php echo $fetch['sellingprice'] ?></td>



        </tr>

        <?php } } else { ?>

        <tr>
            <td colspan="6">No records found</td>
        </tr>

        <?php } ?>
    </tbody>
</table>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">

    <!-- 📄 Pagination (LEFT) -->
    <nav aria-label="Page navigation">
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

    <!-- 📊 Range (RIGHT) -->
    <div class="text-muted">
        <?php
        if($total > 0){
            $startRow = $offset + 1;
            $endRow   = min($offset + $limit, $total);
            echo "Showing $startRow to $endRow of $total entries";
        }
        ?>
    </div>

</div>