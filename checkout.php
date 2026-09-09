<?php
//only logged in members can check out
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

$mid = $_SESSION["member_id"];

$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");

//check the redemption table exists (created by importing the latest easyorder.sql)
$redemption_ready = false;
$tbl_check = mysqli_query($connect,"SHOW TABLES LIKE 'redemption'");
if(mysqli_num_rows($tbl_check)>0)
{
	$redemption_ready = true;
}

//place the order before any HTML is sent, then redirect so refresh cannot submit it again
if(isset($_POST["placeorderbtn"]))
{
	$submitted_token = (string)($_POST["checkout_token"] ?? "");
	$valid_token = isset($_SESSION["checkout_token"]) && $submitted_token!="" && hash_equals($_SESSION["checkout_token"],$submitted_token);
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
		$member = (int)$_SESSION["member_id"];
		$date = date("Y-m-d");
		$payment = trim((string)($_POST["payment"] ?? ""));
		$allowed_payments = array("Credit Card","Online Banking","Cash");
		if(!in_array($payment,$allowed_payments,true))
		{
			throw new Exception("Please select a valid payment method.");
		}

		$delivery = isset($_POST["delivery"]) ? "Yes" : "No";
		$address = "";
		if($delivery=="Yes")
		{
			$delivery_address = trim((string)($_POST["delivery_address"] ?? ""));
			$delivery_city = trim((string)($_POST["delivery_city"] ?? ""));
			$delivery_state = trim((string)($_POST["delivery_state"] ?? ""));
			$delivery_postcode = trim((string)($_POST["delivery_postcode"] ?? ""));

			if($delivery_address=="" || $delivery_city=="" || !in_array($delivery_state,$states,true) || !preg_match("/^\\d{5}$/",$delivery_postcode))
			{
				throw new Exception("Please enter a complete and valid delivery address.");
			}

			$address = $delivery_address.", ".$delivery_postcode." ".$delivery_city.", ".$delivery_state;
			if(strlen($address)>255)
			{
				throw new Exception("The delivery address is too long. Please shorten it and try again.");
			}
		}

		mysqli_begin_transaction($connect);
		$transaction_started = true;

		//serialize every cart/reward operation for this member and recheck that the account is active
		$stmt = mysqli_prepare($connect,"SELECT member_isDelete FROM member WHERE member_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$member);
		mysqli_stmt_execute($stmt);
		$member_result = mysqli_stmt_get_result($stmt);
		$member_row = mysqli_fetch_assoc($member_result);
		mysqli_stmt_close($stmt);
		if(!$member_row || $member_row["member_isDelete"]==1)
		{
			throw new Exception("This member account is no longer active.");
		}

		//read and lock the latest cart rows inside the transaction (not the old page snapshot)
		$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty FROM cart WHERE cart_member=? ORDER BY cart_product FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$member);
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
			mysqli_stmt_bind_param($stmt,"i",$member);
			mysqli_stmt_execute($stmt);
			$reward_result = mysqli_stmt_get_result($stmt);
			while($reward_row = mysqli_fetch_assoc($reward_result))
			{
				$reward_cart[] = $reward_row;
			}
			mysqli_stmt_close($stmt);
		}

		if(count($order_cart)==0 && count($reward_cart)==0)
		{
			throw new Exception("Your cart is empty. Please add an item before placing an order.");
		}

		//always lock products in id order to avoid deadlocks between two different members
		ksort($order_cart);
		$order_products = array();
		$total = 0;
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

			if(!$product_row || $product_row["product_isDelete"]==1 || $product_row["product_status"]!="Active")
			{
				throw new Exception("An item in your cart is no longer available. Please review your cart.");
			}
			if($qty > (int)$product_row["product_stock"])
			{
				throw new Exception("There is not enough stock for ".$product_row["product_name"].". Please review your cart.");
			}
			if((float)$product_row["product_price"]<0)
			{
				throw new Exception($product_row["product_name"]." has an invalid price. Please contact the admin.");
			}

			$product_row["cart_qty"] = $qty;
			$order_products[$pid] = $product_row;
			$total = $total + ((float)$product_row["product_price"] * $qty);
		}

		if($delivery=="Yes")
		{
			$total = $total + 5;
		}

		$stmt = mysqli_prepare($connect,"INSERT INTO orders(order_member,order_date,order_total,order_payment,order_delivery,order_address,order_status) VALUES(?,?,?,?,?,?,'Preparing')");
		mysqli_stmt_bind_param($stmt,"isdsss",$member,$date,$total,$payment,$delivery,$address);
		mysqli_stmt_execute($stmt);
		$orderid = mysqli_insert_id($connect);
		mysqli_stmt_close($stmt);

		$earned_points = max(0,(int)floor($total * 10));
		$stmt = mysqli_prepare($connect,"UPDATE member SET member_points=member_points+? WHERE member_id=?");
		mysqli_stmt_bind_param($stmt,"ii",$earned_points,$member);
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

			//the stock condition is a final safeguard even though the row is already locked
			$stmt = mysqli_prepare($connect,"UPDATE product SET product_stock=product_stock-? WHERE product_id=? AND product_stock>=?");
			mysqli_stmt_bind_param($stmt,"isi",$qty,$pid,$qty);
			mysqli_stmt_execute($stmt);
			if(mysqli_stmt_affected_rows($stmt)!=1)
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The stock changed while placing your order. Please review your cart and try again.");
			}
			mysqli_stmt_close($stmt);
		}

		//reward stock and points were reserved during redemption; only attach the locked rows to this order
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
			mysqli_stmt_bind_param($stmt,"i",$member);
			mysqli_stmt_execute($stmt);
			if(mysqli_stmt_affected_rows($stmt)!=count($reward_cart))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The reward cart changed while placing your order. Please try again.");
			}
			mysqli_stmt_close($stmt);
		}

		$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=?");
		mysqli_stmt_bind_param($stmt,"i",$member);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		mysqli_commit($connect);
		$transaction_started = false;
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

