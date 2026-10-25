<?php
session_start();
if(!isset($_SESSION["customer_id"]))
{
	header("location:login.php");
	exit();
}
include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");

$mid = (int)$_SESSION["customer_id"];
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

	$stmt = mysqli_prepare($connect,"SELECT customer_password FROM customer WHERE customer_id=? AND customer_isDelete=0 LIMIT 1");
	mysqli_stmt_bind_param($stmt,"i",$mid);
	mysqli_stmt_execute($stmt);
	$password_result = mysqli_stmt_get_result($stmt);
	$password_row = mysqli_fetch_assoc($password_result);
	mysqli_stmt_close($stmt);

	if(!$password_row || !easyorder_password_verify($old_password,$password_row["customer_password"]))
	{
		$password_errors["old"] = "The current password is incorrect.";
	}
	else if(easyorder_password_verify($new_password,$password_row["customer_password"]))
	{
		$password_errors["new"] = "Your new password must be different from your current password.";
	}

	if(count($password_errors)===0)
	{
		$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_password=? WHERE customer_id=? AND customer_isDelete=0");
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
<html lang="en">

<head>
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<title>Change Password</title>
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<script src="password_ux.js?v=20260926-1" defer></script>

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

#password-box .password-input-wrap
{position:relative;
display:inline-grid;
width:100%;}

#password-box input.has-visibility-toggle
{padding-right:44px;}

#password-box .password-visibility-toggle
{position:absolute;
right:4px;
top:50%;
transform:translateY(-50%);
display:grid;
place-items:center;
width:30px;
height:28px;
padding:0;
border:0;
border-radius:4px;
background:transparent;
color:#94827E;
cursor:pointer;}

#password-box .password-visibility-toggle[hidden]
{display:none;}

#password-box .password-visibility-toggle:hover
{background:#FFF0EE;
color:#A6102D;}

#password-box .password-visibility-toggle:focus-visible
{outline:2px solid #E9AD70;
outline-offset:2px;}

#password-box .password-visibility-toggle svg
{width:21px;
height:21px;
stroke-linecap:round;
stroke-linejoin:round;}

#password-box .password-eye-closed,
#password-box .is-visible .password-eye-open
{display:none;}

#password-box .is-visible .password-eye-closed
{display:block;}

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

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<div id="main" role="main">
<h2 class="section-title">Change Password</h2>
<p class="intro">Confirm your current password, then choose a different password for your EasyOrder account.</p>

<div class="form-box" id="password-box">
<?php if($password_success!=="") { ?>
<p class="password-success" role="status"><?php echo change_password_h($password_success); ?></p>
<?php } ?>

<?php if(isset($password_errors["general"])) { ?>
<p class="msg" role="alert"><?php echo change_password_h($password_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="change_password.php" novalidate>
<input type="hidden" name="change_password_csrf" value="<?php echo change_password_h($_SESSION["change_password_csrf"]); ?>">

<div class="password-field">
<label for="old_password">Current Password *</label>
<div class="password-input-wrap">
<input type="password" id="old_password" name="old_password" autocomplete="current-password"<?php if(isset($password_errors["old"])) { ?> aria-invalid="true" aria-describedby="old_password-error"<?php } ?> required>
<button type="button" class="password-visibility-toggle" data-password-field="old_password" data-password-label="current password" aria-controls="old_password" aria-label="Show current password" aria-pressed="false" hidden>
<svg class="password-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="change-eye-gap-old_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#change-eye-gap-old_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg>
</button>
</div>
<?php if(isset($password_errors["old"])) { ?><span class="password-error" id="old_password-error"><?php echo change_password_h($password_errors["old"]); ?></span><?php } ?>
</div>

<div class="password-field">
<label for="new_password">New Password *</label>
<div class="password-input-wrap">
<input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" maxlength="72" data-password-strength="change-password-strength" aria-describedby="change-password-strength<?php if(isset($password_errors["new"])) echo " new_password-error"; ?>"<?php if(isset($password_errors["new"])) { ?> aria-invalid="true"<?php } ?> required>
<button type="button" class="password-visibility-toggle" data-password-field="new_password" data-password-label="new password" aria-controls="new_password" aria-label="Show new password" aria-pressed="false" hidden>
<svg class="password-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="change-eye-gap-new_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#change-eye-gap-new_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg>
</button>
</div>
<small id="change-password-strength" class="password-feedback" data-state="empty" aria-live="polite">Use 8 or more characters.</small>
<?php if(isset($password_errors["new"])) { ?><span class="password-error" id="new_password-error"><?php echo change_password_h($password_errors["new"]); ?></span><?php } ?>
</div>

<div class="password-field">
<label for="confirm_password">Confirm New Password *</label>
<div class="password-input-wrap">
<input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="72" data-password-confirm="new_password" data-password-match="change-password-match" aria-describedby="change-password-match<?php if(isset($password_errors["confirm"])) echo " confirm_password-error"; ?>"<?php if(isset($password_errors["confirm"])) { ?> aria-invalid="true"<?php } ?> required>
<button type="button" class="password-visibility-toggle" data-password-field="confirm_password" data-password-label="password confirmation" aria-controls="confirm_password" aria-label="Show password confirmation" aria-pressed="false" hidden>
<svg class="password-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="change-eye-gap-confirm_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#change-eye-gap-confirm_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg>
</button>
</div>
<small id="change-password-match" class="password-feedback" data-state="empty" aria-live="polite">Enter the same password again.</small>
<?php if(isset($password_errors["confirm"])) { ?><span class="password-error" id="confirm_password-error"><?php echo change_password_h($password_errors["confirm"]); ?></span><?php } ?>
</div>

<div class="password-actions">
<input type="submit" name="change_password_btn" value="Change Password">
<a class="btn" href="profile.php#profile">Back to Profile</a>
</div>
</form>
</div>
</div>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

<script>
(function()
{
	document.querySelectorAll('#password-box .password-visibility-toggle').forEach(function(button)
	{
		const input=document.getElementById(button.dataset.passwordField);
		if(!input) return;
		button.hidden=false;
		input.classList.add('has-visibility-toggle');
		button.addEventListener('click',function()
		{
			const visible=input.type==='password';
			input.type=visible ? 'text' : 'password';
			button.classList.toggle('is-visible',visible);
			button.setAttribute('aria-label',(visible ? 'Hide ' : 'Show ')+button.dataset.passwordLabel);
			button.setAttribute('aria-pressed',String(visible));
			input.focus({preventScroll:true});
		});
	});

	const firstError=document.querySelector('form [aria-invalid="true"]');
	if(firstError) firstError.focus();
})();
</script>

</body>
</html>
