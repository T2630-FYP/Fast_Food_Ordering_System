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

	return "image/".($images[$category_name] ?? "logo.png");
}

function catalog_product_image($product_name)
{
	// This preserves the current assignment images in one shared location.
	// Unit 6C will replace this fallback with the database image path uploaded by Admin.
	$images = array(
		"Original Recipe (1 pc)" => "chicken-original.jpg",
		"Hot & Spicy (1 pc)" => "chicken-hotspicy.jpg",
		"Crispy Tenders (3 pcs)" => "chicken-tenders.jpg",
		"Nuggets (6 pcs)" => "chicken-nuggets.jpg",
		"Classic Burger" => "burger-classic.jpg",
		"Beef Burger" => "burger-beef.jpg",
		"Filet-O-Fish" => "burger-fish.jpg",
		"Zinger Burger" => "burger-zinger.jpg",
		"Zinger Double Down" => "burger-zingerdouble.jpg",
		"French Fries" => "side-fries.jpg",
		"Cheezy Wedges" => "side-wedges.jpg",
		"Onion Rings" => "side-onionrings.jpg",
		"Corn Cup" => "side-corncup.jpg",
		"Ice Cream Cone" => "dessert-icecream.jpg",
		"Chocolate Sundae" => "dessert-sundae.jpg",
		"Apple Pie" => "dessert-applepie.jpg",
		"Coca-Cola" => "bev-coke.jpg",
		"Sprite" => "bev-sprite.jpg",
		"Orange Juice" => "bev-orangejuice.jpg",
		"Iced Latte" => "bev-icedlatte.jpg",
		"Mineral Water" => "bev-water.jpg"
	);

	return "image/".($images[$product_name] ?? "logo.png");
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

