<?php
//only logged in customers can see their profile
session_start();
if(!isset($_SESSION["customer_id"]))
{
	require_once(__DIR__."/customer_auth_helpers.php");
	easyorder_redirect_customer_to_login();
}
include("dataconnection.php");
require_once(__DIR__."/customer_navigation.php");

$mid = (int)$_SESSION["customer_id"];
$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");
$profile_errors = array();
$profile_success = "";

function profile_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

//Always identify the editable record from the current session, never from a URL or hidden customer id.
$stmt = mysqli_prepare($connect,"SELECT * FROM customer WHERE customer_id=? AND customer_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($stmt,"i",$mid);
mysqli_stmt_execute($stmt);
$customer_result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($customer_result);
mysqli_stmt_close($stmt);

if(!$customer)
{
	unset($_SESSION["customer_id"],$_SESSION["customer_name"]);
	require_once(__DIR__."/customer_auth_helpers.php");
	easyorder_redirect_customer_to_login();
}

$profile_values = array(
	"name" => $customer["customer_name"],
	"phone" => $customer["customer_phone"],
	"gender" => $customer["customer_gender"],
	"dob" => $customer["customer_dob"],
	"address" => $customer["customer_address"],
	"state" => $customer["customer_state"],
	"city" => $customer["customer_city"],
	"postcode" => $customer["customer_postcode"]
);

if(!isset($_SESSION["profile_csrf"]))
{
	$_SESSION["profile_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["update_profile"]))
{
	$submitted_token = (string)($_POST["profile_csrf"] ?? "");
	if($submitted_token==="" || !hash_equals($_SESSION["profile_csrf"],$submitted_token))
	{
		$profile_errors["general"] = "Your profile form has expired. Please refresh the page and try again.";
	}

	$profile_values = array(
		"name" => trim((string)($_POST["customer_name"] ?? "")),
		"phone" => trim((string)($_POST["customer_phone"] ?? "")),
		"gender" => trim((string)($_POST["customer_gender"] ?? "")),
		"dob" => trim((string)($_POST["customer_dob"] ?? "")),
		"address" => trim((string)($_POST["customer_address"] ?? "")),
		"state" => trim((string)($_POST["customer_state"] ?? "")),
		"city" => trim((string)($_POST["customer_city"] ?? "")),
		"postcode" => trim((string)($_POST["customer_postcode"] ?? ""))
	);

	if(strlen($profile_values["name"])<2 || strlen($profile_values["name"])>100)
	{
		$profile_errors["name"] = "Enter a name between 2 and 100 characters.";
	}
	if(!preg_match("/^\d{7,15}$/",$profile_values["phone"]))
	{
		$profile_errors["phone"] = "Enter a phone number containing 7 to 15 digits.";
	}
	if(!in_array($profile_values["gender"],array("Male","Female"),true))
	{
		$profile_errors["gender"] = "Select a valid gender.";
	}
	$dob_value = DateTime::createFromFormat("!Y-m-d",$profile_values["dob"]);
	if(!$dob_value || $dob_value->format("Y-m-d")!==$profile_values["dob"] || $profile_values["dob"]<"1900-01-01" || $profile_values["dob"]>date("Y-m-d"))
	{
		$profile_errors["dob"] = "Enter a valid date of birth.";
	}
	if(strlen($profile_values["address"])<5 || strlen($profile_values["address"])>140)
	{
		$profile_errors["address"] = "Enter a street address between 5 and 140 characters.";
	}
	if(!in_array($profile_values["state"],$states,true))
	{
		$profile_errors["state"] = "Select a valid state.";
	}
	if(strlen($profile_values["city"])<2 || strlen($profile_values["city"])>50)
	{
		$profile_errors["city"] = "Enter a city between 2 and 50 characters.";
	}
	if(!preg_match("/^\d{5}$/",$profile_values["postcode"]))
	{
		$profile_errors["postcode"] = "Enter a valid 5-digit postcode.";
	}

	if(count($profile_errors)===0)
	{
		$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_name=?,customer_phone=?,customer_gender=?,customer_dob=?,customer_address=?,customer_state=?,customer_city=?,customer_postcode=? WHERE customer_id=? AND customer_isDelete=0");
		mysqli_stmt_bind_param(
			$stmt,
			"ssssssssi",
			$profile_values["name"],
			$profile_values["phone"],
			$profile_values["gender"],
			$profile_values["dob"],
			$profile_values["address"],
			$profile_values["state"],
			$profile_values["city"],
			$profile_values["postcode"],
			$mid
		);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		$_SESSION["customer_name"] = $profile_values["name"];
		$_SESSION["profile_success"] = "Your profile and default delivery address were updated successfully.";
		unset($_SESSION["profile_csrf"]);
		header("location:profile.php#profile");
		exit();
	}
}

if(isset($_SESSION["profile_success"]))
{
	$profile_success = $_SESSION["profile_success"];
	unset($_SESSION["profile_success"]);
}

//Reload persisted customer data after a normal request. On a failed POST, keep submitted values in the form.
if($_SERVER["REQUEST_METHOD"]!=="POST")
{
	$profile_values = array(
		"name" => $customer["customer_name"],
		"phone" => $customer["customer_phone"],
		"gender" => $customer["customer_gender"],
		"dob" => $customer["customer_dob"],
		"address" => $customer["customer_address"],
		"state" => $customer["customer_state"],
		"city" => $customer["customer_city"],
		"postcode" => $customer["customer_postcode"]
	);
}

?>

<!DOCTYPE html>
<html lang="en">

<head><!--Customer profile page after login-->
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile | EasyOrder</title>
<link rel="stylesheet" href="public.css?v=20261024-shell1">
<link rel="stylesheet" href="customer.css?v=20261029-profile1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">

</head>

<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar"><!--Customer navigation bar-->
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-profile-page" role="main">
    <h1 class="eo-profile-title">Profile</h1>

    <section id="profile" class="eo-profile-card" aria-label="Profile settings">
        <form method="post" action="profile.php#profile" novalidate>
            <input type="hidden" name="profile_csrf" value="<?php echo profile_h($_SESSION["profile_csrf"]); ?>">
            <?php if($profile_success!=="") { ?>
                <div class="eo-profile-message success" role="status"><?php echo profile_h($profile_success); ?></div>
            <?php } ?>
            <?php if(isset($profile_errors["general"])) { ?>
                <div class="eo-profile-message error" role="alert"><?php echo profile_h($profile_errors["general"]); ?></div>
            <?php } ?>

            <div class="eo-profile-columns">
                <div class="eo-profile-column">
                    <div class="eo-profile-section-heading">
                        <span class="eo-profile-heading-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7.5" r="3.2"/><path d="M5.7 20c.4-4.1 2.8-6.6 6.3-6.6s5.9 2.5 6.3 6.6"/></svg>
                        </span>
                        <h2>Personal information</h2>
                    </div>

                    <div class="eo-profile-field">
                        <label for="customer_email">Email address</label>
                        <div class="eo-input-wrap">
                            <input type="email" id="customer_email" autocomplete="email" aria-describedby="profile-email-note" value="<?php echo profile_h($customer["customer_email"]); ?>" readonly>
                            <span class="eo-field-lock" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5.8" y="10.3" width="12.4" height="10" rx="2"/><path d="M8.5 10.3V7.5a3.5 3.5 0 0 1 7 0v2.8"/></svg>
                            </span>
                        </div>
                        <small id="profile-email-note" class="eo-profile-help">Your sign-in email can't be changed here.</small>
                    </div>

                    <div class="eo-profile-field">
                        <label for="customer_name">Full name <span class="eo-required" aria-hidden="true">*</span></label>
                        <input type="text" id="customer_name" name="customer_name" autocomplete="name" minlength="2" maxlength="100" required value="<?php echo profile_h($profile_values["name"]); ?>"<?php if(isset($profile_errors["name"])) { ?> aria-invalid="true" aria-describedby="customer_name-error"<?php } ?>>
                        <?php if(isset($profile_errors["name"])) { ?><span class="eo-profile-field-error" id="customer_name-error"><?php echo profile_h($profile_errors["name"]); ?></span><?php } ?>
                    </div>

                    <div class="eo-profile-field">
                        <label for="customer_phone">Phone number <span class="eo-required" aria-hidden="true">*</span></label>
                        <input type="text" id="customer_phone" name="customer_phone" autocomplete="tel" inputmode="numeric" pattern="[0-9]{7,15}" minlength="7" maxlength="15" required value="<?php echo profile_h($profile_values["phone"]); ?>"<?php if(isset($profile_errors["phone"])) { ?> aria-invalid="true" aria-describedby="customer_phone-error"<?php } ?>>
                        <?php if(isset($profile_errors["phone"])) { ?><span class="eo-profile-field-error" id="customer_phone-error"><?php echo profile_h($profile_errors["phone"]); ?></span><?php } ?>
                    </div>

                    <div class="eo-profile-pair">
                        <div class="eo-profile-field">
                            <label for="customer_gender">Gender</label>
                            <div class="eo-select-wrap">
                                <select id="customer_gender" name="customer_gender" required<?php if(isset($profile_errors["gender"])) { ?> aria-invalid="true" aria-describedby="customer_gender-error"<?php } ?>>
                                    <option value="">Select gender</option>
                                    <option value="Male"<?php if($profile_values["gender"]==="Male") echo " selected"; ?>>Male</option>
                                    <option value="Female"<?php if($profile_values["gender"]==="Female") echo " selected"; ?>>Female</option>
                                </select>
                                <svg class="eo-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </div>
                            <?php if(isset($profile_errors["gender"])) { ?><span class="eo-profile-field-error" id="customer_gender-error"><?php echo profile_h($profile_errors["gender"]); ?></span><?php } ?>
                        </div>
                        <div class="eo-profile-field">
                            <label for="customer_dob">Date of birth</label>
                            <input type="date" id="customer_dob" name="customer_dob" autocomplete="bday" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>" required value="<?php echo profile_h($profile_values["dob"]); ?>"<?php if(isset($profile_errors["dob"])) { ?> aria-invalid="true" aria-describedby="customer_dob-error"<?php } ?>>
                            <?php if(isset($profile_errors["dob"])) { ?><span class="eo-profile-field-error" id="customer_dob-error"><?php echo profile_h($profile_errors["dob"]); ?></span><?php } ?>
                        </div>
                    </div>
                </div>

                <div class="eo-profile-column">
                    <div class="eo-profile-section-heading">
                        <span class="eo-profile-heading-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19.2 10c0 5.1-7.2 11.1-7.2 11.1S4.8 15.1 4.8 10a7.2 7.2 0 1 1 14.4 0Z"/><circle cx="12" cy="10" r="2.4"/></svg>
                        </span>
                        <h2>Default delivery address</h2>
                    </div>

                    <div class="eo-profile-field">
                        <label for="customer_address">Street address <span class="eo-required" aria-hidden="true">*</span></label>
                        <textarea id="customer_address" name="customer_address" autocomplete="street-address" minlength="5" maxlength="140" required placeholder="House number, building, street and unit number"<?php if(isset($profile_errors["address"])) { ?> aria-invalid="true" aria-describedby="customer_address-error"<?php } ?>><?php echo profile_h($profile_values["address"]); ?></textarea>
                        <?php if(isset($profile_errors["address"])) { ?><span class="eo-profile-field-error" id="customer_address-error"><?php echo profile_h($profile_errors["address"]); ?></span><?php } ?>
                    </div>

                    <div class="eo-profile-field">
                        <label for="customer_state">State <span class="eo-required" aria-hidden="true">*</span></label>
                        <div class="eo-select-wrap">
                            <select id="customer_state" name="customer_state" autocomplete="address-level1" required<?php if(isset($profile_errors["state"])) { ?> aria-invalid="true" aria-describedby="customer_state-error"<?php } ?>>
                                <option value="">Select state</option>
                                <?php foreach($states as $state_name) { ?>
                                <option value="<?php echo profile_h($state_name); ?>"<?php if($profile_values["state"]===$state_name) echo " selected"; ?>><?php echo profile_h($state_name); ?></option>
                                <?php } ?>
                            </select>
                            <svg class="eo-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </div>
                        <?php if(isset($profile_errors["state"])) { ?><span class="eo-profile-field-error" id="customer_state-error"><?php echo profile_h($profile_errors["state"]); ?></span><?php } ?>
                    </div>

                    <div class="eo-profile-pair">
                        <div class="eo-profile-field">
                            <label for="customer_city">City <span class="eo-required" aria-hidden="true">*</span></label>
                            <input type="text" id="customer_city" name="customer_city" autocomplete="address-level2" minlength="2" maxlength="50" required value="<?php echo profile_h($profile_values["city"]); ?>"<?php if(isset($profile_errors["city"])) { ?> aria-invalid="true" aria-describedby="customer_city-error"<?php } ?>>
                            <?php if(isset($profile_errors["city"])) { ?><span class="eo-profile-field-error" id="customer_city-error"><?php echo profile_h($profile_errors["city"]); ?></span><?php } ?>
                        </div>
                        <div class="eo-profile-field">
                            <label for="customer_postcode">Postcode <span class="eo-required" aria-hidden="true">*</span></label>
                            <input type="text" id="customer_postcode" name="customer_postcode" autocomplete="postal-code" inputmode="numeric" pattern="[0-9]{5}" minlength="5" maxlength="5" required value="<?php echo profile_h($profile_values["postcode"]); ?>"<?php if(isset($profile_errors["postcode"])) { ?> aria-invalid="true" aria-describedby="customer_postcode-error"<?php } ?>>
                            <?php if(isset($profile_errors["postcode"])) { ?><span class="eo-profile-field-error" id="customer_postcode-error"><?php echo profile_h($profile_errors["postcode"]); ?></span><?php } ?>
                        </div>
                    </div>

                    <div class="eo-profile-note" role="note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10.6v5.4M12 7.5h.01"/></svg>
                        <span>Used by default at checkout. Changes there won't update your saved address.</span>
                    </div>
                </div>
            </div>

            <div class="eo-profile-actions">
                <a class="eo-profile-change-password" href="change_password.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5.5" y="10" width="13" height="11" rx="2"/><path d="M8.5 10V7a3.5 3.5 0 1 1 7 0v3"/></svg>
                    Change password
                </a>
                <button type="submit" class="eo-profile-save" name="update_profile" value="1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4.5 12 5 5 10-10"/></svg>
                    Save changes
                </button>
            </div>
        </form>
    </section>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

<script>
window.addEventListener("load",function()
{
	requestAnimationFrame(function()
	{
		const firstError=document.querySelector('form [aria-invalid="true"]');
		if(firstError) firstError.focus();
	});
});
</script>

</body>

</html>
