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

<head><!--Administrator sales report page-->
<title>Sales Report</title>
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
<a href="admin_dashboard.php">Dashboard</a>
<a href="admin_staff.php">Manage Staff</a>
<a href="admin_member.php">Manage Members</a>
<a href="admin_category.php">Manage Categories</a>
<a href="admin_product.php">Manage Products</a>
<a href="admin_order.php">Manage Orders</a>
<a href="admin_reward.php">Manage Rewards</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Sales Report</h2>
<p class="intro">A summary of EasyOrder sales. Cancelled orders are not counted in the figures below.</p>

<?php
//count completed orders (not cancelled, not deleted) and add up their revenue
$result = mysqli_query($connect,"SELECT * FROM orders WHERE order_isDelete=0 AND order_status!='Cancelled'");
$completed_orders = mysqli_num_rows($result);

//add up the number of items sold from all completed orders
$result = mysqli_query($connect,"SELECT * FROM order_items,orders WHERE item_order=order_id AND order_status!='Cancelled' AND order_isDelete=0");
$total_units = 0;
$total_sales = 0;
while($row = mysqli_fetch_assoc($result))
{
	$total_units = $total_units + $row["item_qty"];
	$total_sales = $total_sales + $row["item_subtotal"];
}
?>

<div class="stat-row"><!--Summary cards-->
<div class="stat-card">
<div class="stat-number"><?php echo $completed_orders; ?></div>
<div class="stat-label">Completed Orders</div>
</div>
<div class="stat-card">
<div class="stat-number"><?php echo $total_units; ?></div>
<div class="stat-label">Total Units Sold</div>
</div>
<div class="stat-card">
<div class="stat-number">RM <?php echo number_format($total_sales,2); ?></div>
<div class="stat-label">Total Product Sales</div>
</div>
</div>

<h2 class="section-title">Sales by Category</h2>
<table class="manage-table" border="1"><!--For each category, total up the units and sales of its products-->
<tr>
<th>Category</th>
<th>Units Sold</th>
<th>Sales (RM)</th>
</tr>

<?php
//go through every category and add up the sales of the items belonging to it
$cat_result = mysqli_query($connect,"SELECT * FROM category WHERE category_isDelete=0");

while($cat_row = mysqli_fetch_assoc($cat_result))
{
	$catname = $cat_row["category_name"];
	$cat_units = 0;
	$cat_sales = 0;

	//join order_items, product and orders by product id - only count orders that are not cancelled or deleted
	$item_stmt = mysqli_prepare($connect,"SELECT * FROM order_items,product,orders WHERE item_product=product_id AND item_order=order_id AND product_category=? AND order_status!='Cancelled' AND order_isDelete=0");
	mysqli_stmt_bind_param($item_stmt,"s",$catname);
	mysqli_stmt_execute($item_stmt);
	$item_result = mysqli_stmt_get_result($item_stmt);

	while($item_row = mysqli_fetch_assoc($item_result))
	{
		$cat_units = $cat_units + $item_row["item_qty"];
		$cat_sales = $cat_sales + $item_row["item_subtotal"];
	}
	mysqli_stmt_close($item_stmt);
?>

<tr>
<td><?php echo $catname; ?></td>
<td><?php echo $cat_units; ?></td>
<td><?php echo number_format($cat_sales,2); ?></td>
</tr>

<?php
}
?>

</table>

<h2 class="section-title">Best Selling Products</h2>
<table class="manage-table" border="1"><!--Products ranked by the number of units sold-->
<tr>
<th>Product</th>
<th>Units Sold</th>
<th>Sales (RM)</th>
</tr>

<?php
//group the order items by their product id (not name), so renamed or same-named products are counted correctly
$result = mysqli_query($connect,"SELECT item_product, MAX(product_name) AS cur_name, MAX(item_name) AS snap_name, SUM(item_qty) AS total_qty, SUM(item_subtotal) AS total_sales FROM order_items JOIN orders ON item_order=order_id LEFT JOIN product ON item_product=product_id WHERE order_status!='Cancelled' AND order_isDelete=0 GROUP BY item_product ORDER BY total_qty DESC");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td><?php echo ($row['cur_name']!="" ? $row['cur_name'] : $row['snap_name']); ?></td>
<td><?php echo $row['total_qty']; ?></td>
<td><?php echo number_format($row['total_sales'],2); ?></td>
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
