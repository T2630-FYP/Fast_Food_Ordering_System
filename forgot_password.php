<?php
include("dataconnection.php");
include_once("password_reset_helpers.php");

$forgot_errors = array();
$submitted_email = "";
$forgot_csrf = easyorder_reset_csrf("forgot_password_csrf");

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["forgot_password_btn"]))
{
	$submitted_email = strtolower(trim((string)($_POST["member_email"] ?? "")));
	$submitted_token = (string)($_POST["forgot_password_csrf"] ?? "");

	if(!easyorder_reset_csrf_valid("forgot_password_csrf",$submitted_token))
	{
		$forgot_errors["general"] = "Your request has expired. Please refresh the page and try again.";
	}
	else if(!filter_var($submitted_email,FILTER_VALIDATE_EMAIL) || strlen($submitted_email)>100)
	{
		$forgot_errors["email"] = "Enter a valid email address.";
	}
	else
	{
		if(!easyorder_create_password_reset($connect,$submitted_email))
		{
			$forgot_errors["general"] = "We could not process your request right now. Please try again.";
		}

		if(count($forgot_errors)===0)
		{
			easyorder_clear_verified_reset();
			$_SESSION["reset_request_email"] = $submitted_email;
			$_SESSION["reset_request_notice"] = "If an active EasyOrder account matches that email address, a six-digit verification code has been sent. The code expires in 10 minutes.";
			unset($_SESSION["forgot_password_csrf"],$_SESSION["verify_reset_csrf"]);
			header("location:verify_reset_code.php");
			exit();
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password</title>
<link rel="stylesheet" href="style.css?v=20260915-2">
</head>
<body>

<div id="header">
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar">
<a href="login.php">Login</a>
<a href="register.php">Sign Up</a>
<a href="index.html">Home</a>
</div>

<div id="main">
<h2 class="section-title">Forgot Password</h2>
<p class="intro">Enter the email address registered with your EasyOrder account.</p>

<div class="recovery-card">
<?php if(isset($forgot_errors["general"])) { ?>
<p class="form-message form-message-error"><?php echo easyorder_reset_h($forgot_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="forgot_password.php" novalidate>
<input type="hidden" name="forgot_password_csrf" value="<?php echo easyorder_reset_h($forgot_csrf); ?>">

<div class="recovery-field">
<label for="member_email">Registered Email *</label>
<input type="email" id="member_email" name="member_email" maxlength="100" autocomplete="email" value="<?php echo easyorder_reset_h($submitted_email); ?>" placeholder="e.g. customer@email.com" required>
<?php if(isset($forgot_errors["email"])) { ?><span class="field-error"><?php echo easyorder_reset_h($forgot_errors["email"]); ?></span><?php } ?>
</div>

<div class="recovery-actions">
<input type="submit" name="forgot_password_btn" value="Send Verification Code">
<a class="btn-secondary" href="login.php">Back to Login</a>
</div>
</form>

<p class="recovery-note">For account security, the confirmation message is the same whether or not the email is registered.</p>
</div>
</div>

<footer>
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>
</html>
