<?php
// Simulated card top-up. Full card number, expiry and CVV remain request-only.
session_start();
if(!isset($_SESSION["customer_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");
require_once("wallet_helpers.php");

$mid = (int)$_SESSION["customer_id"];
$wallet = easyorder_wallet_load($connect,$mid);
if(!$wallet || !easyorder_wallet_is_unlocked($mid))
{
	header("location:wallet.php");
	exit();
}

$topup_error = "";
$topup_success = (string)($_SESSION["wallet_topup_success"] ?? "");
$topup_notice_error = (string)($_SESSION["wallet_topup_error"] ?? "");
unset($_SESSION["wallet_topup_success"]);
unset($_SESSION["wallet_topup_error"]);
$amount_value = "";
$cardholder = "";

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["topup_wallet"]))
{
	$submitted_token = (string)($_POST["wallet_topup_token"] ?? "");
	$stored_token = (string)($_SESSION["wallet_topup_token"] ?? "");
	$valid_token = $submitted_token!=="" && $stored_token!=="" && hash_equals($stored_token,$submitted_token);
	unset($_SESSION["wallet_topup_token"]);

	$amount_value = trim((string)($_POST["topup_amount"] ?? ""));
	$cardholder = trim((string)($_POST["cardholder"] ?? ""));
	$card_number = preg_replace("/\D/","",(string)($_POST["card_number"] ?? ""));
	$expiry = trim((string)($_POST["expiry"] ?? ""));
	$cvv = trim((string)($_POST["cvv"] ?? ""));
	$amount = is_numeric($amount_value) ? round((float)$amount_value,2) : 0.00;

	if(!$valid_token)
	{
		$topup_error = "This top-up request was already submitted or has expired. Please try again.";
	}
	else if($amount<1 || $amount>1000)
	{
		$topup_error = "Enter a top-up amount from RM 1.00 to RM 1,000.00.";
	}
	else if(!preg_match("/^[A-Za-z][A-Za-z .'-]{1,59}$/",$cardholder))
	{
		$topup_error = "Please enter the cardholder name shown on the card.";
	}
	else if(strlen($card_number)<13 || strlen($card_number)>19)
	{
		$topup_error = "Please enter a valid card number.";
	}
	else if(!preg_match("/^(0[1-9]|1[0-2])\/(\d{2})$/",$expiry,$expiry_parts))
	{
		$topup_error = "Please enter a valid expiry date in MM/YY format.";
	}
	else
	{
		$expiry_year = 2000+(int)$expiry_parts[2];
		$expiry_month = (int)$expiry_parts[1];
		$current_year = (int)date("Y");
		$current_month = (int)date("n");
		if($expiry_year<$current_year || ($expiry_year===$current_year && $expiry_month<$current_month))
		{
			$topup_error = "This card has expired. Please use another card.";
		}
		else if(!preg_match("/^\d{3,4}$/",$cvv))
		{
			$topup_error = "Please enter a valid 3 or 4-digit CVV.";
		}
	}

	if($topup_error==="")
	{
		$transaction_started = false;
		try
		{
			mysqli_begin_transaction($connect);
			$transaction_started = true;
			$locked_wallet = easyorder_wallet_load($connect,$mid,true);
			if(!$locked_wallet)
			{
				throw new Exception("The wallet could not be found.");
			}

			$request_key = hash("sha256",$submitted_token);
			$stmt = mysqli_prepare($connect,"SELECT wallet_transaction_status,wallet_transaction_reference FROM wallet_transactions WHERE wallet_request_key=? LIMIT 1 FOR UPDATE");
			mysqli_stmt_bind_param($stmt,"s",$request_key);
			mysqli_stmt_execute($stmt);
			$existing_result = mysqli_stmt_get_result($stmt);
			$existing = mysqli_fetch_assoc($existing_result);
			mysqli_stmt_close($stmt);
			if($existing)
			{
				mysqli_commit($connect);
				$transaction_started = false;
				if($existing["wallet_transaction_status"]==="Paid")
				{
					$_SESSION["wallet_topup_success"] = "This top-up was already completed.";
				}
				else
				{
					$_SESSION["wallet_topup_error"] = "This declined top-up was already recorded and no balance was added.";
				}
				header("location:wallet_topup.php");
				exit();
			}

			$reference = easyorder_wallet_reference("TOP");
			$status = $card_number==="4000000000000002" ? "Failed" : "Paid";
			$wallet_id = (int)$locked_wallet["wallet_id"];
			if($status==="Paid")
			{
				if((float)$locked_wallet["wallet_balance"]+$amount>99999999.99)
				{
					throw new Exception("This top-up would exceed the wallet balance limit.");
				}
				$stmt = mysqli_prepare($connect,"UPDATE wallets SET wallet_balance=wallet_balance+? WHERE wallet_id=? AND wallet_customer=?");
				mysqli_stmt_bind_param($stmt,"dii",$amount,$wallet_id,$mid);
				if(!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt)!==1)
				{
					mysqli_stmt_close($stmt);
					throw new Exception("The wallet balance could not be updated.");
				}
				mysqli_stmt_close($stmt);
			}

			$stmt = mysqli_prepare($connect,"INSERT INTO wallet_transactions(wallet_id,wallet_transaction_type,wallet_transaction_amount,wallet_transaction_reference,wallet_transaction_status,wallet_request_key) VALUES(?,'Top Up',?,?,?,?)");
			mysqli_stmt_bind_param($stmt,"idsss",$wallet_id,$amount,$reference,$status,$request_key);
			if(!mysqli_stmt_execute($stmt))
			{
				mysqli_stmt_close($stmt);
				throw new Exception("The top-up transaction could not be recorded.");
			}
			mysqli_stmt_close($stmt);

			mysqli_commit($connect);
			$transaction_started = false;
			if($status==="Paid")
			{
				$_SESSION["wallet_topup_success"] = "Top-up successful. RM ".number_format($amount,2)." was added once. Reference: ".$reference;
			}
			else
			{
				$_SESSION["wallet_topup_error"] = "The simulated payment was declined. No balance was added. Reference: ".$reference;
			}
			header("location:wallet_topup.php");
			exit();
		}
		catch(Throwable $error)
		{
			if($transaction_started)
			{
				mysqli_rollback($connect);
			}
			$topup_error = $error->getMessage();
		}
	}

	// Remove sensitive card values before the response is rendered.
	$card_number = $expiry = $cvv = "";
}

