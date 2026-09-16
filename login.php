<?php
include("dataconnection.php");

if(isset($_SESSION["member_id"]) && $_SERVER["REQUEST_METHOD"]!=="POST")
{
	header("location:dashboard.php");
	exit();
}

$login_errors = array();
$login_email = "";
$login_notice = "";

if(isset($_SESSION["password_reset_success"]))
{
	$login_notice = (string)$_SESSION["password_reset_success"];
	unset($_SESSION["password_reset_success"]);
}
else if(isset($_SESSION["registration_success"]))
{
	$login_notice = (string)$_SESSION["registration_success"];
	unset($_SESSION["registration_success"]);
}

if(!isset($_SESSION["login_csrf"]))
{
	$_SESSION["login_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["loginbtn"]))
{
	$login_email = strtolower(trim((string)($_POST["user_email"] ?? "")));
	$password = (string)($_POST["user_password"] ?? "");
	$submitted_token = (string)($_POST["login_csrf"] ?? "");

	if($submitted_token==="" || !hash_equals($_SESSION["login_csrf"],$submitted_token))
	{
		$login_errors["general"] = "Your login form has expired. Please refresh the page and try again.";
	}
	if($login_email==="")
	{
		$login_errors["email"] = "Enter your registered email address.";
	}
	else if(!filter_var($login_email,FILTER_VALIDATE_EMAIL) || strlen($login_email)>100)
	{
		$login_errors["email"] = "Enter a valid email address.";
	}
	if($password==="")
	{
		$login_errors["password"] = "Enter your password.";
	}

	if(count($login_errors)===0)
	{
		$row = false;
		$stmt = mysqli_prepare($connect,"SELECT member_id,member_name,member_password FROM member WHERE member_email=? AND member_isDelete=0 LIMIT 1");
		if($stmt)
		{
			mysqli_stmt_bind_param($stmt,"s",$login_email);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);
		}

		if($row && easyorder_password_verify($password,$row["member_password"]))
		{
			$_SESSION["member_id"] = (int)$row["member_id"];
			$_SESSION["member_name"] = $row["member_name"];
			unset($_SESSION["login_csrf"]);
			session_regenerate_id(true);
			header("location:dashboard.php");
			exit();
		}

		$login_errors["general"] = "Invalid email or password. Please try again.";
	}
}

function login_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Member Login</title>
<link rel="stylesheet" href="style.css?v=20260916-1">
</head>
<body>

<div id="header">
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar">
<a href="./">Home</a>
<a href="register.php">Sign Up</a>
</div>

<main id="main">
<h2 class="section-title">Member Login</h2>
<p class="intro">Log in to order food, manage your profile and review your orders.</p>

<div class="entry-card entry-card-narrow">
<?php if($login_notice!=="") { ?>
<p class="form-message form-message-success" role="status"><?php echo login_h($login_notice); ?></p>
<?php } ?>

<?php if(isset($login_errors["general"])) { ?>
<p class="form-message form-message-error" role="alert"><?php echo login_h($login_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="login.php" novalidate>
<input type="hidden" name="login_csrf" value="<?php echo login_h($_SESSION["login_csrf"]); ?>">

<div class="entry-field">
<label for="user_email">Email *</label>
<input type="email" id="user_email" name="user_email" maxlength="100" autocomplete="email" value="<?php echo login_h($login_email); ?>" placeholder="e.g. customer@email.com" required>
<?php if(isset($login_errors["email"])) { ?><span class="field-error"><?php echo login_h($login_errors["email"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="user_password">Password *</label>
<input type="password" id="user_password" name="user_password" maxlength="72" autocomplete="current-password" placeholder="Enter your password" required>
<?php if(isset($login_errors["password"])) { ?><span class="field-error"><?php echo login_h($login_errors["password"]); ?></span><?php } ?>
<a class="forgot-password-link" href="forgot_password.php">Forgot Password?</a>
</div>

<div class="entry-actions">
<input type="submit" name="loginbtn" value="Login">
</div>
</form>
</div>

<p class="login-note">Don't have an account? <a href="register.php">Sign up here</a>.</p>
</main>

<footer>
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>
</html>
