<?php
//only logged in members can view and redeem rewards
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

$mid = $_SESSION["member_id"];

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

		//lock the member and use the latest point balance, not the number shown on an older page
		$stmt = mysqli_prepare($connect,"SELECT member_points,member_isDelete FROM member WHERE member_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"i",$mid);
		mysqli_stmt_execute($stmt);
		$member_result = mysqli_stmt_get_result($stmt);
		$member_row = mysqli_fetch_assoc($member_result);
		mysqli_stmt_close($stmt);
		if(!$member_row || $member_row["member_isDelete"]==1)
		{
			throw new Exception("This member account is no longer active.");
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
		if((int)$member_row["member_points"] < $reward_cost)
		{
			throw new Exception("You do not have enough points to redeem this reward.");
		}

		$stmt = mysqli_prepare($connect,"SELECT product_stock,product_status,product_isDelete FROM product WHERE product_id=? FOR UPDATE");
		mysqli_stmt_bind_param($stmt,"s",$linked_pid);
		mysqli_stmt_execute($stmt);
		$product_result = mysqli_stmt_get_result($stmt);
		$product_row = mysqli_fetch_assoc($product_result);
		mysqli_stmt_close($stmt);
		if(!$product_row || $product_row["product_isDelete"]==1 || $product_row["product_status"]!="Active" || $product_row["product_stock"]<=0)
		{
			throw new Exception("Sorry, the product for this reward is no longer available.");
		}

		$date = date("Y-m-d");
		$stmt = mysqli_prepare($connect,"INSERT INTO redemption(redeem_member,redeem_reward,redeem_product,redeem_points,redeem_status,redeem_date) VALUES(?,?,?,?,'Cart',?)");
		mysqli_stmt_bind_param($stmt,"issis",$mid,$reward_name,$linked_pid,$reward_cost,$date);
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

		$stmt = mysqli_prepare($connect,"UPDATE member SET member_points=member_points-? WHERE member_id=? AND member_points>=?");
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
		$_SESSION["cart_notice"] = "Success! ".$reward_name." has been added to your cart as a free reward item.";
		header("location:cart.php");
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

//----- the member's available loyalty points -----
//read straight from the member table; it is kept up to date (points are added when an order
//is placed and subtracted when a reward is redeemed), so no recalculation is needed here
$available_points = 0;
$stmt = mysqli_prepare($connect,"SELECT member_points FROM member WHERE member_id=? AND member_isDelete=0");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if(mysqli_num_rows($result)>0)
{
	$row = mysqli_fetch_assoc($result);
	$available_points = $row["member_points"];
}
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html>

<head><!--Loyalty rewards redemption page-->
<title>Rewards</title>
<link rel="stylesheet" href="style.css">

<style>
#points-banner
{background-color:#FFC72C;
color:#9E0B22;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
padding:20px 25px 20px 25px;
margin-bottom:25px;
text-align:center;}

#points-banner .points-caption
{font-style:italic;
font-size:0.95em;
margin:0px;}

#points-banner .points-number
{font-family:"Arial Black";
font-size:30pt;
letter-spacing:1px;
margin:5px 0px 0px 0px;}

.reward-list
{display:flex;
flex-wrap:wrap;
justify-content:center;
margin-bottom:10px;}

.reward-card
{width:230px;
background-color:#FFFFFF;
border:1px solid #EEEEEE;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
margin:10px;
padding:15px;
display:flex;
flex-direction:column;
align-items:center;
text-align:center;}

.reward-card img
{width:150px;
height:110px;
border-radius:5px;
object-fit:cover;}

.reward-card .reward-name
{color:#C8102E;
font-weight:bold;
font-size:1.05em;
margin:12px 0px 6px 0px;
min-height:2.6em;
display:flex;
align-items:center;
justify-content:center;}

.reward-card .reward-desc
{color:#666666;
font-size:9pt;
margin:0px 0px 12px 0px;
flex-grow:1;}

.reward-card .reward-points
{background-color:#9E0B22;
color:#FFFFFF;
font-weight:bold;
font-size:0.9em;
border-radius:12px;
padding:4px 14px 4px 14px;
margin-bottom:10px;}

.reward-card .redeem-btn
{background-color:#FF7A00;
color:#FFFFFF;
font-weight:bold;
text-decoration:none;
border-radius:5px;
padding:8px 0px 8px 0px;
width:100%;
box-sizing:border-box;
border:0px;
cursor:pointer;}

.reward-card form
{width:100%;}

.reward-card .redeem-btn:hover
{background-color:#C8102E;}

.reward-card .no-points
{background-color:#DDDDDD;
color:#888888;
font-weight:bold;
border-radius:5px;
padding:8px 0px 8px 0px;
width:100%;
box-sizing:border-box;}
</style>

<script>
function confirm_redeem(name,points)//ask the member to confirm before spending their points
{
	return confirm("Redeem "+name+" for "+points+" points? It will be added to your cart.");
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
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Rewards</h2>
<p class="intro">Turn your loyalty points into free menu items! Redeem any reward below and it will be added to your cart, ready for checkout.</p>

<?php
if(isset($reward_error))
{
?>
<p class="msg"><?php echo htmlspecialchars($reward_error,ENT_QUOTES,"ISO-8859-1"); ?></p>
<?php
}
?>

<div id="points-banner"><!--Shows the member's available loyalty points-->
<p class="points-caption">Your Available Loyalty Points</p>
<p class="points-number"><?php echo $available_points; ?></p>
</div>

<h2 class="section-title">Available Rewards</h2>

<div class="reward-list"><!--Reward cards, centered and wrapping 3 per row-->

<?php
//only show rewards that are linked to a product which is active, not deleted and in stock
$reward_result = mysqli_query($connect,"SELECT * FROM reward,product WHERE reward_product=product_id AND reward_isDelete=0 AND reward_status='Active' AND product_isDelete=0 AND product_status='Active' AND product_stock>0");

while($reward = mysqli_fetch_assoc($reward_result))
{
	$rid = $reward["reward_id"];
	$rname = $reward["reward_name"];
	$rdesc = $reward["reward_desc"];
	$rpoints = $reward["reward_points"];
	$rimage = $reward["reward_image"];
?>

<div class="reward-card"><!--Reward item card-->
<img src="image/<?php echo $rimage; ?>" alt="<?php echo $rname; ?>" title="<?php echo $rname; ?>">
<p class="reward-name"><?php echo $rname; ?></p>
<p class="reward-desc"><?php echo $rdesc; ?></p>
<span class="reward-points"><?php echo $rpoints; ?> points</span>

<?php
//enough points -> redeem; otherwise not enough points
if($available_points >= $rpoints)
{
?>
<form method="post" action="reward.php" onsubmit="return confirm('Redeem this reward for <?php echo (int)$rpoints; ?> points? It will be added to your cart.')">
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

</div>

<?php
}
?>

</div>

<hr>

<h2 class="section-title">How It Works</h2>
<ol class="steps">
<li>Earn 1 point for every RM 0.10 you spend on your orders.</li>
<li>Browse the rewards above and pick one you have enough points for.</li>
<li>Click "Redeem" to add the free item to your cart, then check out from your cart to enjoy it.</li>
</ol>

<p style="text-align:center; margin-top:20px;"><a class="btn" href="cart.php">Go to Cart</a> &nbsp; <a class="btn" href="dashboard.php">Back to Dashboard</a></p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
