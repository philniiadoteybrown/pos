<?php
include "dbconn.php";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$page   = $_GET['page'] ?? 1;
$limit  = $_GET['limit'] ?? 10;

$offset = ($page-1)*$limit;

$where = "WHERE 1";

if($search != ""){
    $where .= " AND (
        products.pname LIKE '%$search%' 
        OR units.unit_name LIKE '%$search%'
    )";
}

// total rows
$totalRes = mysqli_query($conn,"
    SELECT COUNT(*) as total 
    FROM units
    LEFT JOIN products 
        ON units.product_id = products.productid
    $where
");
$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

$res = mysqli_query($conn,"
    SELECT units.*, products.pname, products.pdesc
    FROM units
    LEFT JOIN products 
        ON units.product_id = products.productid
    $where
    ORDER BY units.id DESC
    LIMIT $offset,$limit
");
?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Name</th>
            <th>Unit</th>
            <th>Qty Per Unit</th>
            <th>Price (GH¢)</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
        <?php while($u = mysqli_fetch_assoc($res)){ ?>
        <tr>
            <td><?= $u['product_id']." - ". $u['pname']." <em>(". $u['pdesc'] .")</em>"  ?></td>
            <td><?= $u['unit_name'] ?></td>
            <td><?= $u['unit_qty'] ?></td>
            <td><?= $u['price'] ?></td>
            <td>

                <a href="edit_unit.php?id=<?php echo $u['id']; ?>" title="Edit"
                    class="btn btn-warning btn-animation btn-sm"> <span class="fa fa-edit"></span>
                </a>
                <!-- <button onclick="confirmDelete(<?= $u['id'] ?>)" class="btn btn-danger btn-sm">
                    <span class="fa fa-remove"></span>
                </button> -->
                <a href="del_unit.php?id=<?php echo $u['id']; ?>" title="Delete"
                    onclick="confirmDelete(<?= $u['id'] ?>)" class="btn btn-danger btn-animation btn-sm"> <span
                        class="fa fa-remove"></span>
                </a>

            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<script>

</script>

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