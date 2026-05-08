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
$query = "SELECT * FROM purchase_items $where ORDER BY created_at DESC LIMIT $offset,$limit";
$result = mysqli_query($conn, $query);
 ?>



<table class="table table-bordered" id="prodList">
    <thead>
        <tr>
            <th>Product Name</th>

            <th>Qty Purchased</th>
            <th>Cost (GH¢)</th>
            <th>Stock</th>
            <th>Total Cost (GH¢)</th>
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

                <?php echo $fetch['totalpurchase']?>

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
            <td colspan="7">No records found</td>
        </tr>

        <?php } ?>
    </tbody>
</table>