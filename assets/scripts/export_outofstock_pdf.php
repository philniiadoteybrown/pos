<?php
include "dbconn.php";

$search   = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, trim($_GET['category'])) : '';

$enteredQuantities = [];
if (!empty($_GET['quantities'])) {
    $encoded = strtr($_GET['quantities'], '-_', '+/');
    $padding = strlen($encoded) % 4;
    if ($padding) $encoded .= str_repeat('=', 4 - $padding);
    $decoded = base64_decode($encoded, true);
    if ($decoded !== false) {
        $parsed = json_decode($decoded, true);
        if (is_array($parsed)) {
            foreach ($parsed as $productId => $qty) {
                $enteredQuantities[(string)$productId] = max(0, (float)$qty);
            }
        }
    }
}

$where = "WHERE totalstock < 3";
if ($search !== '') {
    $where .= " AND (pname LIKE '%$search%' OR productid LIKE '%$search%' OR pdesc LIKE '%$search%' OR category LIKE '%$search%')";
}
if ($category !== '') $where .= " AND category = '$category'";

$sql = "SELECT productid, pname, pdesc, totalstock, unitprice, qtyperunit, unit FROM products $where ORDER BY pname ASC";
$res = mysqli_query($conn, $sql);
if (!$res) die('Database Error: ' . mysqli_error($conn));

$rows = [];
$grandTotal = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $id = (string)$row['productid'];
    $qty = $enteredQuantities[$id] ?? 0;
    $price = (float)($row['unitprice'] ?? 0);
    $subtotal = $qty * $price;
    $row['entered_qty'] = $qty;
    $row['subtotal'] = $subtotal;
    $grandTotal += $subtotal;
    $rows[] = $row;
}

function pdfEscape($text) {
    $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$text);
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
}
function pdfNumber($value) { return number_format((float)$value, 2, '.', ','); }
function makePdf($rows, $grandTotal) {
    $W = 841.89; $H = 595.28;
    $left = 24; $right = 24; $top = 34; $bottom = 34;
    $rowH = 18; $headerH = 24;
    $widths = [72, 220, 72, 58, 55, 90, 65, 115];
    $headers = ['Product ID','Product Name (Description)','Unit Price','Stock','Qty','Quantity per Unit','Unit','Subtotal'];
    $tableW = array_sum($widths);
    $rowsPerPage = 25;
    $chunks = array_chunk($rows, $rowsPerPage);
    if (!$chunks) $chunks = [[]];
    $pageCount = count($chunks);

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = ''; // pages object filled later
    $fontObj = 3 + ($pageCount * 2);
    $kids = [];

    foreach ($chunks as $pageIndex => $pageRows) {
        $pageObj = count($objects) + 1;
        $contentObj = $pageObj + 1;
        $kids[] = $pageObj . ' 0 R';

        $commands = [];
        $commands[] = "BT /F1 16 Tf {$left} " . ($H - $top) . " Td (Out of Stock) Tj ET";
        $commands[] = "BT /F1 8 Tf {$left} " . ($H - $top - 20) . " Td (Products with stock below 3) Tj ET";
        $yTop = $H - $top - 42;

        $commands[] = sprintf("q 0.90 0.90 0.90 rg %.2f %.2f %.2f %.2f re f Q", $left, $yTop-$headerH, $tableW, $headerH);
        $tableHeight = $headerH + count($pageRows) * $rowH;
        $bottomY = $yTop - $tableHeight;
        $commands[] = sprintf("0.5 w 0 0 0 RG %.2f %.2f %.2f %.2f re S", $left, $bottomY, $tableW, $tableHeight);

        $x = $left;
        foreach ($widths as $w) {
            $commands[] = sprintf("0.5 w %.2f %.2f m %.2f %.2f l S", $x, $bottomY, $x, $yTop);
            $x += $w;
        }
        $commands[] = sprintf("0.5 w %.2f %.2f m %.2f %.2f l S", $x, $bottomY, $x, $yTop);
        for ($i=0; $i <= count($pageRows); $i++) {
            $yy = $yTop - $headerH - ($i * $rowH);
            $commands[] = sprintf("0.5 w %.2f %.2f m %.2f %.2f l S", $left, $yy, $left+$tableW, $yy);
        }

        $x = $left;
        foreach ($headers as $i=>$h) {
            $commands[] = sprintf("BT /F1 7 Tf %.2f %.2f Td (%s) Tj ET", $x+4, $yTop-16, pdfEscape($h));
            $x += $widths[$i];
        }

        foreach ($pageRows as $ri=>$row) {
            $y = $yTop - $headerH - ($ri*$rowH) - 13;
            $desc = trim((string)($row['pdesc'] ?? ''));
            $name = trim((string)($row['pname'] ?? ''));
            $nameDesc = $name . ($desc !== '' ? ' - '.$desc : '');
            $values = [
                $row['productid'], $nameDesc, pdfNumber($row['unitprice']), pdfNumber($row['totalstock']),
                pdfNumber($row['entered_qty']), pdfNumber($row['qtyperunit']), $row['unit'] ?? '', pdfNumber($row['subtotal'])
            ];
            $x = $left;
            foreach ($values as $i=>$value) {
                $display = (string)$value;
                $maxChars = ($i === 1) ? 40 : 18;
                if (strlen($display) > $maxChars) $display = substr($display, 0, $maxChars-3).'...';
                $commands[] = sprintf("BT /F1 7 Tf %.2f %.2f Td (%s) Tj ET", $x+4, $y, pdfEscape($display));
                $x += $widths[$i];
            }
        }

        $commands[] = sprintf("BT /F1 8 Tf %.2f %.2f Td (%s) Tj ET", $left, 15, pdfEscape('Grand Total: GHc '.pdfNumber($grandTotal)));
        $commands[] = sprintf("BT /F1 8 Tf %.2f %.2f Td (%s) Tj ET", $W-105, 15, pdfEscape('Page '.($pageIndex+1).' of '.$pageCount));
        $stream = implode("\n", $commands);

        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$W.' '.$H.'] /Resources << /Font << /F1 '.$fontObj.' 0 R >> >> /Contents '.$contentObj.' 0 R >>';
        $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $i=>$obj) {
        $num = $i+1;
        $offsets[$num] = strlen($pdf);
        $pdf .= $num." 0 obj\n".$obj."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    for ($i=1; $i<=count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $pdf .= "trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    return $pdf;
}

$pdf = makePdf($rows, $grandTotal);
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'download';
header('Content-Type: application/pdf');
header('Content-Disposition: '.($mode === 'preview' ? 'inline' : 'attachment').'; filename="out_of_stock.pdf"');
header('Content-Length: '.strlen($pdf));
echo $pdf;
exit;
