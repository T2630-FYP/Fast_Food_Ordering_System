<?php

// Return only customer page locations, retaining view parameters rather than actions.
function easyorder_customer_return_location($page,$query=array())
{
	$pages = array(
		"category.php" => array("category","search"),
		"cart.php" => array(),
		"checkout.php" => array(),
		"payment.php" => array("order_id"),
		"order_history.php" => array(),
		"order_details.php" => array("order_id","download"),
		"profile.php" => array(),
		"change_password.php" => array(),
		"review.php" => array(),
		"reward.php" => array(),
		"wallet.php" => array(),
		"wallet_topup.php" => array(),
		"wallet_pin_recovery.php" => array()
	);
	if(!is_string($page) || !isset($pages[$page]) || !is_array($query)) return "";

	$parameters = array();
	foreach($pages[$page] as $key)
	{
		if(!isset($query[$key]) || !is_scalar($query[$key])) continue;
		$value = (string)$query[$key];
		if($key==="order_id")
		{
			$id = filter_var($value,FILTER_VALIDATE_INT,array("options"=>array("min_range"=>1)));
			if($id===false) continue;
			$value = (string)$id;
		}
		else if($key==="download")
		{
			if(strtolower($value)!=="pdf") continue;
			$value = "pdf";
		}
		else if(strlen($value)>100)
		{
			continue;
		}
		$parameters[$key] = $value;
	}
	return $page.(count($parameters)>0 ? "?".http_build_query($parameters,"","&",PHP_QUERY_RFC3986) : "");
}

function easyorder_redirect_customer_to_login()
{
	// Resume a page view after login; never replay a form submission or cart action.
	if(($_SERVER["REQUEST_METHOD"] ?? "GET")==="GET")
	{
		$page = basename($_SERVER["SCRIPT_NAME"] ?? "");
		$location = easyorder_customer_return_location($page,$_GET);
		if($location!=="") $_SESSION["customer_login_return"] = $location;
	}
	header("Location: login.php");
	exit();
}

function easyorder_customer_login_destination()
{
	$location = $_SESSION["customer_login_return"] ?? "";
	unset($_SESSION["customer_login_return"]);
	if(!is_string($location) || $location==="" || strlen($location)>2000) return "category.php";

	$parts = parse_url($location);
	if($parts===false || count(array_diff(array_keys($parts),array("path","query")))>0) return "category.php";
	$query = array();
	parse_str($parts["query"] ?? "",$query);
	$destination = easyorder_customer_return_location($parts["path"] ?? "",$query);
	return $destination!=="" ? $destination : "category.php";
}
