<?php
$pagetitle = "Dashboard";

include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";

/* =========================================================
   USER ROLE
   Cashiers use a restricted dashboard menu. Admins keep the
   normal dashboard menu already used by this page.
========================================================= */
$dashboardRole = strtolower(trim($_SESSION['role'] ?? ''));
$isCashier = ($dashboardRole === 'cashier');

date_default_timezone_set('Africa/Accra');

/* =========================================================
   DASHBOARD FILTERS
========================================================= */

$filter    = $_GET['filter'] ?? 'today';
$monthyear = $_GET['monthyear'] ?? '';

$todayDate = date('Y-m-d');

/* =========================================================
   SAFE QUERY HELPERS
========================================================= */

function dashboardScalar($conn, $sql, $default = 0)
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return $default;
    }

    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        return $default;
    }

    $value = array_values($row)[0] ?? $default;

    return is_numeric($value) ? (float)$value : $default;
}

function dashboardRows($conn, $sql)
{
    $result = mysqli_query($conn, $sql);

    return $result ?: false;
}

function percentChange($current, $previous)
{
    $current  = (float)$current;
    $previous = (float)$previous;

    if ($previous == 0) {
        return $current == 0 ? 0 : 100;
    }

    return round((($current - $previous) / $previous) * 100, 2);
}

/* =========================================================
   DATE CONDITIONS
========================================================= */

$startDate = $todayDate;
$endDate   = $todayDate;
$label     = "Today's";

if (
    $monthyear !== ''
    &&
    preg_match('/^\d{4}-\d{2}$/', $monthyear)
) {
    $parts = explode('-', $monthyear);

    $year  = (int)$parts[0];
    $month = (int)$parts[1];

    if ($month >= 1 && $month <= 12) {

        $startDate = sprintf(
            '%04d-%02d-01',
            $year,
            $month
        );

        $endDate = date(
            'Y-m-t',
            strtotime($startDate)
        );

        $label = date(
            'F Y',
            strtotime($startDate)
        );
    }
}
elseif ($filter === 'week') {

    $startDate = date(
        'Y-m-d',
        strtotime('-6 days', strtotime($todayDate))
    );

    $endDate = $todayDate;

    $label = 'Weekly';
}
elseif ($filter === 'month') {

    $startDate = date(
        'Y-m-01',
        strtotime($todayDate)
    );

    $endDate = $todayDate;

    $label = 'Monthly';
}

/* =========================================================
   DATE/TIME RANGE
========================================================= */

$startDateTime = $startDate . ' 00:00:00';

$endDateExclusive = date(
    'Y-m-d 00:00:00',
    strtotime($endDate . ' +1 day')
);

/* Previous period */

$periodDays =
    (
        strtotime($endDate)
        -
        strtotime($startDate)
    ) / 86400 + 1;

$previousEndDate = date(
    'Y-m-d',
    strtotime($startDate . ' -1 day')
);

$previousStartDate = date(
    'Y-m-d',
    strtotime(
        $startDate . ' -' . (int)$periodDays . ' days'
    )
);

$previousStartDateTime =
    $previousStartDate . ' 00:00:00';

$previousEndDateExclusive = date(
    'Y-m-d 00:00:00',
    strtotime($previousEndDate . ' +1 day')
);

$startEsc = mysqli_real_escape_string(
    $conn,
    $startDateTime
);

$endEsc = mysqli_real_escape_string(
    $conn,
    $endDateExclusive
);

$prevStartEsc = mysqli_real_escape_string(
    $conn,
    $previousStartDateTime
);

$prevEndEsc = mysqli_real_escape_string(
    $conn,
    $previousEndDateExclusive
);

/* =========================================================
   SALES
========================================================= */

$salesToday = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM sales
    WHERE created_at >= '$startEsc'
    AND created_at < '$endEsc'
    "
);

$salesYesterday = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM sales
    WHERE created_at >= '$prevStartEsc'
    AND created_at < '$prevEndEsc'
    "
);

/* =========================================================
   PURCHASES
========================================================= */

$purchaseToday = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(
        SUM(
            COALESCE(qty, 0)
            *
            COALESCE(unitprice, 0)
        ),
        0
    )
    FROM purchase_items
    WHERE created_at >= '$startEsc'
    AND created_at < '$endEsc'
    "
);

$purchaseYesterday = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(
        SUM(
            COALESCE(qty, 0)
            *
            COALESCE(unitprice, 0)
        ),
        0
    )
    FROM purchase_items
    WHERE created_at >= '$prevStartEsc'
    AND created_at < '$prevEndEsc'
    "
);

/* =========================================================
   CREDIT
========================================================= */

$creditToday = (int)dashboardScalar(
    $conn,
    "
    SELECT COUNT(DISTINCT customer_id)
    FROM sales
    WHERE payment_method = 'credit'
    AND created_at >= '$startEsc'
    AND created_at < '$endEsc'
    "
);

/* =========================================================
   MONTH SALES
========================================================= */

$monthSales = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM sales
    WHERE YEAR(created_at) = YEAR('$todayDate')
    AND MONTH(created_at) = MONTH('$todayDate')
    "
);

if (
    $monthyear !== ''
    &&
    isset($year, $month)
) {

    $monthSales = dashboardScalar(
        $conn,
        "
        SELECT COALESCE(SUM(total), 0)
        FROM sales
        WHERE YEAR(created_at) = '$year'
        AND MONTH(created_at) = '$month'
        "
    );
}

$lastMonthSales = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM sales
    WHERE YEAR(created_at) =
          YEAR(
              DATE_SUB(
                  '$todayDate',
                  INTERVAL 1 MONTH
              )
          )
    AND MONTH(created_at) =
          MONTH(
              DATE_SUB(
                  '$todayDate',
                  INTERVAL 1 MONTH
              )
          )
    "
);

/* =========================================================
   PERCENTAGES
========================================================= */

$salesPercent = percentChange(
    $salesToday,
    $salesYesterday
);

$purchasePercent = percentChange(
    $purchaseToday,
    $purchaseYesterday
);

$monthPercent = percentChange(
    $monthSales,
    $lastMonthSales
);

/* =========================================================
   ACTUAL PROFIT
========================================================= */

$profitToday = dashboardScalar(
    $conn,
    "
    SELECT
        COALESCE(
            SUM(
                COALESCE(si.subtotal, 0)
                -
                (
                    COALESCE(si.qty, 0)
                    *
                    COALESCE(
                        NULLIF(si.unit_qty, 0),
                        1
                    )
                    *
                    COALESCE(
                        p.costperunit,
                        0
                    )
                )
            ),
            0
        )
    FROM sales_items si

    INNER JOIN products p
        ON si.product_id = p.productid

    INNER JOIN sales s
        ON si.sale_id = s.id

    WHERE s.created_at >= '$startEsc'
    AND s.created_at < '$endEsc'
    "
);

$profitYesterday = dashboardScalar(
    $conn,
    "
    SELECT
        COALESCE(
            SUM(
                COALESCE(si.subtotal, 0)
                -
                (
                    COALESCE(si.qty, 0)
                    *
                    COALESCE(
                        NULLIF(si.unit_qty, 0),
                        1
                    )
                    *
                    COALESCE(
                        p.costperunit,
                        0
                    )
                )
            ),
            0
        )
    FROM sales_items si

    INNER JOIN products p
        ON si.product_id = p.productid

    INNER JOIN sales s
        ON si.sale_id = s.id

    WHERE s.created_at >= '$prevStartEsc'
    AND s.created_at < '$prevEndEsc'
    "
);

$profitPercent = percentChange(
    $profitToday,
    $profitYesterday
);

$todayProfit = $profitToday;

/* =========================================================
   STOCK VALUE
========================================================= */

$totalStockValue = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(
        SUM(
            COALESCE(totalstock, 0)
            *
            COALESCE(costperunit, 0)
        ),
        0
    )
    FROM products
    "
);

/* =========================================================
   EXPECTED PROFIT
========================================================= */

$expectedProfit = dashboardScalar(
    $conn,
    "
    SELECT COALESCE(
        SUM(
            COALESCE(totalstock, 0)
            *
            (
                COALESCE(sellingprice, 0)
                -
                COALESCE(costperunit, 0)
            )
        ),
        0
    )
    FROM products
    "
);

/* =========================================================
   OUT OF STOCK
========================================================= */

$outOfStock = dashboardRows(
    $conn,
    "
    SELECT
        productid,
        pname,
        pdesc,
        category,
        totalstock,
        qtyalert,
        unit
    FROM products
    WHERE totalstock <= 0
    ORDER BY category ASC, pname ASC
    "
);

$outOfStockCount =
    $outOfStock
        ? mysqli_num_rows($outOfStock)
        : 0;

/* =========================================================
   CHART 1
   MONTHLY SALES VS PROFIT
========================================================= */

