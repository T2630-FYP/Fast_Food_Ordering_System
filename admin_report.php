<?php

// Block the report unless a valid administrator session is available.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("Location: admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

// Escape every database and filter value before rendering it in the report.
function admin_report_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Accept only a real YYYY-MM-DD value so invalid dates never reach a query.
function admin_report_valid_date($value)
{
	if($value==="")
	{
		return true;
	}
	$date = DateTime::createFromFormat("!Y-m-d",$value);
	return $date && $date->format("Y-m-d")===$value;
}

// Run one prepared report query with the shared optional date range.
function admin_report_query($connect,$sql,$start_date,$end_date)
{
	$rows = array();
	$stmt = mysqli_prepare($connect,$sql);
	if(!$stmt)
	{
		return $rows;
	}
	mysqli_stmt_bind_param($stmt,"ssss",$start_date,$start_date,$end_date,$end_date);
	if(mysqli_stmt_execute($stmt))
	{
		$result = mysqli_stmt_get_result($stmt);
		while($row = mysqli_fetch_assoc($result))
		{
			$rows[] = $row;
		}
	}
	mysqli_stmt_close($stmt);
	return $rows;
}

// Read and validate the date filter before any sales totals are calculated.
$start_date = trim((string)($_GET["start_date"] ?? ""));
$end_date = trim((string)($_GET["end_date"] ?? ""));
$filter_error = "";

if(!admin_report_valid_date($start_date) || !admin_report_valid_date($end_date))
{
	$filter_error = "Enter a valid start and end date.";
	$start_date = "";
	$end_date = "";
}
else if($start_date!=="" && $end_date!=="" && $start_date>$end_date)
{
	$filter_error = "The start date must be on or before the end date.";
	$start_date = "";
	$end_date = "";
}

// Every report query uses one business definition: active, non-cancelled orders
// whose effective payment record is Paid. The order date controls the period.
$paid_order_filter = "o.order_isDelete=0
	AND LOWER(o.order_status)<>'cancelled'
	AND LOWER(COALESCE(NULLIF(pay.payment_status,''),o.order_payment_status))='paid'
	AND (?='' OR DATE(o.order_date)>=?)
	AND (?='' OR DATE(o.order_date)<=?)";

$summary_rows = admin_report_query($connect,
	"SELECT COUNT(DISTINCT o.order_id) AS paid_orders,COALESCE(SUM(o.order_total),0) AS collected_revenue
	 FROM orders o
	 LEFT JOIN payments pay ON pay.payment_order=o.order_id
	 WHERE ".$paid_order_filter,
	$start_date,$end_date);
$summary = $summary_rows[0] ?? array("paid_orders"=>0,"collected_revenue"=>0);

$item_summary_rows = admin_report_query($connect,
	"SELECT COALESCE(SUM(oi.item_qty),0) AS units_sold,COALESCE(SUM(oi.item_subtotal),0) AS product_sales
	 FROM order_items oi
	 INNER JOIN orders o ON o.order_id=oi.item_order
	 LEFT JOIN payments pay ON pay.payment_order=o.order_id
	 WHERE ".$paid_order_filter,
	$start_date,$end_date);
$item_summary = $item_summary_rows[0] ?? array("units_sold"=>0,"product_sales"=>0);

// Category totals retain historical order-item values while using the current
// product category when the catalogue record is still available.
$category_sales = admin_report_query($connect,
	"SELECT COALESCE(NULLIF(p.product_category,''),'Uncategorised') AS category_name,
		COALESCE(SUM(oi.item_qty),0) AS units_sold,
		COALESCE(SUM(oi.item_subtotal),0) AS product_sales
	 FROM order_items oi
	 INNER JOIN orders o ON o.order_id=oi.item_order
	 LEFT JOIN payments pay ON pay.payment_order=o.order_id
	 LEFT JOIN product p ON p.product_id=oi.item_product
	 WHERE ".$paid_order_filter."
	 GROUP BY COALESCE(NULLIF(p.product_category,''),'Uncategorised')
	 ORDER BY product_sales DESC,units_sold DESC,category_name",
	$start_date,$end_date);

// Rank products by sold quantity and then revenue, using the order-item name as
// a stable fallback if a catalogue product is no longer available.
$best_sellers = admin_report_query($connect,
	"SELECT oi.item_product,
		COALESCE(NULLIF(MAX(p.product_name),''),MAX(oi.item_name)) AS product_name,
		COALESCE(SUM(oi.item_qty),0) AS units_sold,
		COALESCE(SUM(oi.item_subtotal),0) AS product_sales
	 FROM order_items oi
	 INNER JOIN orders o ON o.order_id=oi.item_order
	 LEFT JOIN payments pay ON pay.payment_order=o.order_id
	 LEFT JOIN product p ON p.product_id=oi.item_product
	 WHERE ".$paid_order_filter."
	 GROUP BY oi.item_product
	 ORDER BY units_sold DESC,product_sales DESC,product_name
	 LIMIT 10",
	$start_date,$end_date);

