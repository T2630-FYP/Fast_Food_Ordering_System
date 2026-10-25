<?php

// Keep the visitor and customer link order the same on every public page.
function easyorder_render_customer_navigation_links($customer_logged_in,$current_page=null)
{
	$current_page = $current_page ?? basename($_SERVER["SCRIPT_NAME"] ?? "index.php");
	if($current_page==="") $current_page = "index.php";
	if($current_page==="product.php") $current_page = "category.php";
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
			array("guest_menu.php","Menu"),
			array("about.php","About Us"),
			array("contact.php","Contact Us")
		);

	foreach($links as $link)
	{
		$confirmation = $link[0]==="logout.php"
			? ' onclick="return confirm(\'Are you sure you want to logout?\')"'
			: "";
		$link_page = $link[0]==="./" ? "index.php" : $link[0];
		$current = $current_page===$link_page ? ' aria-current="page"' : "";
		echo '<a href="'.$link[0].'"'.$current.$confirmation.'>'.$link[1]."</a>\n";
	}
}

// Shared Customer branding follows the approved Home page design.
function easyorder_render_customer_header()
{
?>
<div id="header" class="customer-site-header">
<img src="image/logo.jpg" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p class="brand-tagline">MADE FOR YOUR CRAVINGS</p>
</div>
<?php
}

function easyorder_render_customer_footer($customer_logged_in)
{
    $menu_url = $customer_logged_in ? "category.php" : "guest_menu.php";
?>
<footer class="home-footer"><!--Shared Customer footer-->
<div class="home-footer-container">
<div class="home-footer-grid">

<section aria-label="About EasyOrder">
<div class="home-footer-brand">
<img src="image/logo.jpg" width="52" height="52" alt="">
<h2>EasyOrder</h2>
</div>
<p class="home-footer-description">Freshly prepared favourites, friendly service and good food for your everyday cravings.</p>
<div class="home-footer-social">
<a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" aria-label="Instagram official website (opens in a new tab)" title="Instagram">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
<rect x="3" y="3" width="18" height="18" rx="5"/>
<circle cx="12" cy="12" r="4"/>
<circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
</svg>
</a>
<a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Facebook official website (opens in a new tab)" title="Facebook">
<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
<path d="M14 22v-9h3l.5-4H14V7c0-1.2.4-2 2-2h2V1.4C17.3 1.2 16.2 1 15 1c-3 0-5 1.8-5 5v3H7v4h3v9z"/>
</svg>
</a>
<a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn official website (opens in a new tab)" title="LinkedIn">
<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
<circle cx="5.5" cy="5.5" r="2"/>
<path d="M3.5 9h4v12h-4zM10 9h3.8v1.6c.8-1.2 2-1.9 3.6-1.9 3 0 4.1 1.8 4.1 5V21h-4v-6.4c0-1.6-.4-2.7-1.9-2.7-1.4 0-1.6 1.1-1.6 2.7V21h-4z"/>
</svg>
</a>
</div>
</section>

<nav class="home-footer-links" aria-labelledby="home-footer-links-title">
<h3 id="home-footer-links-title">Quick Links</h3>
<ul>
<li><a href="./">Home</a></li>
<li><a href="<?php echo $menu_url; ?>" data-customer-menu-link>Menu</a></li>
<li><a href="about.php">About Us</a></li>
<li><a href="contact.php">Contact Us</a></li>
</ul>
</nav>

<section aria-labelledby="home-footer-contact-title">
<h3 id="home-footer-contact-title">Contact Us</h3>
<div class="home-footer-contact">
<span>+60 7-684 9271</span>
<span>support@easyorder.com</span>
</div>
</section>

<section aria-labelledby="home-footer-visit-title">
<h3 id="home-footer-visit-title">Visit Us</h3>
<address>
EasyOrder &ndash; Mount Austin,<br>
G-55, Jalan Mutiara Emas 10/19,<br>
Taman Mount Austin,<br>
81100 Johor Bahru, Johor, Malaysia
</address>
<div class="home-footer-hours">
<p>Monday &ndash; Sunday, 10am &ndash; 10pm</p>
</div>
</section>

</div>

<div class="home-footer-bottom">
<p>&copy; 2026 EasyOrder. All rights reserved.</p>
</div>
</div>
</footer>
<?php
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
	$page = isset($_GET["page"]) && is_string($_GET["page"])
		? basename($_GET["page"])
		: basename(parse_url($_SERVER["HTTP_REFERER"] ?? "",PHP_URL_PATH) ?? "");
	easyorder_render_customer_navigation_links($customer_logged_in,$page);
}
