<?php
include "dbconn.php";

$q = mysqli_real_escape_string($conn, $_GET['q'] ?? '');

$res = mysqli_query($conn,"
    SELECT *, (totalstock <= qtyalert) AS is_low
    FROM products
    WHERE pname LIKE '%$q%'
    ORDER BY totalstock ASC
");

while($row = mysqli_fetch_assoc($res)){

    $pid   = $row['productid'];
    $name  = htmlspecialchars($row['pname'], ENT_QUOTES);
    $desc  = htmlspecialchars($row['pdesc']);
    $stock = (float)$row['totalstock'];

    // 🔥 GET UNITS
    $units_res = mysqli_query($conn,"
        SELECT unit_name, unit_qty, price 
        FROM units 
        WHERE product_id = '$pid'
        ORDER BY unit_qty ASC
    ");

    $units = [];
    while($u = mysqli_fetch_assoc($units_res)){
        $stock    = (float)$row['totalstock'];
$perUnit  = (float)$row['qtyperunit'];

$full_units = ($perUnit > 0) ? floor($stock / $perUnit) : 0;
$remaining  = ($perUnit > 0) ? ($stock % $perUnit) : $stock;

if($stock <= 0){
    $stockLabel = "❌ Out of stock";
} elseif($perUnit > 0){
    $stockLabel = "{$full_units} {$row['unit']} + {$remaining} pcs";
} else {
    $stockLabel = "{$stock} pcs";
}

$isLow = ($stock <= $row['qtyalert']);

$style = "";

if($stock <= 0){
    $style = "color:red; font-weight:bold; cursor:not-allowed; opacity:0.6;";
} elseif($isLow){
    $style = "color:orange; font-weight:bold;";
}

        $units[] = $u;
    }

    // base price (first unit = Piece)
    $base_price = $units[0]['price'] ?? 0;

    $isOut = ($stock <= 0);

    $style = $isOut 
        ? "opacity:0.5; background:#f8d7da; cursor:not-allowed;" 
        : "cursor:pointer;";

   echo "
<div class='search-item' 
     data-id='{$row['productid']}'
     data-name='{$row['pname']}'
     data-price='{$row['sellingprice']}'
     style='$style'>

    <strong>{$row['pname']}</strong> 
    <em>{$row['pdesc']}</em> | 
    <br>
    <small>Stock: $stockLabel</small>
</div>
";

    // echo "<strong>{$name}</strong><br>";
    // echo " <small>{$desc}</small><br>";

    // ✅ SHOW ALL UNIT PRICES
    foreach($units as $u){
        $uname = htmlspecialchars($u['unit_name']);
        $price = number_format($u['price'],2);

        echo " {<span><strong>{$uname}:</strong> GH¢{$price}</span><br> }";
    }

    // ✅ SIMPLE STOCK DISPLAY (pieces only now)
    if($isOut){
        echo "<span style='color:red; font-size:12px; font-weight:bold;'>
                OUT OF STOCK
              </span>";
    } else {
        echo "<span style='color:green; font-size:12px;'>
                Stock: {$stock} pcs
              </span>";
    }

    echo "</div>";
}
?>