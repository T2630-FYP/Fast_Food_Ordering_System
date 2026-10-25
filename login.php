<?php
include("dataconnection.php");
require_once("customer_navigation.php");

if(isset($_SESSION["customer_id"]) && $_SERVER["REQUEST_METHOD"]!=="POST")
{
	header("location:profile.php");
	exit();
}

$login_errors = array();
$login_email = "";
$login_notice = "";

if(isset($_SESSION["password_reset_success"]))
{
	$login_notice = (string)$_SESSION["password_reset_success"];
	unset($_SESSION["password_reset_success"]);
}
else if(isset($_SESSION["registration_success"]))
{
	$login_notice = (string)$_SESSION["registration_success"];
	unset($_SESSION["registration_success"]);
}

if(!isset($_SESSION["login_csrf"]))
{
	$_SESSION["login_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["loginbtn"]))
{
	$login_email = strtolower(trim((string)($_POST["user_email"] ?? "")));
	$password = (string)($_POST["user_password"] ?? "");
	$submitted_token = (string)($_POST["login_csrf"] ?? "");

	if($submitted_token==="" || !hash_equals($_SESSION["login_csrf"],$submitted_token))
	{
		$login_errors["general"] = "Your login form has expired. Please refresh the page and try again.";
	}
	if($login_email==="")
	{
		$login_errors["email"] = "Enter your registered email address.";
	}
	else if(!filter_var($login_email,FILTER_VALIDATE_EMAIL) || strlen($login_email)>100)
	{
		$login_errors["email"] = "Enter a valid email address.";
	}
	if($password==="")
	{
		$login_errors["password"] = "Enter your password.";
	}

	if(count($login_errors)===0)
	{
		$row = false;
		$stmt = mysqli_prepare($connect,"SELECT customer_id,customer_name,customer_password FROM customer WHERE customer_email=? AND customer_isDelete=0 LIMIT 1");
		if($stmt)
		{
			mysqli_stmt_bind_param($stmt,"s",$login_email);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);
			$row = mysqli_fetch_assoc($result);
			mysqli_stmt_close($stmt);
		}

		if($row && easyorder_password_verify($password,$row["customer_password"]))
		{
			$_SESSION["customer_id"] = (int)$row["customer_id"];
			$_SESSION["customer_name"] = $row["customer_name"];
			unset($_SESSION["login_csrf"]);
			session_regenerate_id(true);
			header("location:profile.php");
			exit();
		}

		$login_errors["general"] = "Invalid email or password. Please try again.";
	}
}

function login_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Login</title>
<link rel="stylesheet" href="style.css?v=20261024-auth1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<script src="customer_navigation.js?v=20261025-account-check1" defer></script>
<style data-eo-auth-style="login">
/* =========================================
   EASYORDER LOGIN V19
   DESKTOP LOGIN DESIGN
========================================= */

/* MAIN COLOR PALETTE */
#main.login-page {
    --login-brown: #39292B;
    --login-ink: #371C23;
    --login-red: #C8102E;
    --login-paper: #FFFCF9;
    --login-muted: #746D69;
    color: var(--login-ink);
}


/* =========================================
   MAIN LOGIN CONTAINER
========================================= */

#main.login-page {
    box-sizing: border-box;

    width: min(1080px, calc(100% - 72px));

    padding: 0;
    margin: 32px auto 42px;
}

.login-page,
.login-page * {
    font-family: "Segoe UI", Arial, sans-serif;
    box-sizing: border-box;
}


/* =========================================
   LOGIN CARD
========================================= */

.login-shell {
    display: grid;

    /* LEFT / RIGHT RATIO */
    grid-template-columns: 1.04fr 0.96fr;

    /* CARD HEIGHT */
    min-height: 560px;

    background: var(--login-paper);

    border: 1px solid rgba(80, 36, 46, 0.09);

    /* ROUNDED CORNERS */
    border-radius: 24px;

    overflow: hidden;

    /* SOFT SHADOW */
    box-shadow:
        0 28px 75px rgba(65, 29, 37, 0.12),
        0 3px 10px rgba(65, 29, 37, 0.03);
}


/* =========================================
   LEFT SIDE - FOOD SHOWCASE
========================================= */

.login-showcase {
    position: relative;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    /* TOP RIGHT BOTTOM LEFT */
    padding: 38px 36px 30px;

    background: #39292B;
}

.login-showcase-content {
    position: relative;
    z-index: 2;
}


/* LEFT MAIN HEADING */

