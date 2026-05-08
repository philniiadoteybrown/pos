<?php
// include "dbconn.php";

// $pid = mysqli_real_escape_string($conn, $_GET['product_id']);

// $res = mysqli_query($conn,"
//     SELECT unit_name, unit_qty, price
//     FROM units
//     WHERE product_id = '$pid'
// ");

// echo "<table class='table table-sm table-bordered'>";
// echo "<tr><th>Unit</th><th>Qty</th><th>Price</th></tr>";

// while($row = mysqli_fetch_assoc($res)){
//     echo "<tr>
//         <td>{$row['unit_name']}</td>
//         <td>{$row['unit_qty']}</td>
//         <td>GH¢ {$row['price']}</td>
//     </tr>";
// }

// echo "</table>";
?>

<?php
include "dbconn.php";

$pid = mysqli_real_escape_string($conn, $_GET['product_id']);

$res = mysqli_query($conn,"
    SELECT id, unit_name, unit_qty, price
    FROM units
    WHERE product_id = '$pid'
");

echo "<table class='table table-sm table-bordered'>";
echo "<tr>
        <th>Unit</th>
        <th>Qty</th>
        <th>Price</th>
      </tr>";

while($row = mysqli_fetch_assoc($res)){

echo "
<tr data-id='{$row['id']}'>

    <td>
        {$row['unit_name']}
    </td>

    <td>
        {$row['unit_qty']}
    </td>

    <td>
        {$row['price']}
    </td>


</tr>
";
}

echo "</table>";
?>