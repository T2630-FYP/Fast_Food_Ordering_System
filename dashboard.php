<?php
//only logged in members can see their dashboard
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

$mid = (int)$_SESSION["member_id"];
$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");
$profile_errors = array();
$profile_success = "";

function profile_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

//Always identify the editable record from the current session, never from a URL or hidden member id.
$stmt = mysqli_prepare($connect,"SELECT * FROM member WHERE member_id=? AND member_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$member_result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($member_result);
mysqli_stmt_close($stmt);

if(!$member)
{
	unset($_SESSION["member_id"],$_SESSION["member_name"]);
	header("location:login.php");
	exit();
}

$profile_values = array(
	"name" => $member["member_name"],
	"phone" => $member["member_phone"],
	"gender" => $member["member_gender"],
	"dob" => $member["member_dob"],
	"address" => $member["member_address"],
	"state" => $member["member_state"],
	"city" => $member["member_city"],
	"postcode" => $member["member_postcode"]
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
		"name" => trim((string)($_POST["member_name"] ?? "")),
		"phone" => trim((string)($_POST["member_phone"] ?? "")),
		"gender" => trim((string)($_POST["member_gender"] ?? "")),
		"dob" => trim((string)($_POST["member_dob"] ?? "")),
		"address" => trim((string)($_POST["member_address"] ?? "")),
		"state" => trim((string)($_POST["member_state"] ?? "")),
		"city" => trim((string)($_POST["member_city"] ?? "")),
		"postcode" => trim((string)($_POST["member_postcode"] ?? ""))
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
		$stmt = mysqli_prepare($connect,"UPDATE member SET member_name=?,member_phone=?,member_gender=?,member_dob=?,member_address=?,member_state=?,member_city=?,member_postcode=? WHERE member_id=? AND member_isDelete=0");
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

		$_SESSION["member_name"] = $profile_values["name"];
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

//Reload persisted member data after a normal request. On a failed POST, keep submitted values in the form.
if($_SERVER["REQUEST_METHOD"]!=="POST")
{
	$profile_values = array(
		"name" => $member["member_name"],
		"phone" => $member["member_phone"],
		"gender" => $member["member_gender"],
		"dob" => $member["member_dob"],
		"address" => $member["member_address"],
		"state" => $member["member_state"],
		"city" => $member["member_city"],
		"postcode" => $member["member_postcode"]
	);
}

$stmt = mysqli_prepare($connect,"SELECT COUNT(*) AS order_count FROM orders WHERE order_member=? AND order_isDelete=0");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$order_result = mysqli_stmt_get_result($stmt);
$order_count = (int)mysqli_fetch_assoc($order_result)["order_count"];
mysqli_stmt_close($stmt);
$member_points = (int)$member["member_points"];
?>

<!DOCTYPE html>
<html>

<head><!--Customer dashboard page after login-->
<title>My Dashboard</title>
<link rel="stylesheet" href="style.css">

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
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<div id="welcome-box">
<h3>Welcome back, <?php echo profile_h($member["member_name"]); ?>!</h3>
<p>This is your account dashboard. Here you can view your profile and track your orders.</p>
</div>

<h2 class="section-title">Account Overview</h2>
<table class="menu-table" width="520px" border="1"><!--Table section for displaying account summary-->
<tr>
<th>Total Orders</th>
<th>Loyalty Points</th>
<th>Member Since</th>
</tr>
<tr>
<td align="center"><?php echo $order_count; ?></td>
<td align="center"><?php echo $member_points; ?></td>
<td align="center"><?php echo profile_h($member["member_joindate"]); ?></td>
</tr>
</table>

<hr>

<section id="profile">
<h2 class="section-title">My Profile</h2>
<p class="intro">Keep your personal information and default delivery address up to date. Checkout will prefill this address, but changes made during one order will not overwrite it.</p>

<div class="form-box" id="profile-box">
<h3>Profile Information</h3>

<?php if($profile_success!=="") { ?>
<p class="profile-success"><?php echo profile_h($profile_success); ?></p>
<?php } ?>

<?php if(isset($profile_errors["general"])) { ?>
<p class="msg"><?php echo profile_h($profile_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="dashboard.php#profile" novalidate>
<input type="hidden" name="profile_csrf" value="<?php echo profile_h($_SESSION["profile_csrf"]); ?>">

<div class="profile-grid">
<div class="profile-field full-width">
<label for="member_email">Email Address</label>
<input type="email" id="member_email" value="<?php echo profile_h($member["member_email"]); ?>" readonly>
<small>Email is used for login and cannot be changed here.</small>
</div>

<div class="profile-field">
<label for="member_name">Full Name *</label>
<input type="text" id="member_name" name="member_name" minlength="2" maxlength="100" required value="<?php echo profile_h($profile_values["name"]); ?>">
<?php if(isset($profile_errors["name"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["name"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="member_phone">Phone Number *</label>
<input type="text" id="member_phone" name="member_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" required value="<?php echo profile_h($profile_values["phone"]); ?>">
<?php if(isset($profile_errors["phone"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["phone"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="member_gender">Gender *</label>
<select id="member_gender" name="member_gender" required>
<option value="">Select gender</option>
<option value="Male" <?php if($profile_values["gender"]==="Male") echo "selected"; ?>>Male</option>
<option value="Female" <?php if($profile_values["gender"]==="Female") echo "selected"; ?>>Female</option>
</select>
<?php if(isset($profile_errors["gender"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["gender"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="member_dob">Date of Birth *</label>
<input type="date" id="member_dob" name="member_dob" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>" required value="<?php echo profile_h($profile_values["dob"]); ?>">
<?php if(isset($profile_errors["dob"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["dob"]); ?></span><?php } ?>
</div>

<div class="profile-field full-width">
<label for="member_address">Default Street Address *</label>
<textarea id="member_address" name="member_address" minlength="5" maxlength="140" required placeholder="House number, building, street and unit number"><?php echo profile_h($profile_values["address"]); ?></textarea>
<?php if(isset($profile_errors["address"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["address"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="member_state">State *</label>
<select id="member_state" name="member_state" required>
<option value="">Select state</option>
<?php foreach($states as $state_name) { ?>
<option value="<?php echo profile_h($state_name); ?>" <?php if($profile_values["state"]===$state_name) echo "selected"; ?>><?php echo profile_h($state_name); ?></option>
<?php } ?>
</select>
<?php if(isset($profile_errors["state"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["state"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="member_city">City *</label>
<input type="text" id="member_city" name="member_city" minlength="2" maxlength="50" required value="<?php echo profile_h($profile_values["city"]); ?>">
<?php if(isset($profile_errors["city"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["city"]); ?></span><?php } ?>
</div>

<div class="profile-field">
<label for="member_postcode">Postcode *</label>
<input type="text" id="member_postcode" name="member_postcode" inputmode="numeric" pattern="[0-9]{5}" minlength="5" maxlength="5" required value="<?php echo profile_h($profile_values["postcode"]); ?>">
<?php if(isset($profile_errors["postcode"])) { ?><span class="profile-error"><?php echo profile_h($profile_errors["postcode"]); ?></span><?php } ?>
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

</body>

</html>
