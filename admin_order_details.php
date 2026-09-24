<?php

// Only authenticated administrators can view customer order records.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("location:admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

if(empty($_SESSION["admin_order_csrf"]))
{
	$_SESSION["admin_order_csrf"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["admin_order_csrf"];

// Escape all customer, order and payment values before rendering them.
function admin_order_details_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// Present stored date-time values in a readable administrator format.
function admin_order_details_datetime($value)
{
	if($value===null || $value==="")
	{
		return "Not available";
	}
	$timestamp = strtotime((string)$value);
	return $timestamp===false ? "Not available" : date("d M Y, h:i A",$timestamp);
}

// Restrict status values to known visual badge classes.
function admin_order_details_status_class($status)
{
	$normalised = strtolower(trim((string)$status));
	if(in_array($normalised,array("paid","delivered","completed","picked up"),true))
	{
		return "status-success";
	}
	if(in_array($normalised,array("cancelled","failed","declined"),true))
	{
		return "status-danger";
	}
	if(in_array($normalised,array("preparing","ready for pickup","out for delivery","processing"),true))
	{
		return "status-info";
	}
	return "status-warning";
}

$requested_order_id = filter_var($_GET["order_id"] ?? null,FILTER_VALIDATE_INT,array("options"=>array("min_range"=>1)));
$order_id = $requested_order_id===false ? 0 : (int)$requested_order_id;
$order = null;
$items = array();
$page_error = "";
$http_status = 200;

if($order_id<=0)
{
	$http_status = 404;
	$page_error = "This order is unavailable.";
}
else
{
	// Join the customer and optional payment row into one administrator record.
	$order_sql = "SELECT o.order_id,o.order_date,o.order_total,o.order_payment,o.order_payment_status,o.order_delivery,o.order_address,o.order_status,"
		."m.member_id,m.member_name,m.member_email,m.member_phone,m.member_address,m.member_city,m.member_state,m.member_postcode,"
		."p.payment_reference,p.payment_method AS saved_payment_method,p.payment_amount,p.payment_status AS saved_payment_status,p.payment_paid_at,p.payment_created_at "
		."FROM orders o INNER JOIN member m ON m.member_id=o.order_member "
		."LEFT JOIN payments p ON p.payment_order=o.order_id "
		."WHERE o.order_id=? AND o.order_isDelete=0 LIMIT 1";
	$order_stmt = mysqli_prepare($connect,$order_sql);
	if(!$order_stmt)
	{
		$http_status = 500;
		$page_error = "Order details are temporarily unavailable. Please try again.";
	}
	else
	{
		mysqli_stmt_bind_param($order_stmt,"i",$order_id);
		if(mysqli_stmt_execute($order_stmt))
		{
			$order_result = mysqli_stmt_get_result($order_stmt);
			$order = mysqli_fetch_assoc($order_result) ?: null;
		}
		else
		{
			$http_status = 500;
			$page_error = "Order details are temporarily unavailable. Please try again.";
		}
		mysqli_stmt_close($order_stmt);
	}

	if(!$order && $page_error==="")
	{
		$http_status = 404;
		$page_error = "This order is unavailable.";
	}
}

if($order)
{
	// Read the immutable product snapshots captured during checkout.
	$item_stmt = mysqli_prepare($connect,"SELECT item_product,item_name,item_price,item_qty,item_subtotal FROM order_items WHERE item_order=? ORDER BY item_id");
	if($item_stmt)
	{
		mysqli_stmt_bind_param($item_stmt,"i",$order_id);
		if(mysqli_stmt_execute($item_stmt))
		{
			$item_result = mysqli_stmt_get_result($item_stmt);
			while($item = mysqli_fetch_assoc($item_result))
			{
				$items[] = $item;
			}
		}
		else
		{
			$http_status = 500;
			$page_error = "The items for this order are temporarily unavailable.";
		}
		mysqli_stmt_close($item_stmt);
	}
	else
	{
		$http_status = 500;
		$page_error = "The items for this order are temporarily unavailable.";
	}
}

http_response_code($http_status);

$item_subtotal = 0.00;
foreach($items as $item)
{
	$item_subtotal += (float)$item["item_subtotal"];
}
$delivery_fee = $order ? max(0.00,(float)$order["order_total"]-$item_subtotal) : 0.00;
$payment_status = $order ? (string)($order["saved_payment_status"] ?: $order["order_payment_status"]) : "";
$payment_method = $order ? (string)($order["saved_payment_method"] ?: $order["order_payment"]) : "";
$can_confirm_payment = $order && in_array(strtolower($payment_status),array("pending","unpaid"),true);
$flash = $_SESSION["admin_order_flash"] ?? null;
unset($_SESSION["admin_order_flash"]);
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Administrator order detail and action page. -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Details | EasyOrder Admin</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">
</head>

<body class="admin-body">

<?php easyorder_admin_shell_start("admin_order.php"); ?>

<!-- Detail page heading and list return action. -->
<section class="admin-page-heading admin-order-detail-heading">
	<div>
		<p class="admin-eyebrow">ORDER RECORD</p>
		<h1>Order Details<?php if($order) { ?> <span>#<?php echo (int)$order["order_id"]; ?></span><?php } ?></h1>
		<p>Review customer, fulfilment, items and payment information in one record.</p>
	</div>
	<a class="admin-secondary-button" href="admin_order.php">Back to Orders</a>
</section>

<?php if($flash) { ?>
<div class="admin-alert admin-alert-<?php echo admin_order_details_html($flash["type"]); ?>" role="status">
	<?php echo admin_order_details_html($flash["message"]); ?>
</div>
<?php } ?>

<?php if($page_error!=="") { ?>
	<section class="admin-order-unavailable">
		<span>!</span><h2>Order Unavailable</h2><p><?php echo admin_order_details_html($page_error); ?></p>
		<a class="admin-primary-button" href="admin_order.php">Return to Orders</a>
	</section>
<?php } else { ?>

<!-- Order overview and current states. -->
<section class="admin-order-detail-hero">
	<div>
		<p>ORDER #<?php echo (int)$order["order_id"]; ?></p>
		<h2><?php echo admin_order_details_html($order["member_name"]); ?></h2>
		<span>Placed <?php echo admin_order_details_html(admin_order_details_datetime($order["order_date"])); ?></span>
	</div>
	<div class="admin-order-detail-statuses">
		<span class="admin-status-badge <?php echo admin_order_details_status_class($payment_status); ?>">Payment: <?php echo admin_order_details_html($payment_status); ?></span>
		<span class="admin-status-badge <?php echo admin_order_details_status_class($order["order_status"]); ?>">Order: <?php echo admin_order_details_html($order["order_status"]); ?></span>
	</div>
</section>

<div class="admin-order-detail-grid">
	<!-- Customer and fulfilment information. -->
	<section class="admin-detail-card">
		<header><p>CUSTOMER</p><h2>Customer &amp; Fulfilment</h2></header>
		<dl class="admin-detail-list">
			<div><dt>Customer</dt><dd><?php echo admin_order_details_html($order["member_name"]); ?> <small>#<?php echo (int)$order["member_id"]; ?></small></dd></div>
			<div><dt>Email</dt><dd><?php echo admin_order_details_html($order["member_email"]); ?></dd></div>
			<div><dt>Phone</dt><dd><?php echo admin_order_details_html($order["member_phone"]); ?></dd></div>
			<div><dt>Method</dt><dd><?php echo $order["order_delivery"]==="Yes" ? "Delivery" : "Pickup"; ?></dd></div>
			<div class="wide"><dt>Address</dt><dd><?php echo $order["order_delivery"]==="Yes" ? admin_order_details_html($order["order_address"]) : "EasyOrder pickup counter"; ?></dd></div>
		</dl>
	</section>

	<!-- Payment record and reference. -->
	<section class="admin-detail-card">
		<header><p>PAYMENT</p><h2>Payment Record</h2></header>
		<dl class="admin-detail-list">
			<div><dt>Method</dt><dd><?php echo admin_order_details_html($payment_method); ?></dd></div>
			<div><dt>Status</dt><dd><span class="admin-status-badge <?php echo admin_order_details_status_class($payment_status); ?>"><?php echo admin_order_details_html($payment_status); ?></span></dd></div>
			<div><dt>Amount</dt><dd>RM <?php echo number_format((float)($order["payment_amount"] ?: $order["order_total"]),2); ?></dd></div>
			<div><dt>Paid At</dt><dd><?php echo admin_order_details_html(admin_order_details_datetime($order["payment_paid_at"])); ?></dd></div>
			<div class="wide"><dt>Reference</dt><dd class="admin-payment-reference"><?php echo $order["payment_reference"] ? admin_order_details_html($order["payment_reference"]) : "Not assigned"; ?></dd></div>
		</dl>
	</section>

	<!-- Ordered item snapshots and totals. -->
	<section class="admin-detail-card admin-detail-items-card">
		<header><p>ORDER CONTENTS</p><h2>Ordered Items</h2><span><?php echo count($items); ?> product line<?php echo count($items)===1 ? "" : "s"; ?></span></header>
		<div class="admin-table-wrap">
			<table class="admin-detail-items-table">
				<thead><tr><th>Product</th><th>Unit Price</th><th>Quantity</th><th>Subtotal</th></tr></thead>
				<tbody>
				<?php foreach($items as $item) { ?>
				<tr>
					<td><strong><?php echo admin_order_details_html($item["item_name"]); ?></strong><small><?php echo admin_order_details_html($item["item_product"]); ?></small></td>
					<td>RM <?php echo number_format((float)$item["item_price"],2); ?></td>
					<td><?php echo (int)$item["item_qty"]; ?></td>
					<td><strong>RM <?php echo number_format((float)$item["item_subtotal"],2); ?></strong></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<div class="admin-order-totals">
			<div><span>Items Subtotal</span><strong>RM <?php echo number_format($item_subtotal,2); ?></strong></div>
			<div><span>Delivery Fee</span><strong>RM <?php echo number_format($delivery_fee,2); ?></strong></div>
			<div class="total"><span>Order Total</span><strong>RM <?php echo number_format((float)$order["order_total"],2); ?></strong></div>
		</div>
	</section>

	<!-- Controlled administrator actions use POST and the session CSRF token. -->
	<aside class="admin-detail-card admin-order-action-card">
		<header><p>ADMIN ACTIONS</p><h2>Manage This Order</h2></header>

		<?php if($can_confirm_payment) { ?>
		<form method="post" action="admin_order.php" class="admin-order-action-form" onsubmit="return confirm('Confirm payment for Order #<?php echo (int)$order["order_id"]; ?>?')">
			<input type="hidden" name="csrf_token" value="<?php echo admin_order_details_html($csrf_token); ?>">
			<input type="hidden" name="action" value="confirm_payment">
			<input type="hidden" name="order_id" value="<?php echo (int)$order["order_id"]; ?>">
			<div><strong>Confirm Payment</strong><p>Record the full RM <?php echo number_format((float)$order["order_total"],2); ?> payment as collected.</p></div>
			<button type="submit" class="admin-primary-button">Confirm Payment</button>
		</form>
		<?php } else { ?>
		<div class="admin-action-complete"><span>&#10003;</span><div><strong>Payment recorded</strong><p>No payment confirmation is required.</p></div></div>
		<?php } ?>

		<form method="post" action="admin_order.php" class="admin-order-action-form">
			<input type="hidden" name="csrf_token" value="<?php echo admin_order_details_html($csrf_token); ?>">
			<input type="hidden" name="action" value="update_status">
			<input type="hidden" name="order_id" value="<?php echo (int)$order["order_id"]; ?>">
			<label for="detail-order-status">Order Status</label>
			<select id="detail-order-status" name="order_status" required>
				<?php foreach(array("Preparing","Ready for Pickup","Picked Up","Out for Delivery","Delivered","Completed") as $status) { ?><option value="<?php echo admin_order_details_html($status); ?>"<?php echo $order["order_status"]===$status ? " selected" : ""; ?>><?php echo admin_order_details_html($status); ?></option><?php } ?>
			</select>
			<button type="submit" class="admin-secondary-button">Update Order Status</button>
		</form>

		<form method="post" action="admin_order.php" class="admin-order-delete-form" onsubmit="return confirm('Remove Order #<?php echo (int)$order["order_id"]; ?> from the administrator list?')">
			<input type="hidden" name="csrf_token" value="<?php echo admin_order_details_html($csrf_token); ?>">
			<input type="hidden" name="action" value="delete_order">
			<input type="hidden" name="order_id" value="<?php echo (int)$order["order_id"]; ?>">
			<button type="submit">Remove Order</button>
		</form>
	</aside>
</div>

<?php } ?>

<?php easyorder_admin_shell_end(); ?>

</body>
</html>
