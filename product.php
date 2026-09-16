<?php
include("dataconnection.php");
require_once("product_catalog_helpers.php");

if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}

$product = null;
$product_id = trim((string)($_GET["id"] ?? ""));
if(preg_match("/^[A-Za-z0-9]{1,5}$/",$product_id))
{
	$product_stmt = mysqli_prepare(
		$connect,
		"SELECT p.product_id,p.product_name,p.product_desc,p.product_category,p.product_price,p.product_stock,p.product_status,c.category_id
		FROM product p
		INNER JOIN category c ON c.category_name=p.product_category
		WHERE p.product_id=?
		AND p.product_isDelete=0
		AND c.category_isDelete=0
		AND c.category_status='Active'
		LIMIT 1"
	);
	mysqli_stmt_bind_param($product_stmt,"s",$product_id);
	mysqli_stmt_execute($product_stmt);
	$product_result = mysqli_stmt_get_result($product_stmt);
	$product = mysqli_fetch_assoc($product_result);
	mysqli_stmt_close($product_stmt);
}

$page_title = $product ? $product["product_name"] : "Product Not Available";
$product_state = $product ? catalog_product_state($product) : null;
?>

<!DOCTYPE html>
<html lang="en">

<head><!--Dynamic product detail page-->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo catalog_h($page_title); ?> | EasyOrder</title>
<link rel="stylesheet" href="style.css?v=20260916-3">
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

<main id="main">

<?php if($product) { ?>
<nav class="catalog-breadcrumb" aria-label="Breadcrumb">
<a href="category.php">Menu</a>
<span aria-hidden="true">/</span>
<a href="category.php?category=<?php echo rawurlencode($product["category_id"]); ?>"><?php echo catalog_h($product["product_category"]); ?></a>
<span aria-hidden="true">/</span>
<span><?php echo catalog_h($product["product_name"]); ?></span>
</nav>

<article class="catalog-detail-card">
<div class="catalog-detail-image">
<img src="<?php echo catalog_h(catalog_product_image($product["product_name"])); ?>" alt="<?php echo catalog_h($product["product_name"]); ?>">
</div>
<div class="catalog-detail-content">
<p class="catalog-product-category"><?php echo catalog_h($product["product_category"]); ?></p>
<h2><?php echo catalog_h($product["product_name"]); ?></h2>
<p class="catalog-detail-description"><?php echo catalog_h($product["product_desc"]); ?></p>
<p class="catalog-detail-price">RM <?php echo number_format((float)$product["product_price"],2); ?></p>
<div class="catalog-detail-stock">
<strong>Availability</strong>
<span class="catalog-status <?php echo catalog_h($product_state["class"]); ?>"><?php echo catalog_h($product_state["label"]); ?></span>
</div>
<p class="catalog-detail-note">Prices are shown in Malaysian Ringgit (RM) and include service tax.</p>
<div class="catalog-detail-actions">
<?php if($product_state["orderable"]) { ?>
<a class="btn" href="cart.php?add=<?php echo rawurlencode($product["product_id"]); ?>">Add to Cart</a>
<?php } else { ?>
<span class="catalog-order-disabled catalog-order-disabled-large">This item cannot be added to the cart.</span>
<?php } ?>
<a class="btn-secondary" href="category.php?category=<?php echo rawurlencode($product["category_id"]); ?>">Back to <?php echo catalog_h($product["product_category"]); ?></a>
</div>
</div>
</article>

<?php } else { ?>
<section class="catalog-empty-state catalog-detail-empty">
<h2>Product Not Available</h2>
<p>This product does not exist or is no longer available in the EasyOrder menu.</p>
<a class="btn" href="category.php">Back to Menu</a>
</section>
<?php } ?>

</main>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