.login-showcase h2 {
    margin: 0 0 20px;

    color: #FFF8F2;

    font-family: "Segoe UI", Arial, sans-serif;

    font-size: clamp(32px, 3.1vw, 42px);

    line-height: 1.12;

    letter-spacing: -2.1px;

    font-weight: 850;
}


/* CHAMPAGNE ITALIC TEXT */

.login-showcase h2 em {
    color: #FFD09A;

    font-family: Georgia, "Times New Roman", serif;

    font-size: 1.045em;

    font-style: italic;

    font-weight: 700;

    line-height: 1.16;

    letter-spacing: -1.5px;
}


/* =========================================
   FOOD PHOTO
========================================= */

.login-food-stage {
    position: relative;

    overflow: hidden;

    flex: 1;

    min-height: 260px;

    margin: 24px -36px -30px;

    background: #6A4230;
}

.login-food-stage img {
    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    /* PHOTO POSITION */
    object-position: center 25%;

    position: absolute;
    inset: 0;
}


/* =========================================
   SOFT BLUR TRANSITION
========================================= */

/* BLUR LAYER */

.login-food-stage::before {
    content: "";

    position: absolute;

    inset: 0 0 auto;

    height: 54px;

    z-index: 1;

    -webkit-backdrop-filter: blur(7px);
    backdrop-filter: blur(7px);

    -webkit-mask-image:
        linear-gradient(
            to bottom,
            #000 0%,
            transparent 100%
        );

    mask-image:
        linear-gradient(
            to bottom,
            #000 0%,
            transparent 100%
        );

    pointer-events: none;
}


/* DARK BROWN GRADIENT */

.login-food-stage::after {
    content: "";

    position: absolute;

    inset: 0 0 auto;

    height: 56px;

    z-index: 2;

    background:
        linear-gradient(
            to bottom,
            #39292B 0%,
            rgba(57, 41, 43, 0.82) 16%,
            rgba(57, 41, 43, 0.37) 54%,
            rgba(57, 41, 43, 0) 100%
        );

    pointer-events: none;
}


/* =========================================
   RIGHT SIDE - LOGIN FORM PANEL
========================================= */

.login-form-panel {
    display: flex;

    align-items: center;

    background: #FFFCF9;

    padding: 36px 40px;
}

.login-form-content {
    width: 100%;

    max-width: 380px;

    margin: auto;
}


/* WELCOME BACK TITLE */

.login-form-content h2 {
    margin: 0 0 9px;

    color: var(--login-ink);

    font-size: clamp(30px, 2.5vw, 34px);

    line-height: 1.18;

    letter-spacing: -1.2px;

    font-weight: 800;
}


/* DESCRIPTION */

.login-description {
    color: #746D69;

    font-size: 14.5px;

    line-height: 1.65;

    margin: 0 0 24px;
}


/* =========================================
   FORM LABELS
========================================= */

.login-form {
    margin: 0;
}

.login-field {
    margin-bottom: 18px;
}

.login-field label {
    display: block;

    color: var(--login-ink);

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 8px;
}


/* =========================================
   INPUT BOXES
========================================= */

.login-input-wrap {
    position: relative;
}

.login-page .login-input-wrap input {
    display: block;

    width: 100%;

    /* INPUT HEIGHT */
    height: 48px;

    background: #FAF6F3;

    border: 1.5px solid #E8DEDA;

    border-radius: 11px;

    padding: 0 17px 0 47px;

    font-size: 15px;

    color: var(--login-ink);

    outline: none;

    transition:
        border-color 0.16s ease,
        background-color 0.16s ease,
        box-shadow 0.16s ease;
}


/* PLACEHOLDER */

.login-page .login-input-wrap input::placeholder {
    color: #897D7B;
}


/* INPUT FOCUS */

.login-page .login-input-wrap input:focus {
    background: #FFFFFF;

    border-color: #C94A5E;

    box-shadow:
        0 0 0 4px rgba(200, 16, 46, 0.10);
}


/* INPUT ICON */

.login-input-icon {
    position: absolute;

    left: 16px;
    top: 50%;

    transform: translateY(-50%);

    display: flex;
    align-items: center;
    justify-content: center;

    width: 20px;
    height: 20px;

    color: #9C8987;

    pointer-events: none;
}

.login-input-icon svg {
    width: 20px;
    height: 20px;
}


/* =========================================
   PASSWORD EYE BUTTON
========================================= */

.login-page #user_password {
    padding-right: 55px;
}

