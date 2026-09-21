<?php
//only logged in members can use the cart
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");
require_once("product_catalog_helpers.php");

$mid = $_SESSION["member_id"];

//check the redemption table exists (created by importing the latest easyorder.sql)
$redemption_ready = false;
$tbl_check = mysqli_query($connect,"SHOW TABLES LIKE 'redemption'");
if(mysqli_num_rows($tbl_check)>0)
{
	$redemption_ready = true;
}

//----- cart operations (done before the cart is displayed) -----
//the cart is saved in the database (table 'cart'), so it stays even after logout or re-login
//all changes lock the member row first, so two tabs for the same member cannot change the cart at the same time
$cart_action = "";
if(isset($_GET["add"]))
{
	$cart_action = "add";
}
else if(isset($_POST["updatebtn"]))
{
	$cart_action = "update";
}
else if(isset($_POST["removebtn"]))
{
	$cart_action = "remove";
}
else if(isset($_POST["clearbtn"]))
{
	$cart_action = "clear";
}
else if(isset($_POST["remove_rewardbtn"]))
{
	$cart_action = "remove_reward";
}

if($cart_action!="")
{
	$transaction_started = false;

	try
	{
		mysqli_begin_transaction($connect);
		$transaction_started = true;

		//this row is also used as a per-member lock for every cart/reward operation
		$stmt = mysqli_prepare($connect,"SELECT member_isDelete FROM member WHERE member_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$member_result = mysqli_stmt_get_result($stmt);
		$member_row = mysqli_fetch_assoc($member_result);
		mysqli_stmt_close($stmt);

		if(!$member_row || $member_row["member_isDelete"]==1)
		{
			throw new Exception("This member account is no longer active.");
		}

		if($cart_action=="add" || $cart_action=="update" || $cart_action=="remove")
		{
			$pid = trim($cart_action=="add" ? (string)$_GET["add"] : (string)($_POST["product_id"] ?? ""));
			if($pid=="")
			{
				throw new Exception("The selected item is invalid.");
			}
		}

		if($cart_action=="add")
		{
			//lock the latest product stock before checking or increasing the quantity
			$stmt = mysqli_prepare($connect,"SELECT product_name,product_stock FROM product WHERE product_id=? AND product_isDelete=0 AND product_status='Active' AND product_stock>0 FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$pid);
			mysqli_stmt_execute($stmt);
			$product_result = mysqli_stmt_get_result($stmt);
			$prow = mysqli_fetch_assoc($product_result);
			mysqli_stmt_close($stmt);

			if(!$prow)
			{
				$_SESSION["cart_error"] = "Sorry, that item is not available and was not added to your cart.";
			}
			else
			{
				$stmt = mysqli_prepare($connect,"SELECT cart_qty FROM cart WHERE cart_member=? AND cart_product=? FOR UPDATE");
				mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
				mysqli_stmt_execute($stmt);
				$cart_rows = mysqli_stmt_get_result($stmt);
				$current_qty = 0;
				while($crow = mysqli_fetch_assoc($cart_rows))
				{
					$current_qty = $current_qty + (int)$crow["cart_qty"];
				}
				mysqli_stmt_close($stmt);

				$newqty = $current_qty + 1;
				if($newqty > $prow["product_stock"])
				{
					$_SESSION["cart_error"] = "You already have the maximum available stock (".$prow["product_stock"].") of ".$prow["product_name"]." in your cart.";
				}
				else
				{
					//replace any old duplicate rows with one accurate row
					$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
					mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);

					$stmt = mysqli_prepare($connect,"INSERT INTO cart(cart_member,cart_product,cart_qty) VALUES(?,?,?)");
					mysqli_stmt_bind_param($stmt,"isi",$mid,$pid,$newqty);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
					$_SESSION["cart_notice"] = $prow["product_name"]." has been added to your cart.";
				}
			}
		}
		else if($cart_action=="update")
		{
			$qty = (int)($_POST["qty"] ?? 0);
			$stmt = mysqli_prepare($connect,"SELECT product_name,product_stock,product_status,product_isDelete FROM product WHERE product_id=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$pid);
			mysqli_stmt_execute($stmt);
			$product_result = mysqli_stmt_get_result($stmt);
			$prow = mysqli_fetch_assoc($product_result);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"SELECT cart_id FROM cart WHERE cart_member=? AND cart_product=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			mysqli_stmt_close($stmt);

			if(!$prow || $prow["product_isDelete"]==1 || $prow["product_status"]!="Active" || $qty<=0 || $prow["product_stock"]<=0)
			{
				$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
				mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				if($prow && ($prow["product_stock"]<=0 || $prow["product_status"]!="Active" || $prow["product_isDelete"]==1))
				{
					$_SESSION["cart_error"] = $prow["product_name"]." is no longer available and has been removed from your cart.";
				}
			}
			else
			{
				if($qty > $prow["product_stock"])
				{
					$qty = (int)$prow["product_stock"];
					$_SESSION["cart_error"] = "Only ".$qty." of ".$prow["product_name"]." are available, so the quantity has been set to ".$qty.".";
				}

				$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
				mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);

				$stmt = mysqli_prepare($connect,"INSERT INTO cart(cart_member,cart_product,cart_qty) VALUES(?,?,?)");
				mysqli_stmt_bind_param($stmt,"isi",$mid,$pid,$qty);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				if(!isset($_SESSION["cart_error"]))
				{
					$_SESSION["cart_notice"] = $prow["product_name"]." quantity has been updated.";
				}
			}
		}
		else if($cart_action=="remove")
		{
			$stmt = mysqli_prepare($connect,"SELECT cart_id FROM cart WHERE cart_member=? AND cart_product=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$_SESSION["cart_notice"] = "The selected item has been removed from your cart.";
		}
		else if($cart_action=="clear")
		{
			$total_refund_points = 0;
			$refund_products = array();

			if($redemption_ready)
			{
				$stmt = mysqli_prepare($connect,"SELECT redeem_product,redeem_points FROM redemption WHERE redeem_member=? AND redeem_status='Cart' FOR UPDATE");
				mysqli_stmt_bind_param($stmt,"i",$mid);
				mysqli_stmt_execute($stmt);
				$clr_result = mysqli_stmt_get_result($stmt);
				while($clr_row = mysqli_fetch_assoc($clr_result))
				{
					$refund_pid = $clr_row["redeem_product"];
					$total_refund_points = $total_refund_points + (int)$clr_row["redeem_points"];
					$refund_products[$refund_pid] = ($refund_products[$refund_pid] ?? 0) + 1;
				}
				mysqli_stmt_close($stmt);

				ksort($refund_products);
				foreach($refund_products as $refund_pid => $refund_qty)
				{
					$stmt = mysqli_prepare($connect,"SELECT product_id FROM product WHERE product_id=? FOR UPDATE");
					mysqli_stmt_bind_param($stmt,"s",$refund_pid);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_store_result($stmt);
					mysqli_stmt_close($stmt);

					$stmt = mysqli_prepare($connect,"UPDATE product SET product_stock=product_stock+? WHERE product_id=?");
					mysqli_stmt_bind_param($stmt,"is",$refund_qty,$refund_pid);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
				}

				if($total_refund_points>0)
				{
					$stmt = mysqli_prepare($connect,"UPDATE member SET member_points=member_points+? WHERE member_id=?");
					mysqli_stmt_bind_param($stmt,"ii",$total_refund_points,$mid);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
				}

				$stmt = mysqli_prepare($connect,"DELETE FROM redemption WHERE redeem_member=? AND redeem_status='Cart'");
				mysqli_stmt_bind_param($stmt,"i",$mid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}

			$stmt = mysqli_prepare($connect,"SELECT cart_id FROM cart WHERE cart_member=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=?");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$_SESSION["cart_notice"] = "Your cart has been cleared.";
		}
		else if($cart_action=="remove_reward")
		{
			if(!$redemption_ready)
			{
				throw new Exception("The reward system is not available.");
			}

			$redeem_id = (int)($_POST["redeem_id"] ?? 0);
			$stmt = mysqli_prepare($connect,"SELECT redeem_product,redeem_points FROM redemption WHERE redeem_id=? AND redeem_member=? AND redeem_status='Cart' FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"ii",$redeem_id,$mid);
			mysqli_stmt_execute($stmt);
			$reward_result = mysqli_stmt_get_result($stmt);
			$reward_row = mysqli_fetch_assoc($reward_result);
			mysqli_stmt_close($stmt);

			//a second click finds no Cart redemption, so points and stock are never refunded twice
			if($reward_row)
			{
				$refund_pid = $reward_row["redeem_product"];
				$refund_points = (int)$reward_row["redeem_points"];

				$stmt = mysqli_prepare($connect,"SELECT product_id FROM product WHERE product_id=? FOR UPDATE");
				mysqli_stmt_bind_param($stmt,"s",$refund_pid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_store_result($stmt);
				mysqli_stmt_close($stmt);

				$stmt = mysqli_prepare($connect,"UPDATE product SET product_stock=product_stock+1 WHERE product_id=?");
				mysqli_stmt_bind_param($stmt,"s",$refund_pid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);

				$stmt = mysqli_prepare($connect,"UPDATE member SET member_points=member_points+? WHERE member_id=?");
				mysqli_stmt_bind_param($stmt,"ii",$refund_points,$mid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);

				$stmt = mysqli_prepare($connect,"DELETE FROM redemption WHERE redeem_id=? AND redeem_member=? AND redeem_status='Cart'");
				mysqli_stmt_bind_param($stmt,"ii",$redeem_id,$mid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				$_SESSION["cart_notice"] = "The reward has been removed and its points have been returned.";
			}
		}

		mysqli_commit($connect);
		$transaction_started = false;
	}
	catch(Throwable $error)
	{
		if($transaction_started)
		{
			mysqli_rollback($connect);
		}
		$_SESSION["cart_error"] = $error->getMessage();
	}

	//Post/Redirect/Get also removes ?add=... so refresh cannot repeat an action
	header("location:cart.php");
	exit();
}

//show a message saved before the redirect, then remove it from the session
if(isset($_SESSION["cart_error"]))
{
	$cart_error = $_SESSION["cart_error"];
	unset($_SESSION["cart_error"]);
}
if(isset($_SESSION["cart_notice"]))
{
	$cart_notice = $_SESSION["cart_notice"];
	unset($_SESSION["cart_notice"]);
}

//load this member's saved cart (normal items) from the database
$cart = array();
$cart_products = array();
$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty FROM cart WHERE cart_member=?");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$cart_result = mysqli_stmt_get_result($stmt);
while($cart_row = mysqli_fetch_assoc($cart_result))
{
	$pid = $cart_row["cart_product"];
	$cart[$pid] = ($cart[$pid] ?? 0) + (int)$cart_row["cart_qty"];
}
mysqli_stmt_close($stmt);

//remove unavailable saved items and adjust quantities when stock has dropped
$removed_items = array();
$stock_adjustments = array();
foreach($cart as $pid => $qty)
{
	$stmt = mysqli_prepare($connect,"SELECT product_name,product_desc,product_category,product_price,product_stock,product_status FROM product WHERE product_id=? AND product_isDelete=0 AND product_status='Active'");
	mysqli_stmt_bind_param($stmt,"s",$pid);
	mysqli_stmt_execute($stmt);
	$avail = mysqli_stmt_get_result($stmt);
	$product_row = mysqli_fetch_assoc($avail);
	if(!$product_row || (int)$product_row["product_stock"]<=0 || $qty<=0)
	{
		mysqli_stmt_close($stmt);
		//keep the product name (if the record still exists) for the message, then drop it from the cart
		$stmt = mysqli_prepare($connect,"SELECT product_name FROM product WHERE product_id=?");
		mysqli_stmt_bind_param($stmt,"s",$pid);
		mysqli_stmt_execute($stmt);
		$nameq = mysqli_stmt_get_result($stmt);
		if(mysqli_num_rows($nameq) > 0)
		{
			$nrow = mysqli_fetch_assoc($nameq);
			$removed_items[] = $nrow["product_name"];
		}
		else
		{
			$removed_items[] = "an item that is no longer available";
		}
		mysqli_stmt_close($stmt);
		$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
		mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		unset($cart[$pid]);
	}
	else
	{
		mysqli_stmt_close($stmt);
		$latest_stock = (int)$product_row["product_stock"];
		if($qty>$latest_stock)
		{
			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_member=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"INSERT INTO cart(cart_member,cart_product,cart_qty) VALUES(?,?,?)");
			mysqli_stmt_bind_param($stmt,"isi",$mid,$pid,$latest_stock);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);

			$cart[$pid] = $latest_stock;
			$stock_adjustments[] = $product_row["product_name"]." was adjusted to ".$latest_stock." because the available stock changed.";
		}
		$cart_products[$pid] = $product_row;
	}
}

//prepare the complete cart summary before rendering the page
$has_normal = count($cart)>0;
$reward_count = 0;
$reward_result = false;
if($redemption_ready)
{
	$stmt = mysqli_prepare($connect,"SELECT r.*,p.product_name FROM redemption r LEFT JOIN product p ON p.product_id=r.redeem_product WHERE r.redeem_member=? AND r.redeem_status='Cart' ORDER BY r.redeem_date DESC,r.redeem_id DESC");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$reward_result = mysqli_stmt_get_result($stmt);
	$reward_count = mysqli_num_rows($reward_result);
	mysqli_stmt_close($stmt);
}
$has_reward = $reward_count>0;

$cart_total = 0;
$normal_item_count = 0;
foreach($cart as $pid => $qty)
{
	if(isset($cart_products[$pid]))
	{
		$cart_total = $cart_total + ((float)$cart_products[$pid]["product_price"] * $qty);
		$normal_item_count = $normal_item_count + $qty;
	}
}
$cart_item_count = $normal_item_count + $reward_count;
$clear_confirm = $has_reward ? "Clear your entire cart? All items and redeemed rewards will be removed, and your reward points will be returned to your account." : "Clear your entire cart?";
?>

<!DOCTYPE html>
<html lang="en">

<head><!--Database-backed shopping cart page-->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart</title>
<link rel="stylesheet" href="style.css?v=20260921-1">
</head>

<body>

<div id="header"><!--Header section for logo, website name and slogan-->
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
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

<main id="main"><!--Main content section-->

<div class="cart-page-heading">
<div>
<h2 class="section-title">Your Shopping Cart</h2>
<p class="intro">Review your items, update quantities and check the order total before checkout.</p>
</div>
</div>

<?php if(isset($cart_error)) { ?>
<div class="form-message form-message-error cart-message" role="alert"><?php echo catalog_h($cart_error); ?></div>
<?php } ?>

<?php if(isset($cart_notice)) { ?>
<div class="form-message form-message-success cart-message" role="status"><?php echo catalog_h($cart_notice); ?></div>
<?php } ?>

<?php if(count($removed_items)>0) { ?>
<div class="form-message form-message-error cart-message" role="alert">Unavailable items were removed from your cart: <?php echo implode(", ",array_map("catalog_h",$removed_items)); ?>.</div>
<?php } ?>

<?php if(count($stock_adjustments)>0) { ?>
<div class="form-message form-message-info cart-message" role="status"><?php echo implode(" ",array_map("catalog_h",$stock_adjustments)); ?></div>
<?php } ?>

<?php if($has_normal || $has_reward) { ?>
<section class="cart-layout" aria-label="Shopping cart">
<div class="cart-items-panel">
<div class="cart-panel-header">
<div>
<h3>Cart Items</h3>
<p><?php echo $cart_item_count; ?> item<?php echo $cart_item_count===1 ? "" : "s"; ?> in your cart</p>
</div>
<form class="cart-clear-form" method="post" action="cart.php" onsubmit="return confirm('<?php echo catalog_h($clear_confirm); ?>')">
<input type="submit" name="clearbtn" value="Clear Cart">
</form>
</div>

<?php if($has_normal) { ?>
<?php foreach($cart as $pid => $qty) { ?>
<?php
if(!isset($cart_products[$pid]))
{
	continue;
}
$row = $cart_products[$pid];
$pname = $row["product_name"];
$pprice = (float)$row["product_price"];
$pstock = (int)$row["product_stock"];
$subtotal = $pprice * $qty;
?>
<article class="cart-item-card">
<a class="cart-item-image" href="product.php?id=<?php echo rawurlencode($pid); ?>">
<img src="<?php echo catalog_h(catalog_product_image($pname)); ?>" alt="<?php echo catalog_h($pname); ?>">
</a>
<div class="cart-item-content">
<div class="cart-item-top">
<div>
<p class="cart-item-category"><?php echo catalog_h($row["product_category"]); ?></p>
<h3><a href="product.php?id=<?php echo rawurlencode($pid); ?>"><?php echo catalog_h($pname); ?></a></h3>
</div>
<div class="cart-item-pricing">
<span>Subtotal</span>
<strong>RM <?php echo number_format($subtotal,2); ?></strong>
</div>
</div>
<p class="cart-item-description"><?php echo catalog_h($row["product_desc"]); ?></p>
<p class="cart-item-stock"><span class="catalog-status catalog-status-in">In Stock</span> <?php echo $pstock; ?> available &middot; RM <?php echo number_format($pprice,2); ?> each</p>
<div class="cart-item-actions">
<form class="cart-quantity-form" method="post" action="cart.php">
<input type="hidden" name="product_id" value="<?php echo catalog_h($pid); ?>">
<label for="qty-<?php echo catalog_h($pid); ?>">Quantity</label>
<input id="qty-<?php echo catalog_h($pid); ?>" type="number" name="qty" min="1" max="<?php echo $pstock; ?>" value="<?php echo $qty; ?>" required>
<input type="submit" name="updatebtn" value="Update">
</form>
<form class="cart-remove-form" method="post" action="cart.php" onsubmit="return confirm('Remove this item from your cart?')">
<input type="hidden" name="product_id" value="<?php echo catalog_h($pid); ?>">
<input type="submit" name="removebtn" value="Remove">
</form>
</div>
</div>
</article>
<?php } ?>
<?php } ?>

<?php if($has_reward) { ?>
<?php while($rrow = mysqli_fetch_assoc($reward_result)) { ?>
<?php
$rd_id = (int)$rrow["redeem_id"];
$rd_name = $rrow["redeem_reward"];
$rd_product_name = $rrow["product_name"] ?: $rd_name;
?>
<article class="cart-item-card cart-reward-card">
<div class="cart-item-image">
<img src="<?php echo catalog_h(catalog_product_image($rd_product_name)); ?>" alt="<?php echo catalog_h($rd_name); ?>">
</div>
<div class="cart-item-content">
<div class="cart-item-top">
<div>
<p class="cart-item-category">EasyOrder Reward</p>
<h3><?php echo catalog_h($rd_name); ?> <span class="cart-reward-label">FREE REWARD</span></h3>
</div>
<div class="cart-item-pricing cart-free-price">
<span>Subtotal</span>
<strong>RM 0.00</strong>
</div>
</div>
<p class="cart-item-description">Redeemed using <?php echo (int)$rrow["redeem_points"]; ?> reward points. Quantity: 1.</p>
<div class="cart-item-actions cart-reward-actions">
<span class="cart-reward-note">Removing this reward returns the points to your account.</span>
<form class="cart-remove-form" method="post" action="cart.php" onsubmit="return confirm('Remove this reward from your cart? Your points will be returned to your account.')">
<input type="hidden" name="redeem_id" value="<?php echo $rd_id; ?>">
<input type="submit" name="remove_rewardbtn" value="Remove">
</form>
</div>
</div>
</article>
<?php } ?>
<?php } ?>
</div>

<aside class="cart-summary-card" aria-label="Order summary">
<h3>Order Summary</h3>
<div class="cart-summary-row">
<span>Items</span>
<strong><?php echo $cart_item_count; ?></strong>
</div>
<div class="cart-summary-row">
<span>Merchandise subtotal</span>
<strong>RM <?php echo number_format($cart_total,2); ?></strong>
</div>
<?php if($has_reward) { ?>
<div class="cart-summary-row cart-summary-reward">
<span>Reward items</span>
<strong>FREE</strong>
</div>
<?php } ?>
<div class="cart-summary-total">
<span>Total</span>
<strong>RM <?php echo number_format($cart_total,2); ?></strong>
</div>
<p class="cart-summary-note">Delivery options and any applicable delivery fee will be confirmed during checkout.</p>
<a class="cart-checkout-button" href="checkout.php">Proceed to Checkout</a>
<a class="cart-continue-link" href="category.php">Continue Shopping</a>
</aside>
</section>
<?php } else { ?>
<section class="cart-empty-state">
<div class="cart-empty-icon" aria-hidden="true">&#128722;</div>
<h3>Your cart is empty</h3>
<p>Browse the EasyOrder menu and add your favourite food to begin an order.</p>
<a class="btn" href="category.php">Browse Menu</a>
</section>
<?php } ?>

</main>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
