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

// Create one session token for every state-changing order action.
if(empty($_SESSION["admin_order_csrf"]))
{
	$_SESSION["admin_order_csrf"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["admin_order_csrf"];

// Escape database and filter values before displaying them in the page.
function admin_order_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// Keep order date and time presentation consistent with customer pages.
function admin_order_datetime($value)
{
	$timestamp = strtotime((string)$value);
	return $timestamp===false ? "Not available" : date("d M Y, h:i A",$timestamp);
}

// Map stored payment and fulfilment states to the shared badge colours.
function admin_order_status_class($status)
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

// Store one result message, then use Post/Redirect/Get to prevent resubmission.
function admin_order_redirect($type,$message,$location="admin_order.php")
{
	$_SESSION["admin_order_flash"] = array("type"=>$type,"message"=>$message);
	header("Location: ".$location,true,303);
	exit();
}

// Process updates only through validated POST requests.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if(!hash_equals($csrf_token,$submitted_token))
	{
		admin_order_redirect("error","The request expired. Please try again.");
	}

	$action = (string)($_POST["action"] ?? "");
	$order_id = filter_var($_POST["order_id"] ?? null,FILTER_VALIDATE_INT,array("options"=>array("min_range"=>1)));
	if($order_id===false)
	{
		admin_order_redirect("error","Please select a valid order.");
	}
	$order_id = (int)$order_id;

	if($action==="confirm_payment")
	{
		// Lock the order and payment rows so repeated clicks cannot create two payments.
		mysqli_begin_transaction($connect);
		try
		{
			$order_stmt = mysqli_prepare($connect,"SELECT order_total,order_payment,order_payment_status FROM orders WHERE order_id=? AND order_isDelete=0 FOR UPDATE");
			if(!$order_stmt)
			{
				throw new Exception("The order could not be locked.");
			}
			mysqli_stmt_bind_param($order_stmt,"i",$order_id);
			if(!mysqli_stmt_execute($order_stmt))
			{
				throw new Exception("The order could not be loaded.");
			}
			$order_result = mysqli_stmt_get_result($order_stmt);
			$locked_order = mysqli_fetch_assoc($order_result) ?: null;
			mysqli_stmt_close($order_stmt);

			if(!$locked_order)
			{
				throw new Exception("The selected order is unavailable.");
			}

			$current_payment_status = strtolower(trim((string)$locked_order["order_payment_status"]));
			if($current_payment_status==="paid")
			{
				mysqli_rollback($connect);
				admin_order_redirect("info","Payment for Order #".$order_id." is already confirmed.","admin_order_details.php?order_id=".$order_id);
			}
			if(!in_array($current_payment_status,array("pending","unpaid"),true))
			{
				throw new Exception("Only pending or unpaid orders can be confirmed.");
			}

			$payment_stmt = mysqli_prepare($connect,"SELECT payment_id,payment_status FROM payments WHERE payment_order=? FOR UPDATE");
			if(!$payment_stmt)
			{
				throw new Exception("The payment record could not be locked.");
			}
			mysqli_stmt_bind_param($payment_stmt,"i",$order_id);
			if(!mysqli_stmt_execute($payment_stmt))
			{
				throw new Exception("The payment record could not be loaded.");
			}
			$payment_result = mysqli_stmt_get_result($payment_stmt);
			$payment_row = mysqli_fetch_assoc($payment_result) ?: null;
			mysqli_stmt_close($payment_stmt);

			$payment_method = (string)$locked_order["order_payment"];
			$payment_amount = (float)$locked_order["order_total"];
			$payment_reference = "EOA-".date("YmdHis")."-".str_pad((string)$order_id,6,"0",STR_PAD_LEFT)."-".strtoupper(bin2hex(random_bytes(2)));

			if($payment_row)
			{
				$save_stmt = mysqli_prepare($connect,"UPDATE payments SET payment_reference=?,payment_method=?,payment_amount=?,payment_status='Paid',payment_paid_at=NOW() WHERE payment_order=?");
				if(!$save_stmt)
				{
					throw new Exception("The payment record could not be prepared.");
				}
				mysqli_stmt_bind_param($save_stmt,"ssdi",$payment_reference,$payment_method,$payment_amount,$order_id);
			}
			else
			{
				$save_stmt = mysqli_prepare($connect,"INSERT INTO payments(payment_order,payment_reference,payment_method,payment_amount,payment_status,payment_paid_at) VALUES(?,?,?,?,'Paid',NOW())");
				if(!$save_stmt)
				{
					throw new Exception("The payment record could not be prepared.");
				}
				mysqli_stmt_bind_param($save_stmt,"issd",$order_id,$payment_reference,$payment_method,$payment_amount);
			}

			if(!mysqli_stmt_execute($save_stmt))
			{
				throw new Exception("The payment record could not be confirmed.");
			}
			mysqli_stmt_close($save_stmt);

			$status_stmt = mysqli_prepare($connect,"UPDATE orders SET order_payment_status='Paid' WHERE order_id=? AND order_isDelete=0");
			if(!$status_stmt)
			{
				throw new Exception("The order payment status could not be prepared.");
			}
			mysqli_stmt_bind_param($status_stmt,"i",$order_id);
			if(!mysqli_stmt_execute($status_stmt))
			{
				throw new Exception("The order payment status could not be updated.");
			}
			mysqli_stmt_close($status_stmt);

			mysqli_commit($connect);
			admin_order_redirect("success","Payment for Order #".$order_id." has been confirmed.","admin_order_details.php?order_id=".$order_id);
		}
		catch(Throwable $error)
		{
			mysqli_rollback($connect);
			admin_order_redirect("error","Payment confirmation failed. ".$error->getMessage(),"admin_order_details.php?order_id=".$order_id);
		}
	}

	if($action==="update_status")
	{
		$new_status = (string)($_POST["order_status"] ?? "");
		$allowed_statuses = array("Preparing","Ready for Pickup","Picked Up","Out for Delivery","Delivered","Completed");
		if(!in_array($new_status,$allowed_statuses,true))
		{
			admin_order_redirect("error","Please select a valid order status.","admin_order_details.php?order_id=".$order_id);
		}

		$status_stmt = mysqli_prepare($connect,"UPDATE orders SET order_status=? WHERE order_id=? AND order_isDelete=0");
		if(!$status_stmt)
		{
			admin_order_redirect("error","The order status could not be prepared.","admin_order_details.php?order_id=".$order_id);
		}
		mysqli_stmt_bind_param($status_stmt,"si",$new_status,$order_id);
		$updated = mysqli_stmt_execute($status_stmt);
		mysqli_stmt_close($status_stmt);
		admin_order_redirect($updated ? "success" : "error",$updated ? "Order #".$order_id." status has been updated." : "The order status could not be updated.","admin_order_details.php?order_id=".$order_id);
	}

	if($action==="delete_order")
	{
		// Preserve the previous soft-delete behaviour while preventing repeated point deductions.
		mysqli_begin_transaction($connect);
		try
		{
			$order_stmt = mysqli_prepare($connect,"SELECT order_member,order_total FROM orders WHERE order_id=? AND order_isDelete=0 FOR UPDATE");
			if(!$order_stmt)
			{
				throw new Exception("The order could not be prepared.");
			}
			mysqli_stmt_bind_param($order_stmt,"i",$order_id);
			if(!mysqli_stmt_execute($order_stmt))
			{
				throw new Exception("The order could not be loaded.");
			}
			$order_result = mysqli_stmt_get_result($order_stmt);
			$order_row = mysqli_fetch_assoc($order_result) ?: null;
			mysqli_stmt_close($order_stmt);
			if(!$order_row)
			{
				throw new Exception("The selected order is unavailable.");
			}

			$member_id = (int)$order_row["order_member"];
			$earned_points = (int)floor((float)$order_row["order_total"]*10);
			$points_stmt = mysqli_prepare($connect,"UPDATE member SET member_points=GREATEST(0,member_points-?) WHERE member_id=?");
			if(!$points_stmt)
			{
				throw new Exception("The member points could not be prepared.");
			}
			mysqli_stmt_bind_param($points_stmt,"ii",$earned_points,$member_id);
			if(!mysqli_stmt_execute($points_stmt))
			{
				throw new Exception("The member points could not be adjusted.");
			}
			mysqli_stmt_close($points_stmt);

			$delete_stmt = mysqli_prepare($connect,"UPDATE orders SET order_isDelete=1 WHERE order_id=? AND order_isDelete=0");
			if(!$delete_stmt)
			{
				throw new Exception("The order removal could not be prepared.");
			}
			mysqli_stmt_bind_param($delete_stmt,"i",$order_id);
			if(!mysqli_stmt_execute($delete_stmt))
			{
				throw new Exception("The order could not be removed.");
			}
			mysqli_stmt_close($delete_stmt);
			mysqli_commit($connect);
			admin_order_redirect("success","Order #".$order_id." has been removed.");
		}
		catch(Throwable $error)
		{
			mysqli_rollback($connect);
			admin_order_redirect("error","The order could not be removed. ".$error->getMessage(),"admin_order_details.php?order_id=".$order_id);
		}
	}

	admin_order_redirect("error","The requested order action is not supported.");
}

$flash = $_SESSION["admin_order_flash"] ?? null;
unset($_SESSION["admin_order_flash"]);

// Validate list filters against known values before binding them to the query.
$search = trim((string)($_GET["search"] ?? ""));
$payment_status_options = array("Paid","Pending","Unpaid","Failed");
$order_status_options = array("Preparing","Ready for Pickup","Picked Up","Out for Delivery","Delivered","Completed","Cancelled");
$delivery_options = array("Yes","No");

$payment_filter = (string)($_GET["payment_status"] ?? "");
$status_filter = (string)($_GET["order_status"] ?? "");
$delivery_filter = (string)($_GET["delivery"] ?? "");
$payment_filter = in_array($payment_filter,$payment_status_options,true) ? $payment_filter : "";
$status_filter = in_array($status_filter,$order_status_options,true) ? $status_filter : "";
$delivery_filter = in_array($delivery_filter,$delivery_options,true) ? $delivery_filter : "";
$search_like = "%".$search."%";

// One prepared query supports search and filters without assembling raw SQL input.
$list_sql = "SELECT o.order_id,o.order_date,o.order_total,o.order_payment,o.order_payment_status,o.order_delivery,o.order_status,"
	."m.member_name,m.member_email,COALESCE(p.payment_status,o.order_payment_status) AS display_payment_status "
	."FROM orders o INNER JOIN member m ON m.member_id=o.order_member "
	."LEFT JOIN payments p ON p.payment_order=o.order_id "
	."WHERE o.order_isDelete=0 "
	."AND (?='' OR CAST(o.order_id AS CHAR) LIKE ? OR m.member_name LIKE ? OR m.member_email LIKE ?) "
	."AND (?='' OR LOWER(COALESCE(p.payment_status,o.order_payment_status))=LOWER(?)) "
	."AND (?='' OR LOWER(o.order_status)=LOWER(?)) "
	."AND (?='' OR o.order_delivery=?) "
	."ORDER BY o.order_date DESC,o.order_id DESC";
$list_stmt = mysqli_prepare($connect,$list_sql);
$orders = array();
$list_error = "";

if($list_stmt)
{
	mysqli_stmt_bind_param($list_stmt,"ssssssssss",$search,$search_like,$search_like,$search_like,$payment_filter,$payment_filter,$status_filter,$status_filter,$delivery_filter,$delivery_filter);
	if(mysqli_stmt_execute($list_stmt))
	{
		$list_result = mysqli_stmt_get_result($list_stmt);
		while($order = mysqli_fetch_assoc($list_result))
		{
			$orders[] = $order;
		}
	}
	else
	{
		$list_error = "Orders are temporarily unavailable. Please try again.";
	}
	mysqli_stmt_close($list_stmt);
}
else
{
	$list_error = "Orders are temporarily unavailable. Please try again.";
}

// Calculate visible-list summaries after applying the selected filters.
$visible_paid = 0;
$visible_attention = 0;
$visible_value = 0.00;
foreach($orders as $order)
{
	$visible_value += (float)$order["order_total"];
	if(strtolower((string)$order["display_payment_status"])==="paid")
	{
		$visible_paid++;
	}
	else if(in_array(strtolower((string)$order["display_payment_status"]),array("pending","unpaid"),true))
	{
		$visible_attention++;
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Administrator order search and management page. -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders | EasyOrder</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">
</head>

<body class="admin-body">

<?php easyorder_admin_shell_start("admin_order.php"); ?>

<!-- Page heading and context. -->
<section class="admin-page-heading">
	<div>
		<p class="admin-eyebrow">ORDER OPERATIONS</p>
		<h1>Manage Orders</h1>
		<p>Search customer orders, review fulfilment and confirm outstanding payments.</p>
	</div>
	<span class="admin-live-indicator"><i></i> Live database</span>
</section>

<?php if($flash) { ?>
<div class="admin-alert admin-alert-<?php echo admin_order_html($flash["type"]); ?>" role="status">
	<?php echo admin_order_html($flash["message"]); ?>
</div>
<?php } ?>

<!-- Search and exact-value filters. -->
<section class="admin-order-filter-panel" aria-labelledby="order-filter-heading">
	<div class="admin-filter-heading">
		<div><p>FIND ORDERS</p><h2 id="order-filter-heading">Search and Filter</h2></div>
		<?php if($search!=="" || $payment_filter!=="" || $status_filter!=="" || $delivery_filter!=="") { ?><a href="admin_order.php">Clear all filters</a><?php } ?>
	</div>
	<form method="get" action="admin_order.php" class="admin-order-filter-form">
		<div class="admin-filter-field admin-filter-search">
			<label for="order-search">Order, customer or email</label>
			<input type="search" id="order-search" name="search" value="<?php echo admin_order_html($search); ?>" placeholder="e.g. 1024 or customer@email.com">
		</div>
		<div class="admin-filter-field">
			<label for="payment-status-filter">Payment Status</label>
			<select id="payment-status-filter" name="payment_status">
				<option value="">All payment statuses</option>
				<?php foreach($payment_status_options as $option) { ?><option value="<?php echo admin_order_html($option); ?>"<?php echo $payment_filter===$option ? " selected" : ""; ?>><?php echo admin_order_html($option); ?></option><?php } ?>
			</select>
		</div>
		<div class="admin-filter-field">
			<label for="order-status-filter">Order Status</label>
			<select id="order-status-filter" name="order_status">
				<option value="">All order statuses</option>
				<?php foreach($order_status_options as $option) { ?><option value="<?php echo admin_order_html($option); ?>"<?php echo $status_filter===$option ? " selected" : ""; ?>><?php echo admin_order_html($option); ?></option><?php } ?>
			</select>
		</div>
		<div class="admin-filter-field">
			<label for="delivery-filter">Fulfilment</label>
			<select id="delivery-filter" name="delivery">
				<option value="">Delivery and pickup</option>
				<option value="Yes"<?php echo $delivery_filter==="Yes" ? " selected" : ""; ?>>Delivery</option>
				<option value="No"<?php echo $delivery_filter==="No" ? " selected" : ""; ?>>Pickup</option>
			</select>
		</div>
		<button type="submit" class="admin-primary-button">Apply Filters</button>
	</form>
</section>

<!-- Summaries recalculate from the visible filtered result. -->
<section class="admin-order-summary-grid" aria-label="Filtered order summary">
	<article><span>OR</span><div><strong><?php echo count($orders); ?></strong><small>Orders shown</small></div></article>
	<article><span>PD</span><div><strong><?php echo $visible_paid; ?></strong><small>Paid orders</small></div></article>
	<article class="attention"><span>!</span><div><strong><?php echo $visible_attention; ?></strong><small>Awaiting payment</small></div></article>
	<article><span>RM</span><div><strong>RM <?php echo number_format($visible_value,2); ?></strong><small>Displayed order value</small></div></article>
</section>

<!-- Desktop order results table. -->
<section class="admin-order-results" aria-labelledby="order-results-heading">
	<header class="admin-results-heading">
		<div><p>ORDER LIST</p><h2 id="order-results-heading">Customer Orders</h2></div>
		<span><?php echo count($orders); ?> result<?php echo count($orders)===1 ? "" : "s"; ?></span>
	</header>

	<?php if($list_error!=="") { ?>
		<div class="admin-empty-state"><span>!</span><strong>Orders unavailable</strong><p><?php echo admin_order_html($list_error); ?></p></div>
	<?php } else if(count($orders)===0) { ?>
		<div class="admin-empty-state"><span>0</span><strong>No matching orders</strong><p>Change or clear the filters to view other orders.</p></div>
	<?php } else { ?>
		<div class="admin-table-wrap admin-order-table-wrap">
			<table class="admin-order-table">
				<thead><tr><th>Order</th><th>Customer</th><th>Date &amp; Time</th><th>Fulfilment</th><th>Total</th><th>Payment</th><th>Order Status</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach($orders as $order) { ?>
					<tr>
						<td><a class="admin-order-number" href="admin_order_details.php?order_id=<?php echo (int)$order["order_id"]; ?>">#<?php echo (int)$order["order_id"]; ?></a><small><?php echo admin_order_html($order["order_payment"]); ?></small></td>
						<td><strong><?php echo admin_order_html($order["member_name"]); ?></strong><small><?php echo admin_order_html($order["member_email"]); ?></small></td>
						<td><?php echo admin_order_html(admin_order_datetime($order["order_date"])); ?></td>
						<td><?php echo $order["order_delivery"]==="Yes" ? "Delivery" : "Pickup"; ?></td>
						<td><strong>RM <?php echo number_format((float)$order["order_total"],2); ?></strong></td>
						<td><span class="admin-status-badge <?php echo admin_order_status_class($order["display_payment_status"]); ?>"><?php echo admin_order_html($order["display_payment_status"]); ?></span></td>
						<td><span class="admin-status-badge <?php echo admin_order_status_class($order["order_status"]); ?>"><?php echo admin_order_html($order["order_status"]); ?></span></td>
						<td>
							<div class="admin-order-row-actions">
								<a href="admin_order_details.php?order_id=<?php echo (int)$order["order_id"]; ?>">View Details</a>
								<?php if(in_array(strtolower((string)$order["display_payment_status"]),array("pending","unpaid"),true)) { ?>
								<form method="post" action="admin_order.php" onsubmit="return confirm('Confirm payment for Order #<?php echo (int)$order["order_id"]; ?>?')">
									<input type="hidden" name="csrf_token" value="<?php echo admin_order_html($csrf_token); ?>">
									<input type="hidden" name="action" value="confirm_payment">
									<input type="hidden" name="order_id" value="<?php echo (int)$order["order_id"]; ?>">
									<button type="submit">Confirm Payment</button>
								</form>
								<?php } ?>
							</div>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	<?php } ?>
</section>

<?php easyorder_admin_shell_end(); ?>

</body>
</html>
