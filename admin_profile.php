<?php

// Only the signed-in administrator can view or update this account page.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("Location: admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

$current_admin_id = (string)$_SESSION["admin_id"];

// Protect profile changes with a session-bound token.
if(empty($_SESSION["admin_profile_csrf"]))
{
	$_SESSION["admin_profile_csrf"] = bin2hex(random_bytes(32));
}
$profile_csrf = $_SESSION["admin_profile_csrf"];

// Escape all profile values before they are displayed.
function admin_profile_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Redirect after POST so browser refresh cannot submit the same update twice.
function admin_profile_redirect($type,$message)
{
	$_SESSION["admin_profile_flash"] = array("type"=>$type,"message"=>$message);
	header("Location: admin_profile.php",true,303);
	exit();
}

// Update only the account identified by the session; posted IDs and roles are never trusted.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if($submitted_token==="" || !hash_equals($profile_csrf,$submitted_token))
	{
		admin_profile_redirect("error","The profile form has expired. Please refresh the page and try again.");
	}
	if((string)($_POST["action"] ?? "")!=="update_profile")
	{
		admin_profile_redirect("error","The requested profile action is not supported.");
	}

	$admin_name = trim((string)($_POST["staff_name"] ?? ""));
	$admin_email = trim((string)($_POST["staff_email"] ?? ""));
	$admin_phone = trim((string)($_POST["staff_phone"] ?? ""));

	if(strlen($admin_name)<2 || strlen($admin_name)>100)
	{
		admin_profile_redirect("error","Enter a name between 2 and 100 characters.");
	}
	if(!filter_var($admin_email,FILTER_VALIDATE_EMAIL) || strlen($admin_email)>100)
	{
		admin_profile_redirect("error","Enter a valid email address.");
	}
	if(preg_match("/^\d{9,15}$/",$admin_phone)!==1)
	{
		admin_profile_redirect("error","Enter a phone number containing 9 to 15 digits.");
	}

	// Check the whole staff table because the unique email index also includes deleted accounts.
	$email_stmt = mysqli_prepare($connect,"SELECT staff_id FROM staff WHERE staff_email=? AND staff_id<>? LIMIT 1");
	mysqli_stmt_bind_param($email_stmt,"ss",$admin_email,$current_admin_id);
	mysqli_stmt_execute($email_stmt);
	$email_result = mysqli_stmt_get_result($email_stmt);
	$email_exists = mysqli_fetch_assoc($email_result)!==null;
	mysqli_stmt_close($email_stmt);
	if($email_exists)
	{
		admin_profile_redirect("error","This email address is already used by another staff account.");
	}

	$save_stmt = mysqli_prepare($connect,"UPDATE staff SET staff_name=?,staff_email=?,staff_phone=? WHERE staff_id=? AND staff_isDelete=0");
	mysqli_stmt_bind_param($save_stmt,"ssss",$admin_name,$admin_email,$admin_phone,$current_admin_id);
	$profile_saved = mysqli_stmt_execute($save_stmt);
	mysqli_stmt_close($save_stmt);
	if(!$profile_saved)
	{
		admin_profile_redirect("error","Your profile could not be updated. Please try again.");
	}

	$_SESSION["admin_name"] = $admin_name;
	admin_profile_redirect("success","Your administrator profile was updated successfully.");
}

// Always load the displayed profile from the current session identity.
$profile_stmt = mysqli_prepare($connect,"SELECT staff_id,staff_name,staff_role,staff_email,staff_phone FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($profile_stmt,"s",$current_admin_id);
mysqli_stmt_execute($profile_stmt);
$profile_result = mysqli_stmt_get_result($profile_stmt);
$admin_profile = mysqli_fetch_assoc($profile_result) ?: null;
mysqli_stmt_close($profile_stmt);

if(!$admin_profile)
{
	unset($_SESSION["admin_id"],$_SESSION["admin_name"],$_SESSION["admin_role"]);
	header("Location: admin_login.php");
	exit();
}

$profile_flash = $_SESSION["admin_profile_flash"] ?? null;
unset($_SESSION["admin_profile_flash"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Admin Profile - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_profile.php"); ?>

	<!-- Account heading explains that this page is limited to the current administrator. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">MY ADMIN ACCOUNT</p>
			<h1>Profile</h1>
			<p>Review your assigned role and keep your own contact information up to date.</p>
		</div>
		<a class="admin-primary-link" href="admin_change_password.php">Change Password</a>
	</section>

	<?php if($profile_flash): ?>
		<div class="admin-alert admin-alert-<?php echo admin_profile_html($profile_flash["type"]); ?>" role="status">
			<?php echo admin_profile_html($profile_flash["message"]); ?>
		</div>
	<?php endif; ?>

	<!-- The role and Staff ID are display-only; only name, email and phone are submitted. -->
	<section class="admin-account-panel">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow">PROFILE DETAILS</p>
				<h2><?php echo admin_profile_html($admin_profile["staff_name"]); ?></h2>
			</div>
			<span class="admin-status-badge status-info"><?php echo admin_profile_html($admin_profile["staff_role"]); ?></span>
		</header>

		<form class="admin-account-form" method="post" action="admin_profile.php">
			<input type="hidden" name="csrf_token" value="<?php echo admin_profile_html($profile_csrf); ?>">
			<input type="hidden" name="action" value="update_profile">

			<div class="admin-account-fields">
				<label class="admin-form-field">
					<span>Staff ID</span>
					<input type="text" name="staff_id" value="<?php echo admin_profile_html($admin_profile["staff_id"]); ?>" readonly>
					<small>Your Staff ID cannot be changed.</small>
				</label>
				<label class="admin-form-field">
					<span>Assigned role</span>
					<input type="text" name="staff_role" value="<?php echo admin_profile_html($admin_profile["staff_role"]); ?>" readonly>
					<small>Role changes are handled through staff management.</small>
				</label>
				<label class="admin-form-field">
					<span>Full name</span>
					<input type="text" name="staff_name" minlength="2" maxlength="100" value="<?php echo admin_profile_html($admin_profile["staff_name"]); ?>" required>
				</label>
				<label class="admin-form-field">
					<span>Email address</span>
					<input type="email" name="staff_email" maxlength="100" value="<?php echo admin_profile_html($admin_profile["staff_email"]); ?>" required>
				</label>
				<label class="admin-form-field">
					<span>Phone number</span>
					<input type="text" name="staff_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" value="<?php echo admin_profile_html($admin_profile["staff_phone"]); ?>" required>
				</label>
			</div>

			<div class="admin-account-form-actions">
				<button type="submit">Save Profile</button>
				<a href="admin_dashboard.php">Back to Dashboard</a>
			</div>
		</form>
	</section>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
