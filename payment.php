<?php
// Only logged-in members can access the simulated card payment page.
session_start();
if(!isset($_SESSION["member_id"]))
{
	header("location:login.php");
	exit();
}

include("dataconnection.php");

$mid = (int)$_SESSION["member_id"];
$order_id = (int)($_POST["order_id"] ?? $_GET["order_id"] ?? 0);
$payment_error = "";
$cardholder = "";
$transaction_started = false;

// Escape dynamic values before displaying them in the HTML page.
function payment_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// Validate the card number with the standard Luhn checksum.
function payment_luhn_valid($number)
{
	$sum = 0;
	$double = false;
	for($i=strlen($number)-1;$i>=0;$i--)
	{
		$digit = (int)$number[$i];
		if($double)
		{
			$digit *= 2;
			if($digit>9)
			{
				$digit -= 9;
			}
		}
		$sum += $digit;
		$double = !$double;
	}

	return $sum>0 && $sum%10===0;
}

// Load only an order owned by the logged-in member. The optional row lock is
// used during payment confirmation to stop two requests paying the same order.
function payment_load_order($connect,$order_id,$member_id,$lock=false)
{
	$sql = "SELECT o.order_id,o.order_date,o.order_total,o.order_payment,o.order_payment_status,o.order_status,"
		."p.payment_id,p.payment_reference,p.payment_method AS saved_payment_method,"
		."p.payment_amount,p.payment_status AS saved_payment_status,p.payment_paid_at "
		."FROM orders o LEFT JOIN payments p ON p.payment_order=o.order_id "
		."WHERE o.order_id=? AND o.order_member=? AND o.order_isDelete=0 LIMIT 1";
	if($lock)
	{
		$sql .= " FOR UPDATE";
	}

	$stmt = mysqli_prepare($connect,$sql);
	if(!$stmt)
	{
		throw new Exception("The payment request could not be loaded.");
	}
	mysqli_stmt_bind_param($stmt,"ii",$order_id,$member_id);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		throw new Exception("The payment request could not be loaded.");
	}
	$result = mysqli_stmt_get_result($stmt);
	$order = mysqli_fetch_assoc($result);
	mysqli_stmt_close($stmt);

	return $order ?: null;
}

// Load the requested order before deciding which payment state to display.
$order = null;
if($order_id>0)
{
	try
	{
		$order = payment_load_order($connect,$order_id,$mid);
	}
	catch(Throwable $error)
	{
		$payment_error = "The payment request is temporarily unavailable. Please try again.";
	}
}

