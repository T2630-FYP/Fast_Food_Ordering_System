<?php
// Only a logged-in customer can request an order detail page.
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");

$mid = (int)$_SESSION["member_id"];
$requested_order_id = filter_var($_GET["order_id"] ?? null,FILTER_VALIDATE_INT,array("options"=>array("min_range"=>1)));
$order_id = $requested_order_id===false ? 0 : (int)$requested_order_id;
$order = null;
$items = array();
$page_error = "";
$http_status = 200;

// Escape all customer, order and payment values before rendering them.
function order_details_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// Keep the order and payment date-time display consistent throughout the site.
function order_details_datetime($value)
{
	if($value===null || $value==="")
	{
		return "Not available";
	}
	$timestamp = strtotime((string)$value);
	return $timestamp===false ? "Not available" : date("d M Y, h:i A",$timestamp);
}

// Limit badge styling to recognised stored states instead of using raw values as CSS.
function order_details_status_class($status,$group)
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

if($order_id<=0)
{
	$http_status = 404;
	$page_error = "This order is unavailable.";
}
else
{
	// The member condition is part of the SQL query so another customer can never
	// retrieve the order, delivery address or payment metadata by changing the URL.
	$order_sql = "SELECT o.order_id,o.order_date,o.order_total,o.order_payment,o.order_payment_status,"
		."o.order_delivery,o.order_address,o.order_status,m.member_name,m.member_email,m.member_phone,"
		."p.payment_reference,p.payment_method AS saved_payment_method,p.payment_amount,"
		."p.payment_status AS saved_payment_status,p.payment_paid_at "
		."FROM orders o INNER JOIN member m ON m.member_id=o.order_member "
		."LEFT JOIN payments p ON p.payment_order=o.order_id "
		."WHERE o.order_id=? AND o.order_member=? AND o.order_isDelete=0 LIMIT 1";
	$order_stmt = mysqli_prepare($connect,$order_sql);
	if(!$order_stmt)
	{
		$http_status = 500;
		$page_error = "Order details are temporarily unavailable. Please try again.";
	}
	else
	{
		mysqli_stmt_bind_param($order_stmt,"ii",$order_id,$mid);
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
	// Load the immutable item snapshots saved when this order was placed.
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
			$page_error = "The items for this order are temporarily unavailable. Please try again.";
		}
		mysqli_stmt_close($item_stmt);
	}
	else
	{
		$http_status = 500;
		$page_error = "The items for this order are temporarily unavailable. Please try again.";
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
$can_retry_card_payment = $order && $order["order_payment"]==="Credit Card" && $payment_status==="Pending";
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Customer order details page -->
<meta charset="UTF-8">
<title>Order Details</title>
<link rel="stylesheet" href="style.css?v=20260922-2">
</head>

<body>

<div id="header"><!-- Header section for logo, website name and slogan -->
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar"><!-- Customer navigation bar -->
<a href="category.php">Menu</a>
<a href="cart.php">Cart</a>
<a href="dashboard.php">My Dashboard</a>
<a href="order_history.php">Order History</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<main id="main"><!-- Secure order details content -->

<div class="order-page-heading"><!-- Page heading and history return link -->
<div>
<p class="checkout-step-label">PURCHASE DETAILS</p>
<h2 class="section-title">Order Details<?php if($order) { ?> <span>#<?php echo (int)$order["order_id"]; ?></span><?php } ?></h2>
<p class="intro">View the saved items, delivery information and payment record for this order.</p>
</div>
<a class="order-heading-link" href="order_history.php">Back to Order History</a>
</div>

<?php if($page_error!=="") { ?>

<section class="order-unavailable-card"><!-- Generic missing or inaccessible order state -->
<div class="order-empty-icon" aria-hidden="true">&#128274;</div>
<h3>Order Unavailable</h3>
<p><?php echo order_details_html($page_error); ?></p>
<a class="btn" href="order_history.php">Return to Order History</a>
</section>

<?php } else { ?>

<article class="order-details-card"><!-- Complete customer-owned order record -->
<header class="order-details-header">
<div>
<p class="order-details-label">ORDER #<?php echo (int)$order["order_id"]; ?></p>
<h3>Placed on <?php echo order_details_html(order_details_datetime($order["order_date"])); ?></h3>
</div>
<div class="order-details-statuses" aria-label="Order and payment statuses">
<span class="order-status-badge <?php echo order_details_status_class($payment_status,"payment"); ?>">Payment: <?php echo order_details_html($payment_status); ?></span>
<span class="order-status-badge <?php echo order_details_status_class($order["order_status"],"order"); ?>">Order: <?php echo order_details_html($order["order_status"]); ?></span>
</div>
</header>

<section class="order-details-section" aria-labelledby="ordered-items-heading"><!-- Purchased item snapshots -->
<div class="order-details-section-heading">
<div>
<p class="checkout-step-label">ORDER CONTENTS</p>
<h3 id="ordered-items-heading">Ordered Items</h3>
</div>
<span><?php echo count($items); ?> product line<?php echo count($items)===1 ? "" : "s"; ?></span>
</div>

<table class="order-items-table">
<thead>
<tr>
<th scope="col">Product</th>
<th scope="col">Unit Price</th>
<th scope="col">Quantity</th>
<th scope="col">Subtotal</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $item) { ?>
<tr>
<td><strong><?php echo order_details_html($item["item_name"]); ?></strong><small><?php echo order_details_html($item["item_product"]); ?></small></td>
<td>RM <?php echo number_format((float)$item["item_price"],2); ?></td>
<td><?php echo (int)$item["item_qty"]; ?></td>
<td><strong>RM <?php echo number_format((float)$item["item_subtotal"],2); ?></strong></td>
</tr>
<?php } ?>
</tbody>
</table>
</section>

<div class="order-information-grid"><!-- Delivery and payment information columns -->
<section class="order-information-panel" aria-labelledby="delivery-information-heading">
<p class="checkout-step-label">FULFILMENT</p>
<h3 id="delivery-information-heading">Delivery Information</h3>
<dl>
<div><dt>Method</dt><dd><?php echo $order["order_delivery"]==="Yes" ? "Delivery" : "Pickup"; ?></dd></div>
<div><dt>Customer</dt><dd><?php echo order_details_html($order["member_name"]); ?></dd></div>
<div><dt>Phone</dt><dd><?php echo order_details_html($order["member_phone"]); ?></dd></div>
<div class="order-information-address"><dt>Address</dt><dd><?php echo $order["order_delivery"]==="Yes" ? order_details_html($order["order_address"]) : "EasyOrder pickup counter"; ?></dd></div>
</dl>
</section>

<section class="order-information-panel" aria-labelledby="payment-information-heading">
<p class="checkout-step-label">PAYMENT</p>
<h3 id="payment-information-heading">Payment Information</h3>
<dl>
<div><dt>Method</dt><dd><?php echo order_details_html($payment_method); ?></dd></div>
<div><dt>Status</dt><dd><span class="order-status-badge <?php echo order_details_status_class($payment_status,"payment"); ?>"><?php echo order_details_html($payment_status); ?></span></dd></div>
<?php if(!empty($order["payment_reference"])) { ?>
<div><dt>Reference</dt><dd class="order-payment-reference"><?php echo order_details_html($order["payment_reference"]); ?></dd></div>
<?php } ?>
<?php if(!empty($order["payment_paid_at"])) { ?>
<div><dt>Paid At</dt><dd><?php echo order_details_html(order_details_datetime($order["payment_paid_at"])); ?></dd></div>
<?php } ?>
</dl>
<?php if($can_retry_card_payment) { ?>
<a class="order-payment-link" href="payment.php?order_id=<?php echo (int)$order["order_id"]; ?>">Complete Card Payment</a>
<?php } ?>
</section>
</div>

<section class="order-total-panel" aria-label="Order totals"><!-- Item, delivery and final totals -->
<div><span>Items Subtotal</span><strong>RM <?php echo number_format($item_subtotal,2); ?></strong></div>
<div><span>Delivery Fee</span><strong>RM <?php echo number_format($delivery_fee,2); ?></strong></div>
<div class="order-final-total"><span>Order Total</span><strong>RM <?php echo number_format((float)$order["order_total"],2); ?></strong></div>
</section>

<div class="order-details-actions"><!-- Order detail navigation actions -->
<?php if($can_retry_card_payment) { ?>
<a class="payment-primary-link" href="payment.php?order_id=<?php echo (int)$order["order_id"]; ?>">Pay This Order</a>
<?php } ?>
<a class="payment-secondary-link" href="order_history.php">Back to Order History</a>
<a class="payment-secondary-link" href="category.php">Order Again</a>
</div>
</article>

<?php } ?>

</main>

<footer><!-- Footer section -->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
