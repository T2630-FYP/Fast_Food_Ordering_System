<?php include("dataconnection.php"); ?>

<?php
//get the category from the url and make sure it is a real, active, non-deleted category
$catname = "";
$catdesc = "";
$valid_category = false;

if(isset($_GET["cat"]))
{
	$cid = (string)$_GET["cat"];
	$cat_stmt = mysqli_prepare($connect,"SELECT * FROM category WHERE category_id=? AND category_isDelete=0 AND category_status='Active'");
	mysqli_stmt_bind_param($cat_stmt,"s",$cid);
	mysqli_stmt_execute($cat_stmt);
	$cat_result = mysqli_stmt_get_result($cat_stmt);
	if(mysqli_num_rows($cat_result) > 0)
	{
		$cat_row = mysqli_fetch_assoc($cat_result);
		$catname = $cat_row["category_name"];
		$catdesc = $cat_row["category_desc"];
		$valid_category = true;
	}
	mysqli_stmt_close($cat_stmt);
}
?>

<!DOCTYPE html>
<html>

<head><!--Dynamic product listing page - shows the products for any category-->
<title><?php echo ($valid_category ? $catname : "Menu"); ?></title>
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
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<?php
//only show the product list if the category is valid (exists, active and not deleted)
if($valid_category)
{
?>

<h2 class="section-title"><?php echo $catname; ?></h2>
<p class="intro"><?php echo $catdesc; ?></p>

<table class="menu-table" width="100%" border="1"><!--Table section for displaying the products in this category-->
<tr>
<th width="140px">Image</th>
<th>Item</th>
<th width="120px">Price</th>
<th width="140px">Order</th>
</tr>

<?php
//only show products that belong to this category, are not deleted and are active
$product_stmt = mysqli_prepare($connect,"SELECT * FROM product WHERE product_category=? AND product_isDelete=0 AND product_status!='Inactive'");
mysqli_stmt_bind_param($product_stmt,"s",$catname);
mysqli_stmt_execute($product_stmt);
$result = mysqli_stmt_get_result($product_stmt);

while($row = mysqli_fetch_assoc($result))
{
	$pid = $row["product_id"];
	$pname = $row["product_name"];
	$pprice = $row["product_price"];
	$pstock = $row["product_stock"];
	$pstatus = $row["product_status"];
	$pdesc = $row["product_desc"];

	//picture for each known product (unknown/new products fall back to the logo)
	if($pname=="Original Recipe (1 pc)"){ $pimg="chicken-original.jpg"; }
	else if($pname=="Hot & Spicy (1 pc)"){ $pimg="chicken-hotspicy.jpg"; }
	else if($pname=="Crispy Tenders (3 pcs)"){ $pimg="chicken-tenders.jpg"; }
	else if($pname=="Nuggets (6 pcs)"){ $pimg="chicken-nuggets.jpg"; }
	else if($pname=="Classic Burger"){ $pimg="burger-classic.jpg"; }
	else if($pname=="Beef Burger"){ $pimg="burger-beef.jpg"; }
	else if($pname=="Filet-O-Fish"){ $pimg="burger-fish.jpg"; }
	else if($pname=="Zinger Burger"){ $pimg="burger-zinger.jpg"; }
	else if($pname=="Zinger Double Down"){ $pimg="burger-zingerdouble.jpg"; }
	else if($pname=="French Fries"){ $pimg="side-fries.jpg"; }
	else if($pname=="Cheezy Wedges"){ $pimg="side-wedges.jpg"; }
	else if($pname=="Onion Rings"){ $pimg="side-onionrings.jpg"; }
	else if($pname=="Corn Cup"){ $pimg="side-corncup.jpg"; }
	else if($pname=="Ice Cream Cone"){ $pimg="dessert-icecream.jpg"; }
	else if($pname=="Chocolate Sundae"){ $pimg="dessert-sundae.jpg"; }
	else if($pname=="Apple Pie"){ $pimg="dessert-applepie.jpg"; }
	else if($pname=="Coca-Cola"){ $pimg="bev-coke.jpg"; }
	else if($pname=="Sprite"){ $pimg="bev-sprite.jpg"; }
	else if($pname=="Orange Juice"){ $pimg="bev-orangejuice.jpg"; }
	else if($pname=="Iced Latte"){ $pimg="bev-icedlatte.jpg"; }
	else if($pname=="Mineral Water"){ $pimg="bev-water.jpg"; }
	else { $pimg="logo.png"; }
?>

<tr>
<td align="center"><img src="image/<?php echo $pimg; ?>" width="120px" height="90px" alt="<?php echo $pname; ?>" title="<?php echo $pname; ?>"></td>
<td><b><?php echo $pname; ?></b><br><?php echo $pdesc; ?></td>
<td align="center"><span class="price">RM <?php echo number_format($pprice,2); ?></span></td>
<td align="center">
<?php
if($pstock>0 && $pstatus=="Active")
{
?>
<a class="btn-small" href="cart.php?add=<?php echo $pid; ?>">Add to Cart</a>
<?php
}
else
{
?>
<span class="out-of-stock">Out of Stock</span>
<?php
}
?>
</td>
</tr>

<?php
}
mysqli_stmt_close($product_stmt);
?>

</table>

<br>

<h4>Good to know:</h4>
<ul class="note">
<li>All prices are in Malaysian Ringgit (RM).</li>
<li>Prices are inclusive of service tax.</li>
</ul>

<?php
}
else
{
//the category id was missing, not found, inactive or deleted
?>

<h2 class="section-title">Category Not Available</h2>
<p class="intro">Sorry, this category is not available at the moment. Please choose another category from the menu.</p>

<?php
}
?>

<p style="text-align:center;"><a class="btn" href="category.php">Back to Menu</a></p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
