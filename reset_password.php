<?php
include("dataconnection.php");
require_once("customer_navigation.php");
include_once("password_reset_helpers.php");

$reset_errors = array();
$reset_request_id = (int)($_SESSION["password_reset_id"] ?? 0);
$reset_customer_id = (int)($_SESSION["password_reset_customer_id"] ?? 0);
$reset_available = false;
$customer_password = "";

if($reset_request_id>0 && $reset_customer_id>0)
{
	$stmt = mysqli_prepare($connect,"SELECT m.customer_password FROM password_reset pr INNER JOIN customer m ON m.customer_id=pr.customer_id WHERE pr.reset_id=? AND pr.customer_id=? AND pr.verified_at IS NOT NULL AND pr.used_at IS NULL AND pr.expires_at>NOW() AND m.customer_isDelete=0 LIMIT 1");
	if($stmt)
	{
		mysqli_stmt_bind_param($stmt,"ii",$reset_request_id,$reset_customer_id);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		$row = mysqli_fetch_assoc($result);
		mysqli_stmt_close($stmt);
		if($row)
		{
			$reset_available = true;
			$customer_password = $row["customer_password"];
		}
	}
}

if(!$reset_available)
{
	easyorder_clear_verified_reset();
}

$reset_csrf = easyorder_reset_csrf("reset_password_csrf");

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["reset_password_btn"]) && $reset_available)
{
	$submitted_token = (string)($_POST["reset_password_csrf"] ?? "");
	$new_password = (string)($_POST["new_password"] ?? "");
	$confirm_password = (string)($_POST["confirm_password"] ?? "");

	if(!easyorder_reset_csrf_valid("reset_password_csrf",$submitted_token))
	{
		$reset_errors["general"] = "Your password form has expired. Please refresh the page and try again.";
	}
	if(strlen($new_password)<8 || strlen($new_password)>72)
	{
		$reset_errors["new"] = "Use a new password containing 8 to 72 characters.";
	}
	if($confirm_password==="" || !hash_equals($new_password,$confirm_password))
	{
		$reset_errors["confirm"] = "The new password and confirmation do not match.";
	}
	if($new_password!=="" && easyorder_password_verify($new_password,$customer_password))
	{
		$reset_errors["new"] = "Your new password must be different from your current password.";
	}

	if(count($reset_errors)===0)
	{
		$transaction_ok = true;
		$locked_password = "";
		mysqli_begin_transaction($connect);

		$stmt = mysqli_prepare($connect,"SELECT m.customer_password FROM password_reset pr INNER JOIN customer m ON m.customer_id=pr.customer_id WHERE pr.reset_id=? AND pr.customer_id=? AND pr.verified_at IS NOT NULL AND pr.used_at IS NULL AND pr.expires_at>NOW() AND m.customer_isDelete=0 LIMIT 1 FOR UPDATE");
		if(!$stmt)
		{
			$transaction_ok = false;
		}
		else
		{
			mysqli_stmt_bind_param($stmt,"ii",$reset_request_id,$reset_customer_id);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$locked_row = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);
			if(!$locked_row)
			{
				$transaction_ok = false;
			}
			else
			{
				$locked_password = $locked_row["customer_password"];
			}
		}

		if($transaction_ok && easyorder_password_verify($new_password,$locked_password))
		{
			$transaction_ok = false;
			$reset_errors["new"] = "Your new password must be different from your current password.";
		}

		if($transaction_ok)
		{
			$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_password=? WHERE customer_id=? AND customer_isDelete=0");
			if(!$stmt)
			{
				$transaction_ok = false;
			}
			else
			{
				mysqli_stmt_bind_param($stmt,"si",$new_password,$reset_customer_id);
				$transaction_ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt)===1;
				mysqli_stmt_close($stmt);
			}
		}

		if($transaction_ok)
		{
			$stmt = mysqli_prepare($connect,"UPDATE password_reset SET used_at=NOW() WHERE reset_id=? AND customer_id=? AND used_at IS NULL AND expires_at>NOW()");
			if(!$stmt)
			{
				$transaction_ok = false;
			}
			else
			{
				mysqli_stmt_bind_param($stmt,"ii",$reset_request_id,$reset_customer_id);
				$transaction_ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt)===1;
				mysqli_stmt_close($stmt);
			}
		}

		if($transaction_ok)
		{
			mysqli_commit($connect);
			easyorder_clear_verified_reset();
			unset($_SESSION["reset_request_email"],$_SESSION["reset_password_csrf"]);
			$_SESSION["password_reset_success"] = "Your password has been reset successfully. You can now log in with your new password.";
			session_regenerate_id(true);
			header("location:login.php");
			exit();
		}

		mysqli_rollback($connect);
		if(!isset($reset_errors["new"]))
		{
			$reset_errors["general"] = "This reset request is no longer valid. Please request a new verification code.";
			$reset_available = false;
			easyorder_clear_verified_reset();
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password</title>
<link rel="stylesheet" href="style.css?v=20261024-shell1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<script src="customer_navigation.js?v=20261024-shell1" defer></script>
<script src="password_ux.js?v=20260926-1" defer></script>
<style>
.recovery-card .password-input-wrap
{position:relative;
display:inline-grid;
width:100%;}

.recovery-card input.has-visibility-toggle
{padding-right:44px;}

.recovery-card .password-visibility-toggle
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

.recovery-card .password-visibility-toggle[hidden]
{display:none;}

.recovery-card .password-visibility-toggle:hover
{background:#FFF0EE;
color:#A6102D;}

.recovery-card .password-visibility-toggle:focus-visible
{outline:2px solid #E9AD70;
outline-offset:2px;}

.recovery-card .password-visibility-toggle svg
{width:21px;
height:21px;
stroke-linecap:round;
stroke-linejoin:round;}

.recovery-card .password-eye-closed,
.recovery-card .is-visible .password-eye-open
{display:none;}

.recovery-card .is-visible .password-eye-closed
{display:block;}
</style>
</head>
<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<div id="main" role="main">
<h2 class="section-title">Reset Password</h2>
<p class="intro">Choose a new password for your EasyOrder account.</p>

<div class="recovery-card">
<?php if(!$reset_available) { ?>
<p class="form-message form-message-error" role="alert">This reset request is invalid or has expired.</p>
<div class="recovery-actions"><a class="btn" href="forgot_password.php">Request New Code</a></div>
<?php } else { ?>

<?php if(isset($reset_errors["general"])) { ?><p class="form-message form-message-error" role="alert"><?php echo easyorder_reset_h($reset_errors["general"]); ?></p><?php } ?>

<form method="post" action="reset_password.php" novalidate>
<input type="hidden" name="reset_password_csrf" value="<?php echo easyorder_reset_h($reset_csrf); ?>">

<div class="recovery-field">
<label for="new_password">New Password *</label>
<div class="password-input-wrap">
<input type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" data-password-strength="reset-password-strength" aria-describedby="reset-password-strength<?php if(isset($reset_errors["new"])) echo " new_password-error"; ?>"<?php if(isset($reset_errors["new"])) { ?> aria-invalid="true"<?php } ?> required>
<button type="button" class="password-visibility-toggle" data-password-field="new_password" data-password-label="new password" aria-controls="new_password" aria-label="Show new password" aria-pressed="false" hidden>
<svg class="password-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="reset-eye-gap-new_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#reset-eye-gap-new_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg>
</button>
</div>
<small id="reset-password-strength" class="password-feedback" data-state="empty" aria-live="polite">Use 8 or more characters.</small>
<?php if(isset($reset_errors["new"])) { ?><span class="field-error" id="new_password-error"><?php echo easyorder_reset_h($reset_errors["new"]); ?></span><?php } ?>
</div>

<div class="recovery-field">
<label for="confirm_password">Confirm New Password *</label>
<div class="password-input-wrap">
<input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" data-password-confirm="new_password" data-password-match="reset-password-match" aria-describedby="reset-password-match<?php if(isset($reset_errors["confirm"])) echo " confirm_password-error"; ?>"<?php if(isset($reset_errors["confirm"])) { ?> aria-invalid="true"<?php } ?> required>
<button type="button" class="password-visibility-toggle" data-password-field="confirm_password" data-password-label="password confirmation" aria-controls="confirm_password" aria-label="Show password confirmation" aria-pressed="false" hidden>
<svg class="password-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="reset-eye-gap-confirm_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#reset-eye-gap-confirm_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg>
</button>
</div>
<small id="reset-password-match" class="password-feedback" data-state="empty" aria-live="polite">Enter the same password again.</small>
<?php if(isset($reset_errors["confirm"])) { ?><span class="field-error" id="confirm_password-error"><?php echo easyorder_reset_h($reset_errors["confirm"]); ?></span><?php } ?>
</div>

<div class="recovery-actions">
<input type="submit" name="reset_password_btn" value="Reset Password">
</div>
</form>

<p class="recovery-note">Your new password must be different from your current password.</p>
<?php } ?>
</div>
</div>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

<script>
(function()
{
	document.querySelectorAll('.recovery-card .password-visibility-toggle').forEach(function(button)
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
