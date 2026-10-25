<?php

function catalog_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

function catalog_category_image($category_name)
{
	$images = array(
		"Fried Chicken" => "cat-chicken.jpg",
		"Burger" => "cat-burger.jpg",
		"Side Dishes" => "cat-sides.jpg",
		"Dessert" => "cat-dessert.jpg",
		"Beverage" => "cat-beverage.jpg"
	);

	return "image/".($images[$category_name] ?? "logo.jpg");
}

function catalog_product_image($stored_path)
{
	// Product paths are stored by Admin and must remain inside the local image folder.
	$path = str_replace("\\","/",trim((string)$stored_path));
	if(preg_match("#^image/[A-Za-z0-9._-]+$#",$path)===1)
	{
		return $path;
	}

	return "image/logo.jpg";
}

function catalog_product_state($product)
{
	if((int)$product["product_stock"]<=0 || $product["product_status"]==="Out of Stock")
	{
		return array("label" => "Out of Stock", "class" => "catalog-status-out", "orderable" => false);
	}

	if($product["product_status"]!=="Active")
	{
		return array("label" => "Unavailable", "class" => "catalog-status-unavailable", "orderable" => false);
	}

	return array(
		"label" => "In Stock (".(int)$product["product_stock"].")",
		"class" => "catalog-status-in",
		"orderable" => true
	);
}

function catalog_public_categories($connect)
{
	$images = array(
		"Fried Chicken" => array("cat-chicken.jpg","Golden, crispy goodness."),
		"Burger" => array("cat-burger.jpg","Big flavour in every bite."),
		"Side Dishes" => array("cat-sides.jpg","The perfect sides to complete your meal."),
		"Dessert" => array("cat-dessert.jpg","A sweet way to finish."),
		"Beverage" => array("cat-beverage.jpg","Cold drinks to keep you refreshed.")
	);
	$result = mysqli_query($connect,"SELECT category_id,category_name FROM category
		WHERE category_isDelete=0 AND category_status='Active'
		AND category_name IN ('Fried Chicken','Burger','Side Dishes','Dessert','Beverage')
		ORDER BY FIELD(category_name,'Fried Chicken','Burger','Side Dishes','Dessert','Beverage')");
	$categories = array();
	while($category = mysqli_fetch_assoc($result))
	{
		$meta = $images[$category["category_name"]];
		$category["image"] = "image/".$meta[0];
		$category["description"] = $meta[1];
		$categories[] = $category;
	}
	mysqli_free_result($result);
	return $categories;
}
