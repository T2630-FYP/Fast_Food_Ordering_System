<?php
include("dataconnection.php");
require_once("customer_navigation.php");
require_once("email_helpers.php");

if(isset($_SESSION["customer_id"]) && $_SERVER["REQUEST_METHOD"]!=="POST")
{
	header("location:profile.php");
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

// Select the panel containing server feedback; passwords are never repopulated.
$signup_account_keys = array("name","phone","email","confirm_email","password","confirm_password");
$signup_personal_keys = array("gender","dob","state","city","postcode");
$signup_account_errors = count(array_intersect($signup_account_keys,array_keys($register_errors)))>0;
$signup_personal_errors = count(array_intersect($signup_personal_keys,array_keys($register_errors)))>0;
$signup_start_step = !$signup_account_errors && $signup_personal_errors && !isset($register_errors["general"]) ? 2 : 1;
$signup_password_reminder = $_SERVER["REQUEST_METHOD"]==="POST" && count($register_errors)>0;
function signup_field_aria($key,$id,$description="")
{
    global $register_errors;
    if(isset($register_errors[$key])) $description = trim($description." ".$id."-error");
    return (isset($register_errors[$key]) ? ' aria-invalid="true"' : "")
        .($description!=="" ? ' aria-describedby="'.register_h($description).'"' : "");
}
function signup_field_error($key,$id)
{
    global $register_errors;
    if(isset($register_errors[$key])) echo '<small class="eo-server-error" id="'.register_h($id).'-error">'.register_h($register_errors[$key]).'</small>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="image/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Your Account</title>
<link rel="stylesheet" href="style.css?v=20261024-auth1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<script src="customer_navigation.js?v=20261024-auth1" defer></script>
<script src="password_ux.js?v=20260926-1" defer></script>
<style data-eo-auth-style="signup">

/* ======================================
   EASYORDER SIGN UP V11
   DESKTOP REGISTRATION DESIGN
====================================== */

#main.eo-signup {
    --eo-cream: #FFF8F0;
    --eo-paper: #FFFCF9;
    --eo-brown: #39292B;
    --eo-ink: #302726;
    --eo-muted: #746D69;
    --eo-red: #C8102E;
    --eo-input: #FAF6F3;
    --eo-line: #E9DFDB;
    --eo-peach: #FFD09A;
}

/* ======================================
   MAIN CONTAINER
====================================== */

#main.eo-signup {
    box-sizing: border-box;
    width: min(1380px, calc(100% - 80px));
    max-width: none;
    padding: 0;
    margin: 48px auto 64px;
    font-family: "Segoe UI", Arial, sans-serif;
}

.eo-signup * {
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

/* ======================================
   MAIN CARD
====================================== */

.eo-auth-card {
    display: grid;

    /* FORM LEFT / PHOTO RIGHT */
    grid-template-columns: 1.14fr 0.86fr;

    min-height: 824px;

    background: var(--eo-paper);

    border: 1px solid rgba(80,36,46,.08);
    border-radius: 28px;
    overflow: hidden;

    box-shadow:
        0 27px 75px rgba(65,29,37,.11),
        0 3px 10px rgba(65,29,37,.025);
}

/* ======================================
   LEFT FORM PANEL
====================================== */

.eo-form-panel {
    background: var(--eo-paper);
    display: flex;
    align-items: flex-start;

    padding: 54px clamp(46px,4.5vw,74px) 40px;
}

.eo-form-inner {
    width: 100%;
    max-width: 680px;
    margin: 0 auto;
}

.eo-form-panel h1 {
    font-size: clamp(38px,3.2vw,46px);
    font-weight: 800;
    line-height: 1.14;
    letter-spacing: -1.6px;

    color: var(--eo-ink);
    margin: 0 0 10px;
}

.eo-subtitle {
    font-size: 16px;
    line-height: 1.5;
    color: var(--eo-muted);
    margin: 0;
}

/* ======================================
   TWO STEP PROGRESS NAVIGATION
====================================== */

.eo-progress {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 24px;

    margin: 32px 0 28px;
}

.eo-progress-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 13px;

    min-height: 68px;
    padding: 4px 0 20px;
    color: #948782;
}

