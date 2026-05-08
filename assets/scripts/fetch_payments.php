<?php
include "dbconn.php";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;

/* ===================== SEARCH ===================== */
$where = "WHERE 1=1";

if($search != ""){
    $where .= " AND (
        c.name LIKE '%$search%' 
        OR c.phone LIKE '%$search%'
        OR cp.id LIKE '%$search%'
    )";
}

/* ===================== TOTAL ROWS ===================== */
$totalRes = mysqli_query($conn,"
SELECT COUNT(*) as total
FROM customer_payments cp
JOIN customers c ON cp.customer_id = c.id
$where
");

$totalRow = mysqli_fetch_assoc($totalRes);
$total = $totalRow['total'];

$total_pages = ceil($total / $limit);

/* ===================== FETCH DATA ===================== */
$query = "
SELECT 
    cp.id,
    cp.customer_id,
    cp.amount,
    cp.created_at,
    c.name,
    c.phone
FROM customer_payments cp
JOIN customers c ON cp.customer_id = c.id
$where
ORDER BY cp.id DESC
LIMIT $offset,$limit
";

$search_result = mysqli_query($conn,$query);
?>

<table class="table table-bordered" id="prodList">
    <thead>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Amount Paid</th>
            <th>Last Payment</th>
        </tr>
    </thead>

    <tbody>
        <?php if(mysqli_num_rows($search_result) > 0){ ?>
        <?php while($fetch = mysqli_fetch_assoc($search_result)){ ?>

        <tr>
            <td><?= $fetch['name'] ?></td>
            <td><?= $fetch['phone'] ?></td>

            <td><?= $fetch['amount'] ?? 'N/A' ?></td>
            <td><?= $fetch['created_at'] ?? 'N/A' ?></td>


        </tr>

        <?php } ?>
        <?php } else { ?>

        <tr>
            <td colspan="5" class="text-center">No records found</td>
        </tr>

        <?php } ?>
    </tbody>
</table>

<!-- BACKDROP -->
<div id="modalBackdrop" class="bp-backdrop" onclick="closePayModal()"></div>

<!-- MODAL -->
<div id="payModal" class="bp-modal">

    <div class="bp-header">
        <h3>Customer Payment</h3>
        <button onclick="closePayModal()">✖</button>
    </div>

    <div class="bp-body">

        <input type="hidden" id="cust_id">

        <p><strong>Name:</strong> <span id="cust_name"></span></p>
        <p><strong>Balance:</strong> GHS <span id="cust_balance"></span></p>

        <label>Amount to Pay</label>
        <input type="number" step="0.01" id="amount" class="bp-input">

    </div>

    <div class="bp-footer">
        <button onclick="submitPayment()" class="bp-btn">Submit Payment</button>
    </div>

</div>

<script>
function openPaymentWindow(id, name, balance) {

    let url = "payment_window.php?id=" + id +
        "&name=" + encodeURIComponent(name) +
        "&balance=" + balance;

    window.open(
        url,
        "PaymentWindow",
        "width=500,height=500,top=100,left=400"
    );
}
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