<?php
include("dataconnection.php");
require_once("email_helpers.php");

if(isset($_SESSION["customer_id"]) && $_SERVER["REQUEST_METHOD"]!=="POST")
{
	header("location:dashboard.php");
	exit();
}

$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");
$register_errors = array();
$register_values = array(
	"name" => "",
	"email" => "",
	"confirm_email" => "",
	"phone" => "",
	"gender" => "",
	"dob" => "",
	"state" => "",
	"city" => "",
	"postcode" => ""
);

function register_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

if(!isset($_SESSION["register_csrf"]))
{
	$_SESSION["register_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["signupbtn"]))
{
	$register_values = array(
		"name" => trim((string)($_POST["cust_name"] ?? "")),
		"email" => strtolower(trim((string)($_POST["cust_email"] ?? ""))),
		"confirm_email" => strtolower(trim((string)($_POST["cust_confirm_email"] ?? ""))),
		"phone" => trim((string)($_POST["cust_phone"] ?? "")),
		"gender" => trim((string)($_POST["gender"] ?? "")),
		"dob" => trim((string)($_POST["cust_dob"] ?? "")),
		"state" => trim((string)($_POST["state"] ?? "")),
		"city" => trim((string)($_POST["cust_city"] ?? "")),
		"postcode" => trim((string)($_POST["cust_postcode"] ?? ""))
	);
	$password = (string)($_POST["cust_password"] ?? "");
	$confirm_password = (string)($_POST["cust_confirm_password"] ?? "");
	$submitted_token = (string)($_POST["register_csrf"] ?? "");

	if($submitted_token==="" || !hash_equals($_SESSION["register_csrf"],$submitted_token))
	{
		$register_errors["general"] = "Your registration form has expired. Please refresh the page and try again.";
	}
	if(strlen($register_values["name"])<2 || strlen($register_values["name"])>100)
	{
		$register_errors["name"] = "Enter your full name using 2 to 100 characters.";
	}
	if(!filter_var($register_values["email"],FILTER_VALIDATE_EMAIL) || strlen($register_values["email"])>100)
	{
		$register_errors["email"] = "Enter a valid email address.";
	}
	if($register_values["confirm_email"]==="" || !hash_equals($register_values["email"],$register_values["confirm_email"]))
	{
		$register_errors["confirm_email"] = "Email addresses do not match.";
	}
	if(strlen($password)<8 || strlen($password)>72)
	{
		$register_errors["password"] = "Use a password containing 8 to 72 characters.";
	}
	if($confirm_password==="" || !hash_equals($password,$confirm_password))
	{
		$register_errors["confirm_password"] = "Passwords do not match.";
	}
	if(!preg_match("/^\d{9,15}$/",$register_values["phone"]))
	{
		$register_errors["phone"] = "Enter a phone number containing 9 to 15 digits.";
	}
	if(!in_array($register_values["gender"],array("Male","Female"),true))
	{
		$register_errors["gender"] = "Select your gender.";
	}

	$dob = DateTime::createFromFormat("Y-m-d",$register_values["dob"]);
	$dob_valid = $dob && $dob->format("Y-m-d")===$register_values["dob"] && $register_values["dob"]>="1900-01-01" && $register_values["dob"]<=date("Y-m-d");
	if(!$dob_valid)
	{
		$register_errors["dob"] = "Enter a valid date of birth.";
	}
	if(!in_array($register_values["state"],$states,true))
	{
		$register_errors["state"] = "Select your state.";
	}
	if(strlen($register_values["city"])<2 || strlen($register_values["city"])>50)
	{
		$register_errors["city"] = "Enter your city or town using 2 to 50 characters.";
	}
	if(!preg_match("/^\d{5}$/",$register_values["postcode"]))
	{
		$register_errors["postcode"] = "Enter a valid 5-digit postcode.";
	}

	if(!isset($register_errors["email"]))
	{
		$stmt = mysqli_prepare($connect,"SELECT customer_id FROM customer WHERE customer_email=? LIMIT 1");
		if($stmt)
		{
			mysqli_stmt_bind_param($stmt,"s",$register_values["email"]);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			if(mysqli_stmt_num_rows($stmt)>0)
			{
				$register_errors["email"] = "This email is already registered. Log in or use another email.";
			}
			mysqli_stmt_close($stmt);
		}
	}

	if(count($register_errors)===0)
	{
		$join_date = date("Y-m-d");
		$stmt = mysqli_prepare($connect,"INSERT INTO customer(customer_name,customer_email,customer_password,customer_phone,customer_gender,customer_dob,customer_state,customer_city,customer_postcode,customer_joindate) VALUES(?,?,?,?,?,?,?,?,?,?)");
		$inserted = false;
		if($stmt)
		{
			mysqli_stmt_bind_param(
				$stmt,
				"ssssssssss",
				$register_values["name"],
				$register_values["email"],
				$password,
				$register_values["phone"],
				$register_values["gender"],
				$register_values["dob"],
				$register_values["state"],
				$register_values["city"],
				$register_values["postcode"],
				$join_date
			);
			try
			{
				$inserted = mysqli_stmt_execute($stmt);
			}
			catch(mysqli_sql_exception $exception)
			{
				if((int)$exception->getCode()===1062)
				{
					$register_errors["email"] = "This email is already registered. Log in or use another email.";
				}
				else
				{
					$register_errors["general"] = "We could not create your account right now. Please try again.";
				}
			}
			mysqli_stmt_close($stmt);
		}

		if($inserted)
		{
			$welcome_sent = easyorder_send_welcome_email($register_values["email"],$register_values["name"],"Customer");
			if(!$welcome_sent)
			{
				error_log("EasyOrder customer welcome email could not be sent.");
			}
			unset($_SESSION["register_csrf"]);
			$_SESSION["registration_success"] = $welcome_sent
				? "Registration successful. A welcome email has been sent and you can now log in."
				: "Registration successful. Email delivery is unavailable, but you can still log in now.";
			header("location:login.php");
			exit();
		}
		else if(count($register_errors)===0)
		{
			$register_errors["general"] = "We could not create your account right now. Please try again.";
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Your Account</title>
<link rel="stylesheet" href="style.css?v=20260916-1">
<script src="password_ux.js?v=20260926-1" defer></script>
</head>
<body>

<div id="header">
<img src="image/logo.png" width="80" height="80" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar">
<a href="./">Home</a>
<a href="login.php">Login</a>
</div>

<main id="main">
<h2 class="section-title">Create Your Account</h2>
<p class="intro">Register once to order food, save your delivery details and track your orders.</p>

<div class="entry-card entry-card-wide">
<?php if(isset($register_errors["general"])) { ?>
<p class="form-message form-message-error" role="alert"><?php echo register_h($register_errors["general"]); ?></p>
<?php } ?>

<form method="post" action="register.php" novalidate>
<input type="hidden" name="register_csrf" value="<?php echo register_h($_SESSION["register_csrf"]); ?>">

<div class="entry-grid">
<div class="entry-field entry-field-full">
<label for="cust_name">Full Name *</label>
<input type="text" id="cust_name" name="cust_name" minlength="2" maxlength="100" autocomplete="name" value="<?php echo register_h($register_values["name"]); ?>" placeholder="e.g. Ali bin Ahmad"<?php if(isset($register_errors["name"])) { ?> aria-invalid="true" aria-describedby="cust_name-error"<?php } ?> required>
<?php if(isset($register_errors["name"])) { ?><span class="field-error" id="cust_name-error"><?php echo register_h($register_errors["name"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_email">Email *</label>
<input type="email" id="cust_email" name="cust_email" maxlength="100" autocomplete="email" value="<?php echo register_h($register_values["email"]); ?>" placeholder="e.g. customer@email.com"<?php if(isset($register_errors["email"])) { ?> aria-invalid="true" aria-describedby="cust_email-error"<?php } ?> required>
<?php if(isset($register_errors["email"])) { ?><span class="field-error" id="cust_email-error"><?php echo register_h($register_errors["email"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_confirm_email">Confirm Email *</label>
<input type="email" id="cust_confirm_email" name="cust_confirm_email" maxlength="100" autocomplete="email" value="<?php echo register_h($register_values["confirm_email"]); ?>"<?php if(isset($register_errors["confirm_email"])) { ?> aria-invalid="true" aria-describedby="cust_confirm_email-error"<?php } ?> required>
<?php if(isset($register_errors["confirm_email"])) { ?><span class="field-error" id="cust_confirm_email-error"><?php echo register_h($register_errors["confirm_email"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_password">Password *</label>
<input type="password" id="cust_password" name="cust_password" minlength="8" maxlength="72" autocomplete="new-password" data-password-strength="register-password-strength" aria-describedby="register-password-strength<?php if(isset($register_errors["password"])) echo " cust_password-error"; ?>"<?php if(isset($register_errors["password"])) { ?> aria-invalid="true"<?php } ?> required>
<small id="register-password-strength" class="password-feedback" data-state="empty" aria-live="polite">Use 8 or more characters.</small>
<?php if(isset($register_errors["password"])) { ?><span class="field-error" id="cust_password-error"><?php echo register_h($register_errors["password"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_confirm_password">Confirm Password *</label>
<input type="password" id="cust_confirm_password" name="cust_confirm_password" minlength="8" maxlength="72" autocomplete="new-password" data-password-confirm="cust_password" data-password-match="register-password-match" aria-describedby="register-password-match<?php if(isset($register_errors["confirm_password"])) echo " cust_confirm_password-error"; ?>"<?php if(isset($register_errors["confirm_password"])) { ?> aria-invalid="true"<?php } ?> required>
<small id="register-password-match" class="password-feedback" data-state="empty" aria-live="polite">Enter the same password again.</small>
<?php if(isset($register_errors["confirm_password"])) { ?><span class="field-error" id="cust_confirm_password-error"><?php echo register_h($register_errors["confirm_password"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_phone">Phone Number *</label>
<input type="text" id="cust_phone" name="cust_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" autocomplete="tel" value="<?php echo register_h($register_values["phone"]); ?>" placeholder="e.g. 0123456789"<?php if(isset($register_errors["phone"])) { ?> aria-invalid="true" aria-describedby="cust_phone-error"<?php } ?> required>
<?php if(isset($register_errors["phone"])) { ?><span class="field-error" id="cust_phone-error"><?php echo register_h($register_errors["phone"]); ?></span><?php } ?>
</div>

<fieldset class="entry-choice-group">
<legend>Gender *</legend>
<label><input type="radio" id="gender_male" name="gender" value="Male"<?php if(isset($register_errors["gender"])) { ?> aria-invalid="true" aria-describedby="gender-error"<?php } ?> <?php if($register_values["gender"]==="Male") echo "checked"; ?>> Male</label>
<label><input type="radio" id="gender_female" name="gender" value="Female"<?php if(isset($register_errors["gender"])) { ?> aria-invalid="true" aria-describedby="gender-error"<?php } ?> <?php if($register_values["gender"]==="Female") echo "checked"; ?>> Female</label>
<?php if(isset($register_errors["gender"])) { ?><span class="field-error" id="gender-error"><?php echo register_h($register_errors["gender"]); ?></span><?php } ?>
</fieldset>

<div class="entry-field">
<label for="cust_dob">Date of Birth *</label>
<input type="date" id="cust_dob" name="cust_dob" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>" autocomplete="bday" value="<?php echo register_h($register_values["dob"]); ?>"<?php if(isset($register_errors["dob"])) { ?> aria-invalid="true" aria-describedby="cust_dob-error"<?php } ?> required>
<?php if(isset($register_errors["dob"])) { ?><span class="field-error" id="cust_dob-error"><?php echo register_h($register_errors["dob"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="state">State *</label>
<select id="state" name="state" autocomplete="address-level1"<?php if(isset($register_errors["state"])) { ?> aria-invalid="true" aria-describedby="state-error"<?php } ?> required>
<option value="">Select your state</option>
<?php foreach($states as $state_name) { ?>
<option value="<?php echo register_h($state_name); ?>" <?php if($register_values["state"]===$state_name) echo "selected"; ?>><?php echo register_h($state_name); ?></option>
<?php } ?>
</select>
<?php if(isset($register_errors["state"])) { ?><span class="field-error" id="state-error"><?php echo register_h($register_errors["state"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_city">City *</label>
<input type="text" id="cust_city" name="cust_city" minlength="2" maxlength="50" autocomplete="address-level2" value="<?php echo register_h($register_values["city"]); ?>" placeholder="e.g. Muar"<?php if(isset($register_errors["city"])) { ?> aria-invalid="true" aria-describedby="cust_city-error"<?php } ?> required>
<?php if(isset($register_errors["city"])) { ?><span class="field-error" id="cust_city-error"><?php echo register_h($register_errors["city"]); ?></span><?php } ?>
</div>

<div class="entry-field">
<label for="cust_postcode">Postcode *</label>
<input type="text" id="cust_postcode" name="cust_postcode" inputmode="numeric" pattern="[0-9]{5}" minlength="5" maxlength="5" autocomplete="postal-code" value="<?php echo register_h($register_values["postcode"]); ?>" placeholder="e.g. 84000"<?php if(isset($register_errors["postcode"])) { ?> aria-invalid="true" aria-describedby="cust_postcode-error"<?php } ?> required>
<?php if(isset($register_errors["postcode"])) { ?><span class="field-error" id="cust_postcode-error"><?php echo register_h($register_errors["postcode"]); ?></span><?php } ?>
</div>
</div>

<div class="entry-actions">
<input type="submit" name="signupbtn" value="Create Account">
<input type="reset" value="Clear Form">
</div>
</form>
</div>

<p class="login-note">Already have an account? <a href="login.php">Log in here</a>.</p>
</main>

<footer>
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

<script>
(function()
{
	const form=document.querySelector('form[action="register.php"]');
	form.addEventListener("reset",function(event)
	{
		event.preventDefault();
		const errorIds=new Set(Array.from(form.querySelectorAll(".field-error"),function(error){ return error.id; }));
		form.querySelectorAll("input:not([type=hidden]):not([type=submit]):not([type=reset]),select").forEach(function(input)
		{
			if(input.type==="radio") input.checked=false;
			else input.value="";
			input.setCustomValidity("");
			input.removeAttribute("aria-invalid");
		});
		form.querySelectorAll("[aria-describedby]").forEach(function(element)
		{
			const ids=element.getAttribute("aria-describedby").split(/\s+/).filter(function(id){ return !errorIds.has(id); });
			if(ids.length) element.setAttribute("aria-describedby",ids.join(" "));
			else element.removeAttribute("aria-describedby");
		});
		form.querySelectorAll(".field-error").forEach(function(error){ error.remove(); });
		form.querySelectorAll("[data-password-strength],[data-password-confirm]").forEach(function(input)
		{
			input.dispatchEvent(new Event("input",{bubbles:true}));
		});
		document.getElementById("cust_name").focus();
	});
	const firstError=form.querySelector('[aria-invalid="true"]');
	if(firstError) firstError.focus();
})();
</script>

</body>
</html>
