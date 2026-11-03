<?php
//only logged in customers can view and redeem rewards
session_start();
if(!isset($_SESSION["customer_id"]))
{
	require_once(__DIR__."/customer_auth_helpers.php");
	easyorder_redirect_customer_to_login();
}
include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
require_once(__DIR__."/product_catalog_helpers.php");

$mid = $_SESSION["customer_id"];

//check the redemption table exists (it is created by importing the latest easyorder.sql)
$redemption_ready = false;
$tbl_check = mysqli_query($connect,"SHOW TABLES LIKE 'redemption'");
if(mysqli_num_rows($tbl_check)>0)
{
	$redemption_ready = true;
}

//redeem before sending HTML, then redirect so refresh cannot redeem the same request again
if(isset($_POST["redeembtn"]))
{
	$submitted_token = (string)($_POST["reward_token"] ?? "");
	$valid_token = isset($_SESSION["reward_token"]) && $submitted_token!="" && hash_equals($_SESSION["reward_token"],$submitted_token);
	unset($_SESSION["reward_token"]);

	if(!$valid_token)
	{
		$_SESSION["reward_error"] = "This reward request was already submitted or has expired. Please try again.";
		header("location:reward.php");
		exit();
	}

	$transaction_started = false;

	try
	{
		if(!$redemption_ready)
		{
			throw new Exception("The reward system is not set up yet. Please ask the admin to import the latest easyorder.sql.");
		}

		$rid = trim((string)($_POST["reward_id"] ?? ""));
		if($rid=="")
		{
			throw new Exception("The selected reward is invalid.");
		}

		mysqli_begin_transaction($connect);
		$transaction_started = true;

		//lock the customer and use the latest point balance, not the number shown on an older page
		$stmt = mysqli_prepare($connect,"SELECT customer_points,customer_isDelete FROM customer WHERE customer_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$customer_result = mysqli_stmt_get_result($stmt);
		$customer_row = mysqli_fetch_assoc($customer_result);
		mysqli_stmt_close($stmt);
		if(!$customer_row || $customer_row["customer_isDelete"]==1)
		{
			throw new Exception("This customer account is no longer active.");
		}

		//lock the reward first, then its linked product stock
		$stmt = mysqli_prepare($connect,"SELECT reward_name,reward_points,reward_product,reward_status,reward_isDelete FROM reward WHERE reward_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"s",$rid);
		mysqli_stmt_execute($stmt);
		$reward_result = mysqli_stmt_get_result($stmt);
		$reward_row = mysqli_fetch_assoc($reward_result);
		mysqli_stmt_close($stmt);
		if(!$reward_row || $reward_row["reward_isDelete"]==1 || $reward_row["reward_status"]!="Active")
		{
			throw new Exception("Sorry, this reward is no longer available.");
		}

		$reward_name = $reward_row["reward_name"];
		$reward_cost = (int)$reward_row["reward_points"];
		$linked_pid = $reward_row["reward_product"];
		if($reward_cost<=0)
		{
			throw new Exception("This reward has an invalid point value. Please contact the admin.");
		}
		if((int)$customer_row["customer_points"] < $reward_cost)
		{
			throw new Exception("You do not have enough points to redeem this reward.");
		}

		$stmt = mysqli_prepare($connect,"SELECT product_stock,product_status FROM product WHERE product_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"s",$linked_pid);
		mysqli_stmt_execute($stmt);
		$product_result = mysqli_stmt_get_result($stmt);
		$product_row = mysqli_fetch_assoc($product_result);
		mysqli_stmt_close($stmt);
		if(!$product_row || $product_row["product_status"]!="Active" || $product_row["product_stock"]<=0)
		{
			throw new Exception("Sorry, the product for this reward is no longer available.");
		}

		$date = date("Y-m-d");
		$position = catalog_next_cart_position($connect,$mid);
		$stmt = mysqli_prepare($connect,"INSERT INTO redemption(redeem_customer,redeem_reward,redeem_product,redeem_points,redeem_status,redeem_date,redeem_cart_position) VALUES(?,?,?,?,'Cart',?,?)");
		mysqli_stmt_bind_param($stmt,"issisi",$mid,$reward_name,$linked_pid,$reward_cost,$date,$position);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		$stmt = mysqli_prepare($connect,"UPDATE product SET product_stock=product_stock-1 WHERE product_id=? AND product_stock>0");
		mysqli_stmt_bind_param($stmt,"s",$linked_pid);
		mysqli_stmt_execute($stmt);
		if(mysqli_stmt_affected_rows($stmt)!=1)
		{
			mysqli_stmt_close($stmt);
			throw new Exception("The reward stock changed. Please try again.");
		}
		mysqli_stmt_close($stmt);

		$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_points=customer_points-? WHERE customer_id=? AND customer_points>=?");
		mysqli_stmt_bind_param($stmt,"iii",$reward_cost,$mid,$reward_cost);
		mysqli_stmt_execute($stmt);
		if(mysqli_stmt_affected_rows($stmt)!=1)
		{
			mysqli_stmt_close($stmt);
			throw new Exception("Your point balance changed. Please try again.");
		}
		mysqli_stmt_close($stmt);

		mysqli_commit($connect);
		$transaction_started = false;
		$_SESSION["reward_feedback"] = array(
			"title"=>"Reward added to cart",
			"message"=>$reward_name." has been added to your cart. ".number_format($reward_cost)." points used."
		);
		header("Location: reward.php",true,303);
		exit();
	}
	catch(Throwable $error)
	{
		if($transaction_started)
		{
			mysqli_rollback($connect);
		}
		$_SESSION["reward_error"] = $error->getMessage();
		header("location:reward.php");
		exit();
	}
}

if(!isset($_SESSION["reward_token"]))
{
	$_SESSION["reward_token"] = bin2hex(random_bytes(32));
}
$reward_token = $_SESSION["reward_token"];

if(isset($_SESSION["reward_error"]))
{
	$reward_error = $_SESSION["reward_error"];
	unset($_SESSION["reward_error"]);
}

if(isset($_SESSION["reward_feedback"]))
{
	$reward_feedback = $_SESSION["reward_feedback"];
	unset($_SESSION["reward_feedback"]);
}

//----- the customer's available loyalty points -----
//read straight from the customer table; it is kept up to date (points are added when an order
//is placed and subtracted when a reward is redeemed), so no recalculation is needed here
$available_points = 0;
$stmt = mysqli_prepare($connect,"SELECT customer_points FROM customer WHERE customer_id=? AND customer_isDelete=0");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if(mysqli_num_rows($result)>0)
{
	$row = mysqli_fetch_assoc($result);
	$available_points = $row["customer_points"];
}
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head><!--Loyalty rewards redemption page-->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rewards | EasyOrder</title>
<link rel="stylesheet" href="public.css?v=20261029-rewards1">
<link rel="stylesheet" href="customer.css?v=20261030-reward-banner1">
<script src="customer.js?v=20261029-rewards2" defer></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">

</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="reward-page"><!--Rewards content only; shared header and footer are unchanged-->

<div class="reward-page-heading"><h1 tabindex="-1">Rewards</h1></div>

<?php
if(isset($reward_error))
{
?>
<p class="reward-message" role="alert"><?php echo catalog_h($reward_error); ?></p>
<?php
}
?>

<div id="points-banner" aria-label="Your available loyalty points"><!-- Live balance from the customer table -->
<div class="points-banner-message">
<h2>Order, Earn &amp; <span>Enjoy!</span></h2>
<p class="points-caption">Every order has its perks</p>
</div>
<div class="points-banner-divider" aria-hidden="true"></div>
<div class="points" style="--points-characters: <?php echo strlen(number_format((int)$available_points)); ?>" aria-label="<?php echo number_format((int)$available_points); ?> loyalty points"><span class="points-value"><?php
    echo number_format((int)$available_points);
?></span><span class="points-unit">PTS</span></div>
</div>

<h2 class="reward-list-heading">Available Rewards</h2>

<div class="reward-list"><!--Reward cards, centered and wrapping 3 per row-->

<?php
//only show rewards that are linked to a product which is active, not deleted and in stock
$reward_result = mysqli_query($connect,"SELECT * FROM reward,product WHERE reward_product=product_id AND reward_isDelete=0 AND reward_status='Active' AND product_status='Active' AND product_stock>0");

$reward_count = 0;
while($reward = mysqli_fetch_assoc($reward_result))
{
	$reward_count++;
	$rid = $reward["reward_id"];
	$rname = $reward["reward_name"];
	$rdesc = $reward["reward_desc"];
	$rpoints = $reward["reward_points"];
	$rimage = $reward["reward_image"];
	$reward_image_path = substr((string)$rimage,0,6)==="image/" ? (string)$rimage : "image/".$rimage;
	$reward_image_url = catalog_local_image_path($reward_image_path)!==null && is_file(__DIR__."/".$reward_image_path)
		? catalog_product_image($reward_image_path) : catalog_image_placeholder();
?>

<article class="reward-card" data-reward-id="<?php echo catalog_h($rid); ?>"><!--Reward item card-->
<img src="<?php echo catalog_h($reward_image_url); ?>" alt="<?php echo catalog_h($rname); ?>" title="<?php echo catalog_h($rname); ?>" loading="lazy" decoding="async">
<h3 class="reward-name"><?php echo catalog_h($rname); ?></h3>
<p class="reward-desc"><?php echo catalog_h($rdesc); ?></p>
<span class="reward-points"><?php echo (int)$rpoints; ?> points</span>

<?php
//enough points -> redeem; otherwise not enough points
if($available_points >= $rpoints)
{
?>
<form method="post" action="reward.php" data-reward-redeem onsubmit="return confirm('Redeem this reward for <?php echo (int)$rpoints; ?> points? It will be added to your cart.')">
<input type="hidden" name="reward_token" value="<?php echo htmlspecialchars($reward_token,ENT_QUOTES,"ISO-8859-1"); ?>">
<input type="hidden" name="reward_id" value="<?php echo htmlspecialchars($rid,ENT_QUOTES,"ISO-8859-1"); ?>">
<input class="redeem-btn" type="submit" name="redeembtn" value="Redeem">
</form>
<?php
}
else
{
?>
<span class="no-points">Not Enough Points</span>
<?php
}
?>

</article>

<?php
}
?>

</div>
<?php if($reward_count===0) { ?>
<p class="reward-empty" role="status">No rewards are available right now. Please check back later.</p>
<?php } ?>

<section class="reward-help" aria-labelledby="reward-help-title">
<h2 id="reward-help-title" class="reward-list-heading">How It Works</h2>
<ol class="reward-help-steps">
<li>Earn 1 point for every RM 0.10 spent on food. SST and delivery fees do not earn points.</li>
<li>Browse the rewards above and choose one you have enough points for.</li>
<li>Click "Redeem" to add the free item to your cart, then check out from your cart.</li>
<li>Remove a redeemed reward from your cart before checkout to have its points returned to your account.</li>
</ol>
</section>

</main>


<div class="eo-cart-announcement" data-cart-announcement role="status" aria-live="polite" aria-atomic="true"></div>
<aside class="eo-cart-toast<?php echo isset($reward_feedback) ? ' is-visible' : ''; ?>" data-cart-toast data-kind="success" aria-label="Cart notification"<?php echo isset($reward_feedback) ? ' data-cart-initial role="status"' : ' hidden'; ?>>
<span class="eo-cart-toast-icon" aria-hidden="true">
<svg class="eo-cart-success-icon" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
<svg class="eo-cart-error-icon" viewBox="0 0 24 24"><path d="M12 7v6m0 4h.01"/><circle cx="12" cy="12" r="9"/></svg>
</span>
<div class="eo-cart-toast-copy"><p class="eo-cart-toast-title" data-cart-toast-title><?php echo catalog_h($reward_feedback['title'] ?? ''); ?></p><p class="eo-cart-toast-message" data-cart-toast-message><?php echo catalog_h($reward_feedback['message'] ?? ''); ?></p><a class="eo-cart-toast-action" data-cart-toast-action href="cart.php">View cart</a></div>
<button class="eo-cart-toast-close" data-cart-toast-close type="button" aria-label="Dismiss cart notification" hidden>×</button>
</aside>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
