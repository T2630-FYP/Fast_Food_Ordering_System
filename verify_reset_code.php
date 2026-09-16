<?php
include("dataconnection.php");
include_once("password_reset_helpers.php");

$verify_errors = array();
$submitted_email = strtolower(trim((string)($_SESSION["reset_request_email"] ?? "")));
$submitted_code = "";
$request_notice = (string)($_SESSION["reset_request_notice"] ?? "");
unset($_SESSION["reset_request_notice"]);
$verify_csrf = easyorder_reset_csrf("verify_reset_csrf");

if(!filter_var($submitted_email,FILTER_VALIDATE_EMAIL) || strlen($submitted_email)>100)
{
	header("location:forgot_password.php");
	exit();
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["resend_reset_btn"]))
{
	$submitted_token = (string)($_POST["verify_reset_csrf"] ?? "");
	if(!easyorder_reset_csrf_valid("verify_reset_csrf",$submitted_token))
	{
		$verify_errors["general"] = "Your verification form has expired. Please refresh the page and try again.";
	}
	else if(!easyorder_create_password_reset($connect,$submitted_email))
	{
		$verify_errors["general"] = "We could not resend the verification code right now. Please try again.";
	}
	else
	{
		easyorder_clear_verified_reset();
		$_SESSION["reset_request_notice"] = "Verification Code has been resent.";
		unset($_SESSION["verify_reset_csrf"]);
		header("location:verify_reset_code.php");
		exit();
	}
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["verify_reset_btn"]))
{
	$submitted_code = trim((string)($_POST["verification_code"] ?? ""));
	$submitted_token = (string)($_POST["verify_reset_csrf"] ?? "");

	if(!easyorder_reset_csrf_valid("verify_reset_csrf",$submitted_token))
	{
		$verify_errors["general"] = "Your verification form has expired. Please refresh the page and try again.";
	}
	if(!preg_match("/^\d{6}$/",$submitted_code))
	{
		$verify_errors["code"] = "Enter the six-digit verification code.";
	}

	if(count($verify_errors)===0)
	{
		$member = false;
		$stmt = mysqli_prepare($connect,"SELECT member_id FROM member WHERE member_email=? AND member_isDelete=0 LIMIT 1");
		if($stmt)
		{
			mysqli_stmt_bind_param($stmt,"s",$submitted_email);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$member = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);
		}

		$reset_request = false;
		if($member)
		{
			$member_id = (int)$member["member_id"];
			$stmt = mysqli_prepare($connect,"SELECT reset_id,reset_code_hash,attempt_count FROM password_reset WHERE member_id=? AND used_at IS NULL AND verified_at IS NULL AND expires_at>NOW() ORDER BY reset_id DESC LIMIT 1");
			if($stmt)
			{
				mysqli_stmt_bind_param($stmt,"i",$member_id);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				$reset_request = mysqli_fetch_assoc($result);
				mysqli_stmt_close($stmt);
			}
		}

		$code_valid = $reset_request && (int)$reset_request["attempt_count"]<5 && hash_equals($reset_request["reset_code_hash"],easyorder_reset_code_hash($submitted_code));

		if(!$code_valid)
		{
			if($reset_request)
			{
				$reset_id = (int)$reset_request["reset_id"];
				$next_attempt = (int)$reset_request["attempt_count"]+1;
				if($next_attempt>=5)
				{
					$stmt = mysqli_prepare($connect,"UPDATE password_reset SET attempt_count=?,used_at=NOW() WHERE reset_id=? AND used_at IS NULL");
				}
				else
				{
					$stmt = mysqli_prepare($connect,"UPDATE password_reset SET attempt_count=? WHERE reset_id=? AND used_at IS NULL");
				}
				if($stmt)
				{
					mysqli_stmt_bind_param($stmt,"ii",$next_attempt,$reset_id);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_close($stmt);
				}
			}

			$verify_errors["general"] = "The verification code is invalid or has expired. Request a new code if needed.";
		}
		else
		{
			$reset_id = (int)$reset_request["reset_id"];
			$stmt = mysqli_prepare($connect,"UPDATE password_reset SET verified_at=NOW() WHERE reset_id=? AND used_at IS NULL AND verified_at IS NULL AND expires_at>NOW()");
			if($stmt)
			{
				mysqli_stmt_bind_param($stmt,"i",$reset_id);
				mysqli_stmt_execute($stmt);
				$verified = mysqli_stmt_affected_rows($stmt)===1;
				mysqli_stmt_close($stmt);
			}
			else
			{
				$verified = false;
			}

			if($verified)
			{
				easyorder_clear_verified_reset();
				$_SESSION["password_reset_id"] = $reset_id;
				$_SESSION["password_reset_member_id"] = (int)$member["member_id"];
				$_SESSION["password_reset_verified_at"] = time();
				$_SESSION["reset_request_email"] = $submitted_email;
				unset($_SESSION["verify_reset_csrf"],$_SESSION["reset_password_csrf"]);
				session_regenerate_id(true);
				header("location:reset_password.php");
				exit();
			}

			$verify_errors["general"] = "The verification code is invalid or has expired. Request a new code if needed.";
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Reset Code</title>
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
<h2 class="section-title">Verify Your Code</h2>
<p class="intro">Enter the six-digit code from the EasyOrder password reset email.</p>

<div class="recovery-card">
<?php if($request_notice!=="") { ?><p class="form-message form-message-info"><?php echo easyorder_reset_h($request_notice); ?></p><?php } ?>
<?php if(isset($verify_errors["general"])) { ?><p class="form-message form-message-error"><?php echo easyorder_reset_h($verify_errors["general"]); ?></p><?php } ?>

<form method="post" action="verify_reset_code.php" novalidate>
<input type="hidden" name="verify_reset_csrf" value="<?php echo easyorder_reset_h($verify_csrf); ?>">

<div class="recovery-field">
<label for="verification_code">Verification Code *</label>
<input class="verification-code-input" type="text" id="verification_code" name="verification_code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" value="<?php echo easyorder_reset_h($submitted_code); ?>" placeholder="000000" required>
<?php if(isset($verify_errors["code"])) { ?><span class="field-error"><?php echo easyorder_reset_h($verify_errors["code"]); ?></span><?php } ?>
</div>

<div class="recovery-actions">
<input type="submit" name="verify_reset_btn" value="Verify Code">
<button class="btn-secondary-action" type="submit" name="resend_reset_btn" value="1" formnovalidate>Request New Code</button>
</div>
</form>

<p class="recovery-note">Code requested for <strong><?php echo easyorder_reset_h($submitted_email); ?></strong>. <a href="forgot_password.php">Use a different email</a>.</p>
</div>
</div>

<footer>
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>
</html>
