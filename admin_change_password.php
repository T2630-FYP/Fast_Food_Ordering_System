<?php

// Only a signed-in administrator can change an administrator password.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("Location: admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

$current_admin_id = (string)$_SESSION["admin_id"];
$password_errors = array();

// Protect password changes with a session-bound token.
if(empty($_SESSION["admin_password_csrf"]))
{
	$_SESSION["admin_password_csrf"] = bin2hex(random_bytes(32));
}
$password_csrf = $_SESSION["admin_password_csrf"];

// Escape feedback before rendering it in the account page.
function admin_password_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Validate the current password and update only the account named by the session.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	$current_password = (string)($_POST["current_password"] ?? "");
	$new_password = (string)($_POST["new_password"] ?? "");
	$confirm_password = (string)($_POST["confirm_password"] ?? "");

	if($submitted_token==="" || !hash_equals($password_csrf,$submitted_token))
	{
		$password_errors["general"] = "The password form has expired. Please refresh the page and try again.";
	}
	if($current_password==="")
	{
		$password_errors["current"] = "Enter your current password.";
	}
	if(strlen($new_password)<8 || strlen($new_password)>50)
	{
		$password_errors["new"] = "Use a new password containing 8 to 50 characters.";
	}
	if($confirm_password==="" || !hash_equals($new_password,$confirm_password))
	{
		$password_errors["confirm"] = "The new password and confirmation do not match.";
	}

	$password_stmt = mysqli_prepare($connect,"SELECT staff_password FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
	mysqli_stmt_bind_param($password_stmt,"s",$current_admin_id);
	mysqli_stmt_execute($password_stmt);
	$password_result = mysqli_stmt_get_result($password_stmt);
	$password_row = mysqli_fetch_assoc($password_result) ?: null;
	mysqli_stmt_close($password_stmt);

	if(!$password_row || !easyorder_password_verify($current_password,$password_row["staff_password"]))
	{
		$password_errors["current"] = "The current password is incorrect.";
	}
	else if($new_password!=="" && easyorder_password_verify($new_password,$password_row["staff_password"]))
	{
		$password_errors["new"] = "Your new password must be different from your current password.";
	}

	if(count($password_errors)===0)
	{
		$save_stmt = mysqli_prepare($connect,"UPDATE staff SET staff_password=? WHERE staff_id=? AND staff_isDelete=0");
		mysqli_stmt_bind_param($save_stmt,"ss",$new_password,$current_admin_id);
		$password_saved = mysqli_stmt_execute($save_stmt) && mysqli_stmt_affected_rows($save_stmt)===1;
		mysqli_stmt_close($save_stmt);

		if($password_saved)
		{
			// Rotate the session identifier after a sensitive account change.
			session_regenerate_id(true);
			unset($_SESSION["admin_password_csrf"]);
			$_SESSION["admin_password_flash"] = "Your administrator password was changed successfully.";
			header("Location: admin_change_password.php",true,303);
			exit();
		}
		$password_errors["general"] = "The password could not be updated. Please try again.";
	}
}

$password_success = (string)($_SESSION["admin_password_flash"] ?? "");
unset($_SESSION["admin_password_flash"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Change Admin Password - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_change_password.php"); ?>

	<!-- Password page heading keeps this sensitive action separate from profile details. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">MY ADMIN ACCOUNT</p>
			<h1>Change Password</h1>
			<p>Confirm your current password before setting a new administrator password.</p>
		</div>
		<a class="admin-secondary-link" href="admin_profile.php">Back to Profile</a>
	</section>

	<?php if($password_success!==""): ?>
		<div class="admin-alert admin-alert-success" role="status"><?php echo admin_password_html($password_success); ?></div>
	<?php endif; ?>
	<?php if(isset($password_errors["general"])): ?>
		<div class="admin-alert admin-alert-error" role="alert"><?php echo admin_password_html($password_errors["general"]); ?></div>
	<?php endif; ?>

	<!-- Password values are accepted only by POST and are never echoed back into the page. -->
	<section class="admin-account-panel admin-password-panel">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow">SECURITY</p>
				<h2>Update your password</h2>
			</div>
		</header>

		<form class="admin-account-form" method="post" action="admin_change_password.php" novalidate>
			<input type="hidden" name="csrf_token" value="<?php echo admin_password_html($password_csrf); ?>">

			<div class="admin-password-fields">
				<label class="admin-form-field">
					<span>Current password</span>
					<input type="password" name="current_password" autocomplete="current-password" required>
					<?php if(isset($password_errors["current"])): ?><small class="admin-field-error"><?php echo admin_password_html($password_errors["current"]); ?></small><?php endif; ?>
				</label>
				<label class="admin-form-field">
					<span>New password</span>
					<input type="password" name="new_password" autocomplete="new-password" minlength="8" maxlength="50" required>
					<?php if(isset($password_errors["new"])): ?><small class="admin-field-error"><?php echo admin_password_html($password_errors["new"]); ?></small><?php else: ?><small>Use 8 to 50 characters.</small><?php endif; ?>
				</label>
				<label class="admin-form-field">
					<span>Confirm new password</span>
					<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="50" required>
					<?php if(isset($password_errors["confirm"])): ?><small class="admin-field-error"><?php echo admin_password_html($password_errors["confirm"]); ?></small><?php endif; ?>
				</label>
			</div>

			<div class="admin-account-form-actions">
				<button type="submit">Change Password</button>
				<a href="admin_profile.php">Cancel</a>
			</div>
		</form>
	</section>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