/* BOTTOM PROGRESS LINE */

.eo-progress-item::after {
    content: "";
    position: absolute;

    left: 0;
    right: 0;
    bottom: 0;

    height: 3px;
    border-radius: 10px;
    background: #EDE4DF;
}

.eo-progress-item.active::after,
.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]::after {
    background: var(--eo-brown);
}

/* STEP NUMBER */

.eo-step-number {
    position: relative;
    flex: 0 0 40px;

    width: 40px;
    height: 40px;

    display: grid;
    place-items: center;

    border-radius: 50%;
    border: 1.5px solid #E4D9D3;

    background: var(--eo-paper);
    color: #A89992;

    font-size: 13px;
    font-weight: 750;
}

.eo-progress-item.active .eo-step-number {
    background: var(--eo-brown);
    border-color: var(--eo-brown);
    color: #FFF9F4;
}

/* STEP LABELS */

.eo-step-copy strong {
    display: block;

    font-size: 14.5px;
    font-weight: 700;
    color: #8A7D78;
}

.eo-step-copy > span {
    display: block;
    font-size: 12px;
    line-height: 1.38;
    margin-top: 4px;
    color: #A29690;
}

.eo-progress-item.active .eo-step-copy strong {
    color: var(--eo-brown);
}

/* COMPLETED STEP */

.eo-complete-check {
    display: none;
}

.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]
.eo-step-number {
    background: #F1E9E3;
    border-color: #E4D8D0;
    font-size: 0;
}

.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]
.eo-complete-check {
    display: grid; place-items: center; position: absolute; inset: 0;
    color: var(--eo-brown); font-size: 19px;
}

.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]
.eo-step-copy strong {
    color: var(--eo-brown);
}

/* ======================================
   STEP CONTENT
====================================== */

.eo-form-step {
    display: none;
    animation: none;
}

.eo-form-step.is-active {
    display: block;
}


.eo-section-label {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.65px;
    color: #A48D88;

    margin: 0 0 19px;
}

/* ======================================
   FORM GRID
====================================== */

.eo-form-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));

    column-gap: 20px;
    row-gap: 23px;
}

.eo-field {
    min-width: 0;
}

.eo-field.span2 {
    grid-column: 1 / -1;
}

.eo-field > label {
    display: block;

    font-size: 14px;
    font-weight: 700;
    color: var(--eo-ink);

    margin-bottom: 10px;
}

/* ======================================
   INPUTS
====================================== */

.eo-input-wrap {
    position: relative;
}

.eo-input-wrap input,
.eo-input-wrap select {
    display: block;

    width: 100%;
    height: 59px;

    background: var(--eo-input);
    border: 1.5px solid var(--eo-line);
    border-radius: 12px;

    padding: 0 17px;

    color: var(--eo-ink);
    font-size: 15px;

    outline: none;

    transition:
        background .15s,
        border-color .15s,
        box-shadow .15s;
}

.eo-input-wrap input::placeholder {
    color: #A49895;
}

.eo-input-wrap input:focus,
.eo-input-wrap select:focus {
    background: #FFFFFF;
    border-color: #C94A5E;
    box-shadow: 0 0 0 4px rgba(200,16,46,.08);
}

.eo-input-wrap input[data-password-strength],
.eo-input-wrap input[data-password-confirm] {
    padding-right: 47px;
}

/* SELECT DROPDOWN */

.eo-input-wrap select {
    cursor: pointer;
    padding-right: 38px;
    color: #857672;
}

/* ======================================
   PASSWORD EYE ICON
====================================== */

.eo-eye {
    position: absolute;
    right: 9px;
    top: 50%;

    transform: translateY(-50%);

    width: 40px;
    height: 40px;

    display: grid;
    place-items: center;

    padding: 0;
    border: none;
    border-radius: 9px;

    background: transparent;
    color: #94827E;
    cursor: pointer;
}

.eo-eye svg {
    width: 21px;
    height: 21px;
}

.eo-eye:hover {
    color: #A6102D;
    background: #FFF0EE;
}

.eo-eye .eo-eye-closed { display: none; }
.eo-eye.is-visible .eo-eye-open { display: none; }
.eo-eye.is-visible .eo-eye-closed { display: block; }

