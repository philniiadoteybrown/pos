<?php

/*
 * Budget PDF export
 * Location: assets/scripts/export_budget_pdf.php
 */

require_once __DIR__ . '/dbconn.php';


/* =========================================================
   READ AND DECODE ROWS FROM REQUEST
========================================================= */

$rowsInput = [];


if (isset($_GET['rows']) && $_GET['rows'] !== '') {

    $encoded = strtr(
        (string)$_GET['rows'],
        '-_',
        '+/'
    );


    $remainder = strlen($encoded) % 4;


    if ($remainder !== 0) {

        $encoded .= str_repeat(
            '=',
            4 - $remainder
        );

    }


    $decoded = base64_decode(
        $encoded,
        true
    );


    if ($decoded !== false) {

        $parsed = json_decode(
            $decoded,
            true
        );


        if (is_array($parsed)) {

            $rowsInput = $parsed;

        }

    }

}


/* =========================================================
   LOAD PRODUCTS
========================================================= */

$rows = [];

$grandTotal = 0.0;


foreach ($rowsInput as $item) {

    if (
        !is_array($item) ||
        !isset($item['productid']) ||
        (string)$item['productid'] === ''
    ) {

        continue;

    }


    $productId =
        mysqli_real_escape_string(
            $conn,
            (string)$item['productid']
        );


    $quantity =
        isset($item['quantity'])
            ? (float)$item['quantity']
            : 0.0;


    if ($quantity < 0) {

        $quantity = 0.0;

    }


    /*
     * Category is loaded directly from the database.
     * This means the PDF always uses the current
     * category assigned to the product.
     */

    $sql = "
        SELECT
            productid,
            pname,
            pdesc,
            qtyperunit,
            unitprice,
            unit,
            category
        FROM products
        WHERE productid = '$productId'
        LIMIT 1
    ";


    $result =
        mysqli_query(
            $conn,
            $sql
        );


    if (
        !$result ||
        mysqli_num_rows($result) === 0
    ) {

        continue;

    }


    $product =
        mysqli_fetch_assoc($result);


    $unitPrice =
        (float)($product['unitprice'] ?? 0);


    $subtotal =
        $quantity * $unitPrice;


    $product['entered_qty'] =
        $quantity;


    $product['subtotal'] =
        $subtotal;


    /*
     * Use an explicit fallback when a product
     * has no category.
     */

    $category =
        trim(
            (string)($product['category'] ?? '')
        );


    if ($category === '') {

        $category = 'Uncategorized';

    }


    $product['pdf_category'] =
        $category;


    $rows[] =
        $product;


    $grandTotal +=
        $subtotal;

}


/* =========================================================
   SORT PRODUCTS BY CATEGORY THEN PRODUCT NAME
========================================================= */

usort(
    $rows,
    function ($a, $b) {

        $categoryA =
            strtolower(
                trim(
                    (string)($a['pdf_category'] ?? '')
                )
            );


        $categoryB =
            strtolower(
                trim(
                    (string)($b['pdf_category'] ?? '')
                )
            );


        if ($categoryA !== $categoryB) {

            return strnatcasecmp(
                $categoryA,
                $categoryB
            );

        }


        $nameA =
            strtolower(
                trim(
                    (string)($a['pname'] ?? '')
                )
            );


        $nameB =
            strtolower(
                trim(
                    (string)($b['pname'] ?? '')
                )
            );


        return strnatcasecmp(
            $nameA,
            $nameB
        );

    }
);


/* =========================================================
   ESCAPE TEXT FOR PDF
========================================================= */

function pdfEscape($text)
{

    $text =
        (string)$text;


    $converted =
        @iconv(
            'UTF-8',
            'Windows-1252//TRANSLIT//IGNORE',
            $text
        );


    if ($converted !== false) {

        $text = $converted;

    }


    return str_replace(
        [
            '\\',
            '(',
            ')',
            "\r",
            "\n"
        ],
        [
            '\\\\',
            '\\(',
            '\\)',
            ' ',
            ' '
        ],
        $text
    );

}


/* =========================================================
   FORMAT MONEY
========================================================= */

function pdfMoney($value)
{

    return number_format(
        (float)$value,
        2,
        '.',
        ','
    );

}


/* =========================================================
   ADD TEXT COMMAND
========================================================= */

function pdfText(
    $commands,
    $x,
    $y,
    $fontSize,
    $text
) {

    $commands[] =
        sprintf(
            "BT /F1 %d Tf %.2f %.2f Td (%s) Tj ET",
            $fontSize,
            $x,
            $y,
            pdfEscape($text)
        );


    return $commands;

}


/* =========================================================
   CREATE BUDGET PDF
========================================================= */

