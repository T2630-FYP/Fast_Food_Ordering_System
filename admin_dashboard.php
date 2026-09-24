<?php

// Block this page if the administrator is not logged in.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("location:admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

// Return one numeric dashboard value while keeping a safe fallback when a
// database query cannot be completed.
function easyorder_dashboard_scalar($connect,$sql,$column,$fallback=0)
{
	$result = mysqli_query($connect,$sql);
	if($result && ($row = mysqli_fetch_assoc($result)))
	{
		return $row[$column];
	}

	return $fallback;
}

// Return result rows for compact dashboard lists and charts.
function easyorder_dashboard_rows($connect,$sql)
{
	$rows = array();
	$result = mysqli_query($connect,$sql);
	if($result)
	{
		while($row = mysqli_fetch_assoc($result))
		{
			$rows[] = $row;
		}
	}

	return $rows;
}

// Map database statuses to the shared visual badge styles.
function easyorder_dashboard_status_class($status)
{
	$normalized = strtolower(trim((string)$status));
	if(in_array($normalized,array("paid","delivered","completed","picked up"),true))
	{
		return "status-success";
	}
	if(in_array($normalized,array("cancelled","failed","declined"),true))
	{
		return "status-danger";
	}
	if(in_array($normalized,array("preparing","out for delivery","processing"),true))
	{
		return "status-info";
	}

	return "status-warning";
}

// Overview counts use active records only. Revenue is counted only after a
// payment is marked Paid and excludes cancelled orders.
$total_members = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM member WHERE member_isDelete=0","total");
$total_products = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM product WHERE product_isDelete=0","total");
$total_orders = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM orders WHERE order_isDelete=0","total");
$total_revenue = (float)easyorder_dashboard_scalar($connect,"SELECT COALESCE(SUM(order_total),0) AS total FROM orders WHERE order_isDelete=0 AND LOWER(order_payment_status)='paid' AND LOWER(order_status)<>'cancelled'","total",0);

// Recent sales KPIs follow the same paid and non-cancelled sales definition.
$today_orders = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM orders WHERE order_isDelete=0 AND DATE(order_date)=CURDATE()","total");
$today_sales = (float)easyorder_dashboard_scalar($connect,"SELECT COALESCE(SUM(order_total),0) AS total FROM orders WHERE order_isDelete=0 AND DATE(order_date)=CURDATE() AND LOWER(order_payment_status)='paid' AND LOWER(order_status)<>'cancelled'","total",0);
$seven_day_sales = (float)easyorder_dashboard_scalar($connect,"SELECT COALESCE(SUM(order_total),0) AS total FROM orders WHERE order_isDelete=0 AND order_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND LOWER(order_payment_status)='paid' AND LOWER(order_status)<>'cancelled'","total",0);
$uncollected_payments = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM orders WHERE order_isDelete=0 AND LOWER(order_status)<>'cancelled' AND LOWER(order_payment_status) IN ('pending','unpaid')","total");

$eligible_orders = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM orders WHERE order_isDelete=0 AND LOWER(order_status)<>'cancelled'","total");
$paid_orders = (int)easyorder_dashboard_scalar($connect,"SELECT COUNT(*) AS total FROM orders WHERE order_isDelete=0 AND LOWER(order_status)<>'cancelled' AND LOWER(order_payment_status)='paid'","total");
$collection_rate = $eligible_orders>0 ? round(($paid_orders/$eligible_orders)*100) : 0;

// Build a complete seven-day chart, including zero-value dates with no sales.
$sales_by_date = array();
$sales_rows = easyorder_dashboard_rows($connect,"SELECT DATE(order_date) AS sales_date,COALESCE(SUM(order_total),0) AS sales_total FROM orders WHERE order_isDelete=0 AND order_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND LOWER(order_payment_status)='paid' AND LOWER(order_status)<>'cancelled' GROUP BY DATE(order_date) ORDER BY sales_date");
foreach($sales_rows as $sales_row)
{
	$sales_by_date[$sales_row["sales_date"]] = (float)$sales_row["sales_total"];
}

