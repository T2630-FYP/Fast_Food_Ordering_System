<?php
include("dataconnection.php");
require_once("product_catalog_helpers.php");

if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}

$categories = array();
$category_lookup = array();
$category_result = mysqli_query($connect,"SELECT category_id,category_name,category_desc FROM category WHERE category_isDelete=0 AND category_status='Active' ORDER BY category_id");
while($category = mysqli_fetch_assoc($category_result))
{
	$categories[] = $category;
	$category_lookup[$category["category_id"]] = $category;
}

$selected_category = trim((string)($_GET["category"] ?? ""));
if($selected_category!=="" && !isset($category_lookup[$selected_category]))
{
	$selected_category = "";
}

$search_input = trim((string)($_GET["search"] ?? ""));
$search_term = $search_input;
$search_error = "";
if(strlen($search_term)>100)
{
	$search_error = "Search terms must contain 100 characters or fewer.";
	$search_term = "";
}

$product_sql = "SELECT p.product_id,p.product_name,p.product_desc,p.product_category,p.product_price,p.product_stock,p.product_status,c.category_id
	FROM product p
	INNER JOIN category c ON c.category_name=p.product_category
	WHERE p.product_isDelete=0
	AND c.category_isDelete=0
	AND c.category_status='Active'
	AND (?='' OR c.category_id=?)
	AND (?='' OR p.product_name LIKE CONCAT('%',?,'%') OR p.product_desc LIKE CONCAT('%',?,'%'))
	ORDER BY c.category_id,p.product_name";
$product_stmt = mysqli_prepare($connect,$product_sql);
mysqli_stmt_bind_param($product_stmt,"sssss",$selected_category,$selected_category,$search_term,$search_term,$search_term);
mysqli_stmt_execute($product_stmt);
$product_result = mysqli_stmt_get_result($product_stmt);
$products = array();
while($product = mysqli_fetch_assoc($product_result))
{
	$products[] = $product;
}
mysqli_stmt_close($product_stmt);

$result_label = "All menu items";
if($selected_category!=="")
{
	$result_label = $category_lookup[$selected_category]["category_name"];
}
?>

<!DOCTYPE html>
<html lang="en">

<head><!--Database-driven menu, search and category filter page-->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu</title>
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

<div id="main"><!--Main content section-->

<h2 class="section-title">Our Menu</h2>
<p class="intro">Search the live menu or choose a category to find your meal.</p>

<form class="catalog-search-form" action="category.php" method="get" role="search">
<?php if($selected_category!=="") { ?>
<input type="hidden" name="category" value="<?php echo catalog_h($selected_category); ?>">
<?php } ?>
<label for="menu-search">Search food</label>
<div class="catalog-search-row">
<input type="search" id="menu-search" name="search" value="<?php echo catalog_h($search_input); ?>" maxlength="100" placeholder="e.g. Zinger Burger">
<button type="submit">Search</button>
<?php if($search_input!=="" || $selected_category!=="") { ?>
<a class="btn-secondary" href="category.php">Clear</a>
<?php } ?>
</div>
<?php if($search_error!=="") { ?>
<span class="field-error"><?php echo catalog_h($search_error); ?></span>
<?php } ?>
</form>

<nav class="catalog-category-grid" aria-label="Menu categories">
<a class="catalog-filter-card<?php echo $selected_category==="" ? " catalog-filter-active" : ""; ?>" href="category.php<?php echo $search_term!=="" ? "?search=".rawurlencode($search_term) : ""; ?>">
<img src="image/logo.png" alt="All menu items">
<span><strong>All Items</strong><small>Browse the complete menu</small></span>
</a>
<?php foreach($categories as $category) { ?>
<?php
$category_query = "?category=".rawurlencode($category["category_id"]);
if($search_term!=="")
{
	$category_query .= "&search=".rawurlencode($search_term);
}
?>
<a class="catalog-filter-card<?php echo $selected_category===$category["category_id"] ? " catalog-filter-active" : ""; ?>" href="category.php<?php echo $category_query; ?>">
<img src="<?php echo catalog_h(catalog_category_image($category["category_name"])); ?>" alt="<?php echo catalog_h($category["category_name"]); ?>">
<span><strong><?php echo catalog_h($category["category_name"]); ?></strong><small><?php echo catalog_h($category["category_desc"]); ?></small></span>
</a>
<?php } ?>
</nav>

<div class="catalog-results-heading">
<h3><?php echo catalog_h($result_label); ?></h3>
<p><?php echo count($products); ?> item<?php echo count($products)===1 ? "" : "s"; ?> found<?php echo $search_term!=="" ? " for “".catalog_h($search_term)."”" : ""; ?>.</p>
</div>

<?php if(count($products)>0) { ?>
<section class="catalog-product-grid" aria-label="Menu products">
<?php foreach($products as $product) { ?>
<?php $state = catalog_product_state($product); ?>
<article class="catalog-product-card">
<a class="catalog-product-image-link" href="product.php?id=<?php echo rawurlencode($product["product_id"]); ?>">
<img src="<?php echo catalog_h(catalog_product_image($product["product_name"])); ?>" alt="<?php echo catalog_h($product["product_name"]); ?>">
</a>
<div class="catalog-product-content">
<p class="catalog-product-category"><?php echo catalog_h($product["product_category"]); ?></p>
<h3><a href="product.php?id=<?php echo rawurlencode($product["product_id"]); ?>"><?php echo catalog_h($product["product_name"]); ?></a></h3>
<p class="catalog-product-description"><?php echo catalog_h($product["product_desc"]); ?></p>
<p class="catalog-product-price">RM <?php echo number_format((float)$product["product_price"],2); ?></p>
<p><span class="catalog-status <?php echo catalog_h($state["class"]); ?>"><?php echo catalog_h($state["label"]); ?></span></p>
<div class="catalog-product-actions">
<a class="btn-secondary" href="product.php?id=<?php echo rawurlencode($product["product_id"]); ?>">View Details</a>
<?php if($state["orderable"]) { ?>
<a class="btn-small" href="cart.php?add=<?php echo rawurlencode($product["product_id"]); ?>">Add to Cart</a>
<?php } else { ?>
<span class="catalog-order-disabled">Cannot Add</span>
<?php } ?>
</div>
</div>
</article>
<?php } ?>
</section>
<?php } else { ?>
<section class="catalog-empty-state">
<h3>No menu items found</h3>
<p>Try another food name or clear the current category filter.</p>
<a class="btn" href="category.php">View All Items</a>
</section>
<?php } ?>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>
