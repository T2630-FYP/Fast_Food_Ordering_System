<?php
//only logged in members can use the cart
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

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

//remove any saved cart items whose product is missing, deleted or no longer active
$removed_items = array();
foreach($cart as $pid => $qty)
{
	$stmt = mysqli_prepare($connect,"SELECT product_name,product_price,product_stock FROM product WHERE product_id=? AND product_isDelete=0 AND product_status='Active'");
	mysqli_stmt_bind_param($stmt,"s",$pid);
	mysqli_stmt_execute($stmt);
	$avail = mysqli_stmt_get_result($stmt);
	if(mysqli_num_rows($avail) == 0)
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
		$cart_products[$pid] = mysqli_fetch_assoc($avail);
		mysqli_stmt_close($stmt);
	}
}
?>

<!DOCTYPE html>
<html>

<head><!--Shopping cart page-->
<title>Cart</title>
<link rel="stylesheet" href="style.css">

<style>
.reward-label
{background-color:#FF7A00;
color:#FFFFFF;
font-weight:bold;
font-size:8pt;
border-radius:10px;
padding:2px 8px 2px 8px;
margin-left:6px;}

tr.reward-item
{background-color:#FFF3E6;}

.free-price
{color:#2E7D32;
font-weight:bold;}

.stock-note
{font-size:8pt;
color:#888888;}
</style>

</head>

<body>

<div id="header"><!--Header section for logo, website name and slogan-->
<img src="image/logo.png" width="80px" height="80px" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar"><!--Customer navigation bar-->
<a href="category.php">Menu</a>
<a href="dashboard.php">My Dashboard</a>
<a href="order_history.php">Order History</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Your Shopping Cart</h2>
<p class="intro">Review the items in your cart below. You can change the quantity or remove items before checking out. Your cart is saved to your account, so it will still be here the next time you log in. Redeemed rewards appear here as free items.</p>

<?php
//if an add/update attempt produced a message (item not available, or stock limit reached), show it
if(isset($cart_error))
{
?>
<p class="msg"><?php echo htmlspecialchars($cart_error,ENT_QUOTES,"ISO-8859-1"); ?></p>
<?php
}
?>

<?php
if(isset($cart_notice))
{
?>
<p class="msg"><?php echo htmlspecialchars($cart_notice,ENT_QUOTES,"ISO-8859-1"); ?></p>
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
//work out what is in the cart: normal items (database) and reward items (redemption table)
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

//show the cart only if it has something in it (a normal item or a reward item)
if($has_normal || $has_reward)
{
?>

<table class="menu-table" width="100%" border="1"><!--Table section for displaying the cart items-->
<tr>
<th>Item</th>
<th>Unit Price</th>
<th>Quantity</th>
<th>Subtotal</th>
<th>Action</th>
</tr>

<?php
$cart_total = 0;

//----- normal items (from the saved cart in the database) -----
if($has_normal)
{
	foreach($cart as $pid => $qty)
	{
		//safety check: skip anything the cleanup above did not already handle
		if(!isset($cart_products[$pid]))
		{
			continue;
		}

		$row = $cart_products[$pid];
		$pname = $row["product_name"];
		$pprice = $row["product_price"];
		$pstock = $row["product_stock"];
		$subtotal = $pprice * $qty;
		$cart_total = $cart_total + $subtotal;
?>

<tr>
<td><?php echo $pname; ?></td>
<td align="center">RM <?php echo number_format($pprice,2); ?></td>
<td align="center">
<form method="post" action="cart.php" style="display:inline;">
<input type="hidden" name="product_id" value="<?php echo htmlspecialchars($pid,ENT_QUOTES,"ISO-8859-1"); ?>">
<input type="number" name="qty" class="qty" min="1" max="<?php echo $pstock; ?>" value="<?php echo $qty; ?>">
<input type="submit" name="updatebtn" value="Update">
<br><span class="stock-note">Stock available: <?php echo $pstock; ?></span>
</form>
</td>
<td align="center">RM <?php echo number_format($subtotal,2); ?></td>
<td align="center">
<form method="post" action="cart.php" style="display:inline;">
<input type="hidden" name="product_id" value="<?php echo htmlspecialchars($pid,ENT_QUOTES,"ISO-8859-1"); ?>">
<input class="btn-small" type="submit" name="removebtn" value="Remove">
</form>
</td>
</tr>

<?php
	}
}
?>

<?php
//----- reward items (redeemed rewards waiting in the cart) -----
if($has_reward)
{
	while($rrow = mysqli_fetch_assoc($reward_result))
	{
		$rd_id = $rrow["redeem_id"];
		$rd_name = $rrow["redeem_reward"];
?>

<tr class="reward-item">
<td><b><?php echo $rd_name; ?></b><span class="reward-label">FREE REWARD</span></td>
<td align="center"><span class="free-price">FREE</span></td>
<td align="center">1</td>
<td align="center"><span class="free-price">RM 0.00</span></td>
<td align="center">
<form method="post" action="cart.php" style="display:inline;" onsubmit="return confirm('Remove this reward from your cart? Your points will be returned to your account.')">
<input type="hidden" name="redeem_id" value="<?php echo (int)$rd_id; ?>">
<input class="btn-small" type="submit" name="remove_rewardbtn" value="Remove">
</form>
</td>
</tr>

<?php
	}
}
?>

<tr>
<td colspan="3" align="right"><b>Total</b></td>
<td align="center"><span class="price">RM <?php echo number_format($cart_total,2); ?></span></td>
<td align="center">
<?php
//if the cart holds any reward items, warn that their points will be returned
$clear_confirm = $has_reward ? "Clear your entire cart? All items and redeemed rewards will be removed, and your reward points will be returned to your account." : "Clear your entire cart?";
?>
<form method="post" action="cart.php" style="display:inline;" onsubmit="return confirm('<?php echo $clear_confirm; ?>')">
<input class="btn-small" type="submit" name="clearbtn" value="Clear">
</form>
</td>
</tr>

</table>

<p style="text-align:center;"><a class="btn" href="category.php">Continue Shopping</a> &nbsp; <a class="btn" href="checkout.php">Proceed to Checkout</a></p>

<?php
}
else
{
?>

<p class="intro" style="text-align:center;">Your cart is empty. <a href="category.php">Start shopping</a> to add some items.</p>

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