$availableMonths = [];

$res = mysqli_query(
    $conn,
    "
    SELECT
        DATE_FORMAT(
            created_at,
            '%Y-%m'
        ) AS month_value,

        DATE_FORMAT(
            created_at,
            '%M %Y'
        ) AS month_label

    FROM sales

    GROUP BY
        YEAR(created_at),
        MONTH(created_at)

    ORDER BY
        YEAR(created_at) DESC,
        MONTH(created_at) DESC
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $availableMonths[] = [
            'value' => $r['month_value'],
            'label' => $r['month_label']
        ];
    }
}

$chartMonth =
    $_GET['chart_month']
    ??
    date('Y-m');

if (
    !preg_match(
        '/^\d{4}-\d{2}$/',
        $chartMonth
    )
) {

    $chartMonth = date('Y-m');
}

$chartMonthTimestamp =
    strtotime(
        $chartMonth . '-01'
    );

if ($chartMonthTimestamp === false) {

    $chartMonth = date('Y-m');

    $chartMonthTimestamp =
        strtotime(
            $chartMonth . '-01'
        );
}

$chartMonthStart =
    date(
        'Y-m-01',
        $chartMonthTimestamp
    );

$chartMonthEnd =
    date(
        'Y-m-t',
        $chartMonthTimestamp
    );

$chartMonthLabel =
    date(
        'F Y',
        $chartMonthTimestamp
    );

$chartMonthStartEsc =
    mysqli_real_escape_string(
        $conn,
        $chartMonthStart . ' 00:00:00'
    );

$chartMonthEndExclusiveEsc =
    mysqli_real_escape_string(
        $conn,
        date(
            'Y-m-d 00:00:00',
            strtotime(
                $chartMonthEnd . ' +1 day'
            )
        )
    );

/* Build days */

$monthDays = [];

$daysInMonth =
    (int)date(
        't',
        $chartMonthTimestamp
    );

for (
    $day = 1;
    $day <= $daysInMonth;
    $day++
) {

    $dateValue =
        sprintf(
            '%s-%02d',
            $chartMonth,
            $day
        );

    $monthDays[$dateValue] = [
        'date' => $dateValue,

        'label' =>
            date(
                'd M',
                strtotime($dateValue)
            ),

        'sales' => 0,

        'profit' => 0
    ];
}

/* Daily sales */

$res = mysqli_query(
    $conn,
    "
    SELECT
        DATE(created_at) AS sale_date,
        COALESCE(SUM(total), 0) AS sales

    FROM sales

    WHERE created_at >= '$chartMonthStartEsc'
    AND created_at < '$chartMonthEndExclusiveEsc'

    GROUP BY DATE(created_at)

    ORDER BY DATE(created_at)
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $dateValue =
            $r['sale_date'];

        if (
            isset(
                $monthDays[$dateValue]
            )
        ) {

            $monthDays[$dateValue]['sales'] =
                (float)$r['sales'];
        }
    }
}

/* Daily profit */

$res = mysqli_query(
    $conn,
    "
    SELECT
        DATE(s.created_at) AS sale_date,

        COALESCE(
            SUM(
                COALESCE(si.subtotal, 0)
                -
                (
                    COALESCE(si.qty, 0)
                    *
                    COALESCE(
                        NULLIF(si.unit_qty, 0),
                        1
                    )
                    *
                    COALESCE(
                        p.costperunit,
                        0
                    )
                )
            ),
            0
        ) AS profit

    FROM sales_items si

    INNER JOIN products p
        ON si.product_id = p.productid

    INNER JOIN sales s
        ON si.sale_id = s.id

    WHERE s.created_at >= '$chartMonthStartEsc'
    AND s.created_at < '$chartMonthEndExclusiveEsc'

    GROUP BY DATE(s.created_at)

    ORDER BY DATE(s.created_at)
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $dateValue =
            $r['sale_date'];

        if (
            isset(
                $monthDays[$dateValue]
            )
        ) {

            $monthDays[$dateValue]['profit'] =
                (float)$r['profit'];
        }
    }
}

$monthlyTrendData =
    array_values($monthDays);

/* =========================================================
   CHART 2
   YEARLY SALES VS PURCHASES VS PROFIT
========================================================= */

$chartYears = [];

$res = mysqli_query(
    $conn,
    "
    SELECT year_value
    FROM
    (
        SELECT YEAR(created_at) AS year_value
        FROM sales

        UNION

        SELECT YEAR(created_at) AS year_value
        FROM purchase_items
    ) years

    WHERE year_value IS NOT NULL

    ORDER BY year_value DESC
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $chartYears[] =
            (int)$r['year_value'];
    }
}

$currentYear =
    (int)date('Y');

if (
    !in_array(
        $currentYear,
        $chartYears
    )
) {

    $chartYears[] =
        $currentYear;
}

rsort($chartYears);

$chartYear =
    isset($_GET['chart_year'])
        ? intval($_GET['chart_year'])
        : $currentYear;

if (
    !in_array(
        $chartYear,
        $chartYears
    )
) {

    $chartYear =
        $currentYear;
}

$yearChartData = [];

$monthNames = [
    1  => 'Jan',
    2  => 'Feb',
    3  => 'Mar',
    4  => 'Apr',
    5  => 'May',
    6  => 'Jun',
    7  => 'Jul',
    8  => 'Aug',
    9  => 'Sep',
    10 => 'Oct',
    11 => 'Nov',
    12 => 'Dec'
];

for (
    $month = 1;
    $month <= 12;
    $month++
) {

    $yearChartData[$month] = [

        'month_number' =>
            $month,

        'month' =>
            $monthNames[$month],

        'sales' =>
            0,

        'purchases' =>
            0,

        'profit' =>
            0
    ];
}

/* Yearly sales */

$res = mysqli_query(
    $conn,
    "
    SELECT
        MONTH(created_at) AS month_number,
        COALESCE(SUM(total), 0) AS sales

    FROM sales

    WHERE YEAR(created_at) = '$chartYear'

    GROUP BY MONTH(created_at)

    ORDER BY MONTH(created_at)
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $monthNumber =
            (int)$r['month_number'];

        if (
            isset(
                $yearChartData[$monthNumber]
            )
        ) {

            $yearChartData[$monthNumber]['sales'] =
                (float)$r['sales'];
        }
    }
}

/* Yearly purchases */

$res = mysqli_query(
    $conn,
    "
    SELECT
        MONTH(created_at) AS month_number,

        COALESCE(
            SUM(
                COALESCE(qty, 0)
                *
                COALESCE(unitprice, 0)
            ),
            0
        ) AS purchases

    FROM purchase_items

    WHERE YEAR(created_at) = '$chartYear'

    GROUP BY MONTH(created_at)

    ORDER BY MONTH(created_at)
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $monthNumber =
            (int)$r['month_number'];

        if (
            isset(
                $yearChartData[$monthNumber]
            )
        ) {

            $yearChartData[$monthNumber]['purchases'] =
                (float)$r['purchases'];
        }
    }
}

/* Yearly profit */

$res = mysqli_query(
    $conn,
    "
    SELECT

        MONTH(s.created_at) AS month_number,

        COALESCE(
            SUM(
                COALESCE(si.subtotal, 0)
                -
                (
                    COALESCE(si.qty, 0)
                    *
                    COALESCE(
                        NULLIF(si.unit_qty, 0),
                        1
                    )
                    *
                    COALESCE(
                        p.costperunit,
                        0
                    )
                )
            ),
            0
        ) AS profit

    FROM sales_items si

    INNER JOIN products p
        ON si.product_id = p.productid

    INNER JOIN sales s
        ON si.sale_id = s.id

    WHERE YEAR(s.created_at) = '$chartYear'

    GROUP BY MONTH(s.created_at)

    ORDER BY MONTH(s.created_at)
    "
);

if ($res) {

    while ($r = mysqli_fetch_assoc($res)) {

        $monthNumber =
            (int)$r['month_number'];

        if (
            isset(
                $yearChartData[$monthNumber]
            )
        ) {

            $yearChartData[$monthNumber]['profit'] =
                (float)$r['profit'];
        }
    }
}

$yearChartData =
    array_values($yearChartData);

/* =========================================================
   DASHBOARD SCANNER IP
========================================================= */

$dashboardScannerIp =
    $_SERVER['SERVER_ADDR'] ?? '';