if(!isset($_SESSION["wallet_topup_token"]))
{
	$_SESSION["wallet_topup_token"] = bin2hex(random_bytes(32));
}
$wallet_topup_token = $_SESSION["wallet_topup_token"];
$wallet = easyorder_wallet_load($connect,$mid);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Top Up Wallet</title>
<link rel="stylesheet" href="style.css?v=20260927-1">
</head>
<body>
<div id="header"><img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo"><h1>EasyOrder</h1><p>Your Favourite Fast Food, Just A Few Clicks Away</p></div>
<div id="navbar"><a href="category.php">Menu</a><a href="cart.php">Cart</a><a href="dashboard.php">My Dashboard</a><a href="order_history.php">Order History</a><a href="wallet.php">Wallet</a><a href="reward.php">Rewards</a><a href="view_review.php">View Reviews</a><a href="about.html">About Us</a><a href="contact.php">Contact Us</a><a href="logout.php">Logout</a></div>

<main id="main" class="wallet-page">
<div class="wallet-page-heading"><div><h2 class="section-title">Top Up Wallet</h2><p class="intro">Add funds to your EasyOrder Wallet using the secure simulated card flow.</p></div><a class="checkout-return-link" href="wallet.php">&larr; Back to Wallet</a></div>
<?php if($topup_error!=="") { ?><div class="checkout-message checkout-message-error" role="alert"><?php echo easyorder_wallet_html($topup_error); ?></div><?php } ?>
<?php if($topup_notice_error!=="") { ?><div class="checkout-message checkout-message-error" role="status"><?php echo easyorder_wallet_html($topup_notice_error); ?></div><?php } ?>
<?php if($topup_success!=="") { ?><div class="wallet-message-success" role="status"><?php echo easyorder_wallet_html($topup_success); ?></div><?php } ?>

<div class="wallet-topup-layout">
<section class="wallet-access-card" aria-labelledby="topup-title">
<h3 id="topup-title">Card Details</h3>
<p>Card information is validated for this request only and is never stored.</p>
<form class="wallet-form" method="post" action="wallet_topup.php" autocomplete="off">
<input type="hidden" name="wallet_topup_token" value="<?php echo easyorder_wallet_html($wallet_topup_token); ?>">
<div class="wallet-field"><label for="topup-amount">Top-Up Amount (RM)</label><input id="topup-amount" type="number" name="topup_amount" min="1" max="1000" step="0.01" value="<?php echo easyorder_wallet_html($amount_value); ?>" required placeholder="50.00"></div>
<div class="wallet-field"><label for="cardholder">Cardholder Name</label><input id="cardholder" type="text" name="cardholder" maxlength="60" value="<?php echo easyorder_wallet_html($cardholder); ?>" required autocomplete="cc-name"></div>
<div class="wallet-field"><label for="card-number">Card Number</label><input id="card-number" type="text" name="card_number" inputmode="numeric" maxlength="23" required autocomplete="off" placeholder="4966 2312 3456 7890"></div>
<div class="wallet-field-row">
<div class="wallet-field"><label for="expiry">Expiry (MM/YY)</label><input id="expiry" type="text" name="expiry" inputmode="numeric" maxlength="5" required autocomplete="off" placeholder="12/30"></div>
<div class="wallet-field"><label for="cvv">CVV</label><input id="cvv" type="password" name="cvv" inputmode="numeric" maxlength="4" required autocomplete="off" placeholder="123"></div>
</div>
<button class="wallet-primary-button" type="submit" name="topup_wallet" value="1">Confirm Top-Up</button>
</form>
</section>

<aside class="wallet-topup-summary"><p class="checkout-step-label">CURRENT BALANCE</p><h3>RM <?php echo number_format((float)$wallet["wallet_balance"],2); ?></h3><p>A successful top-up is credited exactly once and appears in Wallet Transaction History.</p><ul><li>Minimum RM 1.00</li><li>Maximum RM 1,000.00 per top-up</li><li>No card number or CVV is saved</li></ul></aside>
</div>
</main>
<footer><p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p><p><a href="admin_login.php">Admin Login</a></p></footer>

<script>
(function(){
	// Formatting is visual only; the server repeats every validation rule.
	const card=document.getElementById("card-number");
	const expiry=document.getElementById("expiry");
	card.addEventListener("input",function(){ this.value=this.value.replace(/\D/g,"").slice(0,19).replace(/(.{4})/g,"$1 ").trim(); });
	expiry.addEventListener("input",function(){ const digits=this.value.replace(/\D/g,"").slice(0,4); this.value=digits.length>2 ? digits.slice(0,2)+"/"+digits.slice(2) : digits; });
})();
</script>
</body>
</html>
