<?php
include("dataconnection.php");
include_once("password_reset_helpers.php");

$reset_errors = array();
$reset_request_id = (int)($_SESSION["password_reset_id"] ?? 0);
$reset_member_id = (int)($_SESSION["password_reset_member_id"] ?? 0);
$reset_available = false;
$member_password = "";

if($reset_request_id>0 && $reset_member_id>0)
{
	$stmt = mysqli_prepare($connect,"SELECT m.member_password FROM password_reset pr INNER JOIN member m ON m.member_id=pr.member_id WHERE pr.reset_id=? AND pr.member_id=? AND pr.verified_at IS NOT NULL AND pr.used_at IS NULL AND pr.expires_at>NOW() AND m.member_isDelete=0 LIMIT 1");
	if($stmt)
	{
		mysqli_stmt_bind_param($stmt,"ii",$reset_request_id,$reset_member_id);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		$row = mysqli_fetch_assoc($result);
		mysqli_stmt_close($stmt);
		if($row)
		{
			$reset_available = true;
			$member_password = $row["member_password"];
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
	if($new_password!=="" && easyorder_password_verify($new_password,$member_password))
	{
		$reset_errors["new"] = "Your new password must be different from your current password.";
	}

	if(count($reset_errors)===0)
	{
		$transaction_ok = true;
		$locked_password = "";
		mysqli_begin_transaction($connect);

		$stmt = mysqli_prepare($connect,"SELECT m.member_password FROM password_reset pr INNER JOIN member m ON m.member_id=pr.member_id WHERE pr.reset_id=? AND pr.member_id=? AND pr.verified_at IS NOT NULL AND pr.used_at IS NULL AND pr.expires_at>NOW() AND m.member_isDelete=0 LIMIT 1 FOR UPDATE");
		if(!$stmt)
		{
			$transaction_ok = false;
		}
		else
		{
			mysqli_stmt_bind_param($stmt,"ii",$reset_request_id,$reset_member_id);
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
				$locked_password = $locked_row["member_password"];
			}
		}

		if($transaction_ok && easyorder_password_verify($new_password,$locked_password))
		{
			$transaction_ok = false;
			$reset_errors["new"] = "Your new password must be different from your current password.";
		}

		if($transaction_ok)
		{
			$stmt = mysqli_prepare($connect,"UPDATE member SET member_password=? WHERE member_id=? AND member_isDelete=0");
			if(!$stmt)
			{
				$transaction_ok = false;
			}
			else
			{
				mysqli_stmt_bind_param($stmt,"si",$new_password,$reset_member_id);
				$transaction_ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt)===1;
				mysqli_stmt_close($stmt);
			}
		}

		if($transaction_ok)
		{
			$stmt = mysqli_prepare($connect,"UPDATE password_reset SET used_at=NOW() WHERE reset_id=? AND member_id=? AND used_at IS NULL AND expires_at>NOW()");
			if(!$stmt)
			{
				$transaction_ok = false;
			}
			else
			{
				mysqli_stmt_bind_param($stmt,"ii",$reset_request_id,$reset_member_id);
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password</title>
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
<a href="./">Home</a>
</div>

<div id="main">
<h2 class="section-title">Reset Password</h2>
<p class="intro">Choose a new password for your EasyOrder account.</p>

<div class="recovery-card">
<?php if(!$reset_available) { ?>
<p class="form-message form-message-error">This reset request is invalid or has expired.</p>
<div class="recovery-actions"><a class="btn" href="forgot_password.php">Request New Code</a></div>
<?php } else { ?>

<?php if(isset($reset_errors["general"])) { ?><p class="form-message form-message-error"><?php echo easyorder_reset_h($reset_errors["general"]); ?></p><?php } ?>

<form method="post" action="reset_password.php" novalidate>
<input type="hidden" name="reset_password_csrf" value="<?php echo easyorder_reset_h($reset_csrf); ?>">

<div class="recovery-field">
<label for="new_password">New Password *</label>
<input type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required>
<?php if(isset($reset_errors["new"])) { ?><span class="field-error"><?php echo easyorder_reset_h($reset_errors["new"]); ?></span><?php } ?>
</div>

<div class="recovery-field">
<label for="confirm_password">Confirm New Password *</label>
<input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
<?php if(isset($reset_errors["confirm"])) { ?><span class="field-error"><?php echo easyorder_reset_h($reset_errors["confirm"]); ?></span><?php } ?>
</div>

<div class="recovery-actions">
<input type="submit" name="reset_password_btn" value="Reset Password">
</div>
</form>

<p class="recovery-note">Your new password must be different from your current password.</p>
<?php } ?>
</div>
</div>

<footer>
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>
</html>