if (
    !filter_var(
        $dashboardScannerIp,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_IPV4
    )
    ||
    strpos(
        $dashboardScannerIp,
        '127.'
    ) === 0
) {

    $dashboardScannerIp = '';

    $hostName =
        gethostname();

    if ($hostName) {

        $hostIps =
            @gethostbynamel(
                $hostName
            );

        if (is_array($hostIps)) {

            foreach (
                $hostIps
                as $candidateIp
            ) {

                if (
                    filter_var(
                        $candidateIp,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4
                    )
                    &&
                    strpos(
                        $candidateIp,
                        '127.'
                    ) !== 0
                ) {

                    $dashboardScannerIp =
                        $candidateIp;

                    break;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <?php
    include "assets/sections/headers/header_tag.php";
    ?>

    <style>

        /* =========================================================
           REMOVE LEFT SIDEBAR
        ========================================================= */

        body.dashboard-no-sidebar .content-page {
            margin-left: 0 !important;
        }

        body.dashboard-no-sidebar .content,
        body.dashboard-no-sidebar .page-content-wrapper,
        body.dashboard-no-sidebar .container-fluid {
            width: 100% !important;
            max-width: 100% !important;
        }

        .footer {
            left: 0 !important;
        }


        /* =========================================================
           FULL WIDTH TOP MENU
        ========================================================= */

        .dashboard-top-nav-wrap {
            width: 100%;
            margin-bottom: 22px;
        }

        .dashboard-top-nav {
            width: 100%;
            display: grid;
            grid-template-columns:
                repeat(5, minmax(0, 1fr));

            gap: 16px;

            padding: 16px;

            background: #ffffff;

            border: 1px solid #e4e7eb;

            border-radius: 10px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.07);
        }

        .dashboard-menu-btn {
            width: 100%;

            min-height: 135px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 12px;

            padding: 22px 15px;

            border-radius: 10px;

            text-decoration: none !important;

            font-size: 21px;

            font-weight: 700;

            line-height: 1.2;

            box-shadow:
                0 3px 9px rgba(0,0,0,.10);

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .dashboard-menu-btn:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 7px 18px rgba(0,0,0,.16);
        }

        .dashboard-menu-btn .fa,
        .dashboard-menu-btn .mdi {

            font-size: 44px;

            line-height: 1;
        }


        /* =========================================================
           DASHBOARD FILTER
        ========================================================= */

        .dashboard-filter-card {
            margin-bottom: 20px;
        }

        .dashboard-filter-buttons {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;
        }

        .dashboard-filter-buttons .btn {

            min-height: 42px;

            font-weight: 600;
        }

        #monthYearFilter {

            min-width: 200px;
        }


        /* =========================================================
           MAIN 80 / 20 LAYOUT
        ========================================================= */

        .dashboard-layout {

            width: 100%;

            display: grid;

            grid-template-columns:
                minmax(0, 8fr)
                minmax(280px, 2fr);

            gap: 20px;

            align-items: start;
        }

        .dashboard-left {

            width: 100%;

            min-width: 0;
        }

        .dashboard-right {

            width: 100%;

            min-width: 0;
        }


        /* =========================================================
           OUT OF STOCK
        ========================================================= */

        .out-of-stock-card {

            width: 100%;

            position: sticky;

            top: 20px;

            margin-bottom: 20px;
        }

        .out-of-stock-list {

            max-height:
                calc(100vh - 180px);

            overflow-y: auto;

            padding-right: 5px;
        }

        .out-of-stock-item {

            border:
                1px solid #eeeeee;

            border-left:
                5px solid #dc3545;

            border-radius: 7px;

            padding: 12px;

            margin-bottom: 12px;

            background: #ffffff;
        }

        .out-of-stock-name {

            font-size: 15px;

            font-weight: 700;

            color: #343a40;
        }

        .out-of-stock-item .btn {

            font-weight: 600;
        }


        /* =========================================================
           CHARTS
        ========================================================= */

        .full-width-chart {

            width: 100%;
        }

        .chart-container {

            position: relative;

            width: 100%;

            height: 430px;
        }


        /* =========================================================
           SCANNER STATUS
        ========================================================= */

        .dashboard-scanner-bar {

            display: flex;

            width: 100%;

            margin-top: 12px;
        }

        .dashboard-scanner-status {

            width: 100%;

            min-height: 62px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding: 10px 15px;

            background: #f8f9fa;

            border:
                1px solid #dfe4e8;

            border-radius: 8px;
        }

        .dashboard-scanner-status.connected {

            background: #eaf7ef;

            border-color: #badbcc;
        }

        .dashboard-scanner-status-left,
        .dashboard-scanner-status-right {

            display: flex;

            align-items: center;

            gap: 14px;

            flex-wrap: wrap;
        }

        .dashboard-scanner-status-right {

            margin-left: auto;
        }

        .dashboard-scanner-ip {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            font-weight: 600;
        }

        .dashboard-scanner-ip-value {

            min-width: 110px;

            font-family:
                Consolas,
                'Courier New',
                monospace;
        }

        .dashboard-scanner-ip-toggle,
        .dashboard-scanner-disconnect {

            min-height: 44px;

            font-weight: 600;
        }


        /* =========================================================
           MOBILE SCANNER IS MOBILE-ONLY
        ========================================================= */

        .dashboard-mobile-scanner,
        .dashboard-scanner-bar {
            display: none !important;
        }

        /* Cashier scanner is visible because it is one of the four
           cashier dashboard options. Admin keeps the existing
           mobile-only scanner behaviour above. */
        .dashboard-cashier-scanner {
            display: flex !important;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            .dashboard-menu-btn {

                font-size: 18px;

                min-height: 120px;
            }

            .dashboard-menu-btn .fa,
            .dashboard-menu-btn .mdi {

                font-size: 38px;
            }
        }


        @media (max-width: 991.98px) {

            .dashboard-top-nav {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }

            .dashboard-layout {

                grid-template-columns: 1fr;
            }

            .out-of-stock-card {

                position: static;
            }

            .out-of-stock-list {

                max-height: 500px;
            }
        }


        /* =========================================================
           MOBILE DASHBOARD MENU
           Cashiers see:
           1. Dashboard
           2. Open Scanner
           3. POS Terminal
           4. People

           Admins keep the existing index.php behaviour.
        ========================================================= */

        .dashboard-desktop-only {
            display: flex;
        }

        .dashboard-cashier-nav {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        @media (max-width: 767.98px) {

            .dashboard-desktop-only {
                display: none !important;
            }

            /* Cashiers keep People visible on mobile. */
            .dashboard-cashier-nav .dashboard-menu-btn {
                display: flex !important;
            }

            .dashboard-mobile-scanner {
                display: flex !important;
            }

            .dashboard-scanner-bar {
                display: block !important;
            }

            .dashboard-top-nav {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));

                gap: 8px;

                padding: 8px;
            }

            .dashboard-menu-btn {

                min-height: 105px;

                font-size: 15px;

                padding: 12px 6px;
            }

            .dashboard-menu-btn .fa,
            .dashboard-menu-btn .mdi {

                font-size: 31px;
            }

            .dashboard-scanner-status {

                flex-direction: column;

                align-items: stretch;
            }

            .dashboard-scanner-status-right {

                width: 100%;

                margin-left: 0;
            }

            .dashboard-scanner-status-right button {

                flex: 1;
            }

            .chart-container {

                height: 330px;
            }
        }


        @media (max-width: 450px) {

            .dashboard-top-nav {

                grid-template-columns:
                    repeat(3, 1fr);

                gap: 6px;

                padding: 6px;
            }

            .dashboard-menu-btn {

                min-height: 88px;

                font-size: 12px;

                padding: 10px 3px;
            }

            .dashboard-menu-btn .fa,
            .dashboard-menu-btn .mdi {

                font-size: 27px;
            }

            .dashboard-filter-buttons {

                display: grid;

                grid-template-columns:
                    repeat(2, 1fr);
            }

            #monthYearFilter {

                width: 100% !important;

                min-width: 0;
            }
        }

    </style>

</head>


<body class="fixed-left dashboard-no-sidebar">


<!-- =========================================================
     PRELOADER
========================================================= -->

<div id="preloader">

    <div id="status">

        <div class="spinner"></div>

    </div>

</div>


<!-- =========================================================
     WRAPPER
========================================================= -->

<div id="wrapper">


    <!-- =====================================================
         CONTENT PAGE
    ===================================================== -->

    <div class="content-page">

        <div class="content">


            <!-- =================================================
                 TOPBAR
            ================================================= -->

            <?php
            include "assets/sections/topbar.php";
            ?>


            <!-- =================================================
                 LARGE FULL WIDTH MENU
            ================================================= -->

            <div class="dashboard-top-nav-wrap">

                <div
                    class="dashboard-top-nav <?= $isCashier ? 'dashboard-cashier-nav' : '' ?>"
                    role="navigation"
                    aria-label="Dashboard menu"
                >

                    <?php if ($isCashier) { ?>

                        <!-- CASHIER: Dashboard -->
                        <a
                            href="index.php"
                            class="dashboard-menu-btn btn btn-danger"
                            title="Dashboard"
                        >
                            <i class="fa fa-home"></i>
                            <span>Dashboard</span>
                        </a>

                        <!-- CASHIER: Open Scanner -->
                        <button
                            type="button"
                            class="dashboard-menu-btn btn btn-primary dashboard-cashier-scanner"
                            title="Open Scanner"
                            onclick="dashboardStartMobileScanner()"
                        >
                            <i class="fa fa-barcode"></i>
                            <span>Open Scanner</span>
                        </button>

                        <!-- CASHIER: POS Terminal -->
                        <a
                            href="pos.php"
                            class="dashboard-menu-btn btn btn-success"
                            title="POS Terminal"
                        >
                            <i class="fa fa-shopping-cart"></i>
                            <span>POS Terminal</span>
                        </a>

                        <!-- CASHIER: People -->
                        <a
                            href="customers.php"
                            class="dashboard-menu-btn btn btn-dark"
                            title="People"
                        >
                            <i class="fa fa-users"></i>
                            <span>People</span>
                        </a>

                    <?php } else { ?>

                        <!-- ADMIN: keep the existing index.php menu -->
                        <a
                            href="index.php"
                            class="dashboard-menu-btn btn btn-danger"
                            title="Dashboard"
                        >
                            <i class="fa fa-home"></i>
                            <span>Dashboard</span>
                        </a>

                        <!-- Mobile Scanner: existing admin/mobile behaviour -->
                        <button
                            type="button"
                            class="dashboard-menu-btn btn btn-primary dashboard-mobile-scanner"
                            title="Mobile Scanner"
                            onclick="dashboardStartMobileScanner()"
                        >
                            <i class="fa fa-barcode"></i>
                            <span>Mobile Scanner</span>
                        </button>

                        <!-- POS -->
                        <a
                            href="pos.php"
                            class="dashboard-menu-btn btn btn-success"
                            title="POS Terminal"
                        >
                            <i class="fa fa-shopping-cart"></i>
                            <span>POS Terminal</span>
                        </a>

                        <!-- Products -->
                        <a
                            href="products.php"
                            class="dashboard-menu-btn btn btn-info"
                            title="Products"
                        >
                            <i class="fa fa-cubes"></i>
                            <span>Products</span>
                        </a>

                        <!-- Sales -->
                        <a
                            href="sales_items.php"
                            class="dashboard-menu-btn btn btn-warning"
                            title="Sales"
                        >
                            <i class="fa fa-list-alt"></i>
                            <span>Sales</span>
                        </a>

                        <!-- People -->
                        <a
                            href="customers.php"
                            class="dashboard-menu-btn btn btn-dark"
                            title="People"
                        >
                            <i class="fa fa-users"></i>
                            <span>People</span>
                        </a>

                    <?php } ?>

                </div>

                <!-- =================================================
                     SCANNER STATUS
                ================================================= -->

                <div class="dashboard-scanner-bar">

                    <div
                        id="dashboardScannerStatus"
                        class="dashboard-scanner-status"
                        role="status"
                        aria-live="polite"
                    >

                        <div
                            class="dashboard-scanner-status-left"
                        >

                            <span>

                                Scanner Status:

                                <strong
                                    id="dashboardScannerStatusText"
                                >
                                    Checking...
                                </strong>

                            </span>


                            <span
                                class="dashboard-scanner-ip"
                            >

                                <span>
                                    IP:
                                </span>

                                <span
                                    id="dashboardScannerIpValue"
                                    class="dashboard-scanner-ip-value"
                                ></span>

                            </span>


                            <span class="dashboard-scanner-ip">

                                <span>Pair Code:</span>

                                <strong
                                    id="dashboardScannerCodeValue"
                                    class="dashboard-scanner-ip-value"
                                >
                                    ------
                                </strong>

                            </span>

                        </div>


                        <div
                            class="dashboard-scanner-status-right"
                        >

                            <button
                                type="button"
                                id="dashboardScannerIpToggle"
                                class="btn dashboard-scanner-ip-toggle"
                                onclick="toggleDashboardScannerIp()"
                            >

                                <span
                                    class="fa fa-eye-slash"
                                ></span>

                                Hide IP

                            </button>


                            <button
                                type="button"
                                id="dashboardScannerDisconnect"
                                class="btn btn-danger dashboard-scanner-disconnect"
                                onclick="dashboardStopMobileScanner()"
                            >

                                <span
                                    class="fa fa-power-off"
                                ></span>

                                Disconnect

                            </button>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 PAGE CONTENT
            ================================================= -->

            <div class="page-content-wrapper">

                <div class="container-fluid">


                    <!-- =================================================
                         PAGE TITLE
                    ================================================= -->

                    <div class="row">

                        <div class="col-sm-12">

                            <div class="page-title-box">

                                <h4 class="page-title">
                                    Dashboard
                                </h4>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         FILTER
                    ================================================= -->

                    <div class="row">

                        <div class="col-12">

                            <div
                                class="card dashboard-filter-card"
                            >

                                <div class="card-body">

                                    <div
                                        class="dashboard-filter-buttons"
                                    >

                                        <button
                                            type="button"
                                            class="btn btn-sm <?= $filter == 'today' ? 'btn-primary' : 'btn-light' ?>"
                                            onclick="setFilter('today')"
                                        >

                                            <i class="fa fa-calendar-day"></i>

                                            Today

                                        </button>


                                        <button
                                            type="button"
                                            class="btn btn-sm <?= $filter == 'week' ? 'btn-info' : 'btn-light' ?>"
                                            onclick="setFilter('week')"
                                        >

                                            <i class="fa fa-calendar-week"></i>

                                            Week

                                        </button>


                                        <button
                                            type="button"
                                            class="btn btn-sm <?= $filter == 'month' ? 'btn-success' : 'btn-light' ?>"
                                            onclick="setFilter('month')"
                                        >

                                            <i class="fa fa-calendar"></i>

                                            Month

                                        </button>


                                        <select
                                            id="monthYearFilter"
                                            class="form-control"
                                            onchange="filterMonthYear(this.value)"
                                        >

                                            <option value="">
                                                Select Month
                                            </option>

                                            <?php

                                            for (
                                                $i = 0;
                                                $i < 12;
                                                $i++
                                            ) {

                                                $time =
                                                    strtotime(
                                                        "-$i month"
                                                    );

                                                $value =
                                                    date(
                                                        "Y-m",
                                                        $time
                                                    );

                                                $text =
                                                    date(
                                                        "F Y",
                                                        $time
                                                    );

                                            ?>

                                                <option
                                                    value="<?= $value ?>"
                                                    <?= (($_GET['monthyear'] ?? '') == $value)
                                                        ? 'selected'
                                                        : '' ?>
                                                >

                                                    <?= $text ?>

                                                </option>

                                            <?php } ?>

                                        </select>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         80 / 20 MAIN LAYOUT
                    ================================================= -->

                    <div class="dashboard-layout">


                        <!-- =================================================
                             LEFT 80% - DASHBOARD DATA
                        ================================================= -->

                        <div class="dashboard-left">


                            <!-- =================================================
                                 SUMMARY CARDS
                            ================================================= -->

                            <div class="row">


                                <!-- SALES -->

                                <div class="col-md-6 col-lg-6 col-xl-4">

                                    <div class="card m-b-30">

                                        <div class="card-body">

                                            <div class="d-flex flex-row">

                                                <div class="col-3 align-self-center">

                                                    <div class="round">

                                                        <i
                                                            class="mdi mdi-cash"
                                                        ></i>

                                                    </div>

                                                </div>


                                                <div
                                                    class="col-6 text-center align-self-center"
                                                >

                                                    <h5 class="mt-0 round-inner">

                                                        GHS
                                                        <?= number_format(
                                                            $salesToday,
                                                            2
                                                        ) ?>

                                                    </h5>

                                                    <p class="mb-0 text-muted">

                                                        <?= htmlspecialchars($label) ?>

                                                        Sales

                                                    </p>

                                                </div>


                                                <div class="col-3 align-self-center">

                                                    <h6
                                                        class="m-0 text-center <?= ($salesPercent >= 0) ? 'text-success' : 'text-danger' ?>"
                                                    >

                                                        <i
                                                            class="mdi <?= ($salesPercent >= 0)
                                                                ? 'mdi-arrow-up'
                                                                : 'mdi-arrow-down' ?>"
                                                        ></i>

                                                        <?= $salesPercent ?>%

                                                    </h6>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- TOTAL STOCK -->

                                <div class="col-md-6 col-lg-6 col-xl-4">

                                    <div class="card m-b-30">

                                        <div class="card-body">

                                            <div class="d-flex flex-row">

                                                <div class="col-3 align-self-center">

                                                    <div class="round">

                                                        <i
                                                            class="mdi mdi-package-variant"
                                                        ></i>

                                                    </div>

                                                </div>


                                                <div
                                                    class="col-6 text-center align-self-center"
                                                >

                                                    <h5 class="mt-0 round-inner">

                                                        GHS
                                                        <?= number_format(
                                                            $totalStockValue,
                                                            2
                                                        ) ?>

                                                    </h5>

                                                    <p class="mb-0 text-muted">

                                                        Total Stock

                                                    </p>

                                                </div>


                                                <div class="col-3 align-self-center">

                                                    <h6
                                                        class="m-0 text-center text-info"
                                                    >

                                                        <i
                                                            class="mdi mdi-warehouse"
                                                        ></i>

                                                    </h6>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- CREDIT -->

                                <div class="col-md-6 col-lg-6 col-xl-4">

                                    <div class="card m-b-30">

                                        <div class="card-body">

                                            <div class="d-flex flex-row">

                                                <div class="col-3 align-self-center">

                                                    <div class="round">

                                                        <i
                                                            class="mdi mdi-account-alert"
                                                        ></i>

                                                    </div>

                                                </div>


                                                <div
                                                    class="col-6 text-center align-self-center"
                                                >

                                                    <h5 class="mt-0 round-inner">

                                                        <?= $creditToday ?>

                                                    </h5>

                                                    <p class="mb-0 text-muted">

                                                        Credit Sales Today

                                                    </p>

                                                </div>


                                                <div class="col-3 align-self-center">

                                                    <h6
                                                        class="text-warning text-center"
                                                    >

                                                        <i
                                                            class="mdi mdi-alert"
                                                        ></i>

                                                        Active

                                                    </h6>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- =================================================
                                 PROFIT CARDS
                            ================================================= -->

                            <div class="row">


                                <!-- ACTUAL PROFIT -->

                                <div class="col-md-6 col-lg-6 col-xl-6">

                                    <div class="card m-b-30">

                                        <div class="card-body">

                                            <div class="d-flex flex-row">

                                                <div class="col-3 align-self-center">

                                                    <div class="round">

                                                        <i
                                                            class="mdi mdi-cash-multiple"
                                                        ></i>

                                                    </div>

                                                </div>


                                                <div
                                                    class="col-6 text-center align-self-center"
                                                >

                                                    <h5
                                                        class="<?= ($todayProfit >= 0)
                                                            ? 'text-success'
                                                            : 'text-danger' ?>"
                                                    >

                                                        GHS
                                                        <?= number_format(
                                                            $todayProfit,
                                                            2
                                                        ) ?>

                                                    </h5>

                                                    <p class="mb-0 text-muted">

                                                        <?= htmlspecialchars($label) ?>

                                                        Profit

                                                    </p>

                                                </div>


                                                <div class="col-3 align-self-center">

                                                    <h6
                                                        class="m-0 text-center <?= ($todayProfit >= 0)
                                                            ? 'text-success'
                                                            : 'text-danger' ?>"
                                                    >

                                                        <i
                                                            class="mdi <?= ($todayProfit >= 0)
                                                                ? 'mdi-arrow-up'
                                                                : 'mdi-arrow-down' ?>"
                                                        ></i>

                                                    </h6>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- EXPECTED PROFIT -->

                                <div class="col-md-6 col-lg-6 col-xl-6">

                                    <div class="card m-b-30">

                                        <div class="card-body">

                                            <div class="d-flex flex-row">

                                                <div class="col-3 align-self-center">

                                                    <div class="round">

                                                        <i
                                                            class="mdi mdi-chart-line"
                                                        ></i>

                                                    </div>

                                                </div>


                                                <div
                                                    class="col-6 text-center align-self-center"
                                                >

                                                    <h5 class="text-primary">

                                                        GHS
                                                        <?= number_format(
                                                            $expectedProfit,
                                                            2
                                                        ) ?>

                                                    </h5>

                                                    <p class="mb-0 text-muted">

                                                        Expected Profit

                                                    </p>

                                                </div>


                                                <div class="col-3 align-self-center">

                                                    <h6
                                                        class="m-0 text-center text-primary"
                                                    >

                                                        <i
                                                            class="mdi mdi-chart-line"
                                                        ></i>

                                                    </h6>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- =================================================
                                 CHART 1
                            ================================================= -->

                            <div class="row">

                                <div class="col-md-12">

                                    <div class="card m-b-30 full-width-chart">

                                        <div class="card-body">


                                            <div
                                                class="d-flex justify-content-between align-items-center flex-wrap mb-3"
                                            >

                                                <div>

                                                    <h5
                                                        class="header-title mt-0 mb-1"
                                                    >

                                                        Sales vs Profit Trend

                                                    </h5>

                                                    <p
                                                        class="text-muted mb-0"
                                                    >

                                                        Daily sales and actual profit for
                                                        <?= htmlspecialchars($chartMonthLabel) ?>

                                                    </p>

                                                </div>


                                                <div>

                                                    <select
                                                        id="chartMonth"
                                                        class="form-control"
                                                        style="min-width:190px;"
                                                        onchange="filterChartMonth(this.value)"
                                                    >

                                                        <?php

                                                        if (
                                                            !empty(
                                                                $availableMonths
                                                            )
                                                        ) {

                                                            foreach (
                                                                $availableMonths
                                                                as $availableMonth
                                                            ) {

                                                        ?>

                                                            <option
                                                                value="<?= htmlspecialchars($availableMonth['value']) ?>"
                                                                <?= $availableMonth['value'] == $chartMonth
                                                                    ? 'selected'
                                                                    : '' ?>
                                                            >

                                                                <?= htmlspecialchars($availableMonth['label']) ?>

                                                            </option>

                                                        <?php

                                                            }

                                                        }
                                                        else {

                                                        ?>

                                                            <option
                                                                value="<?= htmlspecialchars($chartMonth) ?>"
                                                                selected
                                                            >

                                                                <?= htmlspecialchars($chartMonthLabel) ?>

                                                            </option>

                                                        <?php } ?>

                                                    </select>

                                                </div>

                                            </div>


                                            <div class="chart-container">

                                                <canvas
                                                    id="monthlyTrendChart"
                                                ></canvas>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- =================================================
                                 CHART 2
                            ================================================= -->

                            <div class="row">

                                <div class="col-md-12">

                                    <div class="card m-b-30 full-width-chart">

                                        <div class="card-body">


                                            <div
                                                class="d-flex justify-content-between align-items-center flex-wrap mb-3"
                                            >

                                                <div>

                                                    <h5
                                                        class="header-title mt-0 mb-1"
                                                    >

                                                        Sales vs Purchases vs Profit

                                                    </h5>

                                                    <p
                                                        class="text-muted mb-0"
                                                    >

                                                        Monthly comparison for
                                                        <?= $chartYear ?>

                                                    </p>

                                                </div>


                                                <div>

                                                    <select
                                                        id="chartYear"
                                                        class="form-control"
                                                        style="min-width:150px;"
                                                        onchange="filterChartYear(this.value)"
                                                    >

                                                        <?php

                                                        foreach (
                                                            $chartYears
                                                            as $yearOption
                                                        ) {

                                                        ?>

                                                            <option
                                                                value="<?= $yearOption ?>"
                                                                <?= $yearOption == $chartYear
                                                                    ? 'selected'
                                                                    : '' ?>
                                                            >

                                                                <?= $yearOption ?>

                                                            </option>

                                                        <?php } ?>

                                                    </select>

                                                </div>

                                            </div>


                                            <div class="chart-container">

                                                <canvas
                                                    id="yearlyComparisonChart"
                                                ></canvas>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- =================================================
                                 TABLES
                            ================================================= -->

                            <div class="row">


                                <!-- DEBTORS -->

                                <div class="col-md-12 col-lg-12 col-xl-4">

                                    <div class="card bg-white m-b-30">

                                        <div class="card-body">

                                            <h5
                                                class="header-title mb-4 mt-0"
                                            >

                                                Customers with Outstanding Balance

                                            </h5>

                                            <?php

                                            $debtors = mysqli_query(
                                                $conn,
                                                "
                                                SELECT
                                                    name,
                                                    phone,
                                                    balance,
                                                    last_payment_date
                                                FROM customers
                                                WHERE balance > 0
                                                ORDER BY balance DESC
                                                "
                                            );

                                            ?>

                                            <div class="table-responsive">

                                                <table class="table table-hover">

                                                    <thead>

                                                        <tr>

                                                            <th>
                                                                Name
                                                            </th>

                                                            <th>
                                                                Phone
                                                            </th>

                                                            <th>
                                                                Balance
                                                            </th>

                                                            <th>
                                                                Last Payment
                                                            </th>

                                                        </tr>

                                                    </thead>

                                                    <tbody>

                                                        <?php

                                                        if (
                                                            $debtors
                                                            &&
                                                            mysqli_num_rows(
                                                                $debtors
                                                            ) > 0
                                                        ) {

                                                            while (
                                                                $c =
                                                                mysqli_fetch_assoc(
                                                                    $debtors
                                                                )
                                                            ) {

                                                        ?>

                                                            <tr>

                                                                <td>

                                                                    <?= htmlspecialchars(
                                                                        $c['name']
                                                                    ) ?>

                                                                </td>

                                                                <td>

                                                                    <?= htmlspecialchars(
                                                                        $c['phone']
                                                                    ) ?>

                                                                </td>

                                                                <td
                                                                    style="
                                                                    color:red;
                                                                    font-weight:bold;
                                                                    "
                                                                >

                                                                    <?= number_format(
                                                                        (float)$c['balance'],
                                                                        2
                                                                    ) ?>

                                                                </td>

                                                                <td>

                                                                    <?= !empty(
                                                                        $c['last_payment_date']
                                                                    )
                                                                        ? htmlspecialchars(
                                                                            $c['last_payment_date']
                                                                        )
                                                                        : 'N/A' ?>

                                                                </td>

                                                            </tr>

                                                        <?php

                                                            }

                                                        }
                                                        else {

                                                        ?>

                                                            <tr>

                                                                <td colspan="4">

                                                                    No customers with
                                                                    outstanding balance

                                                                </td>

                                                            </tr>

                                                        <?php } ?>

                                                    </tbody>

                                                </table>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- MOST SOLD -->

                                <div class="col-md-12 col-lg-12 col-xl-4">

                                    <div class="card bg-white m-b-30">

                                        <div class="card-body">

                                            <h5
                                                class="header-title mt-0 mb-4"
                                            >

                                                Most Sold Items

                                            </h5>

                                            <?php

                                            $topItems = mysqli_query(
                                                $conn,
                                                "
                                                SELECT
                                                    product_names,
                                                    COUNT(*) AS total_sales
                                                FROM sales
                                                GROUP BY product_names
                                                ORDER BY total_sales DESC
                                                LIMIT 5
                                                "
                                            );

                                            ?>

                                            <div class="table-responsive">

                                                <table
                                                    class="table table-bordered"
                                                >

                                                    <thead>

                                                        <tr>

                                                            <th>
                                                                Product
                                                            </th>

                                                            <th>
                                                                Sales Count
                                                            </th>

                                                        </tr>

                                                    </thead>

                                                    <tbody>

                                                        <?php

                                                        if ($topItems) {

                                                            while (
                                                                $item =
                                                                mysqli_fetch_assoc(
                                                                    $topItems
                                                                )
                                                            ) {

                                                        ?>

                                                            <tr>

                                                                <td>

                                                                    <?= htmlspecialchars(
                                                                        $item['product_names']
                                                                    ) ?>

                                                                </td>

                                                                <td>

                                                                    <?= $item['total_sales'] ?>

                                                                </td>

                                                            </tr>

                                                        <?php

                                                            }

                                                        }
                                                        else {

                                                        ?>

                                                            <tr>

                                                                <td colspan="2">

                                                                    No sales recorded.

                                                                </td>

                                                            </tr>

                                                        <?php } ?>

                                                    </tbody>

                                                </table>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- TOP PROFIT -->

                                <div class="col-md-12 col-lg-12 col-xl-4">

                                    <div class="card bg-white m-b-30">

                                        <div class="card-body">

                                            <h5
                                                class="header-title mt-0 mb-4"
                                            >

                                                Top Profit Products

                                            </h5>

                                            <?php

                                            $topProfit = mysqli_query(
                                                $conn,
                                                "
                                                SELECT
                                                    si.pname,

                                                    COALESCE(
                                                        SUM(
                                                            COALESCE(
                                                                si.subtotal,
                                                                0
                                                            )
                                                            -
                                                            (
                                                                COALESCE(
                                                                    si.qty,
                                                                    0
                                                                )
                                                                *
                                                                COALESCE(
                                                                    NULLIF(
                                                                        si.unit_qty,
                                                                        0
                                                                    ),
                                                                    1
                                                                )
                                                                *
                                                                COALESCE(
                                                                    p.costperunit,
                                                                    0
                                                                )
                                                            )
                                                        ),
                                                        0
                                                    ) AS profit

                                                FROM sales_items si

                                                INNER JOIN products p
                                                    ON si.product_id =
                                                       p.productid

                                                INNER JOIN sales s
                                                    ON si.sale_id =
                                                       s.id

                                                GROUP BY
                                                    si.product_id,
                                                    si.pname

                                                ORDER BY
                                                    profit DESC

                                                LIMIT 5
                                                "
                                            );

                                            ?>

                                            <div class="table-responsive">

                                                <table
                                                    class="table table-bordered"
                                                >

                                                    <thead>

                                                        <tr>

                                                            <th>
                                                                Product
                                                            </th>

                                                            <th>
                                                                Profit
                                                            </th>

                                                        </tr>

                                                    </thead>

                                                    <tbody>

                                                        <?php

                                                        if ($topProfit) {

                                                            while (
                                                                $p =
                                                                mysqli_fetch_assoc(
                                                                    $topProfit
                                                                )
                                                            ) {

                                                        ?>

                                                            <tr>

                                                                <td>

                                                                    <?= htmlspecialchars(
                                                                        $p['pname']
                                                                    ) ?>

                                                                </td>

                                                                <td
                                                                    class="<?= ((float)$p['profit'] >= 0)
                                                                        ? 'text-success'
                                                                        : 'text-danger' ?>"
                                                                >

                                                                    GHS
                                                                    <?= number_format(
                                                                        (float)$p['profit'],
                                                                        2
                                                                    ) ?>

                                                                </td>

                                                            </tr>

                                                        <?php

                                                            }

                                                        }
                                                        else {

                                                        ?>

                                                            <tr>

                                                                <td colspan="2">

                                                                    No profit data available.

                                                                </td>

                                                            </tr>

                                                        <?php } ?>

                                                    </tbody>

                                                </table>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                        </div>


                        <!-- =================================================
                             RIGHT 20% - OUT OF STOCK
                        ================================================= -->

                        <div class="dashboard-right">

                            <div
                                class="card bg-white out-of-stock-card"
                            >

                                <div class="card-body">


                                    <div
                                        class="d-flex justify-content-between align-items-center mb-3"
                                    >

                                        <h5
                                            class="header-title mt-0 mb-0"
                                        >

                                            <i
                                                class="mdi mdi-package-variant-closed text-danger"
                                            ></i>

                                            Out of Stock

                                        </h5>


                                        <span
                                            class="badge badge-danger"
                                        >

                                            <?= $outOfStockCount ?>

                                        </span>

                                    </div>


                                    <div class="out-of-stock-list">

                                        <?php

                                        if (
                                            $outOfStockCount > 0
                                        ) {

                                            while (
                                                $stockItem =
                                                mysqli_fetch_assoc(
                                                    $outOfStock
                                                )
                                            ) {

                                        ?>

                                            <div
                                                class="out-of-stock-item"
                                            >


                                                <div
                                                    class="d-flex justify-content-between align-items-start"
                                                >

                                                    <div class="pr-2">

                                                        <div
                                                            class="out-of-stock-name"
                                                        >

                                                            <?= htmlspecialchars(
                                                                $stockItem['pname']
                                                            ) ?>

                                                        </div>


                                                        <?php

                                                        if (
                                                            !empty(
                                                                $stockItem['pdesc']
                                                            )
                                                        ) {

                                                        ?>

                                                            <small
                                                                class="text-muted"
                                                            >

                                                                <?= htmlspecialchars(
                                                                    $stockItem['pdesc']
                                                                ) ?>

                                                            </small>

                                                        <?php } ?>

                                                    </div>


                                                    <span
                                                        class="badge badge-danger"
                                                    >

                                                        <?= number_format(
                                                            (float)$stockItem['totalstock'],
                                                            0
                                                        ) ?>

                                                        <?= !empty(
                                                            $stockItem['unit']
                                                        )
                                                            ? ' ' . htmlspecialchars(
                                                                $stockItem['unit']
                                                            )
                                                            : '' ?>

                                                    </span>

                                                </div>


                                                <div
                                                    class="mt-2"
                                                >

                                                    <?php

                                                    if (
                                                        !empty(
                                                            $stockItem['category']
                                                        )
                                                    ) {

                                                    ?>

                                                        <small
                                                            class="text-muted"
                                                        >

                                                            <i
                                                                class="fa fa-tag"
                                                            ></i>

                                                            <?= htmlspecialchars(
                                                                $stockItem['category']
                                                            ) ?>

                                                        </small>

                                                    <?php

                                                    }
                                                    else {

                                                    ?>

                                                        <small
                                                            class="text-muted"
                                                        >

                                                            Uncategorized

                                                        </small>

                                                    <?php } ?>

                                                </div>


                                                <div
                                                    class="mt-2"
                                                >

                                                    <a
                                                        href="restockproducts.php?id=<?= urlencode(
                                                            $stockItem['productid']
                                                        ) ?>"
                                                        class="btn btn-sm btn-danger"
                                                    >

                                                        <i
                                                            class="fa fa-cart-arrow-down"
                                                        ></i>

                                                        Restock

                                                    </a>

                                                </div>


                                            </div>

                                        <?php

                                            }

                                        }
                                        else {

                                        ?>

                                            <div
                                                class="text-center text-muted py-5"
                                            >

                                                <i
                                                    class="mdi mdi-check-circle-outline"
                                                    style="font-size:45px;"
                                                ></i>

                                                <p
                                                    class="mb-0 mt-3"
                                                >

                                                    No products are
                                                    out of stock.

                                                </p>

                                            </div>

                                        <?php } ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                </div>

            </div>


        </div>


        <!-- =================================================
             FOOTER
        ================================================= -->

        <footer class="footer">

            <?php
            include "assets/sections/footers/footer.php";
            ?>

        </footer>


    </div>