//one token represents one checkout form; the first submitted request consumes it
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

//load a consistent cart snapshot for the page; checkout itself will read and lock everything again
$cart = array();
$cart_products = array();
$removed_items = array();
$subtotal = 0;
$display_transaction = false;

try
{
	mysqli_begin_transaction($connect);
	$display_transaction = true;

	$stmt = mysqli_prepare($connect,"SELECT member_id FROM member WHERE member_id=? AND member_isDelete=0 FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)!=1)
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

		if(!$product_row || $product_row["product_isDelete"]==1 || $product_row["product_status"]!="Active")
		{
			$removed_items[] = $product_row ? $product_row["product_name"] : "an item that is no longer available";
			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			unset($cart[$pid]);
		}
		else
		{
			$cart_products[$pid] = $product_row;
			$subtotal = $subtotal + ((float)$product_row["product_price"] * $qty);
		}
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
?>

<!DOCTYPE html>
<html>

<head><!--Checkout and payment page-->
<title>Checkout</title>
<link rel="stylesheet" href="style.css">

<style>
.free-price
{color:#2E7D32;
font-weight:bold;}

.reward-label
{background-color:#FF7A00;
color:#FFFFFF;
font-weight:bold;
font-size:8pt;
border-radius:10px;
padding:2px 8px 2px 8px;
margin-left:6px;}

#delivery_area
{display:none;
background-color:#FFF8F0;
border:1px solid #FFC72C;
border-radius:6px;
padding:15px 20px 5px 20px;
margin:12px 0px 18px 0px;}

#delivery_area label
{display:inline-block;
width:150px;
color:#9E0B22;
font-weight:bold;
font-size:0.9em;
vertical-align:top;}

#delivery_area input[type="text"],
#delivery_area select
{width:310px;
box-sizing:border-box;
border:1px solid #CCCCCC;
border-radius:4px;
padding:8px 10px 8px 10px;}

#delivery_area select
{background-color:#FFFFFF;}

#delivery_area span.hint
{color:#888888;
font-size:0.75em;}
</style>

<script>
let subtotal=<?php echo $subtotal; ?>;//cart subtotal passed in from PHP

function toggle_delivery()//show or hide the detailed delivery address fields and update the order total
{
	let total=subtotal;
	let delivery_area=document.getElementById("delivery_area");

	if(document.checkoutfrm.delivery.checked)
	{
		delivery_area.style.display="block";
		total=subtotal+5.00;
	}
	else
	{
		delivery_area.style.display="none";
		total=subtotal;
		document.getElementById("msg").innerHTML="";
	}

	document.getElementById("order_total").innerHTML="RM "+total.toFixed(2);
}

function place_order()//check the payment and detailed delivery address before submitting
{
	let payment="";
	let i;

	for(i=0;i<document.checkoutfrm.payment.length;i++)
	{
		if(document.checkoutfrm.payment[i].checked)
		{
			payment=document.checkoutfrm.payment[i].value;
		}
	}

	if(payment=="")
	{
		document.getElementById("msg").innerHTML="Please select a payment method.";
		return false;
	}

	if(document.checkoutfrm.delivery.checked)
	{
		let address=document.checkoutfrm.delivery_address.value.trim();
		let city=document.checkoutfrm.delivery_city.value.trim();
		let state=document.checkoutfrm.delivery_state.value;
		let postcode=document.checkoutfrm.delivery_postcode.value.trim();

		if(address=="")
		{
			document.getElementById("msg").innerHTML="Please enter your address.";
			return false;
		}
		else if(city=="")
		{
			document.getElementById("msg").innerHTML="Please enter your city.";
			return false;
		}
		else if(state=="0")
		{
			document.getElementById("msg").innerHTML="Please select your state.";
			return false;
		}
		else if(!/^\d{5}$/.test(postcode))
		{
			document.getElementById("msg").innerHTML="Please enter a valid 5-digit postcode.";
			return false;
		}
	}

	document.getElementById("msg").innerHTML="";
	return true;
}
</script>

</head>

<body>

<div id="header"><!--Header section for logo, website name and slogan-->
<img src="image/logo.png" width="80px" height="80px" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar"><!--Customer navigation bar-->
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

<div id="main"><!--Main content section-->

<h2 class="section-title">Checkout</h2>
<p class="intro">Please confirm your order, delivery and payment details to complete your order.</p>

<?php
if(isset($checkout_error))
{
?>
<p class="msg"><?php echo htmlspecialchars($checkout_error,ENT_QUOTES,"ISO-8859-1"); ?></p>
<?php
}
?>

<?php
//let the member know if any unavailable items were removed from their cart
if(count($removed_items) > 0)
{
?>
<p class="msg">The following items are no longer available and have been removed from your cart: <?php echo implode(", ",$removed_items); ?>.</p>
<?php
}
?>

<?php
//work out what is in the cart
$has_normal = count($cart) > 0;

$reward_count = 0;
if($redemption_ready)
{
	$stmt = mysqli_prepare($connect,"SELECT * FROM redemption WHERE redeem_member=? AND redeem_status='Cart' ORDER BY redeem_date DESC");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$reward_result = mysqli_stmt_get_result($stmt);
	$reward_count = mysqli_num_rows($reward_result);
}
$has_reward = $reward_count>0;

//only show the checkout form if there is something in the cart (a normal item or a reward item)
if($has_normal || $has_reward)
{
?>

<table class="menu-table" width="100%" border="1"><!--Order summary-->
<tr>
<th>Item</th>
<th>Unit Price</th>
<th>Quantity</th>
<th>Subtotal</th>
</tr>

<?php
//normal items
if($has_normal)
{
	foreach($cart as $pid => $qty)
	{
		if(!isset($cart_products[$pid]))
		{
			continue;
		}

		$row = $cart_products[$pid];
		$pname = $row["product_name"];
		$pprice = $row["product_price"];
		$sub = $pprice * $qty;
?>

<tr>
<td><?php echo $pname; ?></td>
<td align="center">RM <?php echo number_format($pprice,2); ?></td>
<td align="center"><?php echo $qty; ?></td>
<td align="center">RM <?php echo number_format($sub,2); ?></td>
</tr>

<?php
	}
}

//reward items (free)
if($has_reward)
{
	while($rrow = mysqli_fetch_assoc($reward_result))
	{
		$rd_name = $rrow["redeem_reward"];
?>

<tr>
<td><b><?php echo $rd_name; ?></b><span class="reward-label">FREE REWARD</span></td>
<td align="center"><span class="free-price">FREE</span></td>
<td align="center">1</td>
<td align="center"><span class="free-price">RM 0.00</span></td>
</tr>

<?php
	}
}
?>

</table>

<form name="checkoutfrm" method="post" action="" onsubmit="return place_order()"><!--Form section for user input-->
<input type="hidden" name="checkout_token" value="<?php echo htmlspecialchars($checkout_token,ENT_QUOTES,"ISO-8859-1"); ?>">
<div class="form-box">

<h3>Delivery & Payment Details</h3>

<p style="text-align:center; font-size:14pt;">Order Total: <span id="order_total" class="price">RM <?php echo number_format($subtotal,2); ?></span></p>

<h4>Delivery Address</h4>
<p><input type="checkbox" name="delivery" value="Yes" onchange="toggle_delivery()"> Deliver this order to an address (+RM 5.00)</p>

<div id="delivery_area"><!--Detailed delivery address fields-->
<p>
<label>Address *</label>
<input type="text" name="delivery_address" maxlength="140" placeholder="House number, building, street and unit number">
</p>

<p>
<label>City *</label>
<input type="text" name="delivery_city" maxlength="50" placeholder="e.g. Muar">
</p>

<p>
<label>State *</label>
<select name="delivery_state">
<option value="0">Select your state</option>
<?php
foreach($states as $state_name)
{
?>
<option value="<?php echo $state_name; ?>"><?php echo $state_name; ?></option>
<?php
}
?>
</select>
</p>

<p>
<label>Postcode *<br><span class="hint">5 digits</span></label>
<input type="text" name="delivery_postcode" maxlength="5" placeholder="e.g. 84000">
</p>
</div>

<p>Payment Method:
<input type="radio" name="payment" value="Credit Card"> Credit Card
<input type="radio" name="payment" value="Online Banking"> Online Banking
<input type="radio" name="payment" value="Cash"> Cash
</p>

<p><input type="submit" name="placeorderbtn" value="Place Order"></p>

<p class="msg"><span id="msg"></span></p>

</div>
</form>

<?php
}
else
{
?>

<p class="intro" style="text-align:center;">Your cart is empty. <a href="category.php">Browse the menu</a> to add some items first.</p>

<?php
}
?>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
