<?php
// Only a logged-in customer can view their order history.
session_start();
if(!isset($_SESSION["customer_id"]))
{
	require_once(__DIR__."/customer_auth_helpers.php");
	easyorder_redirect_customer_to_login();
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
		"order" => array("preparing","delivery","completed","cancelled","delivered","picked up","out for delivery","ready for pickup")
	);

	if(isset($known[$group]) && in_array($normalised,$known[$group],true))
	{
		return "order-status-".str_replace(" ","-",$normalised);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History | EasyOrder</title>
<link rel="stylesheet" href="public.css?v=20261029-history1">
<link rel="stylesheet" href="customer.css?v=20261029-history4">
<script src="customer.js?v=20261029-history4" defer></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!-- Customer navigation bar -->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-order-history"><!-- Order History content only; shared Header and Footer remain unchanged. -->

<div class="eo-history-toolbar">
    <div class="eo-history-heading">
        <h2 class="eo-history-title">Order History</h2>
        <p class="eo-history-subtitle">Review payment and order progress, then open any order for full details.</p>
    </div>

    <?php if($history_error==="" && count($orders)>0) { ?>
    <div class="eo-history-filters" hidden role="group" aria-label="Filter orders by date">
        <label class="eo-history-date">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h2M14 14h2M8 18h2"/></svg>
            <select id="eo-history-date-filter" aria-label="Filter orders by date" aria-controls="eo-history-rows eo-history-custom-range">
                <option value="all">All Dates</option>
                <option value="7">Last 7 Days</option>
                <option value="30">Last 30 Days</option>
                <option value="90">Last 90 Days</option>
                <option value="custom">Custom Range</option>
            </select>
            <svg class="eo-history-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </label>
        <button type="button" id="eo-history-range-trigger" class="eo-history-range-trigger" hidden aria-expanded="false" aria-haspopup="dialog" aria-controls="eo-history-custom-range">Select dates</button>
        <div id="eo-history-custom-range" class="eo-history-custom-range" hidden role="dialog" aria-label="Custom date range">
            <div class="eo-history-calendar-toolbar">
                <strong>Select date range</strong>
                <button type="button" class="eo-history-calendar-close" id="eo-history-calendar-close" aria-label="Close calendar">&times;</button>
            </div>
            <dl class="eo-history-range-summary">
                <div><dt>From</dt><dd id="eo-history-range-from">Select start date</dd></div>
                <div><dt>To</dt><dd id="eo-history-range-to">Select end date</dd></div>
            </dl>
            <div class="eo-history-calendar-header">
                <button type="button" class="eo-history-calendar-nav" data-history-month="-1" aria-label="Previous month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg>
                </button>
                <select id="eo-history-calendar-month" aria-label="Calendar month"></select>
                <select id="eo-history-calendar-year" aria-label="Calendar year"></select>
                <button type="button" class="eo-history-calendar-nav" data-history-month="1" aria-label="Next month">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg>
                </button>
            </div>
            <span id="eo-history-calendar-heading" class="checkout-visually-hidden" aria-live="polite"></span>
            <table class="eo-history-calendar" role="grid" aria-multiselectable="true" aria-labelledby="eo-history-calendar-heading" aria-describedby="eo-history-range-feedback">
                <thead><tr>
                    <th scope="col"><abbr title="Monday">Mon</abbr></th>
                    <th scope="col"><abbr title="Tuesday">Tue</abbr></th>
                    <th scope="col"><abbr title="Wednesday">Wed</abbr></th>
                    <th scope="col"><abbr title="Thursday">Thu</abbr></th>
                    <th scope="col"><abbr title="Friday">Fri</abbr></th>
                    <th scope="col"><abbr title="Saturday">Sat</abbr></th>
                    <th scope="col"><abbr title="Sunday">Sun</abbr></th>
                </tr></thead>
                <tbody id="eo-history-calendar-days"></tbody>
            </table>
            <p id="eo-history-range-feedback" role="status" aria-live="polite">Click a start date, then an end date.</p>
            <button type="button" class="eo-history-clear-dates" id="eo-history-clear-dates">Clear dates</button>
        </div>
    </div>
    <?php } ?>
</div>

<?php if($history_error!=="") { ?>
    <div class="checkout-message checkout-message-error" role="alert"><?php echo order_history_html($history_error); ?></div>
<?php } else if(count($orders)>0) { ?>

<section class="eo-history-card" aria-label="Customer orders">
    <table class="eo-history-table">
        <thead>
            <tr>
                <th scope="col">Order</th>
                <th scope="col">Date &amp; Time</th>
                <th scope="col">Items</th>
                <th scope="col">Total</th>
                <th scope="col">Payment Method</th>
                <th scope="col">Payment Status</th>
                <th scope="col">Order Status</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody id="eo-history-rows">
        <?php foreach($orders as $order) { ?>
            <?php
                $payment_status = (string)$order["display_payment_status"];
                $order_status = (string)$order["order_status"];
                $order_timestamp = strtotime((string)$order["order_date"]);
                $order_date_key = $order_timestamp===false ? "" : date("Y-m-d",$order_timestamp);
                $order_datetime = $order_timestamp===false ? "" : date("c",$order_timestamp);
            ?>
            <tr class="eo-history-row"
                data-order-date="<?php echo order_history_html($order_date_key); ?>">
                <td><strong class="eo-history-order">#<?php echo (int)$order["order_id"]; ?></strong></td>
                <td><time datetime="<?php echo order_history_html($order_datetime); ?>"><?php echo order_history_html(order_history_datetime($order["order_date"])); ?></time></td>
                <td><?php echo (int)$order["total_item_qty"]; ?> item<?php echo (int)$order["total_item_qty"]===1 ? "" : "s"; ?></td>
                <td><strong class="eo-history-total">RM <?php echo number_format((float)$order["order_total"],2); ?></strong></td>
                <td><?php echo order_history_html($order["order_payment"]); ?></td>
                <td><span class="eo-history-badge <?php echo order_history_status_class($payment_status,"payment"); ?>"><?php echo order_history_html($payment_status); ?></span></td>
                <td><span class="eo-history-badge <?php echo order_history_status_class($order_status,"order"); ?>"><?php echo order_history_html($order_status); ?></span></td>
                <td class="eo-history-action"><a href="order_details.php?order_id=<?php echo (int)$order["order_id"]; ?>">View Details</a></td>
            </tr>
        <?php } ?>
            <tr id="eo-history-no-results" hidden>
                <td colspan="8" role="status" aria-live="polite">No orders match the selected date range.</td>
            </tr>
        </tbody>
    </table>
</section>

<?php } else { ?>
    <section class="eo-history-state" aria-label="No orders yet">
        <h3>No Orders Yet</h3>
        <p>Your completed checkouts will appear here with their payment and order status.</p>
        <a class="btn" href="category.php">Browse the Menu</a>
    </section>
<?php } ?>

</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