/* ======================================
   GENDER OPTIONS
====================================== */

.eo-gender-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.eo-gender-options label {
    position: relative;
    cursor: pointer;
}

.eo-gender-options input {
    position: absolute;
    opacity: 0;
}

.eo-gender-options span {
    display: grid;
    place-items: center;

    height: 59px;

    background: var(--eo-input);
    border: 1.5px solid var(--eo-line);
    border-radius: 12px;

    font-size: 15px;
    color: var(--eo-muted);
}

.eo-gender-options input:checked + span {
    background: #FFF0EE;
    border-color: var(--eo-red);
    color: #A6102D;
    font-weight: 700;
}

.eo-gender-options input:focus-visible + span {
    outline: 3px solid #E9AD70;
    outline-offset: 3px;
}

/* ======================================
   ACTION BUTTONS
====================================== */

.eo-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 29px;
}

/* MAIN RED BUTTON */

.eo-primary {
    flex: 1;

    display: flex;
    justify-content: space-between;
    align-items: center;

    height: 61px;

    padding: 0 23px;
    border: none;
    border-radius: 12px;

    background: linear-gradient(
        105deg,
        #C8102E 0%,
        #A30B28 100%
    );

    color: #FFFFFF;
    font-size: 16px;
    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 11px 23px rgba(149,13,41,.17);

    transition:
        transform .15s,
        box-shadow .15s;
}

.eo-primary:hover {
    transform: translateY(-2px);
    box-shadow:
        0 13px 25px rgba(149,13,41,.25);
}

.eo-primary .arrow {
    font-size: 23px;
    font-weight: 400;
}

/* BACK BUTTON */

.eo-back {
    min-width: 110px;
    height: 61px;

    background: transparent;
    border: 1.5px solid #E4D7D2;
    border-radius: 12px;

    color: #6C5A56;
    font-size: 14px;
    font-weight: 700;

    cursor: pointer;
}

/* SIGN IN LINK */

.eo-bottom-link {
    margin-top: 22px;
    text-align: center;

    font-size: 14px;
    color: var(--eo-muted);
}

.eo-bottom-link a {
    color: #A6102D;
    font-weight: 800;
    text-decoration: none;
    margin-left: 4px;
}

/* ======================================
   RIGHT FOOD SHOWCASE V11
====================================== */

.eo-right-showcase {
    position: relative;
    min-height: 0;

    background: var(--eo-brown);
    overflow: hidden;

    isolation: isolate;
}

/* EXPANDED RESTAURANT PHOTO */

.eo-right-showcase::before {
    content: "";

    position: absolute;
    inset: 0;
    z-index: 0;

    background-image:
        linear-gradient(
            180deg,
            #39292B 0%,
            #39292B 12%,
            rgba(57,41,43,.94) 22%,
            rgba(57,41,43,.60) 32%,
            rgba(57,41,43,.02) 51%,
            rgba(57,41,43,0) 61%
        ),
        url("image/login.jpg?v=20261024-signup1");

    background-position: center bottom;
    background-size: 100% auto;
    background-repeat: no-repeat;
}

/* RIGHT HEADING */

.eo-showcase-title {
    position: relative;
    z-index: 2;

    padding: 70px 52px 0;
}

.eo-showcase-title h2 {
    margin: 0;

    color: #FFF8F2;
    font-size: clamp(38px,3.6vw,54px);
    font-weight: 800;
    line-height: 1.13;
    letter-spacing: -1.6px;
}

/* CHAMPAGNE ITALIC */

.eo-showcase-title h2 em {
    color: var(--eo-peach);

    font-family: Georgia, "Times New Roman", serif;
    font-size: 1.02em;
    font-style: italic;
    font-weight: 700;

    letter-spacing: -1.3px;
}

/* ======================================
   MESSAGES AND SMALL TEXT
====================================== */

.eo-step-helper {
    margin: 18px 0 0;
    color: #8C7C78;
    font-size: 13px;
    line-height: 1.45;
}

.eo-server-error {
    display: block;
    margin-top: 7px;
    color: #B94142;
    font-size: 12px;
    line-height: 1.4;
}