// Daily rows make the filtered totals traceable without adding hard-coded data.
$daily_sales = admin_report_query($connect,
	"SELECT DATE(o.order_date) AS sales_date,COUNT(DISTINCT o.order_id) AS paid_orders,
		COALESCE(SUM(o.order_total),0) AS collected_revenue
	 FROM orders o
	 LEFT JOIN payments pay ON pay.payment_order=o.order_id
	 WHERE ".$paid_order_filter."
	 GROUP BY DATE(o.order_date)
	 ORDER BY sales_date DESC",
	$start_date,$end_date);

$paid_orders = (int)$summary["paid_orders"];
$collected_revenue = (float)$summary["collected_revenue"];
$units_sold = (int)$item_summary["units_sold"];
$product_sales_total = (float)$item_summary["product_sales"];
$average_order = $paid_orders>0 ? $collected_revenue/$paid_orders : 0;

if($start_date!=="" && $end_date!=="")
{
	$period_label = date("d M Y",strtotime($start_date))." – ".date("d M Y",strtotime($end_date));
}
else if($start_date!=="")
{
	$period_label = "From ".date("d M Y",strtotime($start_date));
}
else if($end_date!=="")
{
	$period_label = "Up to ".date("d M Y",strtotime($end_date));
}
else
{
	$period_label = "All recorded dates";
}

