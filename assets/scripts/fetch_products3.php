<?php
include "dbconn.php";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn,$_GET['category']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;

// Search condition
$where = "WHERE 1";

if($search != ""){
    $where .= " AND (pname LIKE '%$search%'
              OR pdesc LIKE '%$search%'
              OR productid LIKE '%$search%'
              OR barcode LIKE '%$search%')";
}

if($category != ""){
    $where .= " AND category = '$category'";
}

// Total rows
$totalRes = mysqli_query($conn,"SELECT COUNT(*) as total FROM products $where");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

// Fetch data
$query = "SELECT * FROM products $where ORDER BY category ASC, pname ASC LIMIT $offset,$limit";
$search_result = mysqli_query($conn,$query);
?>

<?php if(isset($msg)){ ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <?php echo $msg ?>
</div>
<?php } ?>

<?php if(isset($errmsg)){ ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <?php echo $errmsg ?>
</div>
<?php } ?>

<?php if(isset($warnmsg)){ ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
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

                $unitqty = (float)$fetch['qtyperunit'];
                $measure = $fetch['unit'];

                $rows = $unitqty > 0
                    ? floor((float)$fetch['totalstock'] / $unitqty)
                    : 0;

                $pcs = $unitqty > 0
                    ? fmod((float)$fetch['totalstock'], $unitqty)
                    : 0;

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

            <td>
                <div class="mobile-product-name">
                    <?php echo htmlspecialchars($fetch['pname']); ?>
                    <?php if((float)$fetch['totalstock'] == 0){ ?>
                        <span class="ti-shopping-cart"></span>
                    <?php } ?>
                </div>

                <div class="mobile-product-description">
                    <?php echo htmlspecialchars($fetch['pdesc']); ?>
                </div>

                <div class="mobile-product-details">

                    <div class="mobile-product-detail">
                        <span class="detail-label">Category</span>
                        <span class="detail-value"><?php echo htmlspecialchars($fetch['category']); ?></span>
                    </div>

                    <div class="mobile-product-detail">
                        <span class="detail-label">Stock</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($rows . " " . $measure . " / " . $pcs . " pc(s)"); ?>
                            <br>
                            <span class="mobile-stock-status" style="<?php echo $stockStyle; ?>">
                                <?php echo htmlspecialchars($stockLabel); ?>
                            </span>
                        </span>
                    </div>

                    <div class="mobile-product-detail">
                        <span class="detail-label">Qty / Unit</span>
                        <span class="detail-value"><?php echo htmlspecialchars($fetch['qtyperunit']); ?></span>
                    </div>

                    <div class="mobile-product-detail">
                        <span class="detail-label">Unit Price</span>
                        <span class="detail-value"><?php echo htmlspecialchars($fetch['unitprice']); ?></span>
                    </div>

                    <div class="mobile-product-detail">
                        <span class="detail-label">Cost / Item</span>
                        <span class="detail-value"><?php echo htmlspecialchars($fetch['costperunit']); ?></span>
                    </div>

                    <div class="mobile-product-detail">
                        <span class="detail-label">Selling Price</span>
                        <span class="detail-value"><?php echo htmlspecialchars($fetch['sellingprice']); ?></span>
                    </div>

                </div>

                <!-- <div class="desktop-product-name">
                    <?php echo htmlspecialchars($fetch['pname']." (".$fetch['pdesc'].")"); ?>
                </div> -->
            </td>

            <td><?php echo htmlspecialchars($fetch['category']); ?></td>

            <td class="<?php echo $stockClass; ?>"
                style="<?php echo $stockStyle; ?>font-weight:700;text-align:center;">

                <?php
                    echo htmlspecialchars($rows . " " . $measure . " / " . $pcs . " pc(s)");
                ?>

                <br>

                <span style="font-size:11px;">
                    <?php echo $stockLabel; ?>
                </span>

            </td>

            <td>
                <?php echo htmlspecialchars($fetch['qtyperunit']); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($fetch['unitprice']); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($fetch['costperunit']); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($fetch['sellingprice']); ?>
            </td>

            <!-- MODIFY COLUMN -->
            <td>
<a
                    href="edit_pro_details.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Edit Product Details"
                    class="btn btn-secondary btn-animation btn-sm"
                    onclick="return confirmEdit('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-edit"></span>
                </a>
                <button
                    title="View Other Unit Prices"
                    type="button"
                    class="btn btn-sm btn-info"
                    onclick="toggleUnits(this)"
                    data-id="<?php echo htmlspecialchars($fetch['productid']); ?>">
                    <span class="fa fa-eye"></span>
                </button>

                <a
                    href="edit_unit2.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    class="btn btn-sm btn-dark"
                    title="Edit Units">
                    <span class="fa fa-edit"></span>
                </a>

                <a
                    href="add_units.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Add Prices"
                    class="btn btn-primary btn-animation btn-sm">
                    <span class="fa fa-plus"></span>
                </a>

            </td>

            <!-- ACTION COLUMN -->
            <td>

                <a
                    href="del_product.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Delete Product"
                    class="btn btn-danger btn-animation btn-sm"
                    onclick="return confirmDelete('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-trash"></span>
                </a>

                <a
                    href="edit_products.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Edit Product"
                    class="btn btn-warning btn-animation btn-sm"
                    onclick="return confirmEdit('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-edit"></span>
                </a>

                <?php if((float)$fetch['totalstock'] != 0){ ?>

                <a
                    href="restockproducts.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Sell Bulk"
                    class="btn btn-info btn-animation btn-sm">
                    <span class="fa fa-shopping-basket"></span>
                </a>

                <?php } ?>

                <?php if((float)$fetch['totalstock'] <= (float)$fetch['qtyalert']){ ?>

                <a
                    href="restockproducts.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Re-stock"
                    class="btn btn-dark btn-animation btn-sm"
                    onclick="return confirmReStock('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-cart-arrow-down"></span>
                </a>

                <?php } else { ?>

                <a
                    href="restockproducts.php?id=<?php echo urlencode($fetch['productid']); ?>"
                    title="Add stock"
                    class="btn btn-success btn-animation btn-sm"
                    onclick="return confirmAddStock('<?php echo addslashes($fetch['pname'].' ('.$fetch['pdesc'].')'); ?>')">
                    <span class="fa fa-cart-plus"></span>
                </a>

                <?php } ?>

            </td>

        </tr>

        <!-- HIDDEN UNIT DETAILS ROW -->
        <tr id="units-<?php echo htmlspecialchars($fetch['productid']); ?>" style="display:none;">
            <td colspan="100%">
                <div class="unit-box">Loading units...</div>
            </td>
        </tr>

        <?php
            }
        } else {
        ?>

        <tr>
            <td colspan="9" style="text-align:center;">No records found</td>
        </tr>

        <?php } ?>

    </tbody>
</table>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">

    <!-- Pagination LEFT -->
    <nav aria-label="Page navigation">
        <ul class="pagination mb-0">

            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="#"
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
                <a class="page-link"
                   href="#"
                   onclick="event.preventDefault(); loadData(<?= $i ?>)">
                    <?= $i ?>
                </a>
            </li>

            <?php endfor; ?>

            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="#"
                   onclick="event.preventDefault(); <?php if($page < $total_pages){ ?>loadData(<?= $page+1 ?>)<?php } ?>">
                    Next
                </a>
            </li>

        </ul>
    </nav>

    <!-- Range RIGHT -->
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
