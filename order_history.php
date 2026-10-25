<?php
// Only a logged-in customer can view their order history.
session_start();
if(!isset($_SESSION["customer_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");

$mid = (int)$_SESSION["customer_id"];
$orders = array();
$history_error = "";

// Escape database values before displaying them in the page.
function order_history_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// Display every saved order time in one consistent Malaysian date-time format.
function order_history_datetime($value)
{
	$timestamp = strtotime((string)$value);
	return $timestamp===false ? "Unavailable" : date("d M Y, h:i A",$timestamp);
}

// Map stored statuses to a small, controlled set of visual badge classes.
function order_history_status_class($status,$group)
{
	$normalised = strtolower(trim((string)$status));
	$known = array(
		"payment" => array("paid","pending","unpaid","failed"),
		"order" => array("preparing","delivery","completed","cancelled")
	);

	if(isset($known[$group]) && in_array($normalised,$known[$group],true))
	{
		return "order-status-".$normalised;
	}

	return "order-status-neutral";
}

// Read only the current customer's active orders, with the latest order first.
$history_sql = "SELECT o.order_id,o.order_date,o.order_total,o.order_payment,o.order_payment_status,o.order_status,"
	."COALESCE(p.payment_status,o.order_payment_status) AS display_payment_status,"
	."(SELECT COALESCE(SUM(oi.item_qty),0) FROM order_items oi WHERE oi.item_order=o.order_id) AS total_item_qty "
	."FROM orders o LEFT JOIN payments p ON p.payment_order=o.order_id "
	."WHERE o.order_customer=? AND o.order_isDelete=0 "
	."ORDER BY o.order_date DESC,o.order_id DESC";
$history_stmt = mysqli_prepare($connect,$history_sql);
if($history_stmt)
{
	mysqli_stmt_bind_param($history_stmt,"i",$mid);
	if(mysqli_stmt_execute($history_stmt))
	{
		$history_result = mysqli_stmt_get_result($history_stmt);
		while($history_row = mysqli_fetch_assoc($history_result))
		{
			$orders[] = $history_row;
		}
	}
	else
	{
		$history_error = "Your order history is temporarily unavailable. Please try again.";
	}
	mysqli_stmt_close($history_stmt);
}
else
{
	$history_error = "Your order history is temporarily unavailable. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Customer order history page -->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<title>Order History</title>
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!-- Customer navigation bar -->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main"><!-- Current customer's order history content -->

<div class="order-page-heading"><!-- Page heading and ordering shortcut -->
<div>
<p class="checkout-step-label">YOUR PURCHASES</p>
<h2 class="section-title">My Order History</h2>
<p class="intro">Review payment and order progress, then open any order for full details.</p>
</div>
<a class="order-heading-link" href="category.php">Continue Shopping</a>
</div>

<?php if($history_error!=="") { ?>
<div class="checkout-message checkout-message-error" role="alert"><?php echo order_history_html($history_error); ?></div>
<?php } else if(count($orders)>0) { ?>

<section class="order-history-card" aria-label="Customer orders"><!-- Desktop order history table -->
<table class="order-history-table">
<thead>
<tr>
<th scope="col">Order</th>
<th scope="col">Date &amp; Time</th>
<th scope="col">Items</th>
<th scope="col">Total</th>
<th scope="col">Payment Method</th>
<th scope="col">Payment Status</th>
<th scope="col">Order Status</th>
<th scope="col"><span class="checkout-visually-hidden">Order action</span></th>
</tr>
</thead>
<tbody>
<?php foreach($orders as $order) { ?>
<?php
$payment_status = (string)$order["display_payment_status"];
$order_status = (string)$order["order_status"];
?>
<tr>
<td><strong class="order-number">#<?php echo (int)$order["order_id"]; ?></strong></td>
<td><time datetime="<?php echo order_history_html(date("c",strtotime($order["order_date"]))); ?>"><?php echo order_history_html(order_history_datetime($order["order_date"])); ?></time></td>
<td><?php echo (int)$order["total_item_qty"]; ?> item<?php echo (int)$order["total_item_qty"]===1 ? "" : "s"; ?></td>
<td><strong class="order-total">RM <?php echo number_format((float)$order["order_total"],2); ?></strong></td>
<td><?php echo order_history_html($order["order_payment"]); ?></td>
<td><span class="order-status-badge <?php echo order_history_status_class($payment_status,"payment"); ?>"><?php echo order_history_html($payment_status); ?></span></td>
<td><span class="order-status-badge <?php echo order_history_status_class($order_status,"order"); ?>"><?php echo order_history_html($order_status); ?></span></td>
<td class="order-history-action"><a href="order_details.php?order_id=<?php echo (int)$order["order_id"]; ?>">View Details</a></td>
</tr>
<?php } ?>
</tbody>
</table>
</section>

<?php } else { ?>

<section class="order-empty-state"><!-- Clear empty state for customers with no orders -->
<div class="order-empty-icon" aria-hidden="true">&#128230;</div>
<h3>No Orders Yet</h3>
<p>Your completed checkouts will appear here with their payment and order status.</p>
<a class="btn" href="category.php">Browse the Menu</a>
</section>

<?php } ?>

</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
