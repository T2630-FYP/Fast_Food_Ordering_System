<?php
// Only the signed-in customer may create or open their own wallet.
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

				if(easyorder_wallet_load($connect,$mid,true))
				{
					throw new Exception("A wallet already exists for this customer account.");
				}

				$stmt = mysqli_prepare($connect,"INSERT INTO wallets(wallet_customer,wallet_pin_hash) VALUES(?,?)");
				mysqli_stmt_bind_param($stmt,"is",$mid,$wallet_pin);
				if(!mysqli_stmt_execute($stmt))
				{
					mysqli_stmt_close($stmt);
					throw new Exception("The wallet could not be created.");
				}
				mysqli_stmt_close($stmt);

				mysqli_commit($connect);
				$transaction_started = false;
				easyorder_wallet_unlock($mid);
				$_SESSION["wallet_success"] = "Your EasyOrder Wallet has been created.";
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
		try
		{
			if(!easyorder_wallet_verify_pin($connect,$wallet,$wallet_pin))
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
		catch(Throwable $error)
		{
			$wallet_error = "The wallet service is temporarily unavailable. Please try again later.";
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
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EasyOrder Wallet</title>
<link rel="stylesheet" href="public.css?v=20261102-wallet-dashboard1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<link rel="stylesheet" href="customer.css?v=20261102-wallet-manual-lock1">
<?php if(!$wallet || !$wallet_unlocked) { ?>
<script src="customer.js?v=20261030-wallet-unlock1" defer></script>
<?php } ?>
</head>
<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!-- Customer navigation bar -->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="wallet-page<?php echo !$wallet ? ' wallet-activation-page' : (!$wallet_unlocked ? ' eo-wallet-locked' : ' eow-dashboard'); ?>"><!-- Customer wallet content -->
<div class="wallet-page-heading<?php if($wallet_unlocked) echo ' eow-heading'; ?>">
<?php if(!$wallet) { ?>
<h1 class="wallet-activation-page-title">Wallet</h1>
<?php } else if(!$wallet_unlocked) { ?>
<h1 class="eo-wallet-unlock-page-title">Wallet</h1>
<?php } else { ?>
<h1>Wallet</h1>
<form class="eow-lock-form" method="post" action="wallet.php">
<input type="hidden" name="wallet_access_token" value="<?php echo easyorder_wallet_html($wallet_access_token); ?>">
<button class="eow-lock-button" type="submit" name="lock_wallet" value="1">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
Lock Wallet</button>
</form>
<?php } ?>
</div>

<?php if($wallet_error!=="") { ?><div class="checkout-message checkout-message-error" role="alert"><?php echo easyorder_wallet_html($wallet_error); ?></div><?php } ?>
<?php if($wallet_success!=="") { ?><div class="wallet-message-success" role="status"><?php echo easyorder_wallet_html($wallet_success); ?></div><?php } ?>

<?php if(!$wallet) { ?>
<section class="wallet-activation-card" aria-labelledby="create-wallet-title">
<div class="wallet-activation-heading">
<span class="wallet-activation-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false">
<path d="M20 8V6a2 2 0 0 0-2-2H6a4 4 0 0 0-4 4v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2H6a2 2 0 0 1 0-4"/>
<path d="M22 13h-6a3 3 0 0 0 0 6h6"/>
</svg>
</span>
<h2 id="create-wallet-title">Activate Your Wallet</h2>
</div>
<form class="wallet-activation-form" method="post" action="wallet.php" autocomplete="off">
<input type="hidden" name="wallet_access_token" value="<?php echo easyorder_wallet_html($wallet_access_token); ?>">
<div class="wallet-activation-field">
<label for="account-password">Account Password</label>
<div class="wallet-activation-input-wrap">
<input id="account-password" type="password" name="account_password" placeholder="Enter your account password" required autocomplete="current-password">
<button type="button" class="wallet-activation-visibility" data-target="account-password" aria-label="Show account password" aria-pressed="false" hidden>
<svg class="wallet-visibility-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
<g class="wallet-visibility-eye"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.75"/></g>
<g class="wallet-visibility-eye-off"><path d="m3 3 18 18M10.7 6.1A11 11 0 0 1 12 6c6.4 0 10 6 10 6a16.5 16.5 0 0 1-3.4 3.8M6.1 6.9C3.5 8.6 2 12 2 12s3.6 6 10 6a10.8 10.8 0 0 0 4.5-.9M10 10a3 3 0 0 0 4 4"/></g>
</svg>
</button>
</div>
</div>
<div class="wallet-activation-pin-row">
<div class="wallet-activation-field">
<label for="wallet-pin">Create Wallet PIN</label>
<div class="wallet-activation-input-wrap">
<input id="wallet-pin" class="wallet-activation-pin-input" type="password" name="wallet_pin" placeholder="Enter 6-digit PIN" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password">
<button type="button" class="wallet-activation-visibility" data-target="wallet-pin" aria-label="Show Wallet PIN" aria-pressed="false" hidden>
<svg class="wallet-visibility-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
<g class="wallet-visibility-eye"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.75"/></g>
<g class="wallet-visibility-eye-off"><path d="m3 3 18 18M10.7 6.1A11 11 0 0 1 12 6c6.4 0 10 6 10 6a16.5 16.5 0 0 1-3.4 3.8M6.1 6.9C3.5 8.6 2 12 2 12s3.6 6 10 6a10.8 10.8 0 0 0 4.5-.9M10 10a3 3 0 0 0 4 4"/></g>
</svg>
</button>
</div>
</div>
<div class="wallet-activation-field">
<label for="confirm-pin">Confirm Wallet PIN</label>
<div class="wallet-activation-input-wrap">
<input id="confirm-pin" class="wallet-activation-pin-input" type="password" name="confirm_pin" placeholder="Re-enter 6-digit PIN" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password">
<button type="button" class="wallet-activation-visibility" data-target="confirm-pin" aria-label="Show PIN confirmation" aria-pressed="false" hidden>
<svg class="wallet-visibility-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
<g class="wallet-visibility-eye"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.75"/></g>
<g class="wallet-visibility-eye-off"><path d="m3 3 18 18M10.7 6.1A11 11 0 0 1 12 6c6.4 0 10 6 10 6a16.5 16.5 0 0 1-3.4 3.8M6.1 6.9C3.5 8.6 2 12 2 12s3.6 6 10 6a10.8 10.8 0 0 0 4.5-.9M10 10a3 3 0 0 0 4 4"/></g>
</svg>
</button>
</div>
</div>
</div>
<div class="wallet-activation-note" role="note">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
<span>You will use this PIN to unlock your wallet and confirm wallet payments.</span>
</div>
<button class="wallet-activation-submit" type="submit" name="create_wallet" value="1">Activate Wallet</button>
</form>
</section>

<?php } else if(!$wallet_unlocked) { ?>
<section class="eo-wallet-unlock-card" aria-labelledby="unlock-wallet-title">
<div class="eo-wallet-unlock-head">
<span class="eo-wallet-unlock-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false">
<path d="M20 8V6a2 2 0 0 0-2-2H6a4 4 0 0 0-4 4v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2H6a2 2 0 0 1 0-4"/>
<path d="M22 13h-6a3 3 0 0 0 0 6h6"/>
</svg>
</span>
<div class="eo-wallet-unlock-heading-text">
<h2 id="unlock-wallet-title">Open Your Wallet</h2>
<p>Enter your 6-digit Wallet PIN to access your wallet.</p>
</div>
</div>
<form class="eo-wallet-unlock-form" method="post" action="wallet.php" autocomplete="off">
<input type="hidden" name="wallet_access_token" value="<?php echo easyorder_wallet_html($wallet_access_token); ?>">
<div class="eo-wallet-unlock-field">
<label for="eo-wallet-unlock-pin">Wallet PIN</label>
<div class="eo-wallet-unlock-input-wrap">
<input id="eo-wallet-unlock-pin" type="password" name="wallet_pin" placeholder="Enter 6-digit Wallet PIN" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="off" autofocus>
<button type="button" class="eo-wallet-unlock-eye" aria-controls="eo-wallet-unlock-pin" aria-label="Show Wallet PIN" aria-pressed="false" hidden>
<svg class="eo-wallet-eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="eo-wallet-eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 6.1A12 12 0 0 1 12 6c6.5 0 10 6 10 6a13 13 0 0 1-3.3 3.8M6.1 6.8C3.4 8.6 2 12 2 12s3.5 6 10 6a11 11 0 0 0 4.5-.9M10 10a3 3 0 0 0 4 4"/></svg>
</button>
</div>
</div>
<button class="eo-wallet-unlock-submit" type="submit" name="unlock_wallet" value="1">Open Wallet</button>
<a class="eo-wallet-unlock-forgot" href="wallet_pin_recovery.php">Forgot Wallet PIN?</a>
</form>
</section>

<?php } else { ?>
<section class="eow-balance" aria-labelledby="eow-balance-title">
<div class="eow-balance-left">
<span class="eow-wallet-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" focusable="false">
<path d="M20 8V6a2 2 0 0 0-2-2H6a4 4 0 0 0-4 4v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2H6a2 2 0 0 1 0-4"/><path d="M22 13h-6a3 3 0 0 0 0 6h6"/>
</svg>
</span>
<div class="eow-balance-copy">
<p class="eow-balance-label" id="eow-balance-title">AVAILABLE BALANCE</p>
<p class="eow-balance-amount">RM <?php echo number_format((float)$wallet["wallet_balance"],2); ?></p>
</div>
</div>
<a class="eow-topup" href="wallet_topup.php">Top Up Wallet</a>
</section>

<div class="eow-pin-row">
<a class="eow-change-pin" href="wallet_pin_recovery.php">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="m9.5 3-.6 2a8 8 0 0 0-1.7 1l-2-.5-2 3.5 1.4 1.5a8 8 0 0 0 0 2l-1.4 1.5 2 3.5 2-.5a8 8 0 0 0 1.7 1l.6 2h4l.6-2a8 8 0 0 0 1.7-1l2 .5 2-3.5-1.4-1.5a8 8 0 0 0 0-2l1.4-1.5-2-3.5-2 .5a8 8 0 0 0-1.7-1l-.6-2Z"/></svg>
Change Wallet PIN</a>
</div>

<section class="eow-history" aria-labelledby="eow-history-title">
<div class="eow-history-heading">
<h2 id="eow-history-title">Transaction History</h2>
<span><?php echo count($wallet_transactions); ?> transaction<?php if(count($wallet_transactions)!==1) echo "s"; ?></span>
</div>
<?php if(count($wallet_transactions)===0) { ?>
<div class="eow-history-empty">
<span class="eow-receipt-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h6M8 9h1"/></svg></span>
<strong>No wallet transactions yet.</strong>
<p>Your top-ups and wallet payments will appear here.</p>
</div>
<?php } else { ?>
<div class="eow-table-wrap" role="region" aria-labelledby="eow-history-title" tabindex="0">
<table class="eow-history-table">
<thead><tr><th scope="col">Date &amp; Time</th><th scope="col">Description</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
<tbody>
<?php foreach($wallet_transactions as $transaction) {
$is_payment = $transaction["wallet_transaction_type"]==="Payment";
$is_paid = $transaction["wallet_transaction_status"]==="Paid";
$status_class = $is_paid ? "eow-status-paid" : ($transaction["wallet_transaction_status"]==="Failed" ? "eow-status-failed" : "eow-status-pending");
$description = $is_payment ? "Payment".(!empty($transaction["wallet_order"]) ? " Order #".(int)$transaction["wallet_order"] : "") : (string)$transaction["wallet_transaction_type"];
$timestamp = strtotime((string)$transaction["wallet_transaction_created_at"]);
?>
<tr>
<td><?php echo $timestamp ? easyorder_wallet_html(date("d M Y, g:i A",$timestamp)) : "—"; ?></td>
<td><?php echo easyorder_wallet_html($description); ?></td>
<td class="<?php echo $is_payment ? "eow-amount-out" : ($is_paid ? "eow-amount-in" : ""); ?>"><?php echo $is_payment ? "− " : ($is_paid ? "+ " : ""); ?>RM <?php echo number_format((float)$transaction["wallet_transaction_amount"],2); ?></td>
<td><span class="eow-status <?php echo $status_class; ?>"><?php echo easyorder_wallet_html($transaction["wallet_transaction_status"]); ?></span></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<?php } ?>
</section>
<?php } ?>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>
</body>
</html>
