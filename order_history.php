<?php
//only logged in members can see their order history
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

//the logged in member's id
$mid = $_SESSION["member_id"];
?>

<!DOCTYPE html>
<html>

<head><!--Customer order history page-->
<title>Order History</title>
<link rel="stylesheet" href="style.css">
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
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">My Order History</h2>
<p class="intro">A record of all your orders with EasyOrder, newest first.</p>

<?php
//get this member's orders, newest first
$result = mysqli_query($connect,"SELECT * FROM orders WHERE order_member='$mid' AND order_isDelete=0 ORDER BY order_date DESC, order_id DESC");

//show the table only if this member has orders
if(mysqli_num_rows($result)>0)
{
?>

<table class="menu-table" width="100%" border="1"><!--Table section for displaying this member's orders-->
<tr>
<th>Order ID</th>
<th>Date</th>
<th>Items</th>
<th>Total</th>
<th>Payment</th>
<th>Status</th>
</tr>

<?php
while($order = mysqli_fetch_assoc($result))
{
	$oid = $order["order_id"];

	//build a list of the items in this order
	$item_result = mysqli_query($connect,"SELECT * FROM order_items WHERE item_order='$oid'");
	$items_text = "";
	while($item = mysqli_fetch_assoc($item_result))
	{
		$items_text = $items_text.$item["item_name"]." x".$item["item_qty"]."  ";
	}
?>

<tr>
<td align="center"><?php echo $oid; ?></td>
<td align="center"><?php echo $order["order_date"]; ?></td>
<td><?php echo $items_text; ?></td>
<td align="center"><span class="price">RM <?php echo number_format($order["order_total"],2); ?></span></td>
<td align="center"><?php echo $order["order_payment"]; ?></td>
<td align="center"><?php echo $order["order_status"]; ?></td>
</tr>

<?php
}
?>

</table>

<?php
}
else
{
?>

<p class="intro" style="text-align:center;">You have not placed any orders yet. <a href="category.php">Start ordering</a> now!</p>

<?php
}
?>

<p style="text-align:center; margin-top:20px;"><a class="btn" href="dashboard.php">Back to Dashboard</a></p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
