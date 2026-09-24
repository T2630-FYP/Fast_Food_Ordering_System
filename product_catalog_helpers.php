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

function catalog_product_image($stored_path)
{
	// Product paths are stored by Admin and must remain inside the local image folder.
	$path = str_replace("\\","/",trim((string)$stored_path));
	if(preg_match("#^image/[A-Za-z0-9._-]+$#",$path)===1)
	{
		return $path;
	}

	return "image/logo.png";
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
