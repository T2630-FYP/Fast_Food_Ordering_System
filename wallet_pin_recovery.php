<?php
// Wallet PIN recovery is separate from customer login password recovery.
session_start();
if(!isset($_SESSION["customer_id"]))
{
	require_once(__DIR__."/customer_auth_helpers.php");
	easyorder_redirect_customer_to_login();
}

include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");
require_once("wallet_helpers.php");

$mid = (int)$_SESSION["customer_id"];
$recovery_error = "";
$recovery_error_code = "validation_error";
$recovery_json = $_SERVER["REQUEST_METHOD"]==="POST"
	&& strpos($_SERVER["HTTP_ACCEPT"] ?? "","application/json")!==false;
$wallet = easyorder_wallet_load($connect,$mid);
if(!$wallet)
{
	header("location:wallet.php");
	exit();
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["change_wallet_pin"]))
{
	$submitted_token = (string)($_POST["wallet_recovery_token"] ?? "");
	$stored_token = (string)($_SESSION["wallet_recovery_token"] ?? "");
	$valid_token = $submitted_token!=="" && $stored_token!=="" && hash_equals($stored_token,$submitted_token);
	unset($_SESSION["wallet_recovery_token"]);

	$account_password = (string)($_POST["account_password"] ?? "");
	$new_pin = (string)($_POST["new_wallet_pin"] ?? "");
	$confirm_pin = (string)($_POST["confirm_wallet_pin"] ?? "");

	if(!$valid_token)
	{
		$recovery_error = "This recovery request was already submitted or has expired. Please try again.";
	}
	else if($account_password==="")
	{
		$recovery_error = "Enter your customer account password to verify your identity.";
	}
	else if(!easyorder_wallet_pin_valid($new_pin))
	{
		$recovery_error = "Create a new 6-digit Wallet PIN.";
	}
	else if($new_pin!==$confirm_pin)
	{
		$recovery_error = "The Wallet PIN confirmation does not match.";
	}
	else
	{
		$transaction_started = false;
		try
		{
			mysqli_begin_transaction($connect);
			$transaction_started = true;

			$stmt = mysqli_prepare($connect,"SELECT customer_password FROM customer WHERE customer_id=? AND customer_isDelete=0 FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"i",$mid);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$customer = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);
			if(!$customer || !easyorder_password_verify($account_password,$customer["customer_password"]))
			{
				throw new Exception("The customer account password is incorrect.");
			}

			$locked_wallet = easyorder_wallet_load($connect,$mid,true);
			if(!$locked_wallet)
			{
				throw new Exception("The wallet could not be found.");
			}
			$stored_pin = (string)$locked_wallet["wallet_pin_hash"];
			$same_pin = easyorder_wallet_pin_valid($stored_pin)
				? hash_equals($stored_pin,$new_pin)
				: password_verify($new_pin,$stored_pin);
			if($same_pin)
			{
				$recovery_error_code = "same_pin";
				throw new Exception("Your new Wallet PIN must be different from your current PIN.");
			}
			$stmt = mysqli_prepare($connect,"UPDATE wallets SET wallet_pin_hash=? WHERE wallet_id=? AND wallet_customer=?");
			mysqli_stmt_bind_param($stmt,"sii",$new_pin,$locked_wallet["wallet_id"],$mid);
			if(!mysqli_stmt_execute($stmt))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The Wallet PIN could not be changed.");
			}
			mysqli_stmt_close($stmt);

			mysqli_commit($connect);
			$transaction_started = false;
			easyorder_wallet_lock();
			$_SESSION["wallet_success"] = "Your Wallet PIN has been changed. Open the wallet with your new PIN.";
			if($recovery_json)
			{
				header("Content-Type: application/json; charset=UTF-8");
				header("Cache-Control: private, no-store");
				echo json_encode(array("ok"=>true,"redirect"=>"wallet.php"));
				exit();
			}
			header("location:wallet.php");
			exit();
		}
		catch(Throwable $error)
		{
			if($transaction_started)
			{
				mysqli_rollback($connect);
			}
			$recovery_error = $error->getMessage();
		}
	}
}

