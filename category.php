<?php
include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
require_once("product_catalog_helpers.php");

if(!isset($_SESSION["customer_id"]))
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

$product_sql = "SELECT p.product_id,p.product_name,p.product_desc,p.product_image,p.product_category,p.product_price,p.product_stock,p.product_status,c.category_id
	FROM product p
	INNER JOIN category c ON c.category_name=p.product_category
	WHERE p.product_isDelete=0
	AND c.category_isDelete=0
	AND c.category_status='Active'
	AND (?='' OR c.category_id=?)
	AND (?='' OR p.product_name LIKE CONCAT('%',?,'%') OR p.product_desc LIKE CONCAT('%',?,'%'))
	ORDER BY c.category_id,
	CASE p.product_name
		WHEN 'Hot & Spicy (1 pc)' THEN 1
		WHEN 'Original Recipe (1 pc)' THEN 2
		WHEN 'Nuggets (6 pcs)' THEN 3
		WHEN 'Crispy Tenders (3 pcs)' THEN 4
		WHEN 'French Fries' THEN 1
		WHEN 'Cheezy Wedges' THEN 2
		WHEN 'Onion Rings' THEN 3
		WHEN 'Corn Cup' THEN 4
		WHEN 'Ice Cream Cone' THEN 1
		WHEN 'Chocolate Sundae' THEN 2
		WHEN 'Apple Pie' THEN 3
		ELSE 1000
	END,p.product_name";
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
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu</title>
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<link rel="stylesheet" href="home_section.css?v=20261024-shell1">
</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-block eo-customer-menu">
<div class="eo-menu">

<section class="eo-menu-intro" aria-labelledby="customer-menu-title">
<div>
<h1 id="customer-menu-title">Our Menu</h1>
<p>Browse our food categories and find your favourites.</p>
</div>
</section>

<form class="eo-menu-search" action="category.php" method="get" role="search">
<?php if($selected_category!=="") { ?>
<input type="hidden" name="category" value="<?php echo catalog_h($selected_category); ?>">
<?php } ?>
<label for="menu-search">Search food
<input type="search" id="menu-search" name="search" value="<?php echo catalog_h($search_input); ?>" maxlength="100" placeholder="What are you craving?"<?php if($search_error!=="") { ?> aria-invalid="true" aria-describedby="menu-search-error"<?php } ?>>
</label>
<button type="submit">Search</button>
</form>
<?php if($search_error!=="") { ?>
<p class="eo-menu-notice" id="menu-search-error" role="alert"><?php echo catalog_h($search_error); ?></p>
<?php } ?>

<nav class="catalog-category-grid" aria-label="Menu categories">
<a class="catalog-filter-card<?php echo $selected_category==="" ? " catalog-filter-active" : ""; ?>" href="category.php<?php echo $search_term!=="" ? "?search=".rawurlencode($search_term) : ""; ?>#customer-menu-products"<?php echo $selected_category==="" ? ' aria-current="page"' : ""; ?>>
<img src="image/all-items.jpg" alt="All menu items">
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
<a class="catalog-filter-card<?php echo $selected_category===$category["category_id"] ? " catalog-filter-active" : ""; ?>" href="category.php<?php echo $category_query; ?>#customer-menu-products"<?php echo $selected_category===$category["category_id"] ? ' aria-current="page"' : ""; ?>>
<img src="<?php echo catalog_h(catalog_category_image($category["category_name"])); ?>" alt="<?php echo catalog_h($category["category_name"]); ?>">
<span><strong><?php echo catalog_h($category["category_name"]); ?></strong><small><?php echo catalog_h($category["category_desc"]); ?></small></span>
</a>
<?php } ?>
</nav>

<section class="eo-menu-results" id="customer-menu-products" aria-labelledby="customer-menu-results-title">
<div class="eo-menu-results-heading">
<h2 id="customer-menu-results-title"><?php echo catalog_h($result_label); ?></h2>
<p><?php echo count($products); ?> item<?php echo count($products)===1 ? "" : "s"; ?> found<?php echo $search_term!=="" ? " for “".catalog_h($search_term)."”" : ""; ?>.</p>
</div>

<?php if(count($products)>0) { ?>
<section class="eo-menu-products" aria-label="Menu products">
<?php foreach($products as $product) { ?>
<?php $state = catalog_product_state($product); ?>
<article class="eo-menu-food">
<a class="eo-menu-food-image-link" href="product.php?id=<?php echo rawurlencode($product["product_id"]); ?>">
<img src="<?php echo catalog_h(catalog_product_image($product["product_image"])); ?>" alt="<?php echo catalog_h($product["product_name"]); ?>">
</a>
<div class="eo-menu-food-copy">
<p class="eo-menu-food-category"><?php echo catalog_h($product["product_category"]); ?></p>
<h3><a href="product.php?id=<?php echo rawurlencode($product["product_id"]); ?>"><?php echo catalog_h($product["product_name"]); ?></a></h3>
<p class="eo-menu-food-description"><?php echo catalog_h($product["product_desc"]); ?></p>
<p class="eo-menu-food-price">RM <?php echo number_format((float)$product["product_price"],2); ?></p>
<p class="eo-menu-food-stock"><span class="catalog-status <?php echo catalog_h($state["class"]); ?>"><?php echo catalog_h($state["label"]); ?></span></p>
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
<section class="eo-menu-empty">
<h3>No menu items found</h3>
<p>Try another food name or clear the current category filter.</p>
<a href="category.php">View All Items</a>
</section>
<?php } ?>
</section>

</div>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

</body>

</html>