$today = date("Y-m-d");
$seven_days_ago = date("Y-m-d",strtotime("-6 days"));
$month_start = date("Y-m-01");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sales Report - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_report.php"); ?>

	<!-- Report heading explains the shared sales definition used by every total. -->
	<section class="admin-page-heading admin-report-heading">
		<div>
			<p class="admin-eyebrow">SALES ANALYTICS</p>
			<h1>Sales Report</h1>
			<p>Review collected revenue, category performance and best sellers using paid, non-cancelled orders.</p>
			<p class="admin-print-context">Report period: <?php echo admin_report_html($period_label); ?> · Printed <?php echo date("d M Y, h:i A"); ?></p>
		</div>
		<!-- Print the currently selected report period and database totals. -->
		<div class="admin-output-actions">
			<button type="button" class="admin-secondary-button" onclick="window.print()">Print Report</button>
			<span class="admin-live-indicator"><i></i> Live database</span>
		</div>
	</section>

	<?php if($filter_error!==""): ?>
		<div class="admin-alert admin-alert-error" role="alert"><?php echo admin_report_html($filter_error); ?> The report has been reset to all dates.</div>
	<?php endif; ?>

	<!-- GET filters make the selected report period bookmarkable and repeatable. -->
	<section class="admin-report-filter-panel">
		<div class="admin-report-filter-copy">
			<span>REPORT PERIOD</span>
			<strong><?php echo admin_report_html($period_label); ?></strong>
			<small>Filtered by order date</small>
		</div>
		<form class="admin-report-filter-form" method="get" action="admin_report.php">
			<label><span>Start date</span><input type="date" name="start_date" value="<?php echo admin_report_html($start_date); ?>"></label>
			<label><span>End date</span><input type="date" name="end_date" value="<?php echo admin_report_html($end_date); ?>"></label>
			<div class="admin-report-filter-actions">
				<button type="submit">Apply Dates</button>
				<a href="admin_report.php">Reset</a>
			</div>
		</form>
		<nav class="admin-report-presets" aria-label="Report date presets">
			<a href="admin_report.php?start_date=<?php echo $today; ?>&amp;end_date=<?php echo $today; ?>">Today</a>
			<a href="admin_report.php?start_date=<?php echo $seven_days_ago; ?>&amp;end_date=<?php echo $today; ?>">Last 7 Days</a>
			<a href="admin_report.php?start_date=<?php echo $month_start; ?>&amp;end_date=<?php echo $today; ?>">This Month</a>
			<a href="admin_report.php">All Time</a>
		</nav>
	</section>

	<!-- Every summary card is calculated from the same paid-order date range. -->
	<section class="admin-report-stat-grid" aria-label="Filtered sales totals">
		<article data-report-metric="paid-orders"><span>OR</span><div><strong><?php echo $paid_orders; ?></strong><small>Paid Orders</small></div></article>
		<article data-report-metric="units-sold"><span>UN</span><div><strong><?php echo $units_sold; ?></strong><small>Units Sold</small></div></article>
		<article data-report-metric="product-sales"><span>PS</span><div><strong>RM <?php echo number_format($product_sales_total,2); ?></strong><small>Product Sales</small></div></article>
		<article class="accent" data-report-metric="collected-revenue"><span>RM</span><div><strong>RM <?php echo number_format($collected_revenue,2); ?></strong><small>Collected Revenue</small></div></article>
		<article data-report-metric="average-order"><span>AV</span><div><strong>RM <?php echo number_format($average_order,2); ?></strong><small>Average Order</small></div></article>
	</section>

	<p class="admin-report-definition"><strong>Report rule:</strong> only Paid, non-cancelled, non-deleted orders are included. Product Sales uses item subtotals; Collected Revenue uses final order totals and may include delivery charges.</p>

	<section class="admin-report-grid">
		<!-- Category performance answers where product revenue comes from. -->
		<article class="admin-report-panel admin-report-category-panel">
			<header class="admin-section-heading">
				<div><p class="admin-eyebrow">CATEGORY PERFORMANCE</p><h2>Sales by Category</h2></div>
				<span><?php echo count($category_sales); ?> categor<?php echo count($category_sales)===1 ? "y" : "ies"; ?></span>
			</header>
			<?php if($category_sales): ?>
				<div class="admin-report-table-wrap">
					<table class="admin-report-table">
						<thead><tr><th>Category</th><th>Units</th><th>Product Sales</th><th>Share</th></tr></thead>
						<tbody>
						<?php foreach($category_sales as $category): ?>
							<?php $category_share = $product_sales_total>0 ? round(((float)$category["product_sales"]/$product_sales_total)*100,1) : 0; ?>
							<tr data-category="<?php echo admin_report_html($category["category_name"]); ?>">
								<td><strong><?php echo admin_report_html($category["category_name"]); ?></strong></td>
								<td><?php echo (int)$category["units_sold"]; ?></td>
								<td>RM <?php echo number_format((float)$category["product_sales"],2); ?></td>
								<td><div class="admin-report-share"><span style="width:<?php echo min(100,max(0,$category_share)); ?>%"></span></div><small><?php echo number_format($category_share,1); ?>%</small></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="admin-empty-state"><span>0</span><strong>No paid category sales</strong><p>Try another date range or confirm an order payment.</p></div>
			<?php endif; ?>
		</article>

		<!-- Product ranking uses the same paid orders as the summary cards. -->
		<article class="admin-report-panel admin-report-best-panel">
			<header class="admin-section-heading">
				<div><p class="admin-eyebrow">PRODUCT PERFORMANCE</p><h2>Best Sellers</h2></div>
				<span>Top 10</span>
			</header>
			<?php if($best_sellers): ?>
				<ol class="admin-report-ranking">
				<?php foreach($best_sellers as $index=>$product): ?>
					<li data-product-id="<?php echo admin_report_html($product["item_product"]); ?>">
						<b><?php echo $index+1; ?></b>
						<div><strong><?php echo admin_report_html($product["product_name"]); ?></strong><small><?php echo admin_report_html($product["item_product"]); ?> · <?php echo (int)$product["units_sold"]; ?> unit<?php echo (int)$product["units_sold"]===1 ? "" : "s"; ?></small></div>
						<span>RM <?php echo number_format((float)$product["product_sales"],2); ?></span>
					</li>
				<?php endforeach; ?>
				</ol>
			<?php else: ?>
				<div class="admin-empty-state"><span>0</span><strong>No best sellers for this period</strong><p>Paid product sales will appear here.</p></div>
			<?php endif; ?>
		</article>

		<!-- Daily totals make the source of the filtered revenue easy to verify. -->
		<article class="admin-report-panel admin-report-daily-panel">
			<header class="admin-section-heading">
				<div><p class="admin-eyebrow">DAILY BREAKDOWN</p><h2>Paid Sales by Date</h2></div>
				<span><?php echo count($daily_sales); ?> active day<?php echo count($daily_sales)===1 ? "" : "s"; ?></span>
			</header>
			<?php if($daily_sales): ?>
				<div class="admin-report-table-wrap">
					<table class="admin-report-table">
						<thead><tr><th>Date</th><th>Paid Orders</th><th>Collected Revenue</th></tr></thead>
						<tbody>
						<?php foreach($daily_sales as $day): ?>
							<tr data-sales-date="<?php echo admin_report_html($day["sales_date"]); ?>"><td><strong><?php echo admin_report_html(date("d M Y",strtotime($day["sales_date"]))); ?></strong></td><td><?php echo (int)$day["paid_orders"]; ?></td><td>RM <?php echo number_format((float)$day["collected_revenue"],2); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="admin-empty-state"><span>0</span><strong>No paid sales in this period</strong><p>Reset the dates to review all recorded sales.</p></div>
			<?php endif; ?>
		</article>
	</section>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
