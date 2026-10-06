<?php
// Wallet PIN recovery is separate from customer login password recovery.
session_start();
if(!isset($_SESSION["customer_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");
require_once("wallet_helpers.php");

$mid = (int)$_SESSION["customer_id"];
$recovery_error = "";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wallet PIN Recovery</title>
<link rel="stylesheet" href="style.css?v=20260927-1">
</head>
<body>
<div id="header"><img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo"><h1>EasyOrder</h1><p>Your Favourite Fast Food, Just A Few Clicks Away</p></div>
<div id="navbar">
<a href="category.php">Menu</a>
<a href="cart.php">Cart</a>
<a href="order_history.php">Order History</a>
<a href="profile.php">My Profile</a>
<a href="wallet.php">Wallet</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<main id="main" class="wallet-page">
<div class="wallet-page-heading"><div><p class="checkout-step-label">WALLET SECURITY</p><h2 class="section-title">Change Wallet PIN</h2><p class="intro">Verify your customer identity before replacing the Wallet PIN.</p></div><a class="checkout-return-link" href="wallet.php">&larr; Back to Wallet</a></div>
<?php if($recovery_error!=="") { ?><div class="checkout-message checkout-message-error" role="alert"><?php echo easyorder_wallet_html($recovery_error); ?></div><?php } ?>

<section class="wallet-access-card" aria-labelledby="recovery-title">
<h3 id="recovery-title">Set a New Wallet PIN</h3>
<p>This changes only the Wallet PIN. It does not change your customer login password.</p>
<form class="wallet-form" method="post" action="wallet_pin_recovery.php" autocomplete="off">
<input type="hidden" name="wallet_recovery_token" value="<?php echo easyorder_wallet_html($wallet_recovery_token); ?>">
<div class="wallet-field"><label for="account-password">Customer Account Password</label><input id="account-password" type="password" name="account_password" required autocomplete="current-password"></div>
<div class="wallet-field-row">
<div class="wallet-field"><label for="new-wallet-pin">New 6-Digit Wallet PIN</label><input id="new-wallet-pin" type="password" name="new_wallet_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password"></div>
<div class="wallet-field"><label for="confirm-wallet-pin">Confirm New Wallet PIN</label><input id="confirm-wallet-pin" type="password" name="confirm_wallet_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password"></div>
</div>
<p class="wallet-security-note">Use your new 6-digit PIN to open your wallet and confirm wallet payments.</p>
<button class="wallet-primary-button" type="submit" name="change_wallet_pin" value="1">Change Wallet PIN</button>
</form>
</section>
</main>
<footer><p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p><p><a href="admin_login.php">Admin Login</a></p></footer>
</body>
</html>
