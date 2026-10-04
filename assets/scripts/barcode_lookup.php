<?php
header('Content-Type: application/json; charset=utf-8');

include "dbconn.php";

$barcode = trim($_GET['barcode'] ?? '');

if ($barcode === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Barcode is required.'
    ]);
    exit;
}

$barcodeEsc = mysqli_real_escape_string($conn, $barcode);

$sql = "
    SELECT
        productid,
        barcode,
        pname,
        sellingprice,
        totalstock,
        qtyperunit
    FROM products
    WHERE barcode = '$barcodeEsc'
       OR productid = '$barcodeEsc'
    LIMIT 1
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error.'
    ]);
    exit;
}

$product = mysqli_fetch_assoc($result);

if (!$product) {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found.'
    ]);
    exit;
}

if ((float)$product['totalstock'] <= 0) {
    echo json_encode([
        'success' => false,
        'out_of_stock' => true,
        'message' => 'Product is out of stock.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'product' => [
        'productid' => $product['productid'],
        'barcode' => $product['barcode'],
        'pname' => $product['pname'],
        'sellingprice' => $product['sellingprice'],
        'totalstock' => $product['totalstock'],
        'qtyperunit' => $product['qtyperunit']
    ]
]);