if(!isset($_SESSION["wallet_recovery_token"]))
{
	$_SESSION["wallet_recovery_token"] = bin2hex(random_bytes(32));
}
$wallet_recovery_token = $_SESSION["wallet_recovery_token"];
if($recovery_json && $recovery_error!=="")
{
	header("Content-Type: application/json; charset=UTF-8");
	header("Cache-Control: private, no-store");
	http_response_code(422);
	echo json_encode(array("ok"=>false,"code"=>$recovery_error_code,"message"=>$recovery_error,"token"=>$wallet_recovery_token));
	exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wallet PIN Recovery</title>
<link rel="stylesheet" href="public.css?v=20261024-shell1">
<link rel="stylesheet" href="customer.css?v=20261102-wallet-pin2">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<script src="customer.js?v=20261102-wallet-pin2" defer></script>
</head>
<body>
<?php easyorder_render_customer_header(); ?>
<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="wallet-page eo-wallet-pin-recovery">
<div class="wallet-page-heading"><div><h1 class="eo-wallet-pin-title">Change Wallet PIN</h1><p class="intro">Verify your customer identity before replacing the Wallet PIN.</p></div><a class="eo-wallet-pin-back" href="wallet.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M19 12H5m6-6-6 6 6 6"/></svg>Back to Wallet</a></div>
<div id="wallet-pin-feedback" class="checkout-message checkout-message-error" role="alert" tabindex="-1"<?php if($recovery_error==="") { ?> hidden<?php } ?>><?php echo easyorder_wallet_html($recovery_error); ?></div>

<section class="wallet-access-card" aria-labelledby="recovery-title">
<h3 id="recovery-title">Set a New Wallet PIN</h3>
<p>This changes only the Wallet PIN. It does not change your customer login password.</p>
<form class="wallet-form" method="post" action="wallet_pin_recovery.php" autocomplete="off" data-wallet-pin-recovery>
<input type="hidden" name="wallet_recovery_token" value="<?php echo easyorder_wallet_html($wallet_recovery_token); ?>">
<div class="wallet-field"><label for="account-password">Customer Account Password</label><input id="account-password" type="password" name="account_password" required autocomplete="current-password"></div>
<div class="wallet-field-row">
<div class="wallet-field"><label for="new-wallet-pin">New 6-Digit Wallet PIN</label><div class="eo-wallet-pin-wrap"><input id="new-wallet-pin" type="password" name="new_wallet_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password"><button type="button" class="wallet-activation-visibility" data-target="new-wallet-pin" aria-label="Show new Wallet PIN" aria-pressed="false" hidden><svg class="wallet-visibility-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g class="wallet-visibility-eye"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.75"/></g><g class="wallet-visibility-eye-off"><path d="m3 3 18 18M10.7 6.1A11 11 0 0 1 12 6c6.4 0 10 6 10 6a16.5 16.5 0 0 1-3.4 3.8M6.1 6.9C3.5 8.6 2 12 2 12s3.6 6 10 6a10.8 10.8 0 0 0 4.5-.9M10 10a3 3 0 0 0 4 4"/></g></svg></button></div></div>
<div class="wallet-field"><label for="confirm-wallet-pin">Confirm New Wallet PIN</label><div class="eo-wallet-pin-wrap"><input id="confirm-wallet-pin" type="password" name="confirm_wallet_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password"><button type="button" class="wallet-activation-visibility" data-target="confirm-wallet-pin" aria-label="Show Wallet PIN confirmation" aria-pressed="false" hidden><svg class="wallet-visibility-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g class="wallet-visibility-eye"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.75"/></g><g class="wallet-visibility-eye-off"><path d="m3 3 18 18M10.7 6.1A11 11 0 0 1 12 6c6.4 0 10 6 10 6a16.5 16.5 0 0 1-3.4 3.8M6.1 6.9C3.5 8.6 2 12 2 12s3.6 6 10 6a10.8 10.8 0 0 0 4.5-.9M10 10a3 3 0 0 0 4 4"/></g></svg></button></div></div>
</div>
<p class="wallet-security-note">Use your new 6-digit PIN to open your wallet and confirm wallet payments.</p>
<button class="wallet-primary-button" type="submit" name="change_wallet_pin" value="1">Change Wallet PIN</button>
</form>
</section>
</main>
<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>
</body>
</html>
