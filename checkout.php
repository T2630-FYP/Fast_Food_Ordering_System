<?php
// Only logged-in members can check out.
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");

$mid = (int)$_SESSION["member_id"];
$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");

function checkout_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// A saved profile address is only the initial value. Changes made at checkout
// belong to this order and are never written back to the member profile.
$saved_address = array("address"=>"","state"=>"","city"=>"","postcode"=>"");
$stmt = mysqli_prepare($connect,"SELECT member_address,member_state,member_city,member_postcode FROM member WHERE member_id=? AND member_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$saved_address_result = mysqli_stmt_get_result($stmt);
if($saved_address_row = mysqli_fetch_assoc($saved_address_result))
{
	$saved_address = array(
		"address" => $saved_address_row["member_address"],
		"state" => $saved_address_row["member_state"],
		"city" => $saved_address_row["member_city"],
		"postcode" => $saved_address_row["member_postcode"]
	);
}
mysqli_stmt_close($stmt);
$has_saved_address = $saved_address["address"]!=="" && $saved_address["city"]!=="" && $saved_address["state"]!=="" && $saved_address["postcode"]!=="";

// Check whether the reward-redemption feature is present in this database.
$redemption_ready = false;
$tbl_check = mysqli_query($connect,"SHOW TABLES LIKE 'redemption'");
if($tbl_check && mysqli_num_rows($tbl_check)>0)
{
	$redemption_ready = true;
}

// Place the order before HTML output, then redirect so refresh cannot submit it again.
if(isset($_POST["placeorderbtn"]))
{
	$delivery_method = trim((string)($_POST["delivery_method"] ?? "Pickup"));
	$payment = trim((string)($_POST["payment"] ?? ""));
	$delivery_address = trim((string)($_POST["delivery_address"] ?? ""));
	$delivery_city = trim((string)($_POST["delivery_city"] ?? ""));
	$delivery_state = trim((string)($_POST["delivery_state"] ?? ""));
	$delivery_postcode = trim((string)($_POST["delivery_postcode"] ?? ""));

	// Keep only non-sensitive form values when server-side validation fails.
	$_SESSION["checkout_form"] = array(
		"delivery_method" => $delivery_method,
		"payment" => $payment,
		"delivery_address" => $delivery_address,
		"delivery_city" => $delivery_city,
		"delivery_state" => $delivery_state,
		"delivery_postcode" => $delivery_postcode
	);

	$submitted_token = (string)($_POST["checkout_token"] ?? "");
	$valid_token = isset($_SESSION["checkout_token"]) && $submitted_token!=="" && hash_equals($_SESSION["checkout_token"],$submitted_token);
	unset($_SESSION["checkout_token"]);

	if(!$valid_token)
	{
		$_SESSION["checkout_error"] = "This checkout was already submitted or has expired. Please review your cart and try again.";
		header("location:checkout.php");
		exit();
	}

	$transaction_started = false;

	try
	{
		if(!in_array($delivery_method,array("Pickup","Delivery"),true))
		{
			throw new Exception("Please select pickup or delivery.");
		}

		if(!in_array($payment,array("Credit Card","Online Banking","Cash"),true))
		{
			throw new Exception("Please select a valid payment method.");
		}

		$delivery = $delivery_method==="Delivery" ? "Yes" : "No";
		$address = "";
		if($delivery==="Yes")
		{
			if($delivery_address==="" || $delivery_city==="" || !in_array($delivery_state,$states,true) || !preg_match("/^\\d{5}$/",$delivery_postcode))
			{
				throw new Exception("Please enter a complete and valid delivery address.");
			}

			$address = $delivery_address.", ".$delivery_postcode." ".$delivery_city.", ".$delivery_state;
			if(strlen($address)>255)
			{
				throw new Exception("The delivery address is too long. Please shorten it and try again.");
			}
		}

		// Payment status is independent from order status. Online methods remain
		// Pending until the simulated payment step confirms success.
		$payment_status = $payment==="Cash" ? "Unpaid" : "Pending";
		$order_datetime = date("Y-m-d H:i:s");

		mysqli_begin_transaction($connect);
		$transaction_started = true;

		// Serialise checkout/cart operations for this member and recheck the account.
		$stmt = mysqli_prepare($connect,"SELECT member_isDelete FROM member WHERE member_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$member_result = mysqli_stmt_get_result($stmt);
		$member_row = mysqli_fetch_assoc($member_result);
		mysqli_stmt_close($stmt);
		if(!$member_row || (int)$member_row["member_isDelete"]===1)
		{
			throw new Exception("This member account is no longer active.");
		}

		// Read and lock the latest cart, not the older page snapshot.
		$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty FROM cart WHERE cart_member=? ORDER BY cart_product FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$cart_result = mysqli_stmt_get_result($stmt);
		$order_cart = array();
		while($cart_row = mysqli_fetch_assoc($cart_result))
		{
			$pid = $cart_row["cart_product"];
			$order_cart[$pid] = ($order_cart[$pid] ?? 0) + (int)$cart_row["cart_qty"];
		}
		mysqli_stmt_close($stmt);

		$reward_cart = array();
		if($redemption_ready)
		{
			$stmt = mysqli_prepare($connect,"SELECT redeem_id,redeem_reward,redeem_product FROM redemption WHERE redeem_member=? AND redeem_status='Cart' ORDER BY redeem_id FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			$reward_result = mysqli_stmt_get_result($stmt);
			while($reward_row = mysqli_fetch_assoc($reward_result))
			{
				$reward_cart[] = $reward_row;
			}
			mysqli_stmt_close($stmt);
		}

		if(count($order_cart)===0 && count($reward_cart)===0)
		{
			throw new Exception("Your cart is empty. Please add an item before placing an order.");
		}

		// Lock products in ID order to avoid deadlocks between simultaneous checkouts.
		ksort($order_cart);
		$order_products = array();
		$total = 0.00;
		foreach($order_cart as $pid => $qty)
		{
			if($qty<=0)
			{
				throw new Exception("An item in your cart has an invalid quantity. Please review your cart.");
			}

			$stmt = mysqli_prepare($connect,"SELECT product_name,product_price,product_stock,product_status,product_isDelete FROM product WHERE product_id=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$pid);
			mysqli_stmt_execute($stmt);
			$product_result = mysqli_stmt_get_result($stmt);
			$product_row = mysqli_fetch_assoc($product_result);
			mysqli_stmt_close($stmt);

			if(!$product_row || (int)$product_row["product_isDelete"]===1 || $product_row["product_status"]!=="Active" || (int)$product_row["product_stock"]<=0)
			{
				throw new Exception("An item in your cart is no longer available. Please review your cart.");
			}
			if($qty>(int)$product_row["product_stock"])
			{
				throw new Exception("There is not enough stock for ".$product_row["product_name"].". Please review your cart.");
			}
			if((float)$product_row["product_price"]<0)
			{
				throw new Exception($product_row["product_name"]." has an invalid price. Please contact the admin.");
			}

			$product_row["cart_qty"] = $qty;
			$order_products[$pid] = $product_row;
			$total += (float)$product_row["product_price"] * $qty;
		}

		if($delivery==="Yes")
		{
			$total += 5.00;
		}

		$stmt = mysqli_prepare($connect,"INSERT INTO orders(order_member,order_date,order_total,order_payment,order_payment_status,order_delivery,order_address,order_status) VALUES(?,?,?,?,?,?,?,'Preparing')");
		mysqli_stmt_bind_param($stmt,"isdssss",$mid,$order_datetime,$total,$payment,$payment_status,$delivery,$address);
		mysqli_stmt_execute($stmt);
		$orderid = mysqli_insert_id($connect);
		mysqli_stmt_close($stmt);

		$earned_points = max(0,(int)floor($total * 10));
		$stmt = mysqli_prepare($connect,"UPDATE member SET member_points=member_points+? WHERE member_id=?");
		mysqli_stmt_bind_param($stmt,"ii",$earned_points,$mid);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		foreach($order_products as $pid => $product_row)
		{
			$pname = $product_row["product_name"];
			$pprice = (float)$product_row["product_price"];
			$qty = (int)$product_row["cart_qty"];
			$sub = $pprice * $qty;

			$stmt = mysqli_prepare($connect,"INSERT INTO order_items(item_order,item_product,item_name,item_price,item_qty,item_subtotal) VALUES(?,?,?,?,?,?)");
			mysqli_stmt_bind_param($stmt,"issdid",$orderid,$pid,$pname,$pprice,$qty,$sub);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"UPDATE product SET product_stock=product_stock-? WHERE product_id=? AND product_stock>=?");
			mysqli_stmt_bind_param($stmt,"isi",$qty,$pid,$qty);
			mysqli_stmt_execute($stmt);
			if(mysqli_stmt_affected_rows($stmt)!==1)
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The stock changed while placing your order. Please review your cart and try again.");
			}
			mysqli_stmt_close($stmt);
		}

		// Reward stock and points were reserved during redemption. Checkout only
		// attaches the locked reward rows to this order.
		foreach($reward_cart as $reward_row)
		{
			$rpid = $reward_row["redeem_product"];
			$rname = $reward_row["redeem_reward"];
			$free_price = 0.00;
			$free_qty = 1;
			$stmt = mysqli_prepare($connect,"INSERT INTO order_items(item_order,item_product,item_name,item_price,item_qty,item_subtotal) VALUES(?,?,?,?,?,?)");
			mysqli_stmt_bind_param($stmt,"issdid",$orderid,$rpid,$rname,$free_price,$free_qty,$free_price);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}

		if(count($reward_cart)>0)
		{
			$stmt = mysqli_prepare($connect,"UPDATE redemption SET redeem_status='Completed' WHERE redeem_member=? AND redeem_status='Cart'");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			if(mysqli_stmt_affected_rows($stmt)!==count($reward_cart))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The reward cart changed while placing your order. Please try again.");
			}
			mysqli_stmt_close($stmt);
		}

		$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=?");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		mysqli_commit($connect);
		$transaction_started = false;
		unset($_SESSION["checkout_form"]);
		$_SESSION["placed_order_id"] = $orderid;
		header("location:review.php");
		exit();
	}
	catch(Throwable $error)
	{
		if($transaction_started)
		{
			mysqli_rollback($connect);
		}
		$_SESSION["checkout_error"] = $error->getMessage();
		header("location:checkout.php");
		exit();
	}
}

if(!isset($_SESSION["checkout_token"]))
{
	$_SESSION["checkout_token"] = bin2hex(random_bytes(32));
}
$checkout_token = $_SESSION["checkout_token"];

if(isset($_SESSION["checkout_error"]))
{
	$checkout_error = $_SESSION["checkout_error"];
	unset($_SESSION["checkout_error"]);
}

$checkout_form = $_SESSION["checkout_form"] ?? array();
unset($_SESSION["checkout_form"]);
$saved_delivery_method = $checkout_form["delivery_method"] ?? "Pickup";
$selected_delivery_method = in_array($saved_delivery_method,array("Pickup","Delivery"),true) ? $saved_delivery_method : "Pickup";
$saved_payment = $checkout_form["payment"] ?? "";
$selected_payment = in_array($saved_payment,array("Credit Card","Online Banking","Cash"),true) ? $saved_payment : "";
$form_address = $checkout_form["delivery_address"] ?? $saved_address["address"];
$form_city = $checkout_form["delivery_city"] ?? $saved_address["city"];
$form_state = $checkout_form["delivery_state"] ?? $saved_address["state"];
$form_postcode = $checkout_form["delivery_postcode"] ?? $saved_address["postcode"];

// Load one consistent cart snapshot for display. Checkout locks it again before writing.
$cart = array();
$cart_products = array();
$removed_items = array();
$adjusted_items = array();
$subtotal = 0.00;
$display_transaction = false;

try
{
	mysqli_begin_transaction($connect);
	$display_transaction = true;

	$stmt = mysqli_prepare($connect,"SELECT member_id FROM member WHERE member_id=? AND member_isDelete=0 FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)!==1)
	{
		mysqli_stmt_close($stmt);
		throw new Exception("This member account is no longer active.");
	}
	mysqli_stmt_close($stmt);

	$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty FROM cart WHERE cart_member=? ORDER BY cart_product FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$cart_result = mysqli_stmt_get_result($stmt);
	while($cart_row = mysqli_fetch_assoc($cart_result))
	{
		$pid = $cart_row["cart_product"];
		$cart[$pid] = ($cart[$pid] ?? 0) + (int)$cart_row["cart_qty"];
	}
	mysqli_stmt_close($stmt);

	ksort($cart);
	foreach($cart as $pid => $qty)
	{
		$stmt = mysqli_prepare($connect,"SELECT product_name,product_price,product_stock,product_status,product_isDelete FROM product WHERE product_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"s",$pid);
		mysqli_stmt_execute($stmt);
		$product_result = mysqli_stmt_get_result($stmt);
		$product_row = mysqli_fetch_assoc($product_result);
		mysqli_stmt_close($stmt);

		if(!$product_row || (int)$product_row["product_isDelete"]===1 || $product_row["product_status"]!=="Active" || (int)$product_row["product_stock"]<=0 || $qty<=0)
		{
			$removed_items[] = $product_row ? $product_row["product_name"] : "an item that is no longer available";
			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			unset($cart[$pid]);
			continue;
		}

		$stock = (int)$product_row["product_stock"];
		if($qty>$stock)
		{
			$qty = $stock;
			$cart[$pid] = $qty;
			$adjusted_items[] = $product_row["product_name"]." (quantity updated to ".$qty.")";
			$stmt = mysqli_prepare($connect,"UPDATE cart SET cart_qty=? WHERE cart_member=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"iis",$qty,$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}

		$cart_products[$pid] = $product_row;
		$subtotal += (float)$product_row["product_price"] * $qty;
	}

	mysqli_commit($connect);
	$display_transaction = false;
}
catch(Throwable $error)
{
	if($display_transaction)
	{
		mysqli_rollback($connect);
	}
	$cart = array();
	$cart_products = array();
	if(!isset($checkout_error))
	{
		$checkout_error = $error->getMessage();
	}
}

$reward_items = array();
if($redemption_ready)
{
	$stmt = mysqli_prepare($connect,"SELECT redeem_id,redeem_reward,redeem_product FROM redemption WHERE redeem_member=? AND redeem_status='Cart' ORDER BY redeem_date DESC,redeem_id DESC");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$reward_result = mysqli_stmt_get_result($stmt);
	while($reward_row = mysqli_fetch_assoc($reward_result))
	{
		$reward_items[] = $reward_row;
	}
	mysqli_stmt_close($stmt);
}

$has_normal = count($cart)>0;
$has_reward = count($reward_items)>0;
$has_checkout_items = $has_normal || $has_reward;
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Checkout and payment page -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout</title>
<link rel="stylesheet" href="style.css?v=20260921-2">
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

<main id="main"><!-- Main content section -->

<div class="checkout-page-heading">
<div>
<p class="checkout-step-label">FINAL STEP</p>
<h2 class="section-title">Checkout</h2>
<p class="intro">Review your order, choose fulfilment and confirm how you will pay.</p>
</div>
<a class="checkout-return-link" href="cart.php">&larr; Return to Cart</a>
</div>

<?php if(isset($checkout_error)) { ?>
<div class="checkout-message checkout-message-error" role="alert"><?php echo checkout_html($checkout_error); ?></div>
<?php } ?>

<?php if(count($removed_items)>0) { ?>
<div class="checkout-message checkout-message-warning" role="status">Unavailable items removed: <?php echo checkout_html(implode(", ",$removed_items)); ?>.</div>
<?php } ?>

<?php if(count($adjusted_items)>0) { ?>
<div class="checkout-message checkout-message-warning" role="status">Stock changed: <?php echo checkout_html(implode(", ",$adjusted_items)); ?>.</div>
<?php } ?>

<?php if($has_checkout_items) { ?>

<form id="checkout-form" class="checkout-form" name="checkoutfrm" method="post" action="" novalidate>
<input type="hidden" name="checkout_token" value="<?php echo checkout_html($checkout_token); ?>">

<div class="checkout-layout">
<div class="checkout-details-column">

<section class="checkout-card" aria-labelledby="fulfilment-heading">
<div class="checkout-card-heading">
<span class="checkout-step-number" aria-hidden="true">1</span>
<div>
<h3 id="fulfilment-heading">How would you like your order?</h3>
<p>Choose pickup or delivery for this order.</p>
</div>
</div>

<fieldset class="checkout-fieldset">
<legend class="checkout-visually-hidden">Fulfilment method</legend>
<div class="checkout-choice-grid">
<label class="checkout-choice-card">
<input type="radio" name="delivery_method" value="Pickup" <?php if($selected_delivery_method==="Pickup") echo "checked"; ?>>
<span class="checkout-choice-copy"><strong>Pickup</strong><small>Collect your order from the restaurant</small></span>
<span class="checkout-choice-price">Free</span>
</label>

<label class="checkout-choice-card">
<input type="radio" name="delivery_method" value="Delivery" <?php if($selected_delivery_method==="Delivery") echo "checked"; ?>>
<span class="checkout-choice-copy"><strong>Delivery</strong><small>Send the order to your selected address</small></span>
<span class="checkout-choice-price">RM 5.00</span>
</label>
</div>
</fieldset>

<div id="delivery-area" class="checkout-delivery-area" <?php if($selected_delivery_method!=="Delivery") echo "hidden"; ?>>
<div class="checkout-address-title">
<div>
<h4>Delivery Address</h4>
<?php if($has_saved_address) { ?>
<p>Your saved address is filled in below. Changes apply only to this order.</p>
<?php } else { ?>
<p>No complete saved address was found. Enter an address for this order.</p>
<?php } ?>
</div>
<?php if($has_saved_address) { ?><span class="checkout-saved-badge">Saved address</span><?php } ?>
</div>

<div class="checkout-address-grid">
<div class="checkout-field checkout-field-full">
<label for="delivery-address">Address <span aria-hidden="true">*</span></label>
<input id="delivery-address" type="text" name="delivery_address" maxlength="140" autocomplete="street-address" value="<?php echo checkout_html($form_address); ?>" placeholder="House number, building, street and unit number">
</div>

<div class="checkout-field">
<label for="delivery-city">City <span aria-hidden="true">*</span></label>
<input id="delivery-city" type="text" name="delivery_city" maxlength="50" autocomplete="address-level2" value="<?php echo checkout_html($form_city); ?>" placeholder="e.g. Muar">
</div>

<div class="checkout-field">
<label for="delivery-state">State <span aria-hidden="true">*</span></label>
<select id="delivery-state" name="delivery_state" autocomplete="address-level1">
<option value="">Select your state</option>
<?php foreach($states as $state_name) { ?>
<option value="<?php echo checkout_html($state_name); ?>" <?php if($form_state===$state_name) echo "selected"; ?>><?php echo checkout_html($state_name); ?></option>
<?php } ?>
</select>
</div>

<div class="checkout-field">
<label for="delivery-postcode">Postcode <span aria-hidden="true">*</span></label>
<input id="delivery-postcode" type="text" name="delivery_postcode" maxlength="5" inputmode="numeric" autocomplete="postal-code" value="<?php echo checkout_html($form_postcode); ?>" placeholder="e.g. 84000">
<small>Enter a 5-digit Malaysian postcode.</small>
</div>
</div>
</div>
</section>

<section class="checkout-card" aria-labelledby="payment-heading">
<div class="checkout-card-heading">
<span class="checkout-step-number" aria-hidden="true">2</span>
<div>
<h3 id="payment-heading">Payment Method</h3>
<p>Select one method. Online payment remains pending until it is confirmed.</p>
</div>
</div>

<fieldset class="checkout-fieldset">
<legend class="checkout-visually-hidden">Payment method</legend>
<div class="checkout-payment-options">
<label class="checkout-choice-card">
<input type="radio" name="payment" value="Credit Card" <?php if($selected_payment==="Credit Card") echo "checked"; ?>>
<span class="checkout-choice-copy"><strong>Credit Card</strong><small>Payment remains pending until confirmation</small></span>
</label>

<label class="checkout-choice-card">
<input type="radio" name="payment" value="Online Banking" <?php if($selected_payment==="Online Banking") echo "checked"; ?>>
<span class="checkout-choice-copy"><strong>Online Banking</strong><small>Payment remains pending until confirmation</small></span>
</label>

<label class="checkout-choice-card">
<input type="radio" name="payment" value="Cash" <?php if($selected_payment==="Cash") echo "checked"; ?>>
<span class="checkout-choice-copy"><strong>Cash</strong><small>Pay when collecting or receiving your order</small></span>
</label>
</div>
</fieldset>
</section>

</div>

<aside class="checkout-summary-card" aria-labelledby="summary-heading">
<h3 id="summary-heading">Order Summary</h3>

<div class="checkout-summary-items">
<?php foreach($cart as $pid => $qty) { if(!isset($cart_products[$pid])) continue; $row=$cart_products[$pid]; $sub=(float)$row["product_price"]*$qty; ?>
<div class="checkout-summary-item">
<div><strong><?php echo checkout_html($row["product_name"]); ?></strong><small>RM <?php echo number_format((float)$row["product_price"],2); ?> &times; <?php echo (int)$qty; ?></small></div>
<span>RM <?php echo number_format($sub,2); ?></span>
</div>
<?php } ?>

<?php foreach($reward_items as $reward_row) { ?>
<div class="checkout-summary-item checkout-summary-reward">
<div><strong><?php echo checkout_html($reward_row["redeem_reward"]); ?></strong><small>Free reward &times; 1</small></div>
<span>RM 0.00</span>
</div>
<?php } ?>
</div>

<div class="checkout-total-row"><span>Subtotal</span><strong>RM <?php echo number_format($subtotal,2); ?></strong></div>
<div class="checkout-total-row"><span>Delivery fee</span><strong id="delivery-fee">RM <?php echo $selected_delivery_method==="Delivery" ? "5.00" : "0.00"; ?></strong></div>
<div class="checkout-payment-status-row"><span>Payment status</span><strong id="payment-status-preview"><?php echo $selected_payment==="Cash" ? "Unpaid" : ($selected_payment!=="" ? "Pending" : "Select method"); ?></strong></div>
<div class="checkout-grand-total"><span>Total</span><strong id="order-total">RM <?php echo number_format($subtotal + ($selected_delivery_method==="Delivery" ? 5 : 0),2); ?></strong></div>

<p id="checkout-client-message" class="checkout-client-message" role="alert" aria-live="assertive"></p>
<button class="checkout-place-order" type="submit" name="placeorderbtn" value="1">Place Order</button>
<p class="checkout-confirm-note">By placing this order, you confirm that the order and delivery details are correct.</p>
</aside>
</div>
</form>

<?php } else { ?>

<section class="checkout-empty-state">
<div class="checkout-empty-icon" aria-hidden="true">&#128722;</div>
<h3>Your cart is empty</h3>
<p>Add food from the menu before proceeding to checkout.</p>
<a class="checkout-place-order" href="category.php">Browse Menu</a>
</section>

<?php } ?>

</main>

<footer><!-- Footer section -->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

<script>
(function()
{
	const checkoutForm=document.getElementById("checkout-form");
	if(!checkoutForm)
	{
		return;
	}

	const subtotal=<?php echo json_encode((float)$subtotal); ?>;
	const deliveryArea=document.getElementById("delivery-area");
	const deliveryFee=document.getElementById("delivery-fee");
	const orderTotal=document.getElementById("order-total");
	const paymentStatus=document.getElementById("payment-status-preview");
	const clientMessage=document.getElementById("checkout-client-message");
	const addressFields=[
		document.getElementById("delivery-address"),
		document.getElementById("delivery-city"),
		document.getElementById("delivery-state"),
		document.getElementById("delivery-postcode")
	];

	function selectedValue(name)
	{
		const selected=checkoutForm.querySelector('input[name="'+name+'"]:checked');
		return selected ? selected.value : "";
	}

	function updateCheckout()
	{
		const isDelivery=selectedValue("delivery_method")==="Delivery";
		const fee=isDelivery ? 5 : 0;
		deliveryArea.hidden=!isDelivery;
		addressFields.forEach(function(field){ field.required=isDelivery; });
		deliveryFee.textContent="RM "+fee.toFixed(2);
		orderTotal.textContent="RM "+(subtotal+fee).toFixed(2);

		const payment=selectedValue("payment");
		paymentStatus.textContent=payment==="Cash" ? "Unpaid" : (payment!=="" ? "Pending" : "Select method");
		clientMessage.textContent="";
	}

	checkoutForm.querySelectorAll('input[name="delivery_method"],input[name="payment"]').forEach(function(input)
	{
		input.addEventListener("change",updateCheckout);
	});
	addressFields.forEach(function(field)
	{
		field.addEventListener("input",function(){ clientMessage.textContent=""; });
		field.addEventListener("change",function(){ clientMessage.textContent=""; });
	});

	checkoutForm.addEventListener("submit",function(event)
	{
		clientMessage.textContent="";
		if(selectedValue("payment")==="")
		{
			event.preventDefault();
			clientMessage.textContent="Please select a payment method.";
			checkoutForm.querySelector('input[name="payment"]').focus();
			return;
		}

		if(selectedValue("delivery_method")==="Delivery")
		{
			for(let i=0;i<addressFields.length;i++)
			{
				if(addressFields[i].value.trim()==="")
				{
					event.preventDefault();
					clientMessage.textContent="Please complete every delivery address field.";
					addressFields[i].focus();
					return;
				}
			}

			if(!/^\d{5}$/.test(document.getElementById("delivery-postcode").value.trim()))
			{
				event.preventDefault();
				clientMessage.textContent="Please enter a valid 5-digit postcode.";
				document.getElementById("delivery-postcode").focus();
			}
		}
	});

	updateCheckout();
})();
</script>

</body>
</html>
