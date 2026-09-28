<?php
// Only the signed-in customer may create or open their own wallet.
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");
require_once("wallet_helpers.php");

$mid = (int)$_SESSION["member_id"];
$wallet_error = "";
$wallet_success = (string)($_SESSION["wallet_success"] ?? "");
unset($_SESSION["wallet_success"]);

try
{
	$wallet = easyorder_wallet_load($connect,$mid);
}
catch(Throwable $error)
{
	$wallet = null;
	$wallet_error = "The wallet service is temporarily unavailable. Please try again later.";
}

if($_SERVER["REQUEST_METHOD"]==="POST" && $wallet_error==="")
{
	$submitted_token = (string)($_POST["wallet_access_token"] ?? "");
	$stored_token = (string)($_SESSION["wallet_access_token"] ?? "");
	$valid_token = $submitted_token!=="" && $stored_token!=="" && hash_equals($stored_token,$submitted_token);
	unset($_SESSION["wallet_access_token"]);

	if(!$valid_token)
	{
		$wallet_error = "This wallet request was already submitted or has expired. Please try again.";
	}
	else if(isset($_POST["lock_wallet"]))
	{
		easyorder_wallet_lock();
		header("location:wallet.php");
		exit();
	}
	else if(!$wallet && isset($_POST["create_wallet"]))
	{
		$account_password = (string)($_POST["account_password"] ?? "");
		$wallet_pin = (string)($_POST["wallet_pin"] ?? "");
		$confirm_pin = (string)($_POST["confirm_pin"] ?? "");

		if($account_password==="")
		{
			$wallet_error = "Enter your customer account password to verify your identity.";
		}
		else if(!easyorder_wallet_pin_valid($wallet_pin))
		{
			$wallet_error = "Create a 6-digit Wallet PIN.";
		}
		else if($wallet_pin!==$confirm_pin)
		{
			$wallet_error = "The Wallet PIN confirmation does not match.";
		}
		else
		{
			$transaction_started = false;
			try
			{
				mysqli_begin_transaction($connect);
				$transaction_started = true;

				$stmt = mysqli_prepare($connect,"SELECT member_password FROM member WHERE member_id=? AND member_isDelete=0 FOR UPDATE");
				mysqli_stmt_bind_param($stmt,"i",$mid);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				$member = mysqli_fetch_assoc($result);
				mysqli_stmt_close($stmt);
				if(!$member || !easyorder_password_verify($account_password,$member["member_password"]))
				{
					throw new Exception("The customer account password is incorrect.");
				}

				if(easyorder_wallet_load($connect,$mid,true))
				{
					throw new Exception("A wallet already exists for this customer account.");
				}

				$pin_hash = password_hash($wallet_pin,PASSWORD_DEFAULT);
				if($pin_hash===false)
				{
					throw new Exception("The Wallet PIN could not be protected.");
				}
				$stmt = mysqli_prepare($connect,"INSERT INTO wallets(wallet_member,wallet_pin_hash) VALUES(?,?)");
				mysqli_stmt_bind_param($stmt,"is",$mid,$pin_hash);
				if(!mysqli_stmt_execute($stmt))
				{
					mysqli_stmt_close($stmt);
					throw new Exception("The wallet could not be created.");
				}
				mysqli_stmt_close($stmt);

				mysqli_commit($connect);
				$transaction_started = false;
				easyorder_wallet_unlock($mid);
				$_SESSION["wallet_success"] = "Your EasyOrder Wallet has been created securely.";
				header("location:wallet.php");
				exit();
			}
			catch(Throwable $error)
			{
				if($transaction_started)
				{
					mysqli_rollback($connect);
				}
				$wallet_error = $error->getMessage();
			}
		}
	}
	else if($wallet && isset($_POST["unlock_wallet"]))
	{
		$wallet_pin = (string)($_POST["wallet_pin"] ?? "");
		if(!easyorder_wallet_pin_valid($wallet_pin) || !password_verify($wallet_pin,$wallet["wallet_pin_hash"]))
		{
			$wallet_error = "The Wallet PIN is incorrect.";
		}
		else
		{
			easyorder_wallet_unlock($mid);
			header("location:wallet.php");
			exit();
		}
	}
}

