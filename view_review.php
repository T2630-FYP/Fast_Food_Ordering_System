<?php
//only logged in customers can view the reviews
session_start();
if(!isset($_SESSION["customer_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
?>

<!DOCTYPE html>
<html>

<head><!--Customer reviews viewing page (view only, no form)-->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<title>Customer Reviews</title>
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">What Our Customers Say</h2>
<p class="intro">See what other EasyOrder customers think about their experience.</p>

<table class="menu-table" width="100%" border="1"><!--Table section for displaying reviews joined with the customer name-->
<tr>
<th width="100px">Order ID</th>
<th width="180px">Customer</th>
<th width="100px">Rating</th>
<th>Comment</th>
<th width="120px">Date</th>
</tr>

<?php
//show all reviews by Order ID descending, with the customer name
$result = mysqli_query($connect,"SELECT * FROM review,customer WHERE review_customer=customer_id ORDER BY review_order DESC, review_id DESC");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td align="center"><?php echo $row["review_order"]; ?></td>
<td><?php echo $row["customer_name"]; ?></td>
<td align="center"><?php echo $row["review_rating"]; ?> / 5</td>
<td><?php echo $row["review_comment"]; ?></td>
<td align="center"><?php echo $row["review_date"]; ?></td>
</tr>

<?php
}
?>

</table>

<p style="text-align:center; margin-top:20px;"><a class="btn" href="profile.php">Back to Profile</a></p>

</div>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
