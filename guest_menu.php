<?php
require_once(__DIR__."/dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
require_once(__DIR__."/product_catalog_helpers.php");
header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Vary: Cookie");

$categories = catalog_public_categories($connect);
$category_lookup = array();
foreach($categories as $category) $category_lookup[$category["category_id"]] = $category;
$requested_category = isset($_GET["category"]) && is_string($_GET["category"]) ? trim($_GET["category"]) : "";
$selected_category = isset($category_lookup[$requested_category]) ? $requested_category : "";
$search_input = isset($_GET["search"]) && is_string($_GET["search"]) ? trim($_GET["search"]) : "";
$search_error = strlen($search_input)>100 ? "Search terms must contain 100 characters or fewer." : "";
$search_term = $search_error==="" ? $search_input : "";

if(isset($_SESSION["customer_id"]))
{
	$query = array();
	if($selected_category!=="") $query["category"] = $selected_category;
	if($search_term!=="") $query["search"] = $search_term;
	header("Location: category.php".(count($query)>0 ? "?".http_build_query($query) : ""));
	exit();
}

// Public queries contain no stock counts; customer actions remain on protected pages.
$stmt = mysqli_prepare($connect,"SELECT p.product_name,p.product_desc,p.product_image,p.product_category,p.product_price
	FROM product p INNER JOIN category c ON c.category_name=p.product_category
	WHERE p.product_isDelete=0 AND p.product_status IN ('Active','Out of Stock')
	AND c.category_isDelete=0 AND c.category_status='Active'
	AND c.category_name IN ('Fried Chicken','Burger','Side Dishes','Dessert','Beverage')
	AND (?='' OR c.category_id=?)
	AND (?='' OR p.product_name LIKE CONCAT('%',?,'%') OR p.product_desc LIKE CONCAT('%',?,'%'))
	ORDER BY FIELD(c.category_name,'Fried Chicken','Burger','Side Dishes','Dessert','Beverage'),
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
	END,p.product_name");
mysqli_stmt_bind_param($stmt,"sssss",$selected_category,$selected_category,$search_term,$search_term,$search_term);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$products = mysqli_fetch_all($result,MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$result_title = $selected_category!=="" ? $category_lookup[$selected_category]["category_name"] : "All menu items";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo catalog_h($selected_category!=="" ? $result_title." | Guest Menu" : "Guest Menu"); ?> | EasyOrder</title>
<link rel="icon" type="image/png" href="image/logo.png">
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<link rel="stylesheet" href="home_section.css?v=20261024-shell1">
<script src="customer_navigation.js?v=20261024-shell1" defer></script>
</head>
<body class="eo-guest" data-session-aware>
<?php easyorder_render_customer_header(); ?>
<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(false); ?>
</div>
<main class="eo-block">
<div class="eo-menu">
<section class="eo-menu-intro" aria-labelledby="guest-menu-title">
<div>
<h1 id="guest-menu-title">Our Menu</h1>
<p>Browse our food categories and find your favourites.</p>
</div>
<a class="eo-order-btn" href="login.php">Log in to order <span aria-hidden="true">↗</span></a>
</section>
<form class="eo-menu-search" action="guest_menu.php" method="get" role="search">
<?php if($selected_category!=="") { ?><input type="hidden" name="category" value="<?php echo catalog_h($selected_category); ?>"><?php } ?>
<label for="guest-menu-search">Search food
<input type="search" id="guest-menu-search" name="search" maxlength="100" placeholder="What are you craving?" value="<?php echo catalog_h($search_input); ?>">
</label>
<button type="submit">Search</button>
</form>
<?php if($search_error!=="") { ?><p class="eo-menu-notice" role="alert"><?php echo catalog_h($search_error); ?></p><?php } ?>
<?php if($requested_category!=="" && $selected_category==="") { ?><p class="eo-menu-notice">That category is no longer available. Browse the menu below.</p><?php } ?>
<nav class="eo-menu-filters catalog-category-grid" aria-label="Food categories">
<a class="eo-menu-filter catalog-filter-card<?php echo $selected_category==="" ? " catalog-filter-active" : ""; ?>" href="guest_menu.php<?php echo $search_term!=="" ? "?search=".rawurlencode($search_term) : ""; ?>#guest-menu-products"<?php echo $selected_category==="" ? ' aria-current="page"' : ""; ?>><img src="image/all-items.jpg" alt=""><span><strong>All Items</strong><small>Browse the complete menu</small></span></a>
<?php foreach($categories as $category) { $filter_query = array("category"=>$category["category_id"]); if($search_term!=="") $filter_query["search"] = $search_term; ?>
<a class="eo-menu-filter catalog-filter-card<?php echo $selected_category===$category["category_id"] ? " catalog-filter-active" : ""; ?>" href="guest_menu.php?<?php echo catalog_h(http_build_query($filter_query)); ?>#guest-menu-products"<?php echo $selected_category===$category["category_id"] ? ' aria-current="page"' : ""; ?>>
<img src="<?php echo catalog_h($category["image"]); ?>" alt="">
<span><strong><?php echo catalog_h($category["category_name"]); ?></strong><small><?php echo catalog_h($category["description"]); ?></small></span>
</a>
<?php } ?>
</nav>
<section class="eo-menu-results" id="guest-menu-products" aria-labelledby="guest-menu-results-title">
<div class="eo-menu-results-heading">
<h2 id="guest-menu-results-title"><?php echo catalog_h($result_title); ?></h2>
<p><?php echo count($products); ?> item<?php echo count($products)===1 ? "" : "s"; ?> found.</p>
</div>
<?php if(count($products)>0) { ?>
<div class="eo-menu-products">
<?php foreach($products as $product) { ?>
<article class="eo-menu-food">
<img src="<?php echo catalog_h(catalog_product_image($product["product_image"])); ?>" alt="<?php echo catalog_h($product["product_name"]); ?>">
<div class="eo-menu-food-copy">
<p class="eo-menu-food-category"><?php echo catalog_h($product["product_category"]); ?></p>
<h3><?php echo catalog_h($product["product_name"]); ?></h3>
<p class="eo-menu-food-description"><?php echo catalog_h($product["product_desc"]); ?></p>
<p class="eo-menu-food-price">RM <?php echo number_format((float)$product["product_price"],2); ?></p>
</div>
</article>
<?php } ?>
</div>
<?php } else { ?>
<div class="eo-menu-empty">
<h3>No menu items found</h3>
<p>Try another food name or browse a different category.</p>
<a href="guest_menu.php">View all items</a>
</div>
<?php } ?>
</section>
</div>
</main>
<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>
</body>
</html>