if(!isset($_SESSION["wallet_access_token"]))
{
	$_SESSION["wallet_access_token"] = bin2hex(random_bytes(32));
}
$wallet_access_token = $_SESSION["wallet_access_token"];
$wallet_unlocked = $wallet && easyorder_wallet_is_unlocked($mid);
$wallet_transactions = array();

if($wallet_unlocked)
{
	// History is filtered through this customer's wallet ID, never a URL ID.
	$stmt = mysqli_prepare($connect,"SELECT wallet_transaction_type,wallet_transaction_amount,wallet_transaction_reference,wallet_transaction_status,wallet_transaction_created_at,wallet_order FROM wallet_transactions WHERE wallet_id=? ORDER BY wallet_transaction_created_at DESC,wallet_transaction_id DESC LIMIT 100");
	mysqli_stmt_bind_param($stmt,"i",$wallet["wallet_id"]);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);
	while($row = mysqli_fetch_assoc($result))
	{
		$wallet_transactions[] = $row;
	}
	mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EasyOrder Wallet</title>
<link rel="stylesheet" href="style.css?v=20260927-1">
</head>
<body>

<div id="header"><!-- EasyOrder customer header -->
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar"><!-- Customer navigation bar -->
<a href="category.php">Menu</a>
<a href="cart.php">Cart</a>
<a href="dashboard.php">My Dashboard</a>
<a href="order_history.php">Order History</a>
<a href="wallet.php">Wallet</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<main id="main" class="wallet-page"><!-- Customer wallet content -->
<div class="wallet-page-heading">
<div>
<p class="checkout-step-label">CUSTOMER WALLET</p>
<h2 class="section-title">EasyOrder Wallet</h2>
<p class="intro">Top up securely, review your wallet activity and pay for orders using your available balance.</p>
</div>
<?php if($wallet_unlocked) { ?>
<form method="post" action="wallet.php">
<input type="hidden" name="wallet_access_token" value="<?php echo easyorder_wallet_html($wallet_access_token); ?>">
<button class="wallet-secondary-button" type="submit" name="lock_wallet" value="1">Lock Wallet</button>
</form>
<?php } ?>
</div>

<?php if($wallet_error!=="") { ?><div class="checkout-message checkout-message-error" role="alert"><?php echo easyorder_wallet_html($wallet_error); ?></div><?php } ?>
<?php if($wallet_success!=="") { ?><div class="wallet-message-success" role="status"><?php echo easyorder_wallet_html($wallet_success); ?></div><?php } ?>