</div>


<!-- =========================================================
     JAVASCRIPT LIBRARIES
========================================================= -->

<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/popper.min.js"></script>

<script src="assets/js/bootstrap.min.js"></script>

<script src="assets/js/modernizr.min.js"></script>

<script src="assets/js/detect.js"></script>

<script src="assets/js/fastclick.js"></script>

<script src="assets/js/jquery.slimscroll.js"></script>

<script src="assets/js/jquery.blockUI.js"></script>

<script src="assets/js/waves.js"></script>

<script src="assets/js/jquery.nicescroll.js"></script>

<script src="assets/js/jquery.scrollTo.min.js"></script>

<script src="assets/plugins/skycons/skycons.min.js"></script>

<script src="assets/plugins/raphael/raphael-min.js"></script>

<script src="assets/plugins/morris/morris.min.js"></script>

<script src="assets/pages/dashborad.js"></script>

<script src="assets/js/app.js"></script>

<script src="assets/js/chart.js"></script>


<script>

/* =========================================================
   SKYCONS
========================================================= */

if (typeof Skycons !== 'undefined') {

    var icons =
        new Skycons(
            {
                "color": "#fff"
            },
            {
                "resizeClear": true
            }
        );

    var list = [

        "clear-day",
        "clear-night",
        "partly-cloudy-day",
        "partly-cloudy-night",
        "cloudy",
        "rain",
        "sleet",
        "snow",
        "wind",
        "fog"

    ];

    var i;

    for (
        i = list.length;
        i--;
    ) {

        icons.set(
            list[i],
            list[i]
        );
    }

    icons.play();
}


