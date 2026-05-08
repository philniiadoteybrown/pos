<?php
include "dbconn.php";

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$where = "WHERE 1";

if($search != ""){
    $s = mysqli_real_escape_string($conn,$search);
    $where .= " AND (pname LIKE '%$s%' OR productid LIKE '%$s%')";
}

if($category != ""){
    $c = mysqli_real_escape_string($conn,$category);
    $where .= " AND category='$c'";
}

$res = mysqli_query($conn,"
    SELECT * FROM products
    $where
    ORDER BY pname ASC
");

while($row = mysqli_fetch_assoc($res)){
?>

<tr>
    <td>
        <?= htmlspecialchars($row['pname']."-".$row['productid']) ?>
        <input type="hidden" name="product_id[]" value="<?= $row['productid'] ?>">
    </td>

    <td>
        <input type="number" class="form-control system" value="<?= $row['totalstock'] ?>" readonly>
    </td>

    <td>
        <div style="display:flex; gap:10px;">
            <input type="number" class="form-control physical" name="physical_qty[]" oninput="calcSmart(this)">

            <button type="button" class="btn btn-sm btn-info" onclick="copySystem(this)">
                <span class="fa fa-copy"></span>
            </button>
        </div>
    </td>

    <td>
        <input type="text" class="form-control diff" readonly>
    </td>

    <td class="status">OK</td>
</tr>

<?php } ?>