// Process only POST requests for an unpaid card order.
if(!$order)
{
	// Use the same unavailable response for invalid and unauthorized order IDs.
	http_response_code(404);
}
else if($_SERVER["REQUEST_METHOD"]==="POST" && $order["order_payment"]==="Credit Card" && $order["order_payment_status"]!=="Paid")
{
	// The one-time token protects the payment action from repeated or forged requests.
	$submitted_token = (string)($_POST["payment_token"] ?? "");
	$stored_token = (string)($_SESSION["payment_tokens"][$order_id] ?? "");
	$valid_token = $submitted_token!=="" && $stored_token!=="" && hash_equals($stored_token,$submitted_token);

	if(!$valid_token)
	{
		$payment_error = "This payment request was already submitted or has expired. Please refresh the page and try again.";
	}
	else if(isset($_POST["cancel_payment"]))
	{
		// Cancelling consumes the token but deliberately leaves the payment Pending.
		unset($_SESSION["payment_tokens"][$order_id]);
		header("location:payment.php?order_id=".$order_id."&cancelled=1");
		exit();
	}
	else if(isset($_POST["pay_now"]))
	{
		// Card values exist only for this request. They are validated below and
		// are never written to the database, session, URL or page response.
		$cardholder = trim((string)($_POST["cardholder"] ?? ""));
		$card_number = preg_replace("/\D/","",(string)($_POST["card_number"] ?? ""));
		$expiry = trim((string)($_POST["expiry"] ?? ""));
		$cvv = trim((string)($_POST["cvv"] ?? ""));

		// Repeat every important format check on the server, even though the
		// browser also provides basic required-field validation.
		if(!preg_match("/^[A-Za-z][A-Za-z .'-]{1,59}$/",$cardholder))
		{
			$payment_error = "Please enter the cardholder name shown on the card.";
		}
		else if(strlen($card_number)<13 || strlen($card_number)>19 || !payment_luhn_valid($card_number))
		{
			$payment_error = "Please enter a valid card number.";
		}
		else if(!preg_match("/^(0[1-9]|1[0-2])\/(\d{2})$/",$expiry,$expiry_parts))
		{
			$payment_error = "Please enter a valid expiry date in MM/YY format.";
		}
		else
		{
			$expiry_year = 2000+(int)$expiry_parts[2];
			$expiry_month = (int)$expiry_parts[1];
			$current_year = (int)date("Y");
			$current_month = (int)date("n");
			if($expiry_year<$current_year || ($expiry_year===$current_year && $expiry_month<$current_month))
			{
				$payment_error = "This card has expired. Please use another card.";
			}
			else if(!preg_match("/^\d{3,4}$/",$cvv))
			{
				$payment_error = "Please enter a valid 3 or 4-digit CVV.";
			}
			else if($card_number==="4000000000000002")
			{
				$payment_error = "The simulated payment was declined. No charge was made and the order remains Pending.";
			}
		}

		if($payment_error==="")
		{
			// Update the payment metadata and order payment status atomically.
			try
			{
				mysqli_begin_transaction($connect);
				$transaction_started = true;
				// Lock and re-read the order so concurrent requests cannot both pay it.
				$locked_order = payment_load_order($connect,$order_id,$mid,true);

				if(!$locked_order || $locked_order["order_payment"]!=="Credit Card")
				{
					throw new Exception("This order is not available for card payment.");
				}

				if($locked_order["order_payment_status"]==="Paid")
				{
					// A repeated request is treated as the existing successful payment.
					mysqli_commit($connect);
					$transaction_started = false;
					unset($_SESSION["payment_tokens"][$order_id]);
					header("location:payment.php?order_id=".$order_id."&result=success");
					exit();
				}

				if($locked_order["order_payment_status"]!=="Pending")
				{
					throw new Exception("This order is not awaiting payment.");
				}

				if(empty($locked_order["payment_id"]))
				{
					// Create safe Pending metadata for an older card order that predates
					// the payments table, without storing any card details.
					$method = "Credit Card";
					$amount = (float)$locked_order["order_total"];
					$stmt = mysqli_prepare($connect,"INSERT INTO payments(payment_order,payment_method,payment_amount,payment_status) VALUES(?,?,?,'Pending')");
					mysqli_stmt_bind_param($stmt,"isd",$order_id,$method,$amount);
					if(!mysqli_stmt_execute($stmt))
					{
						mysqli_stmt_close($stmt);
						throw new Exception("The payment record could not be created.");
					}
					mysqli_stmt_close($stmt);
				}
				else if($locked_order["saved_payment_status"]!=="Pending")
				{
					throw new Exception("This payment is not awaiting confirmation.");
				}

				// Generate the non-sensitive transaction reference and Malaysian paid time.
				$reference = "EO-".date("YmdHis")."-".str_pad((string)$order_id,6,"0",STR_PAD_LEFT)."-".strtoupper(bin2hex(random_bytes(3)));
				$paid_at = date("Y-m-d H:i:s");
				$amount = (float)$locked_order["order_total"];

				// Mark both linked records Paid inside the same transaction.
				$stmt = mysqli_prepare($connect,"UPDATE payments SET payment_reference=?,payment_method='Credit Card',payment_amount=?,payment_status='Paid',payment_paid_at=? WHERE payment_order=? AND payment_status='Pending'");
				mysqli_stmt_bind_param($stmt,"sdsi",$reference,$amount,$paid_at,$order_id);
				if(!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt)!==1)
				{
					mysqli_stmt_close($stmt);
					throw new Exception("The payment could not be confirmed.");
				}
				mysqli_stmt_close($stmt);

				$stmt = mysqli_prepare($connect,"UPDATE orders SET order_payment_status='Paid' WHERE order_id=? AND order_member=? AND order_payment_status='Pending'");
				mysqli_stmt_bind_param($stmt,"ii",$order_id,$mid);
				if(!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt)!==1)
				{
					mysqli_stmt_close($stmt);
					throw new Exception("The order payment status could not be confirmed.");
				}
				mysqli_stmt_close($stmt);

				// Commit once so partial payment success cannot be stored.
				mysqli_commit($connect);
				$transaction_started = false;
				unset($_SESSION["payment_tokens"][$order_id]);
				header("location:payment.php?order_id=".$order_id."&result=success");
				exit();
			}
			catch(Throwable $error)
			{
				if($transaction_started)
				{
					mysqli_rollback($connect);
					$transaction_started = false;
				}
				$payment_error = "Payment could not be completed. No charge was made and the order remains Pending.";
			}
		}
	}
}

// Reload the authoritative database values after validation or payment handling.
if($order)
{
	try
	{
		$order = payment_load_order($connect,$order_id,$mid);
	}
	catch(Throwable $error)
	{
		$order = null;
		$payment_error = "The payment request is temporarily unavailable. Please try again.";
	}
}

