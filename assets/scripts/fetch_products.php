<?php
include "dbconn.php";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn,$_GET['category']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;

// 🔎 search condition
$where = "WHERE 1";

if($search != ""){
    $where .= " AND (pname LIKE '%$search%' 
              OR pdesc LIKE '%$search%' 
              OR productid LIKE '%$search%')";
}

if($category != ""){
    $where .= " AND category = '$category'";
}

// total rows
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM products $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// fetch data
$query = "SELECT * FROM products $where ORDER BY category ASC, pname ASC LIMIT $offset,$limit";
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
    <?php echo $errmsg ?>
</div>
<?php } ?>
<?php if(isset($warnmsg)){ ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
            aria-hidden="true">&times;</span></button>
    <?php echo $warnmsg ?>
</div>
<?php } ?>
<table class="table table-bordered" id="prodList">
    <thead>
        <tr>
            <th>Product Name</th>

            <th>Category</th>
            <th class="table-info">Stock</th>
            <th>Qty per Unit</th>
            <th>Purchase Unit Price</th>
            <th>Cost per Item</th>
            <th>Selling Price</th>
            <th>Modify</th>
            <th>Action</th>
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

    /*
     * Stock status:
     * 0 stock                    = red
     * Above 0 and <= alert qty   = yellow
     * Above alert qty            = green
     */
    if((float)$fetch['totalstock'] <= 0){
        $stockClass = 'stock-out';
        $stockStyle = 'background-color:#dc3545;color:#fff;';
        $stockLabel = 'Out of Stock';
    }elseif((float)$fetch['totalstock'] <= (float)$fetch['qtyalert']){
        $stockClass = 'stock-alert';
        $stockStyle = 'background-color:#ffc107;color:#212529;';
        $stockLabel = 'Minimum Alert';
    }else{
        $stockClass = 'stock-ok';
        $stockStyle = 'background-color:#28a745;color:#fff;';
        $stockLabel = 'In Stock';
    }
?>

        <tr>

            <td><?php echo $fetch['pname']." (".$fetch['pdesc'].")"; ?>
                <?php if($fetch['totalstock']==0){?>

                <span class="ti-shopping-cart"> </span>

                <?php }?>
            </td>

            <td><?php echo $fetch['category'] ?></td>
            <td class="<?php echo $stockClass; ?>" style="<?php echo $stockStyle; ?>font-weight:700;text-align:center;">
                <?php
                    echo "$rows $measure / $pcs pc(s)";
                ?>
                <br>
                <span style="font-size:11px;">
                    <?php echo $stockLabel; ?>
                </span>
            </td>

            <!-- <td>

                <?php echo $fetch['totalstock']. " ".$fetch['unit']."/".$stock; ?>

            </td> -->
            <td>

                <?php echo $fetch['qtyperunit']?>

            </td>

            <td><?php echo $fetch['unitprice'] ?></td>
            <td><?php echo $fetch['costperunit'] ?></td>
            <td><?php echo $fetch['sellingprice'] ?></td>
            <td>
                <button title="View Other Unit Prices" type="button" class="btn btn-sm btn-info"
                    onclick="toggleUnits(this)" data-id="<?php echo $fetch['productid']; ?>">
                    <span class="fa fa-eye"></span>
                </button>

                <a href="edit_units.php?id=<?php echo $fetch['productid']; ?>" class="btn btn-sm btn-dark"
                    title="Edit Units">
                    <span class="fa fa-edit"> </span>
                </a>

                <a href="add_units.php?id=<?php echo $fetch['productid']; ?>" title="Add Prices"
                    class="btn btn-primary btn-animation btn-sm"> <span class="fa fa-plus"></span>
                </a>


            </td>
            <td>


                <a href="del_product.php?id=<?php echo $fetch['productid']; ?>" title="Delete Product"
                    class="btn btn-danger btn-animation btn-sm"
                    onclick="return confirmDelete('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-trash"></span>
                </a>

                <a href="edit_products.php?id=<?php echo $fetch['productid']; ?>" title="Edit Product"
                    class="btn btn-warning btn-animation btn-sm"
                    onclick="return confirmEdit('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-edit"></span>
                </a>
                <?php if($fetch['totalstock']<>0){?>

                <a href="restockproducts.php?id=<?php echo $fetch['productid']; ?>" title="Sell Bulk"
                    class="btn btn-info btn-animation btn-sm"> <span class="fa fa-shopping-basket"></span> </a>
                <?php }?>

                <?php if($fetch['totalstock'] <= $fetch['qtyalert']){ ?>
                <a href="restockproducts.php?id=<?php echo $fetch['productid']; ?>" title="Re-stock"
                    class="btn btn-dark btn-animation btn-sm"
                    onclick="return confirmReStock('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-cart-arrow-down"></span> </a>
                <?php } else { ?>
                <a href="restockproducts.php?id=<?php echo $fetch['productid']; ?>" title="Add stock"
                    class="btn btn-success btn-animation btn-sm"
                    onclick="return confirmAddStock('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-cart-plus"></span> </a>
                <?php } ?>

            </td>

        </tr>
        <tr id="units-<?php echo $fetch['productid']; ?>" style="display:none;">
            <td colspan="100%">
                <div class="unit-box">Loading units...</div>
            </td>
        </tr>
        <?php } } else { ?>

        <tr>
            <td colspan="9" style="text-align:center;">No records found</td>
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