$sales_chart = array();
for($day_offset=6;$day_offset>=0;$day_offset--)
{
	$date_key = date("Y-m-d",strtotime("-".$day_offset." days"));
	$sales_chart[] = array(
		"date"=>$date_key,
		"label"=>date("D",strtotime($date_key)),
		"value"=>$sales_by_date[$date_key] ?? 0
	);
}
$max_chart_sales = max(array_column($sales_chart,"value"));

// Today's best sellers and latest orders are read directly from the live
// order, order item, member, and payment-status relationships.
$top_products = easyorder_dashboard_rows($connect,"SELECT oi.item_name,SUM(oi.item_qty) AS units_sold,SUM(oi.item_subtotal) AS sales_total FROM order_items oi INNER JOIN orders o ON o.order_id=oi.item_order WHERE o.order_isDelete=0 AND DATE(o.order_date)=CURDATE() AND LOWER(o.order_payment_status)='paid' AND LOWER(o.order_status)<>'cancelled' GROUP BY oi.item_product,oi.item_name ORDER BY units_sold DESC,sales_total DESC LIMIT 3");
$recent_orders = easyorder_dashboard_rows($connect,"SELECT o.order_id,o.order_date,o.order_total,o.order_payment,o.order_payment_status,o.order_status,m.member_name FROM orders o INNER JOIN member m ON m.member_id=o.order_member WHERE o.order_isDelete=0 ORDER BY o.order_date DESC,o.order_id DESC LIMIT 6");

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | EasyOrder</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">
</head>

<body class="admin-body">

<?php easyorder_admin_shell_start("admin_dashboard.php"); ?>

<!-- Dashboard heading and live data definition. -->
<section class="admin-page-heading">
	<div>
		<p class="admin-eyebrow">OVERVIEW</p>
		<h1>Dashboard</h1>
		<p>Monitor orders, collected sales and product performance from one workspace.</p>
	</div>
	<span class="admin-live-indicator"><i></i> Live database</span>
</section>

<!-- High-level system totals. -->
<section class="admin-stat-grid" aria-label="EasyOrder overview statistics">
	<article class="admin-stat-card">
		<span class="admin-stat-icon">ME</span>
		<div><strong><?php echo $total_members; ?></strong><span>Active Members</span><small>Registered customer accounts</small></div>
	</article>
	<article class="admin-stat-card">
		<span class="admin-stat-icon">PR</span>
		<div><strong><?php echo $total_products; ?></strong><span>Active Products</span><small>Available catalogue records</small></div>
	</article>
	<article class="admin-stat-card">
		<span class="admin-stat-icon">OR</span>
		<div><strong><?php echo $total_orders; ?></strong><span>Total Orders</span><small>Non-deleted order records</small></div>
	</article>
	<article class="admin-stat-card accent">
		<span class="admin-stat-icon">RM</span>
		<div><strong>RM <?php echo number_format($total_revenue,2); ?></strong><span>Collected Revenue</span><small>Paid, non-cancelled orders</small></div>
	</article>
</section>

