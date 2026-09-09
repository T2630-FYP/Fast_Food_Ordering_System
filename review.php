<?php
//only logged in members can leave a review
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

//show the checkout success message once after the Post/Redirect/Get redirect
$placed_order_id = 0;
if(isset($_SESSION["placed_order_id"]))
{
	$placed_order_id = (int)$_SESSION["placed_order_id"];
	unset($_SESSION["placed_order_id"]);
}

//get the latest order placed by this member so the review is linked to an Order ID
$mid = $_SESSION["member_id"];
$current_order = 0;
$order_result = mysqli_query($connect,"SELECT order_id FROM orders WHERE order_member='$mid' AND order_isDelete=0 ORDER BY order_id DESC LIMIT 1");
if(mysqli_num_rows($order_result)>0)
{
	$order_row = mysqli_fetch_assoc($order_result);
	$current_order = $order_row["order_id"];
}
?>

<!DOCTYPE html>
<html>

<head><!--Customer comments and rating page-->
<title>Review</title>
<link rel="stylesheet" href="style.css">

<script>
function submit_review()//Validate customer rating and comment form
{
	let rating="",comment;
	let i;

	comment=document.reviewfrm.cust_comment.value;

	for(i=0;i<document.reviewfrm.rating.length;i++)
	{
		if(document.reviewfrm.rating[i].checked)
		{
			rating=document.reviewfrm.rating[i].value;
		}
	}

	if(rating==""||comment=="")
	{
		document.getElementById("msg").innerHTML="Please choose a rating and write your comment.";
		return false;
	}
	else
	{
		return true;
	}
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

<?php
if($placed_order_id>0)
{
?>
<script>
alert("Your order has been placed successfully! Your order ID is <?php echo $placed_order_id; ?>. Please leave us a review!");
</script>
<?php
}
?>

<h2 class="section-title">Comments and Rating</h2>
<p class="intro">Thank you for your order! Tell us about your experience with EasyOrder below.</p>

<form name="reviewfrm" method="post" action="" onsubmit="return submit_review()"><!--Form section for user input-->
<div class="form-box">

<h3>Customer Review Form</h3>

<p><b>Order ID:</b> <?php echo $current_order; ?></p>
<input type="hidden" name="order_id" value="<?php echo $current_order; ?>">

<p>Rating:
<input type="radio" name="rating" value="1">1
<input type="radio" name="rating" value="2">2
<input type="radio" name="rating" value="3">3
<input type="radio" name="rating" value="4">4
<input type="radio" name="rating" value="5">5
</p>

<p>Comment:
<br>
<textarea name="cust_comment" rows="5" cols="55"></textarea>
</p>

<p>
<input type="submit" name="submitbtn" value="Submit Review">
<input type="reset" name="resetbtn" value="Clear">
</p>

<p class="msg"><span id="msg"></span></p>

</div>
</form>

<p style="text-align:center; margin-top:20px;"><a class="btn" href="dashboard.php">Back to Dashboard</a> &nbsp; <a class="btn" href="view_review.php">View Customer Reviews</a></p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>

<?php

//save the review when the form is submitted
if(isset($_POST["submitbtn"]))
{
	$mid = mysqli_real_escape_string($connect,$_SESSION["member_id"]);
	$order_id = mysqli_real_escape_string($connect,$_POST["order_id"]);
	$rating = mysqli_real_escape_string($connect,$_POST["rating"]);
	$comment = mysqli_real_escape_string($connect,$_POST["cust_comment"]);
	$date = date("Y-m-d");

	//only save the review if the Order ID belongs to the logged in member
	$order_check = mysqli_query($connect,"SELECT * FROM orders WHERE order_id='$order_id' AND order_member='$mid' AND order_isDelete=0");
	if(mysqli_num_rows($order_check)>0)
	{
		mysqli_query($connect,"INSERT INTO review(review_member,review_order,review_rating,review_comment,review_date)VALUES('$mid','$order_id','$rating','$comment','$date')");
	}
	else
	{
		?>
		<script>
		alert("No valid order was found for this review.");
		window.location="dashboard.php";
		</script>
		<?php
		exit();
	}
	?>
	<script>
	alert("Thank you for your review!");
	window.location="dashboard.php";
	</script>
	<?php
}

?>
