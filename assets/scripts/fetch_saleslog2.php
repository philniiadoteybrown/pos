<?php
include "dbconn.php";

// ✅ Inputs
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 5;

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

<!-- =========================================================
     SALES HISTORY TABLE
     Desktop: 7 columns
     Mobile: 3 compact columns
     ========================================================= -->
<style>
    .sales-history-desktop {
        width: 100%;
    }

    .sales-history-mobile {
        display: none;
    }

    /* Mobile sales history: exactly 3 columns */
    @media (max-width: 575.98px) {

        .sales-history-desktop {
            display: none !important;
        }

        .sales-history-mobile {
            display: table !important;
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
            margin-bottom: 10px;
        }

        .sales-history-mobile th,
        .sales-history-mobile td {
            border: 1px solid #dee2e6 !important;
            padding: 7px 5px !important;
            vertical-align: top !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
            white-space: normal !important;
        }

        .sales-history-mobile thead th {
            background: #f2f2f2;
            font-size: 11px !important;
            line-height: 1.2;
            font-weight: 700;
            text-align: center;
        }

        .sales-history-mobile tbody td {
            font-size: 11px !important;
            line-height: 1.3;
        }

        /* Product | Amount Paid | Payment */
        .sales-history-mobile th:nth-child(1),
        .sales-history-mobile td:nth-child(1) {
            width: 38% !important;
        }

        .sales-history-mobile th:nth-child(2),
        .sales-history-mobile td:nth-child(2) {
            width: 29% !important;
        }

        .sales-history-mobile th:nth-child(3),
        .sales-history-mobile td:nth-child(3) {
            width: 33% !important;
        }

        .mobile-history-product {
            display: block;
            font-weight: 600;
            line-height: 1.25;
        }

        .mobile-history-label {
            display: block;
            margin-top: 4px;
            font-size: 9px;
            color: #777;
            font-weight: 600;
        }

        .mobile-history-total {
            display: block;
            margin-top: 2px;
            font-weight: 700;
        }

        .mobile-history-value {
            display: block;
            font-weight: 600;
        }

        .mobile-history-date {
            display: block;
            margin-top: 4px;
            font-size: 9px;
            color: #777;
            white-space: nowrap !important;
        }
    }
</style>

<!-- Desktop table -->
<table class="table table-bordered sales-history-desktop" id="prodList">
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
                <td><?php echo htmlspecialchars($fetch['product_names'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($fetch['total'] ?? '0.00'); ?></td>
                <td><?php echo htmlspecialchars($fetch['paid'] ?? '0.00'); ?></td>
                <td><?php echo htmlspecialchars($fetch['balance'] ?? '0.00'); ?></td>
                <td><?php echo htmlspecialchars($fetch['payment_method'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($fetch['customer_name'] ?? 'Walk-in'); ?></td>
                <td><?php echo htmlspecialchars($fetch['created_at'] ?? ''); ?></td>
            </tr>

            <?php } ?>

        <?php } else { ?>

            <tr>
                <td colspan="7">No records found</td>
            </tr>

        <?php } ?>
    </tbody>
</table>

<!-- Mobile table: exactly 3 columns -->
<table class="table table-bordered sales-history-mobile">
    <thead>
        <tr>
            <th>
                Product<br>
                <small>(Total)</small>
            </th>
            <th>
                Amount Paid<br>
                <small>(Balance)</small>
            </th>
            <th>
                Payment Method<br>
                <small>(Creditor) (Date)</small>
            </th>
        </tr>
    </thead>

    <tbody>
        <?php
        /* The desktop loop above has consumed the result set.
           Re-run the same query for the mobile table. */
        $mobile_result = mysqli_query($conn, $query);
        ?>

        <?php if($mobile_result && mysqli_num_rows($mobile_result) > 0){ ?>

            <?php while($fetch = mysqli_fetch_assoc($mobile_result)){ ?>

            <tr>
                <!-- Product + Total -->
                <td>
                    <span class="mobile-history-product">
                        <?php echo htmlspecialchars($fetch['product_names'] ?? ''); ?>
                    </span>

                    <span class="mobile-history-label">Total</span>

                    <span class="mobile-history-total">
                        GH¢ <?php echo htmlspecialchars($fetch['total'] ?? '0.00'); ?>
                    </span>
                </td>

                <!-- Paid + Balance -->
                <td>
                    <span class="mobile-history-label">Paid</span>
                    <span class="mobile-history-value">
                        GH¢ <?php echo htmlspecialchars($fetch['paid'] ?? '0.00'); ?>
                    </span>

                    <span class="mobile-history-label">Balance</span>
                    <span class="mobile-history-value">
                        GH¢ <?php echo htmlspecialchars($fetch['balance'] ?? '0.00'); ?>
                    </span>
                </td>

                <!-- Payment + Creditor + Date -->
                <td>
                    <span class="mobile-history-value">
                        <?php echo htmlspecialchars($fetch['payment_method'] ?? ''); ?>
                    </span>

                    <span class="mobile-history-label">Creditor</span>
                    <span class="mobile-history-value">
                        <?php echo htmlspecialchars($fetch['customer_name'] ?? 'Walk-in'); ?>
                    </span>

                    <span class="mobile-history-date">
                        <?php
                        $saleDate = $fetch['created_at'] ?? '';
                        $timestamp = strtotime($saleDate);
                        echo $timestamp ? date('d/m/Y', $timestamp) : htmlspecialchars($saleDate);
                        ?>
                    </span>
                </td>
            </tr>

            <?php } ?>

        <?php } else { ?>

            <tr>
                <td colspan="3">No records found</td>
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