<section class="admin-dashboard-grid">
	<!-- Seven-day paid-sales chart. -->
	<article class="admin-panel admin-sales-panel">
		<header class="admin-panel-heading">
			<div><p>SALES PERFORMANCE</p><h2>Paid Sales — Last 7 Days</h2></div>
			<strong>RM <?php echo number_format($seven_day_sales,2); ?></strong>
		</header>
		<div class="admin-bar-chart" role="img" aria-label="Paid sales totals for the last seven days">
			<?php foreach($sales_chart as $chart_day): ?>
				<?php $bar_height = $max_chart_sales>0 ? max(5,round(($chart_day["value"]/$max_chart_sales)*100)) : 3; ?>
				<div class="admin-chart-column" title="<?php echo htmlspecialchars(date("d M Y",strtotime($chart_day["date"])),ENT_QUOTES,"UTF-8"); ?>: RM <?php echo number_format($chart_day["value"],2); ?>">
					<span class="admin-chart-value">RM <?php echo number_format($chart_day["value"],0); ?></span>
					<span class="admin-chart-bar" style="height:<?php echo (int)$bar_height; ?>%;"></span>
					<strong><?php echo htmlspecialchars($chart_day["label"],ENT_QUOTES,"UTF-8"); ?></strong>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="admin-panel-note">Only orders with a Paid payment status are included in sales figures.</p>
	</article>

	<!-- Operational KPIs that need administrator attention. -->
	<article class="admin-panel admin-kpi-panel">
		<header class="admin-panel-heading"><div><p>RECENT SALES KPI</p><h2>Collection Snapshot</h2></div></header>
		<div class="admin-kpi-list">
			<div><span>Today's orders</span><strong><?php echo $today_orders; ?></strong></div>
			<div><span>Today's paid sales</span><strong>RM <?php echo number_format($today_sales,2); ?></strong></div>
			<div><span>Payment collection</span><strong><?php echo $collection_rate; ?>%</strong></div>
			<div class="attention"><span>Pending / unpaid</span><strong><?php echo $uncollected_payments; ?></strong></div>
		</div>
	</article>

	<!-- Today's top three products use paid order item quantities. -->
	<article class="admin-panel admin-best-seller-panel">
		<header class="admin-panel-heading"><div><p>PRODUCT PERFORMANCE</p><h2>Today’s Top 3 Best Sellers</h2></div></header>
		<?php if(count($top_products)>0): ?>
			<ol class="admin-best-seller-list">
				<?php foreach($top_products as $index=>$product): ?>
					<li>
						<span class="admin-rank"><?php echo $index+1; ?></span>
						<div><strong><?php echo htmlspecialchars($product["item_name"],ENT_QUOTES,"UTF-8"); ?></strong><small><?php echo (int)$product["units_sold"]; ?> unit<?php echo (int)$product["units_sold"]===1 ? "" : "s"; ?> sold</small></div>
						<b>RM <?php echo number_format($product["sales_total"],2); ?></b>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else: ?>
			<div class="admin-empty-state"><span>0</span><strong>No paid product sales today</strong><p>Best sellers will appear after a paid order is recorded.</p></div>
		<?php endif; ?>
	</article>

	<!-- Latest order activity includes both payment and fulfilment states. -->
	<article class="admin-panel admin-recent-orders-panel">
		<header class="admin-panel-heading">
			<div><p>ORDER ACTIVITY</p><h2>Recent Orders</h2></div>
			<a href="admin_order.php">Manage Orders</a>
		</header>
		<?php if(count($recent_orders)>0): ?>
			<div class="admin-table-wrap">
				<table class="admin-dashboard-table">
					<thead><tr><th>Order</th><th>Customer</th><th>Date &amp; Time</th><th>Total</th><th>Payment</th><th>Order Status</th></tr></thead>
					<tbody>
					<?php foreach($recent_orders as $order): ?>
						<tr>
							<td><a class="admin-order-number" href="admin_order_details.php?order_id=<?php echo (int)$order["order_id"]; ?>">#<?php echo (int)$order["order_id"]; ?></a><small><?php echo htmlspecialchars($order["order_payment"],ENT_QUOTES,"UTF-8"); ?></small></td>
							<td><?php echo htmlspecialchars($order["member_name"],ENT_QUOTES,"UTF-8"); ?></td>
							<td><?php echo htmlspecialchars(date("d M Y, h:i A",strtotime($order["order_date"])),ENT_QUOTES,"UTF-8"); ?></td>
							<td>RM <?php echo number_format($order["order_total"],2); ?></td>
							<td><span class="admin-status-badge <?php echo easyorder_dashboard_status_class($order["order_payment_status"]); ?>"><?php echo htmlspecialchars($order["order_payment_status"],ENT_QUOTES,"UTF-8"); ?></span></td>
							<td><span class="admin-status-badge <?php echo easyorder_dashboard_status_class($order["order_status"]); ?>"><?php echo htmlspecialchars($order["order_status"],ENT_QUOTES,"UTF-8"); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<div class="admin-empty-state"><span>0</span><strong>No orders yet</strong><p>New customer orders will appear here.</p></div>
		<?php endif; ?>
	</article>
</section>

<?php easyorder_admin_shell_end(); ?>

</body>
</html>