// Select one clear page state: success, payable, or unavailable.
$payment_success = $order && $order["order_payment_status"]==="Paid" && $order["saved_payment_status"]==="Paid" && !empty($order["payment_reference"]) && !empty($order["payment_paid_at"]);
$payment_available = $order && $order["order_payment"]==="Credit Card" && $order["order_payment_status"]==="Pending";

if($payment_available)
{
	// Keep a separate one-time token for each pending order in this session.
	if(!isset($_SESSION["payment_tokens"]) || !is_array($_SESSION["payment_tokens"]))
	{
		$_SESSION["payment_tokens"] = array();
	}
	if(!isset($_SESSION["payment_tokens"][$order_id]))
	{
		$_SESSION["payment_tokens"][$order_id] = bin2hex(random_bytes(32));
	}
	$payment_token = $_SESSION["payment_tokens"][$order_id];
}
?>

<!DOCTYPE html>
<html lang="en">

<head><!-- Simulated credit and debit card payment page -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Card Payment</title>
<link rel="stylesheet" href="style.css?v=20260922-2">
</head>

<body>

<div id="header"><!-- EasyOrder site header -->
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar"><!-- Customer navigation links -->
<a href="category.php">Menu</a>
<a href="cart.php">Cart</a>
<a href="dashboard.php">My Dashboard</a>
<a href="order_history.php">Order History</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="contact.php">Contact Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<main id="main"><!-- Main card payment content -->

