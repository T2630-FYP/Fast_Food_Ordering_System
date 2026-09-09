<?php
//block this page if the admin is not logged in
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("location:admin_login.php");
	exit();
}
include("dataconnection.php");
?>

<!DOCTYPE html>
<html>

<head><!--Administrator dashboard page-->
<title>Admin Dashboard</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">

<style>
.stat-row
{display:flex;
gap:15px;
flex-wrap:wrap;
margin-bottom:25px;}

.stat-card
{flex:1;
min-width:180px;
background-color:#FFFFFF;
border:2px solid #C8102E;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
padding:18px;
text-align:center;}

.stat-card .stat-number
{font-size:26pt;
font-weight:bold;
color:#C8102E;}

.stat-card .stat-label
{font-size:0.9em;
color:#2B2B2B;
font-weight:bold;}
</style>

</head>

<body>

<div id="header"><!--Header section for logo, website name and slogan-->
<img src="image/logo.png" width="80px" height="80px" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="admin-navbar"><!--Administrator navigation bar-->
<a href="admin_staff.php">Manage Staff</a>
<a href="admin_member.php">Manage Members</a>
<a href="admin_category.php">Manage Categories</a>
<a href="admin_product.php">Manage Products</a>
<a href="admin_order.php">Manage Orders</a>
<a href="admin_reward.php">Manage Rewards</a>
<a href="admin_report.php">Sales Report</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Dashboard</h2>
<p class="intro">Welcome back, <?php echo $_SESSION["admin_name"]; ?>! Here is an overview of the EasyOrder system.</p>

<?php
//count the total members (not deleted)
$result = mysqli_query($connect,"SELECT * FROM member WHERE member_isDelete=0");
$total_members = mysqli_num_rows($result);

//count the total products (not deleted)
$result = mysqli_query($connect,"SELECT * FROM product WHERE product_isDelete=0");
$total_products = mysqli_num_rows($result);

//count the total orders (not deleted)
$result = mysqli_query($connect,"SELECT * FROM orders WHERE order_isDelete=0");
$total_orders = mysqli_num_rows($result);

//add up the revenue from all orders that are not cancelled
$result = mysqli_query($connect,"SELECT * FROM orders WHERE order_isDelete=0 AND order_status!='Cancelled'");
$total_revenue = 0;
while($row = mysqli_fetch_assoc($result))
{
	$total_revenue = $total_revenue + $row["order_total"];
}
?>

<div class="stat-row"><!--Quick statistics cards-->
<div class="stat-card">
<div class="stat-number"><?php echo $total_members; ?></div>
<div class="stat-label">Total Members</div>
</div>
<div class="stat-card">
<div class="stat-number"><?php echo $total_products; ?></div>
<div class="stat-label">Total Products</div>
</div>
<div class="stat-card">
<div class="stat-number"><?php echo $total_orders; ?></div>
<div class="stat-label">Total Orders</div>
</div>
<div class="stat-card">
<div class="stat-number">RM <?php echo number_format($total_revenue,2); ?></div>
<div class="stat-label">Total Revenue</div>
</div>
</div>

<h2 class="section-title">Latest Orders</h2>
<table class="manage-table" border="1"><!--Recent orders joined with the member table to show the member name-->
<tr>
<th>Order ID</th>
<th>Member</th>
<th>Order Date</th>
<th>Total (RM)</th>
<th>Status</th>
</tr>

<?php
$result = mysqli_query($connect,"SELECT * FROM orders,member WHERE order_member=member_id AND order_isDelete=0 ORDER BY order_id DESC");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td><?php echo $row['order_id']; ?></td>
<td><?php echo $row['member_name']; ?></td>
<td><?php echo $row['order_date']; ?></td>
<td><?php echo number_format($row['order_total'],2); ?></td>
<td><?php echo $row['order_status']; ?></td>
</tr>

<?php
}
?>

</table>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="login.php">User Login</a></p>
</footer>

</body>

</html>