/* =========================================================
   NICE SCROLL
========================================================= */

$(document).ready(function () {

    $("#boxscroll").niceScroll({

        cursorborder: "",
        cursorcolor: "#cecece",
        boxzoom: true

    });

    $("#boxscroll2").niceScroll({

        cursorborder: "",
        cursorcolor: "#cecece",
        boxzoom: true

    });

});


/* =========================================================
   FILTER
========================================================= */

function setFilter(type)
{
    window.location.href =
        '?filter=' + encodeURIComponent(type);
}


/* =========================================================
   MONTH FILTER
========================================================= */

function filterMonthYear(value)
{

    if (value === '') {

        window.location.href =
            '?filter=today';

        return;
    }

    window.location.href =
        '?monthyear=' +
        encodeURIComponent(value);
}


/* =========================================================
   CHART MONTH FILTER
========================================================= */

function filterChartMonth(value)
{

    if (!value) {
        return;
    }

    var url =
        new URL(
            window.location.href
        );

    url.searchParams.set(
        'chart_month',
        value
    );

    window.location.href =
        url.toString();
}


/* =========================================================
   CHART YEAR FILTER
========================================================= */

function filterChartYear(value)
{

    if (!value) {
        return;
    }

    var url =
        new URL(
            window.location.href
        );

    url.searchParams.set(
        'chart_year',
        value
    );

    window.location.href =
        url.toString();
}


