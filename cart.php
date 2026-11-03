<?php
//only logged in customers can use the cart
session_start();
require_once(__DIR__."/customer_auth_helpers.php");
$menu_add_request = ($_SERVER["REQUEST_METHOD"] ?? "GET")==="POST" && isset($_POST["menu_add_to_cart"]);
$menu_json_request = $menu_add_request && strpos($_SERVER["HTTP_ACCEPT"] ?? "","application/json")!==false;
$menu_return_query = array();
foreach(array("category"=>"return_category","search"=>"return_search") as $key=>$field)
{
	if(isset($_POST[$field]) && is_string($_POST[$field]) && $_POST[$field]!=="") $menu_return_query[$key] = $_POST[$field];
}
$menu_return_location = easyorder_customer_return_location("category.php",$menu_return_query);
function cart_menu_json_response($status,$result)
{
	http_response_code($status);
	header("Content-Type: application/json; charset=UTF-8");
	header("Cache-Control: private, no-store");
	header("X-Content-Type-Options: nosniff");
	echo json_encode($result,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
	exit();
}
if(!isset($_SESSION["customer_id"]))
{
	if($menu_add_request) $_SESSION["customer_login_return"] = $menu_return_location;
	if($menu_json_request) cart_menu_json_response(401,array("ok"=>false,"code"=>"auth","message"=>"Please sign in again to add items to your cart."));
	easyorder_redirect_customer_to_login();
}
// If the account became unavailable, the shared connection redirects to Login.
// Retain the Menu view for that redirect, then restore the prior value on success.
$had_login_return = array_key_exists("customer_login_return",$_SESSION);
$previous_login_return = $_SESSION["customer_login_return"] ?? null;
if($menu_add_request) $_SESSION["customer_login_return"] = $menu_return_location;
include("dataconnection.php");
if($menu_add_request)
{
	if($had_login_return) $_SESSION["customer_login_return"] = $previous_login_return;
	else unset($_SESSION["customer_login_return"]);
}
require_once(__DIR__."/customer_navigation.php");
require_once("product_catalog_helpers.php");

$mid = $_SESSION["customer_id"];

//check the redemption table exists (created by importing the latest easyorder.sql)
$redemption_ready = false;
$tbl_check = mysqli_query($connect,"SHOW TABLES LIKE 'redemption'");
if(mysqli_num_rows($tbl_check)>0)
{
	$redemption_ready = true;
}

//----- cart operations (done before the cart is displayed) -----
//the cart is saved in the database (table 'cart'), so it stays even after logout or re-login
//all changes lock the customer row first, so two tabs for the same customer cannot change the cart at the same time
$cart_action = "";
if($menu_add_request || isset($_GET["add"]))
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
	$cart_feedback = null;
	$add_result = array("ok"=>false,"code"=>"server","message"=>"We could not add this item right now. Please try again.");

	try
	{
		if($menu_add_request)
		{
			$token = $_POST["csrf_token"] ?? "";
			if(!is_string($token) || empty($_SESSION["cart_add_csrf"]) || !hash_equals($_SESSION["cart_add_csrf"],$token))
			{
				$add_result = array("ok"=>false,"code"=>"csrf","message"=>"This menu page has expired. Refresh it and try again.");
				throw new RuntimeException($add_result["message"]);
			}
		}
		mysqli_begin_transaction($connect);
		$transaction_started = true;

		//this row is also used as a per-customer lock for every cart/reward operation
		$stmt = mysqli_prepare($connect,"SELECT customer_isDelete FROM customer WHERE customer_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$customer_result = mysqli_stmt_get_result($stmt);
		$customer_row = mysqli_fetch_assoc($customer_result);
		mysqli_stmt_close($stmt);

		if(!$customer_row || $customer_row["customer_isDelete"]==1)
		{
			$add_result = array("ok"=>false,"code"=>"auth","message"=>"Please sign in again to add items to your cart.");
			throw new Exception("This customer account is no longer active.");
		}

		if($cart_action=="add" || $cart_action=="update" || $cart_action=="remove")
		{
			$raw_pid = $cart_action==="add" && !$menu_add_request ? ($_GET["add"] ?? "") : ($_POST["product_id"] ?? "");
			$pid = is_string($raw_pid) ? trim($raw_pid) : "";
			if($pid==="" || ($menu_add_request && preg_match('/^[A-Z0-9]{1,5}$/D',$pid)!==1))
			{
				$add_result = array("ok"=>false,"code"=>"invalid","message"=>"The selected item is invalid. Refresh the menu and try again.");
				throw new Exception("The selected item is invalid.");
			}
		}

		if($cart_action=="add")
		{
			//lock the latest product stock before checking or increasing the quantity
			$stmt = mysqli_prepare($connect,"SELECT product_name,product_stock FROM product WHERE product_id=? AND product_status='Active' AND product_stock>0 AND EXISTS (SELECT 1 FROM category WHERE category_name=product.product_category AND category_status='Active') FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$pid);
			mysqli_stmt_execute($stmt);
			$product_result = mysqli_stmt_get_result($stmt);
			$prow = mysqli_fetch_assoc($product_result);
			mysqli_stmt_close($stmt);

			if(!$prow)
			{
				$_SESSION["cart_error"] = "Sorry, that item is not available and was not added to your cart.";
				$add_result = array("ok"=>false,"code"=>"unavailable","message"=>$_SESSION["cart_error"]);
			}
			else
			{
				$stmt = mysqli_prepare($connect,"SELECT cart_qty FROM cart WHERE cart_customer=? AND cart_product=? FOR UPDATE");
				mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
				mysqli_stmt_execute($stmt);
				$cart_rows = mysqli_stmt_get_result($stmt);
				$current_qty = 0;
				$already_in_cart = false;
				while($crow = mysqli_fetch_assoc($cart_rows))
				{
					$already_in_cart = true;
					$current_qty = $current_qty + (int)$crow["cart_qty"];
				}
				mysqli_stmt_close($stmt);

				$newqty = $current_qty + 1;
				if($newqty > $prow["product_stock"])
				{
					$_SESSION["cart_error"] = "You already have the maximum available stock (".$prow["product_stock"].") of ".$prow["product_name"]." in your cart.";
					$add_result = array("ok"=>false,"code"=>"stock_limit","message"=>"You have already added the maximum available quantity of ".$prow["product_name"].".");
				}
				else
				{
					if($already_in_cart)
					{
						$stmt = mysqli_prepare($connect,"UPDATE cart SET cart_qty=? WHERE cart_customer=? AND cart_product=?");
						mysqli_stmt_bind_param($stmt,"iis",$newqty,$mid,$pid);
					}
					else
					{
						$position = catalog_next_cart_position($connect,$mid,$redemption_ready);
						$stmt = mysqli_prepare($connect,"INSERT INTO cart(cart_customer,cart_product,cart_qty,cart_position) VALUES(?,?,?,?)");
						mysqli_stmt_bind_param($stmt,"isii",$mid,$pid,$newqty,$position);
					}
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
					$_SESSION["cart_notice"] = $prow["product_name"]." has been added to your cart.";
					$add_result = array("ok"=>true,"code"=>"added","message"=>$_SESSION["cart_notice"],"quantity"=>$newqty);
				}
			}
		}
		else if($cart_action=="update")
		{
			$qty = (int)($_POST["qty"] ?? 0);
			$stmt = mysqli_prepare($connect,"SELECT product_name,product_stock,product_status FROM product WHERE product_id=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$pid);
			mysqli_stmt_execute($stmt);
			$product_result = mysqli_stmt_get_result($stmt);
			$prow = mysqli_fetch_assoc($product_result);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"SELECT cart_id FROM cart WHERE cart_customer=? AND cart_product=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			$cart_item_exists = mysqli_stmt_num_rows($stmt)>0;
			mysqli_stmt_close($stmt);
			if(!$cart_item_exists) throw new Exception("This item is no longer in your cart. Refresh your cart before continuing.");

			if(!$prow || $prow["product_status"]!="Active" || $qty<=0 || $prow["product_stock"]<=0)
			{
				$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_customer=? AND cart_product=?");
				mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				if($prow && ($prow["product_stock"]<=0 || $prow["product_status"]!="Active"))
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

				$stmt = mysqli_prepare($connect,"UPDATE cart SET cart_qty=? WHERE cart_customer=? AND cart_product=?");
				mysqli_stmt_bind_param($stmt,"iis",$qty,$mid,$pid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				if(!isset($_SESSION["cart_error"]))
				{
					$cart_feedback = array("title"=>"Quantity updated","message"=>$prow["product_name"]." quantity has been updated.","no_action"=>true);
				}
			}
		}
		else if($cart_action=="remove")
		{
			$stmt = mysqli_prepare($connect,"SELECT cart_id FROM cart WHERE cart_customer=? AND cart_product=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_customer=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"is",$mid,$pid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$cart_feedback = array("title"=>"Item removed","message"=>"The selected item has been removed from your cart.","no_action"=>true);
		}
		else if($cart_action=="clear")
		{
			$total_refund_points = 0;
			$refund_products = array();

			if($redemption_ready)
			{
				$stmt = mysqli_prepare($connect,"SELECT redeem_product,redeem_points FROM redemption WHERE redeem_customer=? AND redeem_status='Cart' FOR UPDATE");
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
					$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_points=customer_points+? WHERE customer_id=?");
					mysqli_stmt_bind_param($stmt,"ii",$total_refund_points,$mid);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
				}

				$stmt = mysqli_prepare($connect,"DELETE FROM redemption WHERE redeem_customer=? AND redeem_status='Cart'");
				mysqli_stmt_bind_param($stmt,"i",$mid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}

			$stmt = mysqli_prepare($connect,"SELECT cart_id FROM cart WHERE cart_customer=? FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			mysqli_stmt_close($stmt);

			$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_customer=?");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			if($total_refund_points>0)
			{
				$cart_feedback = array("title"=>"Cart cleared","message"=>"Your cart has been cleared. ".number_format($total_refund_points)." reward points have been returned to your account.");
			}
			else $cart_feedback = array("title"=>"Cart cleared","message"=>"Your cart has been cleared.","no_action"=>true);
		}
		else if($cart_action=="remove_reward")
		{
			if(!$redemption_ready)
			{
				throw new Exception("The reward system is not available.");
			}

			$redeem_id = (int)($_POST["redeem_id"] ?? 0);
			$stmt = mysqli_prepare($connect,"SELECT redeem_product,redeem_points FROM redemption WHERE redeem_id=? AND redeem_customer=? AND redeem_status='Cart' FOR UPDATE");
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

				$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_points=customer_points+? WHERE customer_id=?");
				mysqli_stmt_bind_param($stmt,"ii",$refund_points,$mid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);

				$stmt = mysqli_prepare($connect,"DELETE FROM redemption WHERE redeem_id=? AND redeem_customer=? AND redeem_status='Cart'");
				mysqli_stmt_bind_param($stmt,"ii",$redeem_id,$mid);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				$cart_feedback = array("title"=>"Reward removed","message"=>"The reward has been removed from your cart. ".number_format($refund_points)." points have been returned to your account.");
			}
			else
			{
				$cart_feedback = array("title"=>"Cart updated","message"=>"This reward is no longer in your cart.");
			}
		}

		mysqli_commit($connect);
		$transaction_started = false;
		// Publish action feedback only after the database transaction has committed.
		if($cart_feedback!==null)
		{
			unset($_SESSION["cart_notice"]);
			$_SESSION["cart_feedback"] = $cart_feedback;
		}
	}
	catch(Throwable $error)
	{
		if($transaction_started)
		{
			mysqli_rollback($connect);
		}
		$_SESSION["cart_error"] = $error->getMessage();
		if($add_result["ok"]) $add_result = array("ok"=>false,"code"=>"server","message"=>"We could not add this item right now. Please try again.");
	}

	if($menu_add_request)
	{
		// Menu feedback belongs to this action; do not repeat it on the Cart page.
		unset($_SESSION["cart_error"],$_SESSION["cart_notice"]);
		if($menu_json_request)
		{
			$status_codes = array("added"=>200,"auth"=>401,"csrf"=>403,"invalid"=>422,"unavailable"=>409,"stock_limit"=>409,"server"=>500);
			cart_menu_json_response($status_codes[$add_result["code"]],$add_result);
		}
		$_SESSION["menu_cart_feedback"] = array("kind"=>$add_result["ok"] ? "success" : "error","message"=>$add_result["message"]);
		header("Location: ".$menu_return_location."#customer-menu-products",true,303);
		exit();
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

if(isset($_SESSION["cart_feedback"]))
{
	$cart_feedback = $_SESSION["cart_feedback"];
	unset($_SESSION["cart_feedback"]);
}

//load this customer's saved cart (normal items) from the database
$cart = array();
$cart_products = array();
$cart_positions = array();
$stmt = mysqli_prepare($connect,"SELECT cart_product,cart_qty,cart_position FROM cart WHERE cart_customer=? ORDER BY cart_position,cart_id");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$cart_result = mysqli_stmt_get_result($stmt);
while($cart_row = mysqli_fetch_assoc($cart_result))
{
	$pid = $cart_row["cart_product"];
	$cart[$pid] = ($cart[$pid] ?? 0) + (int)$cart_row["cart_qty"];
	$cart_positions[$pid] = (int)$cart_row["cart_position"];
}
mysqli_stmt_close($stmt);

//remove unavailable saved items and adjust quantities when stock has dropped
$removed_items = array();
$stock_adjustments = array();
foreach($cart as $pid => $qty)
{
	$stmt = mysqli_prepare($connect,"SELECT product_name,product_desc,product_image,product_category,product_price,product_stock,product_status FROM product WHERE product_id=? AND product_status='Active'");
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
		$stmt = mysqli_prepare($connect,"DELETE FROM cart WHERE cart_customer=? AND cart_product=?");
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
			$stmt = mysqli_prepare($connect,"UPDATE cart SET cart_qty=? WHERE cart_customer=? AND cart_product=?");
			mysqli_stmt_bind_param($stmt,"iis",$latest_stock,$mid,$pid);
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
	$stmt = mysqli_prepare($connect,"SELECT r.*,p.product_image FROM redemption r LEFT JOIN product p ON p.product_id=r.redeem_product WHERE r.redeem_customer=? AND r.redeem_status='Cart' ORDER BY r.redeem_cart_position,r.redeem_id");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$reward_result = mysqli_stmt_get_result($stmt);
	$reward_count = mysqli_num_rows($reward_result);
	mysqli_stmt_close($stmt);
}
$has_reward = $reward_count>0;

// One authoritative FIFO list for paid products and free rewards.
$cart_lines = array();
foreach($cart as $pid=>$qty)
{
	if(isset($cart_products[$pid])) $cart_lines[] = array("type"=>"product","position"=>$cart_positions[$pid],"product_id"=>$pid,"quantity"=>$qty);
}
if($reward_result)
{
	while($row = mysqli_fetch_assoc($reward_result)) $cart_lines[] = array("type"=>"reward","position"=>(int)$row["redeem_cart_position"],"reward"=>$row);
}
usort($cart_lines,function($left,$right) { return $left["position"] <=> $right["position"]; });

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
$clear_confirm = $has_reward ? "Clear all items and rewards? Reward points will be returned." : "Clear all items from your cart?";
?>

<!DOCTYPE html>
<html lang="en">

<head><!--Database-backed shopping cart page-->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart</title>
<link rel="stylesheet" href="public.css?v=20261028-cart1">
<link rel="stylesheet" href="customer.css?v=20261029-food-bg1">
<script src="customer.js?v=20261030-cart-order1" defer></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
</head>

<body class="customer-cart-page">

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-cart-main">
<?php
require_once __DIR__ . '/order_pricing_helpers.php';
$eo_cart_estimate = easyorder_order_pricing($cart_total, 'Pickup');
?>
<div class="eo-cart-page">
    <h1 class="eo-cart-title">Cart</h1>

    <?php if (isset($cart_error)): ?>
        <div class="form-message form-message-error eo-cart-message" role="alert"><?php echo catalog_h($cart_error); ?></div>
    <?php endif; ?>
    <?php if (isset($cart_notice)): ?>
        <div class="form-message form-message-success eo-cart-message" role="status"><?php echo catalog_h($cart_notice); ?></div>
    <?php endif; ?>
    <?php if (!empty($removed_items)): ?>
        <div class="form-message form-message-error eo-cart-message" role="alert">
            Unavailable items were removed from your cart: <?php echo implode(', ', array_map('catalog_h', $removed_items)); ?>.
        </div>
    <?php endif; ?>
    <?php if (!empty($stock_adjustments)): ?>
        <div class="form-message form-message-info eo-cart-message" role="status">
            <?php echo implode(' ', array_map('catalog_h', $stock_adjustments)); ?>
        </div>
    <?php endif; ?>

    <?php if ($has_normal || $has_reward): ?>
    <div class="eo-cart-grid">
        <section class="eo-cart-panel eo-cart-items" aria-labelledby="eo-cart-items-heading">
            <div class="eo-cart-panel-heading">
                <h2 id="eo-cart-items-heading">Cart Items <span class="eo-cart-count">(<?php echo (int)$cart_item_count; ?>)</span></h2>
                <form method="post" action="cart.php" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode($clear_confirm, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>)">
                    <button class="eo-cart-clear" type="submit" name="clearbtn" value="1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                        Clear cart
                    </button>
                </form>
            </div>

            <?php foreach ($cart_lines as $eo_line): ?>
            <?php if ($eo_line['type'] === 'product'): ?>
                <?php
				$pid = $eo_line['product_id'];
				$qty = $eo_line['quantity'];
                if (!isset($cart_products[$pid])) { continue; }
                $eo_p = $cart_products[$pid];
                $eo_name = $eo_p['product_name'];
                $eo_price = (float)$eo_p['product_price'];
                $eo_stock = (int)$eo_p['product_stock'];
                $eo_line_total = $eo_price * $qty;
                ?>
                <article class="eo-cart-product" data-cart-product="<?php echo catalog_h($pid); ?>">
                    <div class="eo-cart-photo">
                        <img src="<?php echo catalog_h(catalog_product_image($eo_p['product_image'])); ?>" alt="<?php echo catalog_h($eo_name); ?>">
                    </div>
                    <div class="eo-cart-product-info">
                        <div class="eo-cart-product-top">
                            <div>
                                <h3><?php echo catalog_h($eo_name); ?></h3>
                                <p class="eo-cart-description"><?php echo catalog_h($eo_p['product_desc']); ?></p>
                            </div>
                            <strong class="eo-cart-item-price">RM <?php echo number_format($eo_line_total, 2); ?></strong>
                        </div>
                        <div class="eo-cart-product-bottom">
                            <form method="post" action="cart.php" class="eo-cart-qty-form">
                                <input type="hidden" name="product_id" value="<?php echo catalog_h($pid); ?>">
                                <label for="eo-qty-<?php echo catalog_h($pid); ?>">Quantity</label>
                                <div class="eo-cart-stepper">
                                    <button type="button" class="eo-cart-step" hidden data-eo-delta="-1" aria-label="Decrease <?php echo catalog_h($eo_name); ?> quantity" <?php echo $qty <= 1 ? 'disabled' : ''; ?>>−</button>
                                    <input type="number" id="eo-qty-<?php echo catalog_h($pid); ?>" name="qty" value="<?php echo (int)$qty; ?>" min="1" max="<?php echo $eo_stock; ?>" step="1" inputmode="numeric" required aria-label="<?php echo catalog_h($eo_name); ?> quantity">
                                    <button type="button" class="eo-cart-step" hidden data-eo-delta="1" aria-label="Increase <?php echo catalog_h($eo_name); ?> quantity" <?php echo $qty >= $eo_stock ? 'disabled' : ''; ?>>+</button>
                                </div>
                                <button type="submit" name="updatebtn" value="1" class="eo-cart-update">Update quantity</button>
                            </form>
                            <form method="post" action="cart.php" onsubmit="return confirm('Remove this item from your cart?')">
                                <input type="hidden" name="product_id" value="<?php echo catalog_h($pid); ?>">
                                <button class="eo-cart-remove" type="submit" name="removebtn" value="1">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php else: ?>
                <?php $eo_reward = $eo_line['reward']; ?>
                    <article class="eo-cart-product eo-cart-reward">
                        <div class="eo-cart-photo">
                            <img src="<?php echo catalog_h(catalog_product_image($eo_reward['product_image'])); ?>" alt="<?php echo catalog_h($eo_reward['redeem_reward']); ?>">
                        </div>
                        <div class="eo-cart-product-info">
                            <div class="eo-cart-product-top">
                                <div>
                                    <h3><?php echo catalog_h($eo_reward['redeem_reward']); ?> <span class="eo-cart-reward-badge">FREE REWARD</span></h3>
                                    <p class="eo-cart-description">Redeemed using <?php echo (int)$eo_reward['redeem_points']; ?> points. Quantity: 1.</p>
                                </div>
                                <strong class="eo-cart-item-price eo-cart-free">RM 0.00</strong>
                            </div>
                            <div class="eo-cart-product-bottom eo-cart-reward-bottom">
                                <span class="eo-cart-description">Removing returns your reward points.</span>
                                <form method="post" action="cart.php" onsubmit="return confirm('Remove this reward? Your points will be returned.')">
                                    <input type="hidden" name="redeem_id" value="<?php echo (int)$eo_reward['redeem_id']; ?>">
                                    <button class="eo-cart-remove" name="remove_rewardbtn" value="1" type="submit">Remove</button>
                                </form>
                            </div>
                        </div>
                    </article>
            <?php endif; ?>
            <?php endforeach; ?>

            <div class="eo-cart-items-footer">
                <a class="eo-cart-secondary" href="category.php"><span aria-hidden="true">←</span> Continue Shopping</a>
            </div>
        </section>

        <aside class="eo-cart-panel eo-cart-summary" aria-labelledby="eo-cart-summary-heading">
            <div class="eo-cart-panel-heading">
                <h2 id="eo-cart-summary-heading">Order Summary</h2>
            </div>
            <div class="eo-cart-summary-content">
                <div class="eo-cart-summary-line"><span>Subtotal</span><strong>RM <?php echo number_format($eo_cart_estimate['subtotal'], 2); ?></strong></div>
                <div class="eo-cart-summary-line"><span>Est. SST (<?php echo catalog_h((string)(EASYORDER_SST_RATE * 100)); ?>%)</span><strong>RM <?php echo number_format($eo_cart_estimate['sst'], 2); ?></strong></div>
                <div class="eo-cart-summary-total"><span>Est. Total</span><strong>RM <?php echo number_format($eo_cart_estimate['total'], 2); ?></strong></div>
                <div class="eo-cart-delivery-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h11v13H3zM14 9h4l3 4v5h-7z"/><circle cx="7.5" cy="18" r="1.7"/><circle cx="17.5" cy="18" r="1.7"/></svg>
                    <span>Delivery fee of RM <?php echo number_format(EASYORDER_DELIVERY_FEE, 2); ?> applies if selected at checkout.</span>
                </div>
                <a class="eo-cart-checkout" href="checkout.php">Proceed to Checkout <span aria-hidden="true">→</span></a>
            </div>
        </aside>
    </div>
    <?php else: ?>
        <div class="eo-cart-empty">
            <h2>Your cart is empty</h2>
            <p>Browse the EasyOrder menu and add your favourite food to begin an order.</p>
            <a class="eo-cart-checkout eo-cart-browse" href="category.php">Browse Menu →</a>
        </div>
    <?php endif; ?>
</div>
</main>


<div class="eo-cart-announcement" data-cart-announcement role="status" aria-live="polite" aria-atomic="true"></div>
<aside class="eo-cart-toast<?php echo isset($cart_feedback) ? ' is-visible' : ''; ?>" data-cart-toast data-kind="success"<?php echo !empty($cart_feedback['no_action']) ? ' data-cart-no-action' : ''; ?> aria-label="Cart notification"<?php echo isset($cart_feedback) ? ' data-cart-initial role="status"' : ' hidden'; ?>>
<span class="eo-cart-toast-icon" aria-hidden="true">
<svg class="eo-cart-success-icon" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
<svg class="eo-cart-error-icon" viewBox="0 0 24 24"><path d="M12 7v6m0 4h.01"/><circle cx="12" cy="12" r="9"/></svg>
</span>
<div class="eo-cart-toast-copy"><p class="eo-cart-toast-title" data-cart-toast-title><?php echo catalog_h($cart_feedback['title'] ?? ''); ?></p><p class="eo-cart-toast-message" data-cart-toast-message><?php echo catalog_h($cart_feedback['message'] ?? ''); ?></p><a class="eo-cart-toast-action" data-cart-toast-action href="reward.php"<?php echo !empty($cart_feedback['no_action']) ? ' hidden' : ''; ?>>View rewards</a></div>
<button class="eo-cart-toast-close" data-cart-toast-close type="button" aria-label="Dismiss cart notification" hidden>×</button>
</aside>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
