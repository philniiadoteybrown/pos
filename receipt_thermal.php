<?php
include "assets/scripts/dbconn.php";

$sale_id = $_GET['sale_id'] ?? 0;

if(!$sale_id){
    die("Invalid receipt");
}

// SALE
$sale = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT s.*, c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id='$sale_id'
"));

// ITEMS
$items = mysqli_query($conn,"
    SELECT si.*, p.pdesc
    FROM sales_items si
    LEFT JOIN products p ON si.product_id = p.productid
    WHERE si.sale_id='$sale_id'
");
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Receipt</title>

    <style>
    body {
        font-family: monospace;
        width: 280px;
        /* 58mm printer size */
        margin: 0 auto;
        font-size: 12px;
    }

    .center {
        text-align: center;
    }

    .small {
        font-size: 11px;
    }

    hr {
        border: none;
        border-top: 1px dashed #000;
        margin: 5px 0;
    }

    table {
        width: 100%;
    }

    td {
        font-size: 12px;
        padding: 2px 0;
    }

    .right {
        text-align: right;
    }

    @media print {
        .no-print {
            display: none;
        }

        body {
            margin: 0;
        }
    }
    </style>

</head>

<body onload="window.print()">

    <div class="center">
        <b>PHilynda Enterprice</b><br>
        <span class="small">Tel: 0241-555-511</span><br>
        <span class="small">Loc: Inside Hospital Quarters</span>
    </div>

    <hr>

    <div class="small">
        Receipt #: <?= $sale['id'] ?><br>
        Date: <?= date("d-m-Y H:i", strtotime($sale['created_at'])) ?><br>
        Payment: <?= strtoupper($sale['payment_method']) ?>
    </div>

    <?php if($sale['payment_method'] == 'credit'){ ?>
    <div class="small">
        Customer: <?= $sale['customer_name'] ?? 'N/A' ?>
    </div>
    <?php } ?>

    <hr>

    <table>
        <?php while($i = mysqli_fetch_assoc($items)){ ?>
        <tr>
            <td>
                <?= $i['pname']." (".$i['pdesc'].")" ?>
                <br>
                <span class="small"><?= $i['qty'] ?> x <?= number_format($i['price'],2) ?></span>
            </td>
            <td class="right">
                <?= number_format($i['subtotal'],2) ?>
            </td>
        </tr>
        <?php } ?>
    </table>

    <hr>

    <table>
        <tr>
            <td><b>Total</b></td>
            <td class="right"><b><?= number_format($sale['total'],2) ?></b></td>
        </tr>
        <tr>
            <td>Paid</td>
            <td class="right"><?= number_format($sale['paid'],2) ?></td>
        </tr>
        <tr>
            <td>Balance</td>
            <td class="right"><?= number_format($sale['balance'],2) ?></td>
        </tr>
    </table>

    <hr>

    <div class="center small">
        Thank you for shopping!<br>
        Please come again.
    </div>

    <button class="no-print" onclick="window.print()">Print</button>

</body>

</html>