.login-password-toggle {
    position: absolute;

    right: 11px;
    top: 50%;

    transform: translateY(-50%);

    display: flex;
    align-items: center;
    justify-content: center;

    width: 38px;
    height: 38px;

    padding: 0;

    border: none;
    border-radius: 9px;

    background: transparent;

    color: #95817F;

    cursor: pointer;

    transition:
        color 0.16s ease,
        background-color 0.16s ease;
}

.login-password-toggle:hover {
    color: #A6102D;
    background: #FFF0EE;
}

.login-password-toggle svg {
    width: 21px;
    height: 21px;
}

/* EYE ICON SWITCHING */

.login-eye-closed {
    display: none;
}

.login-password-toggle.is-visible .login-eye-open {
    display: none;
}

.login-password-toggle.is-visible .login-eye-closed {
    display: block;
}


/* =========================================
   FORGOT PASSWORD
========================================= */

.login-forgot-row {
    display: flex;

    justify-content: flex-end;

    margin-top: 8px;
}

.login-forgot-row a {
    color: #A6102D;

    font-size: 12px;

    font-weight: 700;

    text-decoration: none;
}

.login-forgot-row a:hover {
    text-decoration: underline;
}


/* =========================================
   SIGN IN BUTTON
========================================= */

.login-page .login-submit {
    display: flex;

    justify-content: space-between;
    align-items: center;

    width: 100%;

    min-height: 50px;

    border: none;

    border-radius: 12px;

    background:
        linear-gradient(
            105deg,
            #C8102E 0%,
            #A30B28 100%
        );

    color: #FFFFFF;

    padding: 0 23px;

    margin-top: 14px;

    font-size: 16px;

    font-weight: 800;

    box-shadow:
        0 9px 21px rgba(149, 13, 41, 0.16);

    cursor: pointer;

    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        filter 0.18s ease;
}


/* BUTTON ARROW */

.login-page .login-submit span {
    font-size: 24px;
    font-weight: 400;
}


/* BUTTON HOVER */

.login-page .login-submit:hover {
    filter: brightness(0.98);

    transform: translateY(-2px);

    box-shadow:
        0 13px 25px rgba(149, 13, 41, 0.24);
}


/* =========================================
   CREATE ACCOUNT
========================================= */

.login-create-account {
    display: flex;

    flex-wrap: wrap;

    align-items: baseline;
    justify-content: center;

    gap: 6px;

    color: #746D69;

    font-size: 14px;

    margin: 20px 0 0;
}

.login-create-account a {
    color: #A6102D;

    font-weight: 800;

    text-decoration: none;
}

.login-create-account a:hover {
    text-decoration: underline;
}


/* =========================================
   ERROR / SUCCESS
========================================= */

.login-feedback {
    text-align: left;
    font-size: 13px;
    margin: 0 0 20px;
}

.login-page .field-error {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: #B94142;
}

.login-page input[aria-invalid="true"] {
    border-color: #B94142;
}


/* =========================================
   KEYBOARD FOCUS
========================================= */

.login-page .login-password-toggle:focus-visible,
.login-page .login-submit:focus-visible,
.login-page a:focus-visible {
    outline: 3px solid #E9AD70;
    outline-offset: 3px;
}


/* =========================================
   SMALLER DESKTOP / LAPTOP SCREENS
========================================= */

@media (max-width: 1030px) {

    #main.login-page {
        width: calc(100% - 40px);
        margin: 28px auto 36px;
    }

    .login-showcase {
        padding: 34px 30px 26px;
    }

    .login-food-stage {
        min-height: 240px;
        margin: 20px -30px -26px;
    }

    .login-form-panel {
        padding: 30px 32px;
    }
}
</style>
</head>
<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="login-page eo-auth-page" data-auth-view="login">
    <div class="login-shell eo-auth-frame">

        <!-- LEFT SIDE: FOOD SHOWCASE -->
        <aside class="eo-auth-showcase" aria-label="EasyOrder burger, fries, fried chicken and cola">
