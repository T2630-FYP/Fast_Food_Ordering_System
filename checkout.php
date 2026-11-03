<?php
// Only logged-in customers can check out.
session_start();
if(!isset($_SESSION["customer_id"]))
{
	require_once(__DIR__."/customer_auth_helpers.php");
	easyorder_redirect_customer_to_login();
}

include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
require_once("order_pricing_helpers.php");
require_once("wallet_helpers.php");

$mid = (int)$_SESSION["customer_id"];
$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");

function checkout_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// A saved profile address is only the initial value. Changes made at checkout
// belong to this order and are never written back to the customer profile.
$saved_address = array("address"=>"","state"=>"","city"=>"","postcode"=>"");
$stmt = mysqli_prepare($connect,"SELECT customer_address,customer_state,customer_city,customer_postcode FROM customer WHERE customer_id=? AND customer_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$saved_address_result = mysqli_stmt_get_result($stmt);
if($saved_address_row = mysqli_fetch_assoc($saved_address_result))
{
	$saved_address = array(
		"address" => $saved_address_row["customer_address"],
		"state" => $saved_address_row["customer_state"],
		"city" => $saved_address_row["customer_city"],
		"postcode" => $saved_address_row["customer_postcode"]
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
	// Read the submitted PIN without copying it into the session or order record.
	$wallet_pin = (string)($_POST["wallet_pin"] ?? "");

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

		if(!in_array($payment,array("Credit Card","Cash","EasyOrder Wallet"),true))
		{
			throw new Exception("Please select a valid payment method.");
		}
		if($payment==="EasyOrder Wallet" && !easyorder_wallet_pin_valid($wallet_pin))
		{
			throw new Exception("Enter your 6-digit Wallet PIN to pay with EasyOrder Wallet.");
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

		// Payment status is independent from order status. Card payments remain
		// Pending until the simulated payment step confirms success.
		$payment_status = $payment==="Cash" ? "Unpaid" : ($payment==="EasyOrder Wallet" ? "Paid" : "Pending");
		$order_datetime = date("Y-m-d H:i:s");

		mysqli_begin_transaction($connect);
		$transaction_started = true;

		// Serialise checkout/cart operations for this customer and recheck the account.
		$stmt = mysqli_prepare($connect,"SELECT customer_isDelete FROM customer WHERE customer_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$customer_result = mysqli_stmt_get_result($stmt);
		$customer_row = mysqli_fetch_assoc($customer_result);
		mysqli_stmt_close($stmt);
		if(!$customer_row || (int)$customer_row["customer_isDelete"]===1)
		{
			throw new Exception("This customer account is no longer active.");
		}

		// Read and lock the latest cart, not the older page snapshot.
		$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty FROM cart WHERE cart_customer=? ORDER BY cart_product FOR UPDATE");
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
			$stmt = mysqli_prepare($connect,"SELECT redeem_id,redeem_reward,redeem_product FROM redemption WHERE redeem_customer=? AND redeem_status='Cart' ORDER BY redeem_id FOR UPDATE");
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
		$order_subtotal = 0.00;
		foreach($order_cart as $pid => $qty)
		{
			if($qty<=0)
			{
				throw new Exception("An item in your cart has an invalid quantity. Please review your cart.");
			}

			$stmt = mysqli_prepare($connect,"SELECT product_name,product_category,product_price,product_stock,product_status FROM product WHERE product_id=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$pid);
			mysqli_stmt_execute($stmt);
			$product_result = mysqli_stmt_get_result($stmt);
			$product_row = mysqli_fetch_assoc($product_result);
			mysqli_stmt_close($stmt);

			if(!$product_row || $product_row["product_status"]!=="Active" || (int)$product_row["product_stock"]<=0)
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
			$order_subtotal += (float)$product_row["product_price"] * $qty;
		}

		// Calculate SST from merchandise only; rewards remain free and delivery is not taxed.
		$order_pricing = easyorder_order_pricing($order_subtotal,$delivery);
		$total = $order_pricing["total"];
		$earned_points = easyorder_food_reward_points($order_pricing["subtotal"]);
		$locked_wallet = null;
		if($payment==="EasyOrder Wallet")
		{
			// Lock the customer's own wallet before checking PIN and balance so
			// concurrent checkouts cannot spend the same funds.
			$locked_wallet = easyorder_wallet_load($connect,$mid,true);
			if(!$locked_wallet)
			{
				throw new Exception("Create an EasyOrder Wallet before choosing wallet payment.");
			}
			if(!easyorder_wallet_verify_pin($connect,$locked_wallet,$wallet_pin))
			{
				throw new Exception("The Wallet PIN is incorrect. No order was created and no balance was deducted.");
			}
			if((float)$locked_wallet["wallet_balance"]<$total)
			{
				throw new Exception("Your Wallet balance is insufficient. Top up the wallet or choose another payment method.");
			}
		}

		$stmt = mysqli_prepare($connect,"INSERT INTO orders(order_customer,order_date,order_total,order_payment,order_payment_status,order_delivery,order_address,order_points_earned,order_status) VALUES(?,?,?,?,?,?,?,?,'Preparing')");
		mysqli_stmt_bind_param($stmt,"isdssssi",$mid,$order_datetime,$total,$payment,$payment_status,$delivery,$address,$earned_points);
		mysqli_stmt_execute($stmt);
		$orderid = mysqli_insert_id($connect);
		mysqli_stmt_close($stmt);

		// Create one safe payment record for the card flow. Card details are
		// collected on the next page and are never stored in the database.
		if($payment==="Credit Card")
		{
			$stmt = mysqli_prepare($connect,"INSERT INTO payments(payment_order,payment_method,payment_amount,payment_status) VALUES(?,?,?,'Pending')");
			if(!$stmt)
			{
				throw new Exception("The payment record could not be prepared.");
			}
			mysqli_stmt_bind_param($stmt,"isd",$orderid,$payment,$total);
			if(!mysqli_stmt_execute($stmt))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The payment record could not be created.");
			}
			mysqli_stmt_close($stmt);
		}
		else if($payment==="EasyOrder Wallet")
		{
			// The order, payment metadata, wallet debit and wallet history all
			// share this transaction. A failure in any write rolls everything back.
			$reference = easyorder_wallet_reference("PAY");
			$paid_at = date("Y-m-d H:i:s");
			$stmt = mysqli_prepare($connect,"INSERT INTO payments(payment_order,payment_reference,payment_method,payment_amount,payment_status,payment_paid_at) VALUES(?,?,?,?,'Paid',?)");
			mysqli_stmt_bind_param($stmt,"issds",$orderid,$reference,$payment,$total,$paid_at);
			if(!mysqli_stmt_execute($stmt))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The Wallet payment record could not be created.");
			}
			mysqli_stmt_close($stmt);

			$wallet_id = (int)$locked_wallet["wallet_id"];
			$stmt = mysqli_prepare($connect,"UPDATE wallets SET wallet_balance=wallet_balance-? WHERE wallet_id=? AND wallet_customer=? AND wallet_balance>=?");
			mysqli_stmt_bind_param($stmt,"diid",$total,$wallet_id,$mid,$total);
			if(!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt)!==1)
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The Wallet balance changed before payment completed. Please try again.");
			}
			mysqli_stmt_close($stmt);

			$request_key = hash("sha256","checkout:".$submitted_token);
			$stmt = mysqli_prepare($connect,"INSERT INTO wallet_transactions(wallet_id,wallet_order,wallet_transaction_type,wallet_transaction_amount,wallet_transaction_reference,wallet_transaction_status,wallet_request_key) VALUES(?,?,'Payment',?,?,'Paid',?)");
			mysqli_stmt_bind_param($stmt,"iidss",$wallet_id,$orderid,$total,$reference,$request_key);
			if(!mysqli_stmt_execute($stmt))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The Wallet transaction could not be recorded.");
			}
			mysqli_stmt_close($stmt);
		}

		$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_points=customer_points+? WHERE customer_id=?");
		mysqli_stmt_bind_param($stmt,"ii",$earned_points,$mid);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		foreach($order_products as $pid => $product_row)
		{
			$pname = $product_row["product_name"];
			$pcategory = $product_row["product_category"];
			$pprice = (float)$product_row["product_price"];
			$qty = (int)$product_row["cart_qty"];
			$sub = $pprice * $qty;

			$stmt = mysqli_prepare($connect,"INSERT INTO order_items(item_order,item_product,item_name,item_category,item_price,item_qty,item_subtotal) VALUES(?,?,?,?,?,?,?)");
			mysqli_stmt_bind_param($stmt,"isssdid",$orderid,$pid,$pname,$pcategory,$pprice,$qty,$sub);
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
			$stmt = mysqli_prepare($connect,"SELECT product_category FROM product WHERE product_id=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$rpid);mysqli_stmt_execute($stmt);
			$reward_product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);
			if(!$reward_product) throw new Exception("A reward item is no longer available. Please review your cart.");
			$rcategory = $reward_product["product_category"];
			$free_price = 0.00;
			$free_qty = 1;
			$stmt = mysqli_prepare($connect,"INSERT INTO order_items(item_order,item_product,item_name,item_category,item_price,item_qty,item_subtotal) VALUES(?,?,?,?,?,?,?)");
			mysqli_stmt_bind_param($stmt,"isssdid",$orderid,$rpid,$rname,$rcategory,$free_price,$free_qty,$free_price);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}

		if(count($reward_cart)>0)
		{
			$stmt = mysqli_prepare($connect,"UPDATE redemption SET redeem_status='Completed' WHERE redeem_customer=? AND redeem_status='Cart'");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			if(mysqli_stmt_affected_rows($stmt)!==count($reward_cart))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The reward cart changed while placing your order. Please try again.");
			}
			mysqli_stmt_close($stmt);
		}

		$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_customer=?");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		mysqli_commit($connect);
		$transaction_started = false;
		unset($_SESSION["checkout_form"]);
		if($payment==="Credit Card")
		{
			header("location:payment.php?order_id=".$orderid);
		}
		else
		{
			$_SESSION["placed_order_id"] = $orderid;
			header("location:review.php");
		}
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
$selected_payment = in_array($saved_payment,array("Credit Card","Cash","EasyOrder Wallet"),true) ? $saved_payment : "";
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

	$stmt = mysqli_prepare($connect,"SELECT customer_id FROM customer WHERE customer_id=? AND customer_isDelete=0 FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)!==1)
	{
		mysqli_stmt_close($stmt);
		throw new Exception("This customer account is no longer active.");
	}
	mysqli_stmt_close($stmt);

	$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty FROM cart WHERE cart_customer=? ORDER BY cart_product FOR UPDATE");
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
		$stmt = mysqli_prepare($connect,"SELECT product_name,product_category,product_price,product_stock,product_status FROM product WHERE product_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"s",$pid);
		mysqli_stmt_execute($stmt);
		$product_result = mysqli_stmt_get_result($stmt);
		$product_row = mysqli_fetch_assoc($product_result);
		mysqli_stmt_close($stmt);

		if(!$product_row || $product_row["product_status"]!=="Active" || (int)$product_row["product_stock"]<=0 || $qty<=0)
		{
			$removed_items[] = $product_row ? $product_row["product_name"] : "an item that is no longer available";
			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_customer=? AND cart_product=?");
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
			$stmt = mysqli_prepare($connect,"UPDATE cart SET cart_qty=? WHERE cart_customer=? AND cart_product=?");
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
	$stmt = mysqli_prepare($connect,"SELECT redeem_id,redeem_reward,redeem_product FROM redemption WHERE redeem_customer=? AND redeem_status='Cart' ORDER BY redeem_date DESC,redeem_id DESC");
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
$checkout_pricing = easyorder_order_pricing($subtotal,$selected_delivery_method);
$checkout_wallet = null;
try
{
	$checkout_wallet = easyorder_wallet_load($connect,$mid);
}
catch(Throwable $error)
{
	if(!isset($checkout_error))
	{
		$checkout_error = "Wallet payment is temporarily unavailable, but you may choose another payment method.";
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Checkout and payment page -->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout</title>
<link rel="stylesheet" href="public.css?v=20261028-checkout1">
<link rel="stylesheet" href="customer.css?v=20261029-food-bg1">
<script src="customer.js?v=20261028-checkout1" defer></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
</head>

<body class="customer-checkout-page">

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!-- Customer navigation bar -->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-checkout-main">
<?php
require_once __DIR__ . '/product_catalog_helpers.php';

// Keep the design's Credit Card default, while preserving any selection restored
// after a failed checkout. Unavailable Wallet falls back to Credit Card.
$eo_checkout_method = in_array($selected_payment, array('Credit Card','Cash','EasyOrder Wallet'), true)
    ? $selected_payment : 'Credit Card';
if ($eo_checkout_method === 'EasyOrder Wallet' && !$checkout_wallet) {
    $eo_checkout_method = 'Credit Card';
}
$eo_delivery_method = $selected_delivery_method === 'Delivery' ? 'Delivery' : 'Pickup';

// Order summary thumbnails come from the existing product records.
// Images are read only, and are restricted to the local image/ folder.
$eo_checkout_images = array();
$eo_image_ids = array_keys($cart);
foreach ($reward_items as $eo_reward_ref) {
    $eo_image_ids[] = $eo_reward_ref['redeem_product'];
}
$eo_image_ids = array_unique($eo_image_ids);
if ($eo_image_ids) {
    $eo_img_stmt = mysqli_prepare($connect, 'SELECT product_image FROM product WHERE product_id=? LIMIT 1');
    if ($eo_img_stmt) {
        foreach ($eo_image_ids as $eo_img_id) {
            mysqli_stmt_bind_param($eo_img_stmt, 's', $eo_img_id);
            mysqli_stmt_execute($eo_img_stmt);
            $eo_img_result = mysqli_stmt_get_result($eo_img_stmt);
            $eo_img_row = $eo_img_result ? mysqli_fetch_assoc($eo_img_result) : null;
            $eo_checkout_images[(string)$eo_img_id] = catalog_product_image($eo_img_row['product_image'] ?? '');
            if ($eo_img_result) mysqli_free_result($eo_img_result);
        }
        mysqli_stmt_close($eo_img_stmt);
    }
}
?>
<div class="eo-checkout-page" data-subtotal="<?php echo checkout_html(number_format((float)$subtotal, 2, '.', '')); ?>" data-sst="<?php echo checkout_html(number_format((float)$checkout_pricing['sst'], 2, '.', '')); ?>" data-delivery-fee="<?php echo checkout_html(number_format((float)EASYORDER_DELIVERY_FEE, 2, '.', '')); ?>">
    <div class="eo-checkout-topbar">
        <h1>Checkout</h1>
        <a href="cart.php" class="eo-checkout-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
            Back to Cart
        </a>
    </div>

    <?php if (isset($checkout_error)): ?>
        <div class="eo-checkout-alert eo-checkout-alert-error" role="alert"><?php echo checkout_html($checkout_error); ?></div>
    <?php endif; ?>
    <?php if (!empty($removed_items)): ?>
        <div class="eo-checkout-alert" role="status">Unavailable items removed: <?php echo checkout_html(implode(', ', $removed_items)); ?>.</div>
    <?php endif; ?>
    <?php if (!empty($adjusted_items)): ?>
        <div class="eo-checkout-alert" role="status">Stock changed: <?php echo checkout_html(implode(', ', $adjusted_items)); ?>.</div>
    <?php endif; ?>

    <?php if ($has_checkout_items): ?>
    <form id="eo-checkout-form" method="post" action="checkout.php" name="checkoutfrm" novalidate>
        <input type="hidden" name="checkout_token" value="<?php echo checkout_html($checkout_token); ?>">
        <div class="eo-checkout-grid">
            <div class="eo-checkout-left">
                <section class="eo-checkout-panel" aria-labelledby="eo-fulfil-heading">
                    <div class="eo-checkout-panel-title"><h2 id="eo-fulfil-heading">Pickup or Delivery</h2></div>
                    <div class="eo-checkout-panel-inner">
                        <fieldset class="eo-checkout-fieldset">
                            <legend class="eo-checkout-sr-only">Choose pickup or delivery</legend>
                            <div class="eo-checkout-fulfil-options">
                                <label class="eo-checkout-fulfil-choice">
                                    <input type="radio" name="delivery_method" value="Pickup" <?php if ($eo_delivery_method === 'Pickup') echo 'checked'; ?>>
                                    <span class="eo-checkout-fulfil-heading">
                                        <span class="eo-checkout-choice-label">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 9h14l-1 12H6L5 9Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>
                                            <strong>Pickup</strong>
                                        </span>
                                        <span class="eo-checkout-fee eo-checkout-fee-free">FREE</span>
                                    </span>
                                    <span class="eo-checkout-fulfil-bottom">
                                        <span class="eo-checkout-fulfil-description">Collect your order at the restaurant.</span>
                                        <span class="eo-checkout-selection" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>
                                    </span>
                                </label>
                                <label class="eo-checkout-fulfil-choice">
                                    <input type="radio" name="delivery_method" value="Delivery" <?php if ($eo_delivery_method === 'Delivery') echo 'checked'; ?>>
                                    <span class="eo-checkout-fulfil-heading">
                                        <span class="eo-checkout-choice-label">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 5h12v12H2zM14 9h4l4 5v3h-8z"/><circle cx="6.5" cy="18" r="2"/><circle cx="18.5" cy="18" r="2"/></svg>
                                            <strong>Delivery</strong>
                                        </span>
                                        <span class="eo-checkout-fee">RM <?php echo number_format(EASYORDER_DELIVERY_FEE, 2); ?></span>
                                    </span>
                                    <span class="eo-checkout-fulfil-bottom">
                                        <span class="eo-checkout-fulfil-description">Delivered straight to your address.</span>
                                        <span class="eo-checkout-selection" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>
                        <!-- Address appears ONLY when Delivery is selected; backend still validates it. -->
                        <div id="eo-checkout-delivery-address" class="eo-checkout-address" <?php if ($eo_delivery_method !== 'Delivery') echo 'hidden'; ?>>
                            <h3>Delivery Address</h3>
                            <p class="eo-checkout-address-note"><?php echo $has_saved_address ? "Your saved address is filled in below. Changes apply only to this order." : "Enter an address for this order."; ?></p>
                            <div class="eo-checkout-address-fields">
                                <div class="eo-checkout-field eo-checkout-field-full">
                                    <label for="eo-delivery-address">Address <span>*</span></label>
                                    <input id="eo-delivery-address" name="delivery_address" type="text" autocomplete="street-address" maxlength="140" value="<?php echo checkout_html($form_address); ?>" placeholder="House number, building, street and unit number">
                                </div>
                                <div class="eo-checkout-field">
                                    <label for="eo-delivery-city">City <span>*</span></label>
                                    <input id="eo-delivery-city" name="delivery_city" type="text" autocomplete="address-level2" maxlength="50" value="<?php echo checkout_html($form_city); ?>" placeholder="City">
                                </div>
                                <div class="eo-checkout-field">
                                    <label for="eo-delivery-state">State <span>*</span></label>
                                    <select id="eo-delivery-state" name="delivery_state" autocomplete="address-level1">
                                        <option value="">Select state</option>
                                        <?php foreach ($states as $eo_state): ?>
                                        <option value="<?php echo checkout_html($eo_state); ?>" <?php if ($form_state === $eo_state) echo 'selected'; ?>><?php echo checkout_html($eo_state); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="eo-checkout-field">
                                    <label for="eo-delivery-postcode">Postcode <span>*</span></label>
                                    <input id="eo-delivery-postcode" name="delivery_postcode" type="text" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="postal-code" value="<?php echo checkout_html($form_postcode); ?>" placeholder="5-digit postcode">
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="eo-checkout-panel" aria-labelledby="eo-payment-heading">
                    <div class="eo-checkout-panel-title"><h2 id="eo-payment-heading">Payment Method</h2></div>
                    <div class="eo-checkout-panel-inner">
                        <fieldset class="eo-checkout-fieldset">
                            <legend class="eo-checkout-sr-only">Select payment method</legend>
                            <div class="eo-checkout-payment-options">
                                <label class="eo-checkout-pay-choice">
                                    <input type="radio" name="payment" value="Credit Card" <?php if ($eo_checkout_method === 'Credit Card') echo 'checked'; ?>>
                                    <span class="eo-checkout-pay-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h5"/></svg>
                                    </span>
                                    <span class="eo-checkout-pay-copy"><strong>Credit / Debit Card</strong><small>Continue to the card payment page.</small></span>
                                    <span class="eo-checkout-selection" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>
                                </label>
                                <label class="eo-checkout-pay-choice">
                                    <input type="radio" name="payment" value="Cash" <?php if ($eo_checkout_method === 'Cash') echo 'checked'; ?>>
                                    <span class="eo-checkout-pay-icon eo-checkout-cash-icon">
                                        <!-- The selected SINGLE US-dollar banknote icon, no extra icon package. -->
                                        <svg viewBox="0 0 38 25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1.6" y="3.7" width="34.8" height="18" rx="2.2"/><path d="M5.3 8h4M28.5 17h4"/><circle cx="19" cy="12.7" r="7"/><path d="M20.9 9.8c-.5-.8-2.9-1.1-3.8 0-.8 1.1.3 2 1.9 2.4 1.8.4 2.7 1.4 1.9 2.6-.8 1.3-3.3 1.1-4.2.3M19 8.3v8.7"/></svg>
                                    </span>
                                    <span class="eo-checkout-pay-copy"><strong>Cash</strong><small>Pay when collecting or receiving your order.</small></span>
                                    <span class="eo-checkout-selection" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span>
                                </label>
                                <label class="eo-checkout-pay-choice<?php if (!$checkout_wallet) echo ' eo-checkout-pay-disabled'; ?>">
                                    <input type="radio" name="payment" value="EasyOrder Wallet" <?php if ($eo_checkout_method === 'EasyOrder Wallet') echo 'checked'; ?> <?php if (!$checkout_wallet) echo 'disabled'; ?>>
                                    <span class="eo-checkout-pay-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 8V6a2 2 0 0 0-2-2H6a4 4 0 0 0-4 4v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2H6a2 2 0 0 1 0-4"/><path d="M22 13h-6a3 3 0 0 0 0 6h6"/></svg>
                                    </span>
                                    <span class="eo-checkout-pay-copy"><strong>Wallet</strong>
                                        <small><?php echo $checkout_wallet ? 'Available balance: RM '.number_format((float)$checkout_wallet['wallet_balance'],2) : 'Create your wallet to use this payment option.'; ?></small>
                                    </span>
                                    <?php if (!$checkout_wallet): ?><span class="eo-checkout-unavailable">Unavailable</span><?php else: ?><span class="eo-checkout-selection" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span><?php endif; ?>
                                </label>
                            </div>
                        </fieldset>
                        <div id="eo-checkout-wallet-area" class="eo-checkout-wallet-area" <?php if ($eo_checkout_method !== 'EasyOrder Wallet') echo 'hidden'; ?>>
                            <label for="eo-wallet-pin">Wallet PIN <span>*</span></label>
                            <input id="eo-wallet-pin" name="wallet_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" placeholder="6-digit Wallet PIN">
                            <small>Enter your PIN to pay. <a href="wallet_pin_recovery.php">Forgot PIN?</a></small>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="eo-checkout-panel eo-checkout-summary" aria-labelledby="eo-checkout-summary-heading">
                <div class="eo-checkout-panel-title"><h2 id="eo-checkout-summary-heading">Order Summary</h2></div>
                <div class="eo-checkout-summary-inner">
                    <div class="eo-checkout-order-list">
                        <?php foreach ($cart as $eo_pid => $eo_qty): ?>
                            <?php if (!isset($cart_products[$eo_pid])) continue; $eo_item = $cart_products[$eo_pid]; ?>
                            <div class="eo-checkout-order-product">
                                <div class="eo-checkout-food-thumb"><img src="<?php echo checkout_html($eo_checkout_images[(string)$eo_pid] ?? 'image/logo.jpg'); ?>" alt=""></div>
                                <div class="eo-checkout-product-info"><strong><?php echo checkout_html($eo_item['product_name']); ?></strong><small>Quantity: <?php echo (int)$eo_qty; ?></small></div>
                                <strong class="eo-checkout-product-price">RM <?php echo number_format((float)$eo_item['product_price'] * (int)$eo_qty, 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($reward_items as $eo_reward): ?>
                            <div class="eo-checkout-order-product">
                                <div class="eo-checkout-food-thumb"><img src="<?php echo checkout_html($eo_checkout_images[(string)$eo_reward['redeem_product']] ?? 'image/logo.jpg'); ?>" alt=""></div>
                                <div class="eo-checkout-product-info"><strong><?php echo checkout_html($eo_reward['redeem_reward']); ?></strong><small>Free reward · Quantity: 1</small></div>
                                <strong class="eo-checkout-product-price">RM 0.00</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="eo-checkout-money-breakdown">
                        <div class="eo-checkout-money-row"><span>Subtotal</span><strong>RM <?php echo number_format((float)$checkout_pricing['subtotal'], 2); ?></strong></div>
                        <div class="eo-checkout-money-row"><span>SST (<?php echo checkout_html((string)(EASYORDER_SST_RATE * 100)); ?>%)</span><strong id="eo-checkout-sst">RM <?php echo number_format((float)$checkout_pricing['sst'], 2); ?></strong></div>
                        <div class="eo-checkout-money-row"><span>Delivery Fee</span><strong id="eo-checkout-fee">RM <?php echo number_format((float)$checkout_pricing['delivery_fee'], 2); ?></strong></div>
                    </div>
                    <div class="eo-checkout-final-total"><span>Final Total</span><strong id="eo-checkout-total">RM <?php echo number_format((float)$checkout_pricing['total'], 2); ?></strong></div>
                    <p id="eo-checkout-feedback" class="eo-checkout-feedback" role="alert" aria-live="assertive" hidden></p>
                    <button type="submit" name="placeorderbtn" value="1" id="eo-checkout-submit" class="eo-checkout-main-button">
                        <span id="eo-checkout-button-label"><?php echo $eo_checkout_method === 'Credit Card' ? 'Continue to Payment' : ($eo_checkout_method === 'EasyOrder Wallet' ? 'Pay with Wallet' : 'Place Order'); ?></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </aside>
        </div>
    </form>
    <?php else: ?>
        <div class="eo-checkout-empty">
            <h2>Your cart is empty</h2>
            <p>Add some food before checking out.</p>
            <a href="category.php" class="eo-checkout-main-button">Browse Menu →</a>
        </div>
    <?php endif; ?>
</div>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>


</body>
</html>
