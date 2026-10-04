<?php

include "dbconn.php";


// ==========================================
// INPUTS
// ==========================================

$search = isset($_GET['search'])
    ? mysqli_real_escape_string($conn, trim($_GET['search']))
    : '';

$page = isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;

$limit = isset($_GET['limit'])
    ? (int)$_GET['limit']
    : 10;


// ==========================================
// VALIDATE PAGINATION
// ==========================================

if ($page <= 0) {
    $page = 1;
}

if ($limit <= 0) {
    $limit = 10;
}


// ==========================================
// OFFSET
// ==========================================

$offset = ($page - 1) * $limit;


// ==========================================
// SEARCH CONDITION
// ==========================================

$where = "WHERE 1=1";


if ($search != "") {

    $where .= "
        AND (
            products.pname LIKE '%$search%'
            OR products.productid LIKE '%$search%'
            OR units.unit_name LIKE '%$search%'
        )
    ";

}


// ==========================================
// TOTAL RECORDS
// ==========================================

$totalRes = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total

    FROM units

    LEFT JOIN products
        ON units.product_id = products.productid

    $where
    "
);


if (!$totalRes) {

    echo "
        <div class='alert alert-danger'>
            Database error: " . htmlspecialchars(mysqli_error($conn)) . "
        </div>
    ";

    exit;
}


$totalRow = mysqli_fetch_assoc($totalRes);

$total = (int)$totalRow['total'];


// ==========================================
// TOTAL PAGES
// ==========================================

$total_pages = ($limit > 0)
    ? ceil($total / $limit)
    : 1;


if ($total_pages < 1) {
    $total_pages = 1;
}


// ==========================================
// IF PAGE IS TOO HIGH
// ==========================================

if ($page > $total_pages && $total_pages > 0) {

    $page = $total_pages;

    $offset = ($page - 1) * $limit;

}


// ==========================================
// FETCH UNITS
// ==========================================

$res = mysqli_query(
    $conn,
    "
    SELECT
        units.id,
        units.product_id,
        units.unit_name,
        units.unit_qty,
        units.price,

        products.pname,
        products.pdesc

    FROM units

    LEFT JOIN products
        ON units.product_id = products.productid

    $where

    ORDER BY units.id DESC

    LIMIT $offset, $limit
    "
);


if (!$res) {

    echo "
        <div class='alert alert-danger'>
            Database error: " . htmlspecialchars(mysqli_error($conn)) . "
        </div>
    ";

    exit;
}

?>



<!-- ==========================================
     TABLE
========================================== -->

<div class="table-responsive">

    <table class="table table-bordered table-hover">

        <thead>

            <tr>

                <th>Product</th>

                <th>Unit</th>

                <th>Qty Per Unit</th>

                <th>Price (GH¢)</th>

                <th width="120">Action</th>

            </tr>

        </thead>


        <tbody>

            <?php if (mysqli_num_rows($res) > 0) { ?>


                <?php while ($u = mysqli_fetch_assoc($res)) { ?>

                    <tr>

                        <!-- PRODUCT -->

                        <td>

                            <strong>
                                <?= htmlspecialchars($u['product_id']) ?>
                            </strong>

                            -

                            <?= htmlspecialchars($u['pname']) ?>

                            <?php if (!empty($u['pdesc'])) { ?>

                                <em class="text-muted">

                                    (
                                    <?= htmlspecialchars($u['pdesc']) ?>
                                    )

                                </em>

                            <?php } ?>

                        </td>


                        <!-- UNIT -->

                        <td>

                            <?= htmlspecialchars($u['unit_name']) ?>

                        </td>


                        <!-- QTY -->

                        <td>

                            <?= htmlspecialchars($u['unit_qty']) ?>

                        </td>


                        <!-- PRICE -->

                        <td>

                            <?= number_format((float)$u['price'], 2) ?>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <!-- EDIT -->

                            <a
                                href="edit_units.php?id=<?= urlencode($u['id']) ?>"
                                title="Edit"
                                class="btn btn-warning btn-animation btn-sm"
                            >

                                <span class="fa fa-edit"></span>

                            </a>


                            <!-- DELETE -->

                            <a
                                href="del_unit.php?id=<?= urlencode($u['id']) ?>"
                                title="Delete"
                                class="btn btn-danger btn-animation btn-sm"
                                onclick="return confirm('Are you sure you want to delete this unit?');"
                            >

                                <span class="fa fa-remove"></span>

                            </a>

                        </td>

                    </tr>

                <?php } ?>


            <?php } else { ?>

                <tr>

                    <td
                        colspan="5"
                        class="text-center text-muted p-4"
                    >

                        No product units found.

                    </td>

                </tr>

            <?php } ?>

        </tbody>

    </table>

</div>



<!-- ==========================================
     PAGINATION
========================================== -->

<?php if ($total > 0) { ?>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">


    <!-- PAGINATION -->

    <nav aria-label="Page navigation">

        <ul class="pagination mb-0">


            <!-- PREVIOUS -->

            <li
                class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"
            >

                <a
                    class="page-link"
                    href="#"
                    onclick="
                        event.preventDefault();

                        <?php if ($page > 1) { ?>

                            loadData(<?= $page - 1 ?>);

                        <?php } ?>
                    "
                >

                    Previous

                </a>

            </li>


            <!-- PAGE NUMBERS -->

            <?php

            $start = max(1, $page - 2);

            $end = min($total_pages, $page + 2);

            for ($i = $start; $i <= $end; $i++):

            ?>

                <li
                    class="page-item <?= ($i == $page) ? 'active' : '' ?>"
                >

                    <a
                        class="page-link"
                        href="#"
                        onclick="
                            event.preventDefault();
                            loadData(<?= $i ?>);
                        "
                    >

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>


            <!-- NEXT -->

            <li
                class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"
            >

                <a
                    class="page-link"
                    href="#"
                    onclick="
                        event.preventDefault();

                        <?php if ($page < $total_pages) { ?>

                            loadData(<?= $page + 1 ?>);

                        <?php } ?>
                    "
                >

                    Next

                </a>

            </li>


        </ul>

    </nav>



    <!-- RANGE -->

    <div class="text-muted mt-2">

        <?php

        $startRow = $offset + 1;

        $endRow = min(
            $offset + $limit,
            $total
        );

        echo "Showing $startRow to $endRow of $total entries";

        ?>

    </div>


</div>

<?php } ?>