function makeBudgetPdf(
    array $rows,
    $grandTotal
) {

    /*
     * A4 landscape
     */

    $pageWidth = 841.89;
    $pageHeight = 595.28;


    $left = 30;
    $right = 30;
    $top = 35;
    $bottom = 35;


    $tableWidth =
        $pageWidth -
        $left -
        $right;


    $headerHeight = 26;

    $categoryHeight = 22;

    $rowHeight = 21;


    /*
     * Table columns:
     *
     * Product
     * Unit Price
     * Quantity per Unit
     * Quantity
     * Sub Total
     */

    $widths = [
        350,
        100,
        135,
        75,
        $tableWidth - 350 - 100 - 135 - 75
    ];


    $headers = [
        'Product Name (Description)',
        'Unit Price',
        'Quantity per Unit',
        'Quantity',
        'Sub Total'
    ];


    /*
     * Build category groups.
     */

    $groups = [];


    foreach ($rows as $row) {

        $category =
            trim(
                (string)(
                    $row['pdf_category']
                    ?? 'Uncategorized'
                )
            );


        if ($category === '') {

            $category = 'Uncategorized';

        }


        if (!isset($groups[$category])) {

            $groups[$category] = [];

        }


        $groups[$category][] =
            $row;

    }


    /*
     * Flatten the grouped data into printable
     * elements.
     *
     * Each element is either:
     *
     * category
     * row
     */

    $elements = [];


    foreach ($groups as $category => $categoryRows) {

        $elements[] = [
            'type' => 'category',
            'category' => $category
        ];


        foreach ($categoryRows as $row) {

            $elements[] = [
                'type' => 'row',
                'data' => $row
            ];

        }

    }


    /*
     * Calculate how many printable elements
     * can fit on each page.
     *
     * Reserve room for:
     *
     * - Title
     * - Table
     * - Grand total
     * - Page number
     */

    $availableHeight =
        $pageHeight -
        $top -
        $bottom -
        80;


    $rowsPerPage =
        max(
            1,
            (int)floor(
                (
                    $availableHeight -
                    $headerHeight
                ) /
                $rowHeight
            )
        );


    /*
     * Create page chunks.
     */

    $chunks = [];


    $currentChunk = [];

    $currentHeight = 0;


    foreach ($elements as $element) {

        $elementHeight =
            $element['type'] === 'category'
                ? $categoryHeight
                : $rowHeight;


        /*
         * Start a new page if this element
         * will not fit.
         */

        if (
            !empty($currentChunk) &&
            (
                $currentHeight +
                $elementHeight
            ) > (
                $availableHeight -
                $headerHeight
            )
        ) {

            $chunks[] =
                $currentChunk;


            $currentChunk = [];

            $currentHeight = 0;

        }


        $currentChunk[] =
            $element;


        $currentHeight +=
            $elementHeight;

    }


    if (!empty($currentChunk)) {

        $chunks[] =
            $currentChunk;

    }


    /*
     * If there are no products, still produce
     * one PDF page.
     */

    if (empty($chunks)) {

        $chunks = [[]];

    }


    $pageCount =
        count($chunks);


    /*
     * PDF objects:
     *
     * catalog = 1
     * pages   = 2
     * then page/content pairs
     * then font
     */

    $objects = [];


    $objects[1] =
        '<< /Type /Catalog /Pages 2 0 R >>';


    $objects[2] = '';


    $kids = [];

    $pageObjectNumbers = [];

    $contentObjectNumbers = [];


    $nextObject = 3;


    /* =====================================================
       BUILD EACH PAGE
    ===================================================== */

    foreach (
        $chunks as $pageIndex => $pageElements
    ) {

        $pageObject =
            $nextObject++;


        $contentObject =
            $nextObject++;


        $pageObjectNumbers[] =
            $pageObject;


        $contentObjectNumbers[] =
            $contentObject;


        $kids[] =
            $pageObject . ' 0 R';


        $commands = [];


        /*
         * Title
         */

        $commands =
            pdfText(
                $commands,
                $left,
                $pageHeight - $top,
                18,
                'Budget'
            );


        $commands =
            pdfText(
                $commands,
                $left,
                $pageHeight - $top - 20,
                8,
                'Prepared Budget'
            );


        /*
         * Table starts below title.
         */

        $tableTop =
            $pageHeight -
            $top -
            42;


        /*
         * Calculate table height.
         */

        $tableContentHeight = 0;


        foreach ($pageElements as $element) {

            $tableContentHeight +=
                $element['type'] === 'category'
                    ? $categoryHeight
                    : $rowHeight;

        }


        $tableHeight =
            $headerHeight +
            $tableContentHeight;


        $tableBottom =
            $tableTop -
            $tableHeight;


        /*
         * Header background.
         */

        $commands[] =
            sprintf(
                "q 0.90 0.90 0.90 rg %.2f %.2f %.2f %.2f re f Q",
                $left,
                $tableTop - $headerHeight,
                $tableWidth,
                $headerHeight
            );


        /*
         * Outer border.
         */

        $commands[] =
            sprintf(
                "0.6 w 0 0 0 RG %.2f %.2f %.2f %.2f re S",
                $left,
                $tableBottom,
                $tableWidth,
                $tableHeight
            );


        /*
         * Vertical lines.
         */

        $x = $left;


        foreach ($widths as $width) {

            $commands[] =
                sprintf(
                    "0.5 w %.2f %.2f m %.2f %.2f l S",
                    $x,
                    $tableBottom,
                    $x,
                    $tableTop
                );


            $x += $width;

        }


        /*
         * Final vertical line.
         */

        $commands[] =
            sprintf(
                "0.5 w %.2f %.2f m %.2f %.2f l S",
                $x,
                $tableBottom,
                $x,
                $tableTop
            );


        /*
         * Header text.
         */

        $x = $left;


        foreach (
            $headers as $index => $header
        ) {

            $commands =
                pdfText(
                    $commands,
                    $x + 5,
                    $tableTop - 17,
                    8,
                    $header
                );


            $x +=
                $widths[$index];

        }


        /*
         * Row/category rendering.
         */

        $currentY =
            $tableTop -
            $headerHeight;


        foreach (
            $pageElements as $element
        ) {

            /*
             * ---------------------------------------------
             * CATEGORY HEADER
             * ---------------------------------------------
             */

            if (
                $element['type'] === 'category'
            ) {

                $category =
                    (string)(
                        $element['category']
                        ?? 'Uncategorized'
                    );


                $categoryY =
                    $currentY -
                    $categoryHeight;


                /*
                 * Light category background.
                 */

                $commands[] =
                    sprintf(
                        "q 0.96 0.96 0.96 rg %.2f %.2f %.2f %.2f re f Q",
                        $left,
                        $categoryY,
                        $tableWidth,
                        $categoryHeight
                    );


                /*
                 * Horizontal line.
                 */

                $commands[] =
                    sprintf(
                        "0.5 w %.2f %.2f m %.2f %.2f l S",
                        $left,
                        $categoryY,
                        $left + $tableWidth,
                        $categoryY
                    );


                /*
                 * Category label.
                 */

                $commands =
                    pdfText(
                        $commands,
                        $left + 5,
                        $categoryY + 7,
                        9,
                        strtoupper($category)
                    );


                $currentY =
                    $categoryY;


                continue;

            }


            /*
             * ---------------------------------------------
             * PRODUCT ROW
             * ---------------------------------------------
             */

            $row =
                $element['data'];


            $rowBottom =
                $currentY -
                $rowHeight;


            /*
             * Horizontal line.
             */

            $commands[] =
                sprintf(
                    "0.5 w %.2f %.2f m %.2f %.2f l S",
                    $left,
                    $rowBottom,
                    $left + $tableWidth,
                    $rowBottom
                );


            /*
             * Text baseline.
             */

            $y =
                $rowBottom + 7;


            /*
             * Product name.
             */

            $name =
                trim(
                    (string)(
                        $row['pname']
                        ?? ''
                    )
                );


            /*
             * Product description.
             */

            $description =
                trim(
                    (string)(
                        $row['pdesc']
                        ?? ''
                    )
                );


            $nameDescription =
                $name;


            if (
                $description !== ''
            ) {

                $nameDescription .=
                    ' - ' .
                    $description;

            }


            /*
             * Quantity per unit.
             */

            $qpu =
                (string)(
                    $row['qtyperunit']
                    ?? ''
                );


            $unit =
                trim(
                    (string)(
                        $row['unit']
                        ?? ''
                    )
                );


            if ($unit !== '') {

                $qpu .=
                    ' ' .
                    $unit;

            }


            if ($qpu === '') {

                $qpu = '-';

            }


            /*
             * Values.
             */

            $values = [

                $nameDescription,

                'GHc ' .
                pdfMoney(
                    $row['unitprice'] ?? 0
                ),

                $qpu,

                number_format(
                    (float)(
                        $row['entered_qty']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

                'GHc ' .
                pdfMoney(
                    $row['subtotal'] ?? 0
                )

            ];


            /*
             * Draw row values.
             */

            $x = $left;


            foreach (
                $values as $columnIndex => $value
            ) {

                $display =
                    (string)$value;


                /*
                 * Truncate long text so it
                 * does not overflow columns.
                 */

                $maxLength =
                    ($columnIndex === 0)
                        ? 58
                        : 22;


                if (
                    strlen($display) >
                    $maxLength
                ) {

                    $display =
                        substr(
                            $display,
                            0,
                            $maxLength - 3
                        ) .
                        '...';

                }


                $commands =
                    pdfText(
                        $commands,
                        $x + 5,
                        $y,
                        8,
                        $display
                    );


                $x +=
                    $widths[$columnIndex];

            }


            $currentY =
                $rowBottom;

        }


        /*
         * Grand total.
         *
         * Only show it on the final page.
         */

        if (
            $pageIndex ===
            $pageCount - 1
        ) {

            $commands =
                pdfText(
                    $commands,
                    $left,
                    22,
                    10,
                    'Grand Total: GHc ' .
                    pdfMoney($grandTotal)
                );

        }


        /*
         * Page number.
         */

        $pageText =
            'Page ' .
            ($pageIndex + 1) .
            ' of ' .
            $pageCount;


        $commands =
            pdfText(
                $commands,
                $pageWidth - 100,
                22,
                8,
                $pageText
            );


        /*
         * Build page stream.
         */

        $stream =
            implode(
                "\n",
                $commands
            ) .
            "\n";


        /*
         * Temporary font reference.
         * Replaced after font object is created.
         */

        $objects[$pageObject] =
            sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                0,
                $contentObject
            );


        $objects[$contentObject] =
            '<< /Length ' .
            strlen($stream) .
            " >>\nstream\n" .
            $stream .
            "endstream";

    }


    /* =====================================================
       FONT
    ===================================================== */

    $fontObject =
        $nextObject++;


    $objects[$fontObject] =
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';


    /*
     * Replace temporary font references.
     */

    foreach (
        $pageObjectNumbers as $pageObject
    ) {

        $objects[$pageObject] =
            str_replace(
                ' /F1 0 R',
                ' /F1 ' .
                $fontObject .
                ' 0 R',
                $objects[$pageObject]
            );

    }


    /*
     * Pages object.
     */

    $objects[2] =
        '<< /Type /Pages /Kids [' .
        implode(
            ' ',
            $kids
        ) .
        '] /Count ' .
        $pageCount .
        ' >>';


    /*
     * Sort objects.
     */

    ksort(
        $objects,
        SORT_NUMERIC
    );


    /*
     * Start PDF.
     */

    $pdf =
        "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";


    $offsets = [0];


    $maxObject =
        max(
            array_keys($objects)
        );


    /*
     * Write objects.
     */

    for (
        $objectNumber = 1;
        $objectNumber <= $maxObject;
        $objectNumber++
    ) {

        $offsets[$objectNumber] =
            strlen($pdf);


        $pdf .=
            $objectNumber .
            " 0 obj\n";


        $pdf .=
            $objects[$objectNumber] .
            "\n";


        $pdf .=
            "endobj\n";

    }


    /*
     * Cross-reference table.
     */

    $xrefOffset =
        strlen($pdf);


    $pdf .=
        "xref\n";


    $pdf .=
        "0 " .
        ($maxObject + 1) .
        "\n";


    $pdf .=
        "0000000000 65535 f \n";


    for (
        $objectNumber = 1;
        $objectNumber <= $maxObject;
        $objectNumber++
    ) {

        $pdf .= sprintf(
            "%010d 00000 n \n",
            $offsets[$objectNumber]
        );

    }


    /*
     * Trailer.
     */

    $pdf .=
        "trailer\n";


    $pdf .=
        "<< /Size " .
        ($maxObject + 1) .
        " /Root 1 0 R >>\n";


    $pdf .=
        "startxref\n" .
        $xrefOffset .
        "\n";


    $pdf .=
        "%%EOF\n";


    return $pdf;

}


/* =========================================================
   GENERATE PDF
========================================================= */

$pdf =
    makeBudgetPdf(
        $rows,
        $grandTotal
    );


/* =========================================================
   PREVIEW / DOWNLOAD MODE
========================================================= */

$mode =
    isset($_GET['mode'])
        ? strtolower(
            trim(
                (string)$_GET['mode']
            )
        )
        : 'download';


$isPreview =
    ($mode === 'preview');


/*
 * Make absolutely sure no PHP notices/warnings
 * are mixed into the PDF.
 */

while (
    ob_get_level() > 0
) {

    ob_end_clean();

}


/* =========================================================
   PDF RESPONSE HEADERS
========================================================= */

header(
    'Content-Type: application/pdf'
);


header(
    'Content-Disposition: ' .
    (
        $isPreview
            ? 'inline'
            : 'attachment'
    ) .
    '; filename="budget.pdf"'
);


header(
    'Content-Length: ' .
    strlen($pdf)
);


header(
    'Cache-Control: private, max-age=0, must-revalidate'
);


header(
    'Pragma: public'
);


echo $pdf;

exit;

?>