<img class="eo-auth-photo" src="image/login.jpg?v=20261024-auth1" alt="Burger, fries, fried chicken and cola">
<h2 class="eo-auth-showcase-heading">A little hungry?<br><em>We’ve got you.</em></h2>
</aside>


        <!-- RIGHT SIDE: LOGIN FORM -->
        <section class="login-form-panel eo-auth-form-panel">

            <div class="login-form-content">

                <h2>Welcome back</h2>

                <p class="login-description">
                    Sign in to your account and pick up
                    where you left off.
                </p>

                <!-- LOGIN SUCCESS MESSAGE -->
                <?php if ($login_notice !== "") { ?>
                    <p class="form-message form-message-success login-feedback" role="status">
                        <?php echo login_h($login_notice); ?>
                    </p>
                <?php } ?>

                <!-- LOGIN ERROR MESSAGE -->
                <?php if (isset($login_errors["general"])) { ?>
                    <p class="form-message form-message-error login-feedback" role="alert">
                        <?php echo login_h($login_errors["general"]); ?>
                    </p>
                <?php } ?>


                <form
                    method="post"
                    action="login.php"
                    class="login-form"
                    novalidate
                >

                    <input
                        type="hidden"
                        name="login_csrf"
                        value="<?php echo login_h($_SESSION['login_csrf']); ?>"
                    >


                    <!-- EMAIL -->
                    <div class="login-field">

                        <label for="user_email">
                            Email address
                        </label>

                        <div class="login-input-wrap">

                            <span class="login-input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <rect x="3" y="5"
                                          width="18" height="14"
                                          rx="2"/>
                                    <path d="m4 7 8 6 8-6"/>
                                </svg>
                            </span>

                            <input
                                type="email"
                                id="user_email"
                                name="user_email"
                                maxlength="100"
                                autocomplete="email"
                                placeholder="Enter your email address"
                                value="<?php echo login_h($login_email); ?>"
                                <?php if (isset($login_errors["email"])) { ?>aria-invalid="true" aria-describedby="login-email-error"<?php } ?>
                                required
                            >

                        </div>

                        <?php if (isset($login_errors["email"])) { ?>
                            <span class="field-error" id="login-email-error">
                                <?php echo login_h($login_errors["email"]); ?>
                            </span>
                        <?php } ?>

                    </div>


                    <!-- PASSWORD -->
                    <div class="login-field">

                        <label for="user_password">
                            Password
                        </label>

                        <div class="login-input-wrap">

                            <span class="login-input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <rect x="5" y="10"
                                          width="14" height="11"
                                          rx="2"/>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                            </span>

                            <input
                                type="password"
                                id="user_password"
                                name="user_password"
                                maxlength="72"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                <?php if (isset($login_errors["password"])) { ?>aria-invalid="true" aria-describedby="login-password-error"<?php } ?>
                                required
                            >

                            <!-- SHOW/HIDE PASSWORD -->
                            <button
                                type="button"
                                class="login-password-toggle"
                                id="login-password-toggle"
                                aria-controls="user_password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >

                                <!-- EYE -->
                                <svg class="login-eye-open" aria-hidden="true"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>

                                <!-- EYE OFF -->
                                <svg class="login-eye-closed" aria-hidden="true"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="1.8"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">
                                    <defs>
                                        <mask id="login-eye-slash-gap" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24">
                                            <rect width="24" height="24" fill="white" stroke="none"/>
                                            <path d="M3 3l18 18" stroke="black" stroke-width="4.6"/>
                                        </mask>
                                    </defs>
                                    <g mask="url(#login-eye-slash-gap)">
                                        <path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </g>
                                    <path d="M3 3l18 18"/>
                                </svg>

                            </button>

                        </div>

                        <?php if (isset($login_errors["password"])) { ?>
                            <span class="field-error" id="login-password-error">
                                <?php echo login_h($login_errors["password"]); ?>
                            </span>
                        <?php } ?>

                        <div class="login-forgot-row">
                            <a href="forgot_password.php">
                                Forgot password?
                            </a>
                        </div>

                    </div>


                    <!-- SIGN IN BUTTON -->
                    <button
                        type="submit"
                        class="login-submit"
                        name="loginbtn"
                        value="1"
                    >
                        Sign in
                        <span aria-hidden="true">→</span>
                    </button>

                </form>


                <!-- REGISTER LINK -->
                <div class="login-create-account">
                    <span>Don't have an account?</span>

                    <a href="register.php">
                        Create an account ↗
                    </a>
                </div>

            </div>

        </section>

    </div>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

<script data-eo-auth-init="login">
(function () {
    "use strict";
    const passwordInput = document.getElementById("user_password");
    const toggleButton = document.getElementById("login-password-toggle");
    if (!passwordInput || !toggleButton) return;

    toggleButton.addEventListener("click", function () {
        const isVisible = passwordInput.type === "text";
        passwordInput.type = isVisible ? "password" : "text";
        toggleButton.classList.toggle("is-visible", !isVisible);
        toggleButton.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
        toggleButton.setAttribute("aria-pressed", String(!isVisible));
        passwordInput.focus();
    });
})();
</script>
</body>
</html>
