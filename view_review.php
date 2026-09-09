<?php
//only logged in members can view the reviews
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");
?>

<!DOCTYPE html>
<html>

<head><!--Customer reviews viewing page (view only, no form)-->
<title>Customer Reviews</title>
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
<a href="order_history.php">Order History</a>
<a href="reward.php">Rewards</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">What Our Customers Say</h2>
<p class="intro">See what other EasyOrder customers think about their experience.</p>

<table class="menu-table" width="100%" border="1"><!--Table section for displaying reviews joined with the member name-->
<tr>
<th width="100px">Order ID</th>
<th width="180px">Member</th>
<th width="100px">Rating</th>
<th>Comment</th>
<th width="120px">Date</th>
</tr>

<?php
//show all reviews by Order ID descending, with the member name
$result = mysqli_query($connect,"SELECT * FROM review,member WHERE review_member=member_id ORDER BY review_order DESC, review_id DESC");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td align="center"><?php echo $row["review_order"]; ?></td>
<td><?php echo $row["member_name"]; ?></td>
<td align="center"><?php echo $row["review_rating"]; ?> / 5</td>
<td><?php echo $row["review_comment"]; ?></td>
<td align="center"><?php echo $row["review_date"]; ?></td>
</tr>

<?php
}
?>

</table>

<p style="text-align:center; margin-top:20px;"><a class="btn" href="dashboard.php">Back to Dashboard</a></p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