<?php if($payment_success) { ?>

<section class="payment-success-card" aria-labelledby="payment-success-title"><!-- Successful payment receipt -->
<div class="payment-success-icon" aria-hidden="true">&#10003;</div>
<p class="checkout-step-label">PAYMENT COMPLETE</p>
<h2 id="payment-success-title">Payment Successful</h2>
<p class="payment-success-intro">Your card payment has been confirmed and linked to your EasyOrder order.</p>

<dl class="payment-result-list">
<div><dt>Order ID</dt><dd>#<?php echo (int)$order["order_id"]; ?></dd></div>
<div><dt>Amount</dt><dd>RM <?php echo number_format((float)$order["payment_amount"],2); ?></dd></div>
<div><dt>Payment Method</dt><dd><?php echo payment_html($order["saved_payment_method"]); ?></dd></div>
<div><dt>Payment Status</dt><dd><span class="payment-status-badge payment-status-paid">Paid</span></dd></div>
<div><dt>Transaction Reference</dt><dd class="payment-reference"><?php echo payment_html($order["payment_reference"]); ?></dd></div>
<div><dt>Paid At</dt><dd><?php echo payment_html(date("d M Y, h:i A",strtotime($order["payment_paid_at"]))); ?></dd></div>
</dl>

<div class="payment-success-actions">
<a class="payment-primary-link" href="order_details.php?order_id=<?php echo (int)$order["order_id"]; ?>">View Order Details</a>
<a class="payment-secondary-link" href="order_history.php">View Order History</a>
</div>
</section>

<?php } else if($payment_available) { ?>

<div class="checkout-page-heading payment-page-heading"><!-- Payment page heading and history link -->
<div>
<p class="checkout-step-label">SECURE PROTOTYPE</p>
<h2 class="section-title">Card Payment</h2>
<p class="intro">Complete the simulated payment for Order #<?php echo (int)$order["order_id"]; ?>.</p>
</div>
<a class="checkout-return-link" href="order_history.php">View Order History</a>
</div>

<?php if(isset($_GET["cancelled"])) { ?>
<div class="checkout-message checkout-message-warning" role="status">Payment was cancelled. No charge was made and this order remains Pending.</div>
<?php } ?>

<?php if($payment_error!=="") { ?>
<div class="checkout-message checkout-message-error" role="alert"><?php echo payment_html($payment_error); ?></div>
<?php } ?>

<div class="payment-layout"><!-- Desktop payment form and order summary columns -->
<section class="payment-form-card" aria-labelledby="card-details-heading"><!-- Simulated card input form -->
<div class="payment-card-heading">
<div>
<h3 id="card-details-heading">Card Details</h3>
<p>This is a local FYP payment simulation. No real charge will be made.</p>
</div>
<span class="payment-secure-badge">Secure Simulation</span>
</div>

<form id="payment-form" method="post" action="payment.php" autocomplete="off">
<input type="hidden" name="order_id" value="<?php echo (int)$order["order_id"]; ?>">
<input type="hidden" name="payment_token" value="<?php echo payment_html($payment_token); ?>">

<div class="payment-field">
<label for="cardholder">Cardholder Name <span>*</span></label>
<input id="cardholder" name="cardholder" type="text" maxlength="60" value="<?php echo payment_html($cardholder); ?>" placeholder="Name shown on card" required>
</div>

<div class="payment-field">
<label for="card-number">Card Number <span>*</span></label>
<input id="card-number" name="card_number" type="text" inputmode="numeric" maxlength="23" placeholder="1234 5678 9012 3456" aria-describedby="card-security-note" required>
</div>

<div class="payment-field-row">
<div class="payment-field">
<label for="expiry">Expiry <span>*</span></label>
<input id="expiry" name="expiry" type="text" inputmode="numeric" maxlength="5" placeholder="MM/YY" required>
</div>
<div class="payment-field">
<label for="cvv">CVV <span>*</span></label>
<input id="cvv" name="cvv" type="password" inputmode="numeric" maxlength="4" placeholder="3 or 4 digits" required>
</div>
</div>

<p class="payment-security-note" id="card-security-note">EasyOrder does not save the full card number or CVV.</p>

<div class="payment-test-note">
<strong>Prototype test cards</strong>
<span>Success: 4242 4242 4242 4242</span>
<span>Declined: 4000 0000 0000 0002</span>
</div>

<div class="payment-form-actions">
<button class="payment-primary-button" id="pay-now" type="submit" name="pay_now" value="1">Pay RM <?php echo number_format((float)$order["order_total"],2); ?></button>
<button class="payment-cancel-button" type="submit" name="cancel_payment" value="1" formnovalidate>Cancel Payment</button>
</div>
</form>
</section>

<aside class="payment-order-card" aria-labelledby="payment-summary-heading"><!-- Current order payment summary -->
<h3 id="payment-summary-heading">Payment Summary</h3>
<dl class="payment-summary-list">
<div><dt>Order ID</dt><dd>#<?php echo (int)$order["order_id"]; ?></dd></div>
<div><dt>Order Date</dt><dd><?php echo payment_html(date("d M Y, h:i A",strtotime($order["order_date"]))); ?></dd></div>
<div><dt>Payment Method</dt><dd>Credit / Debit Card</dd></div>
<div><dt>Order Status</dt><dd><?php echo payment_html($order["order_status"]); ?></dd></div>
<div><dt>Payment Status</dt><dd><span class="payment-status-badge payment-status-pending">Pending</span></dd></div>
</dl>
<div class="payment-amount-row"><span>Amount to Pay</span><strong>RM <?php echo number_format((float)$order["order_total"],2); ?></strong></div>
<p class="payment-summary-note">Your order already exists. Cancelling or a failed validation will not mark it as Paid.</p>
</aside>
</div>

<?php } else { ?>

<section class="payment-unavailable-card"><!-- Invalid or unavailable payment state -->
<h2>Payment Unavailable</h2>
<?php if($payment_error!=="") { ?>
<p><?php echo payment_html($payment_error); ?></p>
<?php } else if($order && $order["order_payment"]!=="Credit Card") { ?>
<p>This order does not use card payment.</p>
<?php } else { ?>
<p>This payment request is invalid or no longer available.</p>
<?php } ?>
<a class="payment-primary-link" href="order_history.php">View Order History</a>
</section>

<?php } ?>

</main>

<footer><!-- Website footer -->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

<?php if($payment_available) { ?>
<script>
(function()
{
	const form=document.getElementById("payment-form");
	const cardNumber=document.getElementById("card-number");
	const expiry=document.getElementById("expiry");
	const cvv=document.getElementById("cvv");
	const payButton=document.getElementById("pay-now");

	// Format card inputs for readability without storing their values.
	cardNumber.addEventListener("input",function()
	{
		const digits=cardNumber.value.replace(/\D/g,"").slice(0,19);
		cardNumber.value=digits.replace(/(.{4})/g,"$1 ").trim();
	});

	expiry.addEventListener("input",function()
	{
		const digits=expiry.value.replace(/\D/g,"").slice(0,4);
		expiry.value=digits.length>2 ? digits.slice(0,2)+"/"+digits.slice(2) : digits;
	});

	cvv.addEventListener("input",function()
	{
		cvv.value=cvv.value.replace(/\D/g,"").slice(0,4);
	});

	form.addEventListener("submit",function(event)
	{
		if(event.submitter && event.submitter.name==="cancel_payment")
		{
			return;
		}
		if(form.checkValidity())
		{
			// Delay the disabled state until the browser has captured the clicked
			// submit button's name/value in the form request.
			window.setTimeout(function()
			{
				payButton.disabled=true;
				payButton.textContent="Processing Payment...";
			},0);
		}
	});
})();
</script>
<?php } ?>

</body>
</html>
