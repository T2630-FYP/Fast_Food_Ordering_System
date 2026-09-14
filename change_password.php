<?php
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");

$mid = (int)$_SESSION["member_id"];
$password_errors = array();
$password_success = "";

function change_password_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

if(!isset($_SESSION["change_password_csrf"]))
{
	$_SESSION["change_password_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["change_password_btn"]))
{
	$submitted_token = (string)($_POST["change_password_csrf"] ?? "");
	$old_password = (string)($_POST["old_password"] ?? "");
	$new_password = (string)($_POST["new_password"] ?? "");
	$confirm_password = (string)($_POST["confirm_password"] ?? "");

	if($submitted_token==="" || !hash_equals($_SESSION["change_password_csrf"],$submitted_token))
	{
		$password_errors["general"] = "Your password form has expired. Please refresh the page and try again.";
	}
	if($old_password==="")
	{
		$password_errors["old"] = "Enter your current password.";
	}
	if(strlen($new_password)<8 || strlen($new_password)>72)
	{
		$password_errors["new"] = "Use a new password containing 8 to 72 characters.";
	}
	if($confirm_password==="" || !hash_equals($new_password,$confirm_password))
	{
		$password_errors["confirm"] = "The new password and confirmation do not match.";
	}

	$stmt = mysqli_prepare($connect,"SELECT member_password FROM member WHERE member_id=? AND member_isDelete=0 LIMIT 1");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$password_result = mysqli_stmt_get_result($stmt);
	$password_row = mysqli_fetch_assoc($password_result);
	mysqli_stmt_close($stmt);

	if(!$password_row || !easyorder_password_verify($old_password,$password_row["member_password"]))
	{
		$password_errors["old"] = "The current password is incorrect.";
	}
	else if(easyorder_password_verify($new_password,$password_row["member_password"]))
	{
		$password_errors["new"] = "Your new password must be different from your current password.";
	}

	if(count($password_errors)===0)
	{
		$stmt = mysqli_prepare($connect,"UPDATE member SET member_password=? WHERE member_id=? AND member_isDelete=0");
		if(!$stmt)
		{
			$password_errors["general"] = "The password could not be updated. Please try again.";
		}
		else
		{
			mysqli_stmt_bind_param($stmt,"si",$new_password,$mid);
			mysqli_stmt_execute($stmt);
			$updated = mysqli_stmt_affected_rows($stmt)===1;
			mysqli_stmt_close($stmt);

			if($updated)
			{
				session_regenerate_id(true);
				$_SESSION["change_password_success"] = "Your password was changed successfully.";
				unset($_SESSION["change_password_csrf"]);
				header("location:change_password.php");
				exit();
			}

			$password_errors["general"] = "The password could not be updated. Please try again.";
		}
	}
}

if(isset($_SESSION["change_password_success"]))
{
	$password_success = $_SESSION["change_password_success"];
	unset($_SESSION["change_password_success"]);
}
?>

<!DOCTYPE html>
<html>

<head>
<title>Change Password</title>
<link rel="stylesheet" href="style.css">

<style>
#password-box
{width:520px;
box-sizing:border-box;}

.password-field
{margin-bottom:16px;}

.password-field label
{display:block;
color:#9E0B22;
font-weight:bold;
margin-bottom:6px;}

.password-field input
{width:100%;
box-sizing:border-box;}

.password-error
{display:block;
color:#C8102E;
font-size:0.78em;
font-weight:bold;
margin-top:5px;}

.password-success
{background-color:#E8F5E9;
border:1px solid #4CAF50;
color:#1B5E20;
border-radius:5px;
padding:10px 14px;
text-align:center;}

.password-actions
{display:flex;
justify-content:center;
align-items:center;
gap:10px;
margin-top:20px;}

@media(max-width:600px)
{
	#main,
	#password-box
	{width:94%;}

	.password-actions
	{flex-direction:column;}
}
</style>
</head>

<body>

<div id="header">
<img src="image/logo.png" width="80px" height="80px" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar">
<a href="category.php">Menu</a>
<a href="cart.php">Cart</a>
<a href="dashboard.php">My Dashboard</a>
<a href="order_history.php">Order History</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main">
<h2 class="section-title">Change Password</h2>
<p class="intro">Confirm your current password, then choose a different password for your EasyOrder account.</p>

<div class="form-box" id="password-box">
<?php if($password_success!=="") { ?>
<p class="password-success"><?php echo change_password_h($password_success); ?></p>
<?php } ?>

<?php if(isset($password_errors["general"])) { ?>
<p class="msg"><?php echo change_password_h($password_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="change_password.php" novalidate>
<input type="hidden" name="change_password_csrf" value="<?php echo change_password_h($_SESSION["change_password_csrf"]); ?>">

<div class="password-field">
<label for="old_password">Current Password *</label>
<input type="password" id="old_password" name="old_password" autocomplete="current-password" required>
<?php if(isset($password_errors["old"])) { ?><span class="password-error"><?php echo change_password_h($password_errors["old"]); ?></span><?php } ?>
</div>

<div class="password-field">
<label for="new_password">New Password *</label>
<input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" maxlength="72" required>
<?php if(isset($password_errors["new"])) { ?><span class="password-error"><?php echo change_password_h($password_errors["new"]); ?></span><?php } ?>
</div>

<div class="password-field">
<label for="confirm_password">Confirm New Password *</label>
<input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="72" required>
<?php if(isset($password_errors["confirm"])) { ?><span class="password-error"><?php echo change_password_h($password_errors["confirm"]); ?></span><?php } ?>
</div>

<div class="password-actions">
<input type="submit" name="change_password_btn" value="Change Password">
<a class="btn" href="dashboard.php#profile">Back to Profile</a>
</div>
</form>
</div>
</div>

<footer>
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>
</html>