/* =========================================================
   CHART DATA
========================================================= */

const monthlyTrendData =
    <?= json_encode(
        $monthlyTrendData,
        JSON_NUMERIC_CHECK
    ); ?>;

const yearChartData =
    <?= json_encode(
        $yearChartData,
        JSON_NUMERIC_CHECK
    ); ?>;


/* =========================================================
   CHART 1
   SALES VS PROFIT
========================================================= */

const monthlyTrendCanvas =
    document.getElementById(
        'monthlyTrendChart'
    );

if (monthlyTrendCanvas) {

    new Chart(
        monthlyTrendCanvas,
        {

            type: 'line',

            data: {

                labels:
                    monthlyTrendData.map(
                        function (item) {

                            return item.label;

                        }
                    ),

                datasets: [

                    {

                        label: 'Sales',

                        data:
                            monthlyTrendData.map(
                                function (item) {

                                    return item.sales;

                                }
                            ),

                        fill: false,

                        tension: 0.35,

                        borderWidth: 3,

                        pointRadius: 3,

                        pointHoverRadius: 6

                    },

                    {

                        label: 'Profit',

                        data:
                            monthlyTrendData.map(
                                function (item) {

                                    return item.profit;

                                }
                            ),

                        fill: false,

                        tension: 0.35,

                        borderWidth: 3,

                        pointRadius: 3,

                        pointHoverRadius: 6

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {

                    intersect: false,

                    mode: 'index'

                },

                plugins: {

                    legend: {

                        display: true,

                        position: 'top'

                    },

                    tooltip: {

                        callbacks: {

                            label:
                                function (context) {

                                    return (

                                        context.dataset.label
                                        +
                                        ': GHS '
                                        +
                                        Number(
                                            context.raw
                                        ).toLocaleString(
                                            undefined,
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            }
                                        )

                                    );

                                }

                        }

                    }

                },

                scales: {

                    x: {

                        title: {

                            display: true,

                            text: 'Day'

                        }

                    },

                    y: {

                        beginAtZero: true,

                        title: {

                            display: true,

                            text: 'Amount (GHS)'

                        },

                        ticks: {

                            callback:
                                function (value) {

                                    return (
                                        'GHS '
                                        +
                                        Number(
                                            value
                                        ).toLocaleString()
                                    );

                                }

                        }

                    }

                }

            }

        }
    );
}


/* =========================================================
   CHART 2
   YEARLY COMPARISON
========================================================= */

const yearlyComparisonCanvas =
    document.getElementById(
        'yearlyComparisonChart'
    );

if (yearlyComparisonCanvas) {

    new Chart(
        yearlyComparisonCanvas,
        {

            type: 'bar',

            data: {

                labels:
                    yearChartData.map(
                        function (item) {

                            return item.month;

                        }
                    ),

                datasets: [

                    {

                        label: 'Sales',

                        data:
                            yearChartData.map(
                                function (item) {

                                    return item.sales;

                                }
                            ),

                        borderWidth: 1

                    },

                    {

                        label: 'Purchases',

                        data:
                            yearChartData.map(
                                function (item) {

                                    return item.purchases;

                                }
                            ),

                        borderWidth: 1

                    },

                    {

                        label: 'Profit',

                        data:
                            yearChartData.map(
                                function (item) {

                                    return item.profit;

                                }
                            ),

                        borderWidth: 1

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {

                    intersect: false,

                    mode: 'index'

                },

                plugins: {

                    legend: {

                        display: true,

                        position: 'top'

                    },

                    tooltip: {

                        callbacks: {

                            label:
                                function (context) {

                                    return (

                                        context.dataset.label
                                        +
                                        ': GHS '
                                        +
                                        Number(
                                            context.raw
                                        ).toLocaleString(
                                            undefined,
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            }
                                        )

                                    );

                                }

                        }

                    }

                },

                scales: {

                    x: {

                        title: {

                            display: true,

                            text: 'Month'

                        },

                        stacked: false

                    },

                    y: {

                        beginAtZero: true,

                        title: {

                            display: true,

                            text: 'Amount (GHS)'

                        },

                        ticks: {

                            callback:
                                function (value) {

                                    return (
                                        'GHS '
                                        +
                                        Number(
                                            value
                                        ).toLocaleString()
                                    );

                                }

                        }

                    }

                }

            }

        }
    );
}

</script>


<!-- =========================================================
     MOBILE SCANNER
========================================================= -->

<script>

(function () {

    var scannerToken = null;

    var statusTimer = null;

    var heartbeatTimer = null;

    var scannerRunning = false;


    function scannerStatusBox()
    {

        return document.getElementById(
            'dashboardScannerStatus'
        );

    }


    function setScannerStatus(
        message,
        connected
    )
    {

        var box =
            scannerStatusBox();

        var text =
            document.getElementById(
                'dashboardScannerStatusText'
            );

        if (text) {

            text.textContent =
                message;
        }

        if (box) {

            box.classList.toggle(
                'connected',
                !!connected
            );

        }

    }


    function getScannerUrl()
    {

        var host =
            window.location.hostname
            ||
            'localhost';

        var port =
            window.location.port
                ? ':' + window.location.port
                : '';

        var protocol =
            window.location.protocol
            ||
            'http:';


        if (
            host === 'localhost'
            ||
            host === '127.0.0.1'
            ||
            host === '::1'
        ) {

            var detected =
                <?= json_encode(
                    $dashboardScannerIp
                ) ?>;

            if (detected) {

                host =
                    detected;
            }

        }


        return (
            protocol
            +
            '//'
            +
            host
            +
            port
            +
            '/philynda/mobile_scanner.php'
        );

    }


    function openScannerPage()
    {

        var url =
            getScannerUrl();

        var w =
            window.open(
                url,
                '_blank'
            );

        if (!w) {
            window.location.href = url;
        }

    }


    window.dashboardOpenMobileScanner =
        function ()
        {
            openScannerPage();
        };


    function jsonFetch(
        url,
        options
    )
    {

        return fetch(

            url,

            Object.assign(
                {
                    cache: 'no-store'
                },

                options || {}
            )

        )

        .then(
            function (response) {

                return response
                    .text()
                    .then(
                        function (text) {

                            var data;

                            try {

                                data =
                                    JSON.parse(
                                        text
                                    );

                            }
                            catch (e) {

                                throw new Error(
                                    'Scanner API did not return JSON (HTTP '
                                    +
                                    response.status
                                    +
                                    ').'
                                );

                            }

                            return {

                                response:
                                    response,

                                data:
                                    data

                            };

                        }
                    );

            }
        );

    }


    function checkExistingScanner()
    {

        return jsonFetch(
            'assets/scripts/mobile_scanner_api.php?action=status&_='
            +
            Date.now()
        )

        .then(
            function (result) {

                if (
                    !result.response.ok
                    ||
                    !result.data.success
                ) {

                    setScannerStatus(
                        'Not connected',
                        false
                    );

                    return false;
                }


                if (
                    !result.data.active
                    ||
                    !result.data.token
                ) {

                    setScannerStatus(
                        'Not connected',
                        false
                    );

                    return false;
                }


                scannerToken =
                    String(
                        result.data.token
                    );

                scannerRunning =
                    true;

                var existingCode =
                    document.getElementById(
                        'dashboardScannerCodeValue'
                    );

                if (existingCode && result.data.code) {
                    existingCode.textContent =
                        String(result.data.code);
                }


                setScannerStatus(

                    result.data.paired
                        ? 'Phone connected'
                        : 'Waiting for phone...',

                    !!result.data.paired

                );


                startDashboardHeartbeat();


                return true;

            }
        )

        .catch(
            function (error) {

                console.warn(
                    'Dashboard scanner status:',
                    error
                );

                setScannerStatus(
                    'Not connected',
                    false
                );

                return false;

            }
        );

    }


    window.dashboardStartMobileScanner =
        function ()
        {

            /*
             * The scanner button is intentionally available only on
             * mobile dashboard layouts.
             *
             * Open the scanner page immediately. This avoids popup
             * blockers and avoids relying on an asynchronous popup.
             * The scanner page itself will then use the existing
             * pairing code/session flow.
             */
            var scannerUrl = getScannerUrl();

            if (!scannerUrl) {
                setScannerStatus(
                    'Mobile scanner URL is unavailable.',
                    false
                );
                return;
            }

            window.location.href = scannerUrl;

        };


    function dashboardHeartbeat()
    {

        if (
            !scannerRunning
            ||
            !scannerToken
        ) {

            return;
        }


        jsonFetch(

            'assets/scripts/mobile_scanner_api.php?action=status&token='
            +
            encodeURIComponent(
                scannerToken
            )
            +
            '&_='
            +
            Date.now()

        )

        .then(
            function (result) {

                if (
                    !result.response.ok
                    ||
                    !result.data.success
                ) {

                    scannerRunning =
                        false;

                    scannerToken =
                        null;

                    setScannerStatus(
                        result.data.message
                            ||
                            'Scanner connection ended.',
                        false
                    );

                    return;

                }


                setScannerStatus(

                    result.data.paired
                        ? 'Phone connected'
                        : 'Waiting for phone...',

                    !!result.data.paired

                );

            }
        )

        .catch(
            function (error) {

                console.warn(
                    'Dashboard scanner heartbeat:',
                    error
                );

            }
        );

    }


    function startDashboardHeartbeat()
    {

        if (heartbeatTimer) {

            clearInterval(
                heartbeatTimer
            );

        }


        heartbeatTimer =
            setInterval(
                dashboardHeartbeat,
                60000
            );


        dashboardHeartbeat();

    }


    var dashboardScannerIpVisible =
        true;

    var dashboardScannerIp =
        <?= json_encode(
            $dashboardScannerIp
        ) ?>;


    function renderDashboardScannerIp()
    {

        var valueEl =
            document.getElementById(
                'dashboardScannerIpValue'
            );

        var toggle =
            document.getElementById(
                'dashboardScannerIpToggle'
            );


        if (!valueEl) {

            return;
        }


        var hasIp =
            !!dashboardScannerIp;


        if (!hasIp) {

            valueEl.textContent =
                'Unavailable';

            if (toggle) {

                toggle.style.display =
                    'none';

            }

            return;
        }


        if (dashboardScannerIpVisible) {

            valueEl.textContent =
                dashboardScannerIp;

            if (toggle) {

                toggle.innerHTML =
                    '<span class="fa fa-eye-slash"></span> Hide IP';

            }

        }
        else {

            valueEl.textContent =
                '••••••••';

            if (toggle) {

                toggle.innerHTML =
                    '<span class="fa fa-eye"></span> Show IP';

            }

        }

    }


    window.toggleDashboardScannerIp =
        function ()
        {

            dashboardScannerIpVisible =
                !dashboardScannerIpVisible;

            renderDashboardScannerIp();

        };


    window.dashboardStopMobileScanner =
        function ()
        {

            var button =
                document.getElementById(
                    'dashboardScannerDisconnect'
                );


            if (button) {

                button.disabled =
                    true;

            }


            if (!scannerToken) {

                scannerRunning =
                    false;

                setScannerStatus(
                    'Not connected',
                    false
                );

                if (button) {

                    button.disabled =
                        false;

                }

                return;

            }


            jsonFetch(

                'assets/scripts/mobile_scanner_api.php?action=stop',

                {

                    method: 'POST',

                    headers: {

                        'Content-Type':
                            'application/x-www-form-urlencoded'

                    },

                    body:
                        'token='
                        +
                        encodeURIComponent(
                            scannerToken
                        )

                }

            )

            .then(
                function (result) {

                    if (
                        !result.data
                        ||
                        result.data.success !== true
                    ) {

                        throw new Error(

                            (
                                result.data
                                &&
                                result.data.message
                            )
                            ||
                            'Unable to disconnect the mobile scanner.'

                        );

                    }


                    if (heartbeatTimer) {

                        clearInterval(
                            heartbeatTimer
                        );

                    }


                    if (statusTimer) {

                        clearInterval(
                            statusTimer
                        );

                    }


                    heartbeatTimer =
                        null;

                    statusTimer =
                        null;

                    scannerRunning =
                        false;

                    scannerToken =
                        null;

                    var disconnectedCode =
                        document.getElementById(
                            'dashboardScannerCodeValue'
                        );

                    if (disconnectedCode) {
                        disconnectedCode.textContent =
                            '------';
                    }


                    setScannerStatus(
                        'Not connected',
                        false
                    );

                }
            )

            .catch(
                function (error) {

                    console.error(
                        'Dashboard scanner disconnect:',
                        error
                    );

                    setScannerStatus(

                        error.message
                            ||
                            'Unable to disconnect the mobile scanner.',

                        false

                    );

                }
            )

            .finally(
                function () {

                    if (button) {

                        button.disabled =
                            false;

                    }

                }
            );

        };


    renderDashboardScannerIp();


    checkExistingScanner();

})();

</script>


<!-- =========================================================
     PRELOADER REMOVAL
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function ()
    {

        var preloader =
            document.getElementById(
                'preloader'
            );

        if (preloader) {

            preloader.style.display =
                'none';

        }

    }
);

</script>


<!-- =========================================================
     IDLE REFRESH
========================================================= -->

<script>

let idleTimer;

const idleTime =
    60000;


function resetIdleTimer()
{

    clearTimeout(
        idleTimer
    );


    idleTimer =
        setTimeout(
            function ()
            {

                window.location.reload();

            },
            idleTime
        );

}


[
    'mousemove',
    'mousedown',
    'click',
    'scroll',
    'keypress',
    'keydown',
    'touchstart'
].forEach(
    function (event)
    {

        document.addEventListener(
            event,
            resetIdleTimer,
            true
        );

    }
);


resetIdleTimer();

</script>


</body>

</html>