<?php if(!$wallet) { ?>
<section class="wallet-access-card" aria-labelledby="create-wallet-title">
<div class="wallet-card-icon" aria-hidden="true">W</div>
<p class="checkout-step-label">FIRST-TIME SETUP</p>
<h3 id="create-wallet-title">Create Your Wallet</h3>
<p>Verify your existing customer account, then create a separate 6-digit Wallet PIN. This does not create another customer account.</p>
<form class="wallet-form" method="post" action="wallet.php" autocomplete="off">
<input type="hidden" name="wallet_access_token" value="<?php echo easyorder_wallet_html($wallet_access_token); ?>">
<div class="wallet-field">
<label for="account-password">Customer Account Password</label>
<input id="account-password" type="password" name="account_password" required autocomplete="current-password">
</div>
<div class="wallet-field-row">
<div class="wallet-field">
<label for="wallet-pin">Create Wallet PIN</label>
<input id="wallet-pin" type="password" name="wallet_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password">
</div>
<div class="wallet-field">
<label for="confirm-pin">Confirm Wallet PIN</label>
<input id="confirm-pin" type="password" name="confirm_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password">
</div>
</div>
<p class="wallet-security-note">Your Wallet PIN is stored as a secure hash and cannot be read back.</p>
<button class="wallet-primary-button" type="submit" name="create_wallet" value="1">Create Wallet</button>
</form>
</section>

<?php } else if(!$wallet_unlocked) { ?>
<section class="wallet-access-card" aria-labelledby="unlock-wallet-title">
<div class="wallet-card-icon" aria-hidden="true">W</div>
<p class="checkout-step-label">WELCOME BACK</p>
<h3 id="unlock-wallet-title">Open Your Wallet</h3>
<p>Enter your 6-digit Wallet PIN. Your customer login password and Wallet PIN are separate.</p>
<form class="wallet-form" method="post" action="wallet.php" autocomplete="off">
<input type="hidden" name="wallet_access_token" value="<?php echo easyorder_wallet_html($wallet_access_token); ?>">
<div class="wallet-field">
<label for="wallet-pin">Wallet PIN</label>
<input id="wallet-pin" type="password" name="wallet_pin" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="current-password" autofocus>
</div>
<div class="wallet-form-actions">
<button class="wallet-primary-button" type="submit" name="unlock_wallet" value="1">Open Wallet</button>
<a class="wallet-text-link" href="wallet_pin_recovery.php">Forgot Wallet PIN?</a>
</div>
</form>
</section>

<?php } else { ?>
<section class="wallet-balance-card" aria-labelledby="wallet-balance-title">
<div>
<p class="checkout-step-label">AVAILABLE BALANCE</p>
<h3 id="wallet-balance-title">RM <?php echo number_format((float)$wallet["wallet_balance"],2); ?></h3>
<p>Wallet #<?php echo str_pad((string)$wallet["wallet_id"],6,"0",STR_PAD_LEFT); ?> &middot; Open for 15 minutes after PIN verification</p>
</div>
<a class="wallet-primary-link" href="wallet_topup.php">Top Up Wallet</a>
</section>

<div class="wallet-dashboard-links">
<a href="checkout.php"><strong>Pay at Checkout</strong><span>Choose EasyOrder Wallet when placing an order.</span></a>
<a href="wallet_pin_recovery.php"><strong>Change Wallet PIN</strong><span>Verify your customer password and set a new PIN.</span></a>
</div>

<section class="wallet-history-card" aria-labelledby="wallet-history-title">
<div class="wallet-history-heading">
<div><p class="checkout-step-label">ACCOUNT ACTIVITY</p><h3 id="wallet-history-title">Transaction History</h3></div>
<span><?php echo count($wallet_transactions); ?> transaction<?php if(count($wallet_transactions)!==1) echo "s"; ?></span>
</div>
<?php if(count($wallet_transactions)===0) { ?>
<div class="wallet-empty-history"><p>No wallet transactions yet.</p><a href="wallet_topup.php">Make your first top-up</a></div>
<?php } else { ?>
<div class="wallet-table-wrap">
<table class="wallet-history-table">
<thead><tr><th>Date &amp; Time</th><th>Type</th><th>Reference</th><th>Status</th><th>Amount</th></tr></thead>
<tbody>
<?php foreach($wallet_transactions as $transaction) { $is_payment=$transaction["wallet_transaction_type"]==="Payment"; $is_paid=$transaction["wallet_transaction_status"]==="Paid"; ?>
<tr>
<td><?php echo easyorder_wallet_html(date("d M Y, g:i A",strtotime($transaction["wallet_transaction_created_at"]))); ?></td>
<td><?php echo easyorder_wallet_html($transaction["wallet_transaction_type"]); ?><?php if($transaction["wallet_order"]) { ?><small>Order #<?php echo (int)$transaction["wallet_order"]; ?></small><?php } ?></td>
<td class="wallet-reference"><?php echo easyorder_wallet_html($transaction["wallet_transaction_reference"]); ?></td>
<td><span class="wallet-status wallet-status-<?php echo strtolower(easyorder_wallet_html($transaction["wallet_transaction_status"])); ?>"><?php echo easyorder_wallet_html($transaction["wallet_transaction_status"]); ?></span></td>
<td class="<?php echo $is_payment ? "wallet-amount-out" : ($is_paid ? "wallet-amount-in" : ""); ?>"><?php echo $is_payment ? "- " : ($is_paid ? "+ " : ""); ?>RM <?php echo number_format((float)$transaction["wallet_transaction_amount"],2); ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php } ?>
</section>
<?php } ?>
</main>

<footer><p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p><p><a href="admin_login.php">Admin Login</a></p></footer>
</body>
</html>
