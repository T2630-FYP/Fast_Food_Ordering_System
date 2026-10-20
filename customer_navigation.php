<?php

// Keep the visitor and customer link order the same on every public page.
function easyorder_render_customer_navigation_links($customer_logged_in)
{
	$links = $customer_logged_in
		? array(
			array("category.php","Menu"),
			array("cart.php","Cart"),
			array("order_history.php","Order History"),
			array("profile.php","My Profile"),
			array("wallet.php","Wallet"),
			array("reward.php","Rewards"),
			array("view_review.php","View Reviews"),
			array("about.php","About Us"),
			array("contact.php","Contact Us"),
			array("index.php","Home"),
			array("logout.php","Logout")
		)
		: array(
			array("login.php","Login"),
			array("register.php","Sign Up"),
			array("./","Home"),
			array("about.php","About Us"),
			array("contact.php","Contact Us")
		);

	foreach($links as $link)
	{
		$confirmation = $link[0]==="logout.php"
			? ' onclick="return confirm(\'Are you sure you want to logout?\')"'
			: "";
		echo '<a href="'.$link[0].'"'.$confirmation.'>'.$link[1]."</a>\n";
	}
}

// Public pages refresh links using the same validated PHP session.
if(isset($_SERVER["SCRIPT_FILENAME"]) && realpath($_SERVER["SCRIPT_FILENAME"])===realpath(__FILE__))
{
	require_once(__DIR__."/dataconnection.php");
	header("Content-Type: text/html; charset=UTF-8");
	header("Cache-Control: private, no-store, max-age=0");
	header("Pragma: no-cache");
	header("Vary: Cookie");
	$customer_logged_in = isset($_SESSION["customer_id"]);
	session_write_close();
	easyorder_render_customer_navigation_links($customer_logged_in);
}