.eo-server-error[hidden] {
    display: none;
}

.eo-signup button:focus-visible,
.eo-signup a:focus-visible {
    outline: 3px solid #E9AD70;
    outline-offset: 3px;
}

/* SMALLER DESKTOP SCREENS */

@media (max-width: 1200px) {
    #main.eo-signup {
        width: calc(100% - 48px);
    }

    .eo-form-panel {
        padding-left: 38px;
        padding-right: 38px;
    }

    .eo-showcase-title {
        padding-left: 34px;
        padding-right: 25px;
    }

    .eo-progress {
        gap: 18px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .eo-signup * {
        animation: none !important;
        transition: none !important;
    }
}

/* Local registration feedback and semantic field groups. */
.eo-signup .eo-gender-field { border: 0; padding: 0; margin: 0; }
.eo-signup .eo-gender-field > legend { padding: 0; margin-bottom: 10px; color: var(--eo-ink); font-size: 14px; font-weight: 700; }
.eo-signup .eo-eye svg { stroke-linecap: round; stroke-linejoin: round; }
.eo-signup .eo-complete-check svg { width: 18px; height: 18px; }
.eo-signup .password-feedback { font-size: 12px; line-height: 1.4; }
.eo-signup .eo-section-label:focus { outline: none; }
.eo-signup [aria-invalid="true"] { border-color: #B94142; }
.eo-signup .eo-form-step[hidden] { display: none; }
.eo-signup .eo-password-reminder { color: #A6102D; }

</style>
<noscript><style>
.eo-signup .eo-form-step { display: block; animation: none; }
.eo-signup .eo-progress, .eo-signup #next-btn, .eo-signup #back-btn, .eo-signup .eo-eye { display: none; }
.eo-signup .eo-form-step + .eo-form-step { margin-top: 32px; }
</style></noscript>
</head>
<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-signup eo-auth-page" data-auth-view="signup" data-start-step="<?php echo $signup_start_step; ?>">
<div class="eo-auth-card eo-auth-frame">
<section class="eo-form-panel eo-auth-form-panel" aria-labelledby="signup-title">
<div class="eo-form-inner">
<h1 id="signup-title">Create your account</h1>
<p class="eo-subtitle" id="signup-subtitle" aria-live="polite"><?php echo $signup_start_step===2 ? "Just one more step to get started." : "A few details, then you're ready to order."; ?></p>
<div class="eo-progress" data-step="<?php echo $signup_start_step; ?>" role="list" aria-label="Registration progress">
<div class="eo-progress-item<?php echo $signup_start_step===1 ? " active" : ""; ?>" data-indicator="1" role="listitem"<?php if($signup_start_step===1) echo ' aria-current="step"'; ?>>
<span class="eo-step-number">01<span class="eo-complete-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span></span>
<div class="eo-step-copy"><strong>Account details</strong><span>Sign-in information</span></div>
</div>
<div class="eo-progress-item<?php echo $signup_start_step===2 ? " active" : ""; ?>" data-indicator="2" role="listitem"<?php if($signup_start_step===2) echo ' aria-current="step"'; ?>>
<span class="eo-step-number">02</span><div class="eo-step-copy"><strong>Personal details</strong><span>Just a few more details</span></div>
</div>
</div>
<?php if(isset($register_errors["general"])) { ?><p class="form-message form-message-error" role="alert"><?php echo register_h($register_errors["general"]); ?></p><?php } ?>
<form id="signup-form" method="post" action="register.php" novalidate>
<input type="hidden" name="register_csrf" value="<?php echo register_h($_SESSION["register_csrf"]); ?>">
<input type="hidden" name="signupbtn" value="1">
<div class="eo-form-step<?php echo $signup_start_step===1 ? " is-active" : ""; ?>" data-step="1" aria-labelledby="signup-account-label">
<p class="eo-section-label" id="signup-account-label" tabindex="-1">YOUR ACCOUNT</p>
<div class="eo-form-grid">
<div class="eo-field">
<label for="cust_name">Full name</label>
<div class="eo-input-wrap"><input id="cust_name" name="cust_name" type="text" placeholder="Your full name" autocomplete="name" minlength="2" maxlength="100" value="<?php echo register_h($register_values["name"]); ?>"<?php echo signup_field_aria("name","cust_name",""); ?> required></div>
<?php signup_field_error("name","cust_name"); ?>
</div>
<div class="eo-field">
<label for="cust_phone">Phone number</label>
<div class="eo-input-wrap"><input id="cust_phone" name="cust_phone" type="tel" placeholder="e.g. 0123456789" autocomplete="tel" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" value="<?php echo register_h($register_values["phone"]); ?>"<?php echo signup_field_aria("phone","cust_phone",""); ?> required></div>
<?php signup_field_error("phone","cust_phone"); ?>
</div>
<div class="eo-field">
<label for="cust_email">Email address</label>
<div class="eo-input-wrap"><input id="cust_email" name="cust_email" type="email" placeholder="you@example.com" autocomplete="email" maxlength="100" value="<?php echo register_h($register_values["email"]); ?>"<?php echo signup_field_aria("email","cust_email",""); ?> required></div>
<?php signup_field_error("email","cust_email"); ?>
</div>
<div class="eo-field">
<label for="cust_confirm_email">Confirm email</label>
<div class="eo-input-wrap"><input id="cust_confirm_email" name="cust_confirm_email" type="email" placeholder="Re-enter your email" autocomplete="email" maxlength="100" value="<?php echo register_h($register_values["confirm_email"]); ?>"<?php echo signup_field_aria("confirm_email","cust_confirm_email",""); ?> required></div>
<?php signup_field_error("confirm_email","cust_confirm_email"); ?>
</div>
<div class="eo-field"><label for="cust_password">Password</label>
<div class="eo-input-wrap"><input type="password" id="cust_password" name="cust_password" placeholder="Create a password" minlength="8" maxlength="72" autocomplete="new-password" data-password-strength="register-password-strength"<?php echo signup_field_aria("password","cust_password","register-password-strength"); ?> required><button type="button" class="eo-eye" data-toggle="cust_password" aria-controls="cust_password" aria-label="Show password" aria-pressed="false">
<svg class="eo-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="eo-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="signup-eye-gap-cust_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#signup-eye-gap-cust_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg></button></div>
<small id="register-password-strength" class="password-feedback" data-state="empty" aria-live="polite">Use 8 or more characters.</small>
<?php signup_field_error("password","cust_password"); ?></div>
<div class="eo-field"><label for="cust_confirm_password">Confirm password</label>
<div class="eo-input-wrap"><input type="password" id="cust_confirm_password" name="cust_confirm_password" placeholder="Re-enter password" minlength="8" maxlength="72" autocomplete="new-password" data-password-confirm="cust_password" data-password-match="register-password-match"<?php echo signup_field_aria("confirm_password","cust_confirm_password","register-password-match"); ?> required><button type="button" class="eo-eye" data-toggle="cust_confirm_password" aria-controls="cust_confirm_password" aria-label="Show password" aria-pressed="false">
<svg class="eo-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="eo-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="signup-eye-gap-cust_confirm_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#signup-eye-gap-cust_confirm_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg></button></div>
<small id="register-password-match" class="password-feedback" data-state="empty" aria-live="polite">Enter the same password again.</small>
<?php signup_field_error("confirm_password","cust_confirm_password"); ?></div>
</div>
<div class="eo-actions"><button type="button" id="next-btn" class="eo-primary"><span>Continue to personal details</span><span class="arrow" aria-hidden="true">→</span></button></div>
</div>
<div class="eo-form-step<?php echo $signup_start_step===2 ? " is-active" : ""; ?>" data-step="2" aria-labelledby="signup-personal-label">
<p class="eo-section-label" id="signup-personal-label" tabindex="-1">A LITTLE ABOUT YOU</p>
<div class="eo-form-grid">
<fieldset class="eo-field eo-gender-field"><legend>Gender</legend><div class="eo-gender-options">
<label><input type="radio" id="gender_male" name="gender" value="Male"<?php echo signup_field_aria("gender","gender","gender-client-error"); ?> <?php if($register_values["gender"]==="Male") echo "checked"; ?>><span>Male</span></label>
<label><input type="radio" id="gender_female" name="gender" value="Female"<?php echo signup_field_aria("gender","gender","gender-client-error"); ?> <?php if($register_values["gender"]==="Female") echo "checked"; ?>><span>Female</span></label>
</div><small id="gender-client-error" class="eo-server-error" hidden>Please select your gender.</small><?php signup_field_error("gender","gender"); ?></fieldset>
<div class="eo-field">
<label for="cust_dob">Date of birth</label>
<div class="eo-input-wrap"><input id="cust_dob" name="cust_dob" type="date" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>" autocomplete="bday" value="<?php echo register_h($register_values["dob"]); ?>"<?php echo signup_field_aria("dob","cust_dob",""); ?> required></div>
<?php signup_field_error("dob","cust_dob"); ?>
</div>
<div class="eo-field span2"><label for="state">State</label><div class="eo-input-wrap">
<select id="state" name="state" autocomplete="address-level1"<?php echo signup_field_aria("state","state",""); ?> required><option value="">Select your state</option>
<?php foreach($states as $state_name) { ?><option value="<?php echo register_h($state_name); ?>" <?php if($register_values["state"]===$state_name) echo "selected"; ?>><?php echo register_h($state_name); ?></option><?php } ?>
</select></div><?php signup_field_error("state","state"); ?></div>
<div class="eo-field">
<label for="cust_city">City / town</label>
<div class="eo-input-wrap"><input id="cust_city" name="cust_city" type="text" placeholder="e.g. Johor Bahru" minlength="2" maxlength="50" autocomplete="address-level2" value="<?php echo register_h($register_values["city"]); ?>"<?php echo signup_field_aria("city","cust_city",""); ?> required></div>
<?php signup_field_error("city","cust_city"); ?>
</div>
<div class="eo-field">
<label for="cust_postcode">Postcode</label>
<div class="eo-input-wrap"><input id="cust_postcode" name="cust_postcode" type="text" placeholder="e.g. 80000" inputmode="numeric" pattern="[0-9]{5}" minlength="5" maxlength="5" autocomplete="postal-code" value="<?php echo register_h($register_values["postcode"]); ?>"<?php echo signup_field_aria("postcode","cust_postcode",""); ?> required></div>
<?php signup_field_error("postcode","cust_postcode"); ?>
</div>
</div>
<p class="eo-step-helper">Your details will be used to set up your EasyOrder account.</p>
<?php if($signup_password_reminder) { ?><p class="eo-step-helper eo-password-reminder" id="signup-password-reminder">Re-enter your password before submitting again.</p><?php } ?>
<div class="eo-actions"><button type="button" class="eo-back" id="back-btn">← Back</button><button type="submit" class="eo-primary"><span>Create account</span><span class="arrow" aria-hidden="true">→</span></button></div>
</div>
</form>
<div class="eo-bottom-link">Already have an account? <a href="login.php">Sign in ↗</a></div>
</div>
</section>
<aside class="eo-auth-showcase" aria-label="EasyOrder burger, fries, fried chicken and cola">
<img class="eo-auth-photo" src="image/login.jpg?v=20261024-auth1" alt="Burger, fries, fried chicken and cola">
<h2 class="eo-auth-showcase-heading">Good things<br><em>start here.</em></h2>
</aside>
</div>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

<script data-eo-auth-init="signup">
(function () {
    "use strict";
    const root = document.querySelector(".eo-signup");
    const form = document.getElementById("signup-form");
    if (!root || !form) return;
    const steps = [...root.querySelectorAll(".eo-form-step")];
    const indicators = [...root.querySelectorAll(".eo-progress-item")];
    const progress = root.querySelector(".eo-progress");
    const subtitle = document.getElementById("signup-subtitle");
    const email = document.getElementById("cust_email");
    const confirmEmail = document.getElementById("cust_confirm_email");
    const password = document.getElementById("cust_password");
    const confirmPassword = document.getElementById("cust_confirm_password");
    const genderError = document.getElementById("gender-client-error");
    const reminder = document.getElementById("signup-password-reminder");
    let currentStep = 1;

    function showStep(number, focus) {
        currentStep = number;
        steps.forEach(function (step) {
            const active = step.dataset.step === String(number);
            step.classList.toggle("is-active", active);
            step.hidden = !active;
        });
        indicators.forEach(function (item) {
            const active = item.dataset.indicator === String(number);
            item.classList.toggle("active", active);
            if (active) item.setAttribute("aria-current", "step");
            else item.removeAttribute("aria-current");
        });
        progress.dataset.step = String(number);
        subtitle.textContent = number === 1
            ? "A few details, then you're ready to order."
            : "Just one more step to get started.";
        if (reminder) reminder.hidden = password.value !== "" && confirmPassword.value !== "";
        if (focus) steps[number - 1].querySelector(".eo-section-label").focus({preventScroll: true});
    }

    function updateMatches() {
        const matchingEmail = email.value.trim().toLowerCase() === confirmEmail.value.trim().toLowerCase();
        confirmEmail.setCustomValidity(confirmEmail.value !== "" && !matchingEmail ? "Email addresses do not match." : "");
        confirmPassword.setCustomValidity(confirmPassword.value !== "" && confirmPassword.value !== password.value ? "Passwords do not match." : "");
    }

    function validateStep(number) {
        const panel = steps[number - 1];
        panel.querySelectorAll("input:not([type='password']):not([data-password-strength]):not([data-password-confirm]):not([type='radio'])").forEach(function (field) {
            if (field.value !== field.value.trim()) field.value = field.value.trim();
        });
        panel.querySelectorAll("input:not([type='radio']), select").forEach(function (field) { field.setCustomValidity(""); });
        if (number === 1) updateMatches();
        if (number === 2) {
            const gender = panel.querySelector('input[name="gender"]:checked');
            genderError.hidden = !!gender;
            panel.querySelectorAll('input[name="gender"]').forEach(function (field) {
                if (gender) field.removeAttribute("aria-invalid");
                else field.setAttribute("aria-invalid", "true");
            });
            if (!gender) {
                panel.querySelector('input[name="gender"]').focus();
                return false;
            }
        }
        for (const field of panel.querySelectorAll("input:not([type='radio']), select")) {
            if (field.value !== "" && field.minLength > 0 && field.value.length < field.minLength) {
                field.setCustomValidity("Please use at least " + field.minLength + " characters.");
            }
            if (field.maxLength > 0 && field.value.length > field.maxLength) {
                field.setCustomValidity("Please use no more than " + field.maxLength + " characters.");
            }
            if (!field.checkValidity()) {
                field.focus();
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    function goNext() {
        if (validateStep(1)) showStep(2, true);
    }
    document.getElementById("next-btn").addEventListener("click", goNext);
    document.getElementById("back-btn").addEventListener("click", function () { showStep(1, true); });
    [email, confirmEmail, password, confirmPassword].forEach(function (field) {
        field.addEventListener("input", updateMatches);
    });
    form.querySelectorAll('input[name="gender"]').forEach(function (field) {
        field.addEventListener("change", function () {
            genderError.hidden = true;
            form.querySelectorAll('input[name="gender"]').forEach(function (input) { input.removeAttribute("aria-invalid"); });
        });
    });
    root.querySelectorAll(".eo-eye").forEach(function (button) {
        button.addEventListener("click", function () {
            const field = document.getElementById(button.dataset.toggle);
            const visible = field.type === "password";
            field.type = visible ? "text" : "password";
            button.classList.toggle("is-visible", visible);
            button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
            button.setAttribute("aria-pressed", String(visible));
            field.focus();
        });
    });
    form.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && !event.isComposing && currentStep === 1 && event.target.matches("input")) {
            event.preventDefault();
            goNext();
        }
    });
    form.addEventListener("submit", function (event) {
        if (currentStep === 1) {
            event.preventDefault();
            goNext();
            return;
        }
        showStep(1);
        if (!validateStep(1)) {
            event.preventDefault();
            return;
        }
        showStep(2);
        if (!validateStep(2)) event.preventDefault();
    });
    showStep(Number(root.dataset.startStep) === 2 ? 2 : 1);
    const firstError = root.querySelector('.eo-form-step.is-active [aria-invalid="true"]');
    if (firstError) firstError.focus();
})();

</script>

</body>
</html>
