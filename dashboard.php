<?php
//only logged in customers can see their dashboard
session_start();
if(!isset($_SESSION["customer_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

$mid = (int)$_SESSION["customer_id"];
$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");
$profile_errors = array();
$profile_success = "";

function profile_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

//Always identify the editable record from the current session, never from a URL or hidden customer id.
$stmt = mysqli_prepare($connect,"SELECT * FROM customer WHERE customer_id=? AND customer_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$customer_result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($customer_result);
mysqli_stmt_close($stmt);

if(!$customer)
{
	unset($_SESSION["customer_id"],$_SESSION["customer_name"]);
	header("location:login.php");
	exit();
}

$profile_values = array(
	"name" => $customer["customer_name"],
	"phone" => $customer["customer_phone"],
	"gender" => $customer["customer_gender"],
	"dob" => $customer["customer_dob"],
	"address" => $customer["customer_address"],
	"state" => $customer["customer_state"],
	"city" => $customer["customer_city"],
	"postcode" => $customer["customer_postcode"]
);

if(!isset($_SESSION["profile_csrf"]))
{
	$_SESSION["profile_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["update_profile"]))
{
	$submitted_token = (string)($_POST["profile_csrf"] ?? "");
	if($submitted_token==="" || !hash_equals($_SESSION["profile_csrf"],$submitted_token))
	{
		$profile_errors["general"] = "Your profile form has expired. Please refresh the page and try again.";
	}

	$profile_values = array(
		"name" => trim((string)($_POST["customer_name"] ?? "")),
		"phone" => trim((string)($_POST["customer_phone"] ?? "")),
		"gender" => trim((string)($_POST["customer_gender"] ?? "")),
		"dob" => trim((string)($_POST["customer_dob"] ?? "")),
		"address" => trim((string)($_POST["customer_address"] ?? "")),
		"state" => trim((string)($_POST["customer_state"] ?? "")),
		"city" => trim((string)($_POST["customer_city"] ?? "")),
		"postcode" => trim((string)($_POST["customer_postcode"] ?? ""))
	);

	if(strlen($profile_values["name"])<2 || strlen($profile_values["name"])>100)
	{
		$profile_errors["name"] = "Enter a name between 2 and 100 characters.";
	}
	if(!preg_match("/^\d{9,15}$/",$profile_values["phone"]))
	{
		$profile_errors["phone"] = "Enter a phone number containing 9 to 15 digits.";
	}
	if(!in_array($profile_values["gender"],array("Male","Female"),true))
	{
		$profile_errors["gender"] = "Select a valid gender.";
	}
	$dob_value = DateTime::createFromFormat("!Y-m-d",$profile_values["dob"]);
	if(!$dob_value || $dob_value->format("Y-m-d")!==$profile_values["dob"] || $profile_values["dob"]<"1900-01-01" || $profile_values["dob"]>date("Y-m-d"))
	{
		$profile_errors["dob"] = "Enter a valid date of birth.";
	}
	if(strlen($profile_values["address"])<5 || strlen($profile_values["address"])>140)
	{
		$profile_errors["address"] = "Enter a street address between 5 and 140 characters.";
	}
	if(!in_array($profile_values["state"],$states,true))
	{
		$profile_errors["state"] = "Select a valid state.";
	}
	if(strlen($profile_values["city"])<2 || strlen($profile_values["city"])>50)
	{
		$profile_errors["city"] = "Enter a city between 2 and 50 characters.";
	}
	if(!preg_match("/^\d{5}$/",$profile_values["postcode"]))
	{
		$profile_errors["postcode"] = "Enter a valid 5-digit postcode.";
	}

	if(count($profile_errors)===0)
	{
		$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_name=?,customer_phone=?,customer_gender=?,customer_dob=?,customer_address=?,customer_state=?,customer_city=?,customer_postcode=? WHERE customer_id=? AND customer_isDelete=0");
		mysqli_stmt_bind_param(
			$stmt,
			"ssssssssi",
			$profile_values["name"],
			$profile_values["phone"],
			$profile_values["gender"],
			$profile_values["dob"],
			$profile_values["address"],
			$profile_values["state"],
			$profile_values["city"],
			$profile_values["postcode"],
			$mid
		);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		$_SESSION["customer_name"] = $profile_values["name"];
		$_SESSION["profile_success"] = "Your profile and default delivery address were updated successfully.";
		unset($_SESSION["profile_csrf"]);
		header("location:dashboard.php#profile");
		exit();
	}
}

if(isset($_SESSION["profile_success"]))
{
	$profile_success = $_SESSION["profile_success"];
	unset($_SESSION["profile_success"]);
}

//Reload persisted customer data after a normal request. On a failed POST, keep submitted values in the form.
if($_SERVER["REQUEST_METHOD"]!=="POST")
{
	$profile_values = array(
		"name" => $customer["customer_name"],
		"phone" => $customer["customer_phone"],
		"gender" => $customer["customer_gender"],
		"dob" => $customer["customer_dob"],
		"address" => $customer["customer_address"],
		"state" => $customer["customer_state"],
		"city" => $customer["customer_city"],
		"postcode" => $customer["customer_postcode"]
	);
}

$stmt = mysqli_prepare($connect,"SELECT COUNT(*) AS order_count FROM orders WHERE order_customer=? AND order_isDelete=0");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$order_result = mysqli_stmt_get_result($stmt);
$order_count = (int)mysqli_fetch_assoc($order_result)["order_count"];
mysqli_stmt_close($stmt);
$customer_points = (int)$customer["customer_points"];
?>

<!DOCTYPE html>
<html lang="en">

<head><!--Customer dashboard page after login-->
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard</title>
<link rel="stylesheet" href="style.css?v=20260916-1">

<style>
#welcome-box
{background-color:#FFC72C;
color:#9E0B22;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
padding:18px 25px 18px 25px;
margin-bottom:20px;
text-align:center;}

#welcome-box h3
{font-family:"Arial Black";
letter-spacing:1px;
margin:5px 0px 5px 0px;}

#welcome-box p
{font-style:italic;
margin:0px;}

.account-table
{width:100%;
margin:auto;}

.account-table td
{padding:8px 12px 8px 12px;
border-bottom:1px solid #EEEEEE;}

td.field
{color:#9E0B22;
font-weight:bold;
width:180px;}

#profile
{scroll-margin-top:20px;}

#profile-box
{width:760px;
box-sizing:border-box;}

.profile-grid
{display:grid;
grid-template-columns:1fr 1fr;
gap:16px 20px;}

.profile-field
{display:flex;
flex-direction:column;}

.profile-field.full-width
{grid-column:1 / -1;}

.profile-field label
{color:#9E0B22;
font-weight:bold;
font-size:0.9em;
margin-bottom:6px;}

.profile-field input,
.profile-field select,
.profile-field textarea
{width:100%;
box-sizing:border-box;
border:1px solid #CCCCCC;
border-radius:4px;
padding:9px 10px;}

.profile-field textarea
{min-height:78px;
resize:vertical;}

.profile-field input[readonly]
{background-color:#F3F3F3;
color:#666666;}

.profile-error
{color:#C8102E;
font-size:0.78em;
font-weight:bold;
margin-top:5px;}

.profile-success
{background-color:#E8F5E9;
border:1px solid #4CAF50;
color:#1B5E20;
border-radius:5px;
padding:10px 14px;
text-align:center;}

.profile-actions
{grid-column:1 / -1;
text-align:center;
margin-top:5px;}

.profile-password-link
{display:inline-block;
background-color:#9E0B22;
color:#FFFFFF;
font-weight:bold;
text-decoration:none;
border-radius:5px;
padding:10px 22px;
margin-left:8px;}

.profile-password-link:hover
{background-color:#C8102E;}

@media(max-width:800px)
{
	#main,
	#profile-box
	{width:94%;}

	.profile-grid
	{grid-template-columns:1fr;}

	.profile-field.full-width,
	.profile-actions
	{grid-column:1;}
}
</style>

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
<a href="order_history.php">Order History</a>
<a href="#profile">My Profile</a>
<a href="wallet.php">Wallet</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main" role="main"><!--Main content section-->

<div id="welcome-box">
<h3>Welcome back, <?php echo profile_h($customer["customer_name"]); ?>!</h3>
<p>This is your account dashboard. Here you can view your profile and track your orders.</p>
</div>

<h2 class="section-title">Account Overview</h2>
<table class="menu-table" width="520px" border="1"><!--Table section for displaying account summary-->
<tr>
<th>Total Orders</th>
<th>Loyalty Points</th>
<th>Customer Since</th>
</tr>
<tr>
<td align="center"><?php echo $order_count; ?></td>
<td align="center"><?php echo $customer_points; ?></td>
<td align="center"><?php echo profile_h($customer["customer_joindate"]); ?></td>
</tr>
</table>

<hr>

<section id="profile">
<h2 class="section-title">My Profile</h2>
<p class="intro">Keep your personal information and default delivery address up to date. Checkout will prefill this address, but changes made during one order will not overwrite it.</p>

<div class="form-box" id="profile-box">
<h3>Profile Information</h3>

<?php if($profile_success!=="") { ?>
<p class="profile-success" role="status"><?php echo profile_h($profile_success); ?></p>
<?php } ?>

<?php if(isset($profile_errors["general"])) { ?>
<p class="msg" role="alert"><?php echo profile_h($profile_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="dashboard.php#profile" novalidate>
<input type="hidden" name="profile_csrf" value="<?php echo profile_h($_SESSION["profile_csrf"]); ?>">

<div class="profile-grid">
<div class="profile-field full-width">
<label for="customer_email">Email Address</label>
<input type="email" id="customer_email" autocomplete="email" aria-describedby="profile-email-note" value="<?php echo profile_h($customer["customer_email"]); ?>" readonly>
<small id="profile-email-note">Email is used for login and cannot be changed here.</small>
</div>

<div class="profile-field">
<label for="customer_name">Full Name *</label>
<input type="text" id="customer_name" autocomplete="name" name="customer_name" minlength="2" maxlength="100"<?php if(isset($profile_errors["name"])) { ?> aria-invalid="true" aria-describedby="customer_name-error"<?php } ?> required value="<?php echo profile_h($profile_values["name"]); ?>">
<?php if(isset($profile_errors["name"])) { ?><span class="profile-error" id="customer_name-error"><?php echo profile_h($profile_errors["name"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="customer_phone">Phone Number *</label>
<input type="text" id="customer_phone" autocomplete="tel" name="customer_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15"<?php if(isset($profile_errors["phone"])) { ?> aria-invalid="true" aria-describedby="customer_phone-error"<?php } ?> required value="<?php echo profile_h($profile_values["phone"]); ?>">
<?php if(isset($profile_errors["phone"])) { ?><span class="profile-error" id="customer_phone-error"><?php echo profile_h($profile_errors["phone"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="customer_gender">Gender *</label>
<select id="customer_gender" name="customer_gender"<?php if(isset($profile_errors["gender"])) { ?> aria-invalid="true" aria-describedby="customer_gender-error"<?php } ?> required>
<option value="">Select gender</option>
<option value="Male" <?php if($profile_values["gender"]==="Male") echo "selected"; ?>>Male</option>
<option value="Female" <?php if($profile_values["gender"]==="Female") echo "selected"; ?>>Female</option>
</select>
<?php if(isset($profile_errors["gender"])) { ?><span class="profile-error" id="customer_gender-error"><?php echo profile_h($profile_errors["gender"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="customer_dob">Date of Birth *</label>
<input type="date" id="customer_dob" autocomplete="bday" name="customer_dob" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>"<?php if(isset($profile_errors["dob"])) { ?> aria-invalid="true" aria-describedby="customer_dob-error"<?php } ?> required value="<?php echo profile_h($profile_values["dob"]); ?>">
<?php if(isset($profile_errors["dob"])) { ?><span class="profile-error" id="customer_dob-error"><?php echo profile_h($profile_errors["dob"]); ?></span><?php } ?>
</div>

<div class="profile-field full-width">
<label for="customer_address">Default Street Address *</label>
<textarea id="customer_address" autocomplete="street-address" name="customer_address" minlength="5" maxlength="140"<?php if(isset($profile_errors["address"])) { ?> aria-invalid="true" aria-describedby="customer_address-error"<?php } ?> required placeholder="House number, building, street and unit number"><?php echo profile_h($profile_values["address"]); ?></textarea>
<?php if(isset($profile_errors["address"])) { ?><span class="profile-error" id="customer_address-error"><?php echo profile_h($profile_errors["address"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="customer_state">State *</label>
<select id="customer_state" autocomplete="address-level1" name="customer_state"<?php if(isset($profile_errors["state"])) { ?> aria-invalid="true" aria-describedby="customer_state-error"<?php } ?> required>
<option value="">Select state</option>
<?php foreach($states as $state_name) { ?>
<option value="<?php echo profile_h($state_name); ?>" <?php if($profile_values["state"]===$state_name) echo "selected"; ?>><?php echo profile_h($state_name); ?></option>
<?php } ?>
</select>
<?php if(isset($profile_errors["state"])) { ?><span class="profile-error" id="customer_state-error"><?php echo profile_h($profile_errors["state"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="customer_city">City *</label>
<input type="text" id="customer_city" autocomplete="address-level2" name="customer_city" minlength="2" maxlength="50"<?php if(isset($profile_errors["city"])) { ?> aria-invalid="true" aria-describedby="customer_city-error"<?php } ?> required value="<?php echo profile_h($profile_values["city"]); ?>">
<?php if(isset($profile_errors["city"])) { ?><span class="profile-error" id="customer_city-error"><?php echo profile_h($profile_errors["city"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="customer_postcode">Postcode *</label>
<input type="text" id="customer_postcode" autocomplete="postal-code" name="customer_postcode" inputmode="numeric" pattern="[0-9]{5}" minlength="5" maxlength="5"<?php if(isset($profile_errors["postcode"])) { ?> aria-invalid="true" aria-describedby="customer_postcode-error"<?php } ?> required value="<?php echo profile_h($profile_values["postcode"]); ?>">
<?php if(isset($profile_errors["postcode"])) { ?><span class="profile-error" id="customer_postcode-error"><?php echo profile_h($profile_errors["postcode"]); ?></span><?php } ?>
</div>

<div class="profile-actions">
<input type="submit" name="update_profile" value="Save Profile">
<a class="profile-password-link" href="change_password.php">Change Password</a>
</div>
</div>
</form>
</div>
</section>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

<script>
window.addEventListener("load",function()
{
	requestAnimationFrame(function()
	{
		const firstError=document.querySelector('form [aria-invalid="true"]');
		if(firstError) firstError.focus();
	});
});
</script>

</body>

</html>
