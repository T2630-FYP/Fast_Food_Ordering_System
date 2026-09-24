<?php

// Block this page unless a valid administrator session is available.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("Location: admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

$staff_roles = array("Manager","Cashier","Chef","Delivery");

// Refresh the signed-in role from the database before enforcing staff-management permissions.
$current_staff_id = (string)$_SESSION["admin_id"];
$role_stmt = mysqli_prepare($connect,"SELECT staff_role FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
mysqli_stmt_bind_param($role_stmt,"s",$current_staff_id);
mysqli_stmt_execute($role_stmt);
$role_result = mysqli_stmt_get_result($role_stmt);
$current_staff_row = mysqli_fetch_assoc($role_result) ?: null;
mysqli_stmt_close($role_stmt);
$current_staff_role = (string)($current_staff_row["staff_role"] ?? "");
$_SESSION["admin_role"] = $current_staff_role;
$can_manage_staff = $current_staff_role==="Manager";

// Protect staff account changes against cross-site request forgery.
if(empty($_SESSION["admin_staff_csrf"]))
{
	$_SESSION["admin_staff_csrf"] = bin2hex(random_bytes(32));
}
$staff_csrf = $_SESSION["admin_staff_csrf"];

// Escape database and form values before placing them in HTML.
function admin_staff_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Use a flash message and redirect so refreshing never repeats a staff change.
function admin_staff_redirect($type,$message,$location="admin_staff.php")
{
	$_SESSION["admin_staff_flash"] = array("type"=>$type,"message"=>$message);
	header("Location: ".$location,true,303);
	exit();
}

// Lock the active Manager rows before a role change or deletion so concurrent
// requests cannot remove every Manager from the system.
function admin_staff_lock_management_context($connect,$current_staff_id,$target_staff_id)
{
	$manager_ids = array();
	$manager_stmt = mysqli_prepare($connect,"SELECT staff_id FROM staff WHERE staff_role='Manager' AND staff_isDelete=0 FOR UPDATE");
	if(!$manager_stmt || !mysqli_stmt_execute($manager_stmt))
	{
		if($manager_stmt) mysqli_stmt_close($manager_stmt);
		return null;
	}
	$manager_result = mysqli_stmt_get_result($manager_stmt);
	while($manager_row = mysqli_fetch_assoc($manager_result))
	{
		$manager_ids[] = (string)$manager_row["staff_id"];
	}
	mysqli_stmt_close($manager_stmt);

	$target_stmt = mysqli_prepare($connect,"SELECT staff_role FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1 FOR UPDATE");
	if(!$target_stmt)
	{
		return null;
	}
	mysqli_stmt_bind_param($target_stmt,"s",$target_staff_id);
	mysqli_stmt_execute($target_stmt);
	$target_result = mysqli_stmt_get_result($target_stmt);
	$target_row = mysqli_fetch_assoc($target_result) ?: null;
	mysqli_stmt_close($target_stmt);

	return array(
		"actor_is_manager"=>in_array((string)$current_staff_id,$manager_ids,true),
		"manager_count"=>count($manager_ids),
		"target_role"=>(string)($target_row["staff_role"] ?? "")
	);
}

// Keep the existing staff lifecycle while validating all writes on the server.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	// Staff lists are visible to administrators, but only Managers can change accounts.
	if(!$can_manage_staff)
	{
		admin_staff_redirect("error","Only a Manager can add, edit or remove staff accounts.");
	}

	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if($submitted_token==="" || !hash_equals($staff_csrf,$submitted_token))
	{
		admin_staff_redirect("error","The request expired. Please refresh the page and try again.");
	}

	$action = (string)($_POST["action"] ?? "");
	if($action==="save_staff")
	{
		$mode = (string)($_POST["mode"] ?? "add");
		$staff_id = strtoupper(trim((string)($_POST["staff_id"] ?? "")));
		$staff_name = trim((string)($_POST["staff_name"] ?? ""));
		$staff_role = trim((string)($_POST["staff_role"] ?? ""));
		$staff_email = trim((string)($_POST["staff_email"] ?? ""));
		$staff_phone = trim((string)($_POST["staff_phone"] ?? ""));
		$staff_password = (string)($_POST["staff_password"] ?? "");
		$confirm_password = (string)($_POST["confirm_password"] ?? "");
		$editor_location = $mode==="update" ? "admin_staff.php?edit=".rawurlencode($staff_id)."#staff-editor" : "admin_staff.php#staff-editor";

		if(!in_array($mode,array("add","update"),true) || preg_match("/^[A-Z0-9]{1,5}$/",$staff_id)!==1)
		{
			admin_staff_redirect("error","Enter a valid Staff ID using up to 5 letters or numbers.",$editor_location);
		}
		if(strlen($staff_name)<2 || strlen($staff_name)>100 || !in_array($staff_role,$staff_roles,true))
		{
			admin_staff_redirect("error","Enter a valid staff name and role.",$editor_location);
		}
		if(!filter_var($staff_email,FILTER_VALIDATE_EMAIL) || strlen($staff_email)>100)
		{
			admin_staff_redirect("error","Enter a valid staff email address.",$editor_location);
		}
		if(preg_match("/^\d{9,15}$/",$staff_phone)!==1)
		{
			admin_staff_redirect("error","Enter a phone number containing 9 to 15 digits.",$editor_location);
		}
		if($mode==="add" && (strlen($staff_password)<8 || strlen($staff_password)>50))
		{
			admin_staff_redirect("error","Use a password containing 8 to 50 characters.",$editor_location);
		}
		if($mode==="add" && ($confirm_password==="" || !hash_equals($staff_password,$confirm_password)))
		{
			admin_staff_redirect("error","The password and confirmation do not match.",$editor_location);
		}

		// The database unique key covers active and soft-deleted staff accounts.
		$email_stmt = mysqli_prepare($connect,"SELECT staff_id FROM staff WHERE staff_email=? AND staff_id<>? LIMIT 1");
		mysqli_stmt_bind_param($email_stmt,"ss",$staff_email,$staff_id);
		mysqli_stmt_execute($email_stmt);
		$email_result = mysqli_stmt_get_result($email_stmt);
		$email_exists = mysqli_fetch_assoc($email_result)!==null;
		mysqli_stmt_close($email_stmt);
		if($email_exists)
		{
			admin_staff_redirect("error","This staff email is already registered.",$editor_location);
		}

		if($mode==="update")
		{
			$staff_saved = false;
			$update_error = "";
			mysqli_begin_transaction($connect);
			try
			{
				$lock_context = admin_staff_lock_management_context($connect,$current_staff_id,$staff_id);
				if(!$lock_context || !$lock_context["actor_is_manager"])
				{
					$update_error = "Only a current Manager can update staff accounts.";
				}
				else if($lock_context["target_role"]==="")
				{
					$update_error = "The selected staff account is no longer available.";
				}
				else if($lock_context["target_role"]==="Manager" && $staff_role!=="Manager" && $lock_context["manager_count"]<=1)
				{
					$update_error = "The last active Manager cannot be assigned another role.";
				}
				else
				{
					// Existing passwords are changed only by their signed-in owner after current-password verification.
					$save_stmt = mysqli_prepare($connect,"UPDATE staff SET staff_name=?,staff_role=?,staff_email=?,staff_phone=? WHERE staff_id=? AND staff_isDelete=0");
					mysqli_stmt_bind_param($save_stmt,"sssss",$staff_name,$staff_role,$staff_email,$staff_phone,$staff_id);
					$staff_saved = mysqli_stmt_execute($save_stmt);
					mysqli_stmt_close($save_stmt);
				}

				if($staff_saved)
				{
					mysqli_commit($connect);
				}
				else
				{
					mysqli_rollback($connect);
				}
			}
			catch(Throwable $error)
			{
				mysqli_rollback($connect);
				$update_error = "The staff account could not be updated.";
			}

			if($update_error!=="")
			{
				admin_staff_redirect("error",$update_error,$editor_location);
			}
			if($staff_saved && hash_equals((string)$_SESSION["admin_id"],$staff_id))
			{
				$_SESSION["admin_name"] = $staff_name;
				$_SESSION["admin_role"] = $staff_role;
			}
			admin_staff_redirect($staff_saved ? "success" : "error",$staff_saved ? "Staff account updated successfully." : "The staff account could not be updated.");
		}

		$id_stmt = mysqli_prepare($connect,"SELECT staff_id FROM staff WHERE staff_id=? LIMIT 1");
		mysqli_stmt_bind_param($id_stmt,"s",$staff_id);
		mysqli_stmt_execute($id_stmt);
		$id_result = mysqli_stmt_get_result($id_stmt);
		$id_exists = mysqli_fetch_assoc($id_result)!==null;
		mysqli_stmt_close($id_stmt);
		if($id_exists)
		{
			admin_staff_redirect("error","This Staff ID is already registered.",$editor_location);
		}

		$save_stmt = mysqli_prepare($connect,"INSERT INTO staff(staff_id,staff_name,staff_role,staff_email,staff_phone,staff_password,staff_isDelete) VALUES(?,?,?,?,?,?,0)");
		mysqli_stmt_bind_param($save_stmt,"ssssss",$staff_id,$staff_name,$staff_role,$staff_email,$staff_phone,$staff_password);
		$staff_saved = mysqli_stmt_execute($save_stmt);
		mysqli_stmt_close($save_stmt);
		admin_staff_redirect($staff_saved ? "success" : "error",$staff_saved ? "Staff account added successfully." : "The staff account could not be added.");
	}

	if($action==="delete_staff")
	{
		$staff_id = strtoupper(trim((string)($_POST["staff_id"] ?? "")));
		if(preg_match("/^[A-Z0-9]{1,5}$/",$staff_id)!==1)
		{
			admin_staff_redirect("error","The selected staff account is invalid.");
		}
		if(hash_equals((string)$_SESSION["admin_id"],$staff_id))
		{
			admin_staff_redirect("error","You cannot remove the administrator account currently signed in.");
		}

		$staff_removed = false;
		$delete_error = "";
		mysqli_begin_transaction($connect);
		try
		{
			$lock_context = admin_staff_lock_management_context($connect,$current_staff_id,$staff_id);
			if(!$lock_context || !$lock_context["actor_is_manager"])
			{
				$delete_error = "Only a current Manager can remove staff accounts.";
			}
			else if($lock_context["target_role"]==="")
			{
				$delete_error = "The selected staff account is no longer available.";
			}
			else if($lock_context["target_role"]==="Manager" && $lock_context["manager_count"]<=1)
			{
				$delete_error = "The last active Manager cannot be removed.";
			}
			else
			{
				$delete_stmt = mysqli_prepare($connect,"UPDATE staff SET staff_isDelete=1 WHERE staff_id=? AND staff_isDelete=0");
				mysqli_stmt_bind_param($delete_stmt,"s",$staff_id);
				mysqli_stmt_execute($delete_stmt);
				$staff_removed = mysqli_stmt_affected_rows($delete_stmt)===1;
				mysqli_stmt_close($delete_stmt);
			}

			if($staff_removed)
			{
				mysqli_commit($connect);
			}
			else
			{
				mysqli_rollback($connect);
			}
		}
		catch(Throwable $error)
		{
			mysqli_rollback($connect);
			$delete_error = "The staff account could not be removed.";
		}

		if($delete_error!=="")
		{
			admin_staff_redirect("error",$delete_error);
		}
		admin_staff_redirect($staff_removed ? "success" : "error",$staff_removed ? "Staff account removed successfully." : "The staff account could not be removed.");
	}

	admin_staff_redirect("error","The requested staff action is not supported.");
}

$staff_flash = $_SESSION["admin_staff_flash"] ?? null;
unset($_SESSION["admin_staff_flash"]);

$search = trim((string)($_GET["search"] ?? ""));
$role_filter = trim((string)($_GET["role"] ?? ""));
if($role_filter!=="" && !in_array($role_filter,$staff_roles,true))
{
	$role_filter = "";
}

// Search active staff by visible account details and optionally narrow by role.
$like_search = "%".$search."%";
$staff_stmt = mysqli_prepare($connect,
	"SELECT staff_id,staff_name,staff_role,staff_email,staff_phone
	 FROM staff
	 WHERE staff_isDelete=0
	 AND (?='' OR staff_id LIKE ? OR staff_name LIKE ? OR staff_role LIKE ? OR staff_email LIKE ? OR staff_phone LIKE ?)
	 AND (?='' OR staff_role=?)
	 ORDER BY staff_name");
$staff_accounts = array();
if($staff_stmt)
{
	mysqli_stmt_bind_param($staff_stmt,"ssssssss",$search,$like_search,$like_search,$like_search,$like_search,$like_search,$role_filter,$role_filter);
	mysqli_stmt_execute($staff_stmt);
	$staff_result = mysqli_stmt_get_result($staff_stmt);
	while($staff_row = mysqli_fetch_assoc($staff_result))
	{
		$staff_accounts[] = $staff_row;
	}
	mysqli_stmt_close($staff_stmt);
}

// Load only the requested active staff account, without ever retrieving its password.
$editing_staff = null;
$edit_id = strtoupper(trim((string)($_GET["edit"] ?? "")));
if($edit_id!=="" && !$can_manage_staff)
{
	admin_staff_redirect("error","Only a Manager can edit staff accounts.");
}
if($edit_id!=="" && preg_match("/^[A-Z0-9]{1,5}$/",$edit_id)===1)
{
	$edit_stmt = mysqli_prepare($connect,"SELECT staff_id,staff_name,staff_role,staff_email,staff_phone FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
	mysqli_stmt_bind_param($edit_stmt,"s",$edit_id);
	mysqli_stmt_execute($edit_stmt);
	$edit_result = mysqli_stmt_get_result($edit_stmt);
	$editing_staff = mysqli_fetch_assoc($edit_result) ?: null;
	mysqli_stmt_close($edit_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Staff - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_staff.php"); ?>

	<!-- Staff management heading and direct editor shortcut. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">ADMINISTRATOR MANAGEMENT</p>
			<h1>Staff</h1>
			<p>Search administrator accounts and review their assigned roles. Account changes are restricted to Managers.</p>
		</div>
		<?php if($can_manage_staff): ?><a class="admin-primary-link" href="#staff-editor">Add Staff</a><?php endif; ?>
	</section>

	<?php if($staff_flash): ?>
		<div class="admin-alert admin-alert-<?php echo admin_staff_html($staff_flash["type"]); ?>" role="status">
			<?php echo admin_staff_html($staff_flash["message"]); ?>
		</div>
	<?php endif; ?>

	<!-- Search and role filters are GET parameters so they are easy to reset. -->
	<section class="admin-user-filter-panel">
		<form class="admin-user-filter-form" method="get" action="admin_staff.php">
			<label class="admin-user-search">
				<span>Search staff</span>
				<input type="search" name="search" value="<?php echo admin_staff_html($search); ?>" placeholder="Staff ID, name, role, email or phone">
			</label>
			<label>
				<span>Role</span>
				<select name="role">
					<option value="">All roles</option>
					<?php foreach($staff_roles as $role_name): ?>
						<option value="<?php echo admin_staff_html($role_name); ?>"<?php echo $role_filter===$role_name ? " selected" : ""; ?>><?php echo admin_staff_html($role_name); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="admin-user-filter-actions">
				<button type="submit">Apply Filters</button>
				<a href="admin_staff.php">Reset</a>
			</div>
		</form>
	</section>

	<!-- Staff results display role information without exposing any password value. -->
	<section class="admin-user-results">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow">STAFF LIST</p>
				<h2>Administrator accounts</h2>
			</div>
			<span class="admin-user-result-meta"><?php echo count($staff_accounts); ?> result<?php echo count($staff_accounts)===1 ? "" : "s"; ?></span>
		</header>
		<div class="admin-user-table-wrap">
			<table class="admin-user-table admin-staff-table">
				<thead>
					<tr><th>Staff</th><th>Role</th><th>Email</th><th>Phone</th><th>Actions</th></tr>
				</thead>
				<tbody>
				<?php if(!$staff_accounts): ?>
					<tr><td class="admin-list-empty" colspan="5">No staff accounts match the current filters.</td></tr>
				<?php else: ?>
					<?php foreach($staff_accounts as $staff): ?>
						<tr>
							<td><strong><?php echo admin_staff_html($staff["staff_name"]); ?></strong><small><?php echo admin_staff_html($staff["staff_id"]); ?></small></td>
							<td><span class="admin-status-badge status-info"><?php echo admin_staff_html($staff["staff_role"]); ?></span></td>
							<td><?php echo admin_staff_html($staff["staff_email"]); ?></td>
							<td><?php echo admin_staff_html($staff["staff_phone"]); ?></td>
							<td>
								<div class="admin-user-row-actions">
									<?php if($can_manage_staff): ?>
										<a href="admin_staff.php?edit=<?php echo rawurlencode($staff["staff_id"]); ?>#staff-editor">Edit</a>
									<?php endif; ?>
									<?php if($can_manage_staff && !hash_equals((string)$_SESSION["admin_id"],(string)$staff["staff_id"])): ?>
										<form method="post" action="admin_staff.php" onsubmit="return confirm('Remove this staff account?');">
											<input type="hidden" name="csrf_token" value="<?php echo admin_staff_html($staff_csrf); ?>">
											<input type="hidden" name="action" value="delete_staff">
											<input type="hidden" name="staff_id" value="<?php echo admin_staff_html($staff["staff_id"]); ?>">
											<button type="submit">Remove</button>
										</form>
									<?php elseif(hash_equals((string)$_SESSION["admin_id"],(string)$staff["staff_id"])): ?>
										<span class="admin-current-account">Signed in</span>
									<?php else: ?>
										<span class="admin-current-account admin-read-only-account">Read only</span>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<?php if($can_manage_staff): ?>
	<!-- Managers can add or edit a staff account without exposing an existing password. -->
	<section id="staff-editor" class="admin-user-editor">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow"><?php echo $editing_staff ? "UPDATE STAFF" : "NEW STAFF"; ?></p>
				<h2><?php echo $editing_staff ? "Edit ".admin_staff_html($editing_staff["staff_name"]) : "Add an administrator account"; ?></h2>
			</div>
			<?php if($editing_staff): ?><a class="admin-secondary-link" href="admin_staff.php#staff-editor">Cancel Edit</a><?php endif; ?>
		</header>

		<form class="admin-user-editor-form" method="post" action="admin_staff.php">
			<input type="hidden" name="csrf_token" value="<?php echo admin_staff_html($staff_csrf); ?>">
			<input type="hidden" name="action" value="save_staff">
			<input type="hidden" name="mode" value="<?php echo $editing_staff ? "update" : "add"; ?>">

			<div class="admin-user-fields">
				<label class="admin-form-field"><span>Staff ID</span><input type="text" name="staff_id" maxlength="5" pattern="[A-Za-z0-9]{1,5}" value="<?php echo admin_staff_html($editing_staff["staff_id"] ?? ""); ?>"<?php echo $editing_staff ? " readonly" : ""; ?> required></label>
				<label class="admin-form-field"><span>Full name</span><input type="text" name="staff_name" minlength="2" maxlength="100" value="<?php echo admin_staff_html($editing_staff["staff_name"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>Role</span><select name="staff_role" required><option value="">Select role</option><?php foreach($staff_roles as $role_name): ?><option value="<?php echo admin_staff_html($role_name); ?>"<?php echo ($editing_staff["staff_role"] ?? "")===$role_name ? " selected" : ""; ?>><?php echo admin_staff_html($role_name); ?></option><?php endforeach; ?></select></label>
				<label class="admin-form-field"><span>Email address</span><input type="email" name="staff_email" maxlength="100" value="<?php echo admin_staff_html($editing_staff["staff_email"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>Phone number</span><input type="text" name="staff_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" value="<?php echo admin_staff_html($editing_staff["staff_phone"] ?? ""); ?>" required></label>
				<?php if(!$editing_staff): ?>
					<label class="admin-form-field"><span>Password</span><input type="password" name="staff_password" minlength="8" maxlength="50" autocomplete="new-password" required><small>Use 8 to 50 characters.</small></label>
					<label class="admin-form-field"><span>Confirm password</span><input type="password" name="confirm_password" minlength="8" maxlength="50" autocomplete="new-password" required><small>Enter the same password again.</small></label>
				<?php else: ?>
					<div class="admin-form-note"><strong>Password protected</strong><span>Existing passwords are changed only from the signed-in administrator's Profile page.</span></div>
				<?php endif; ?>
			</div>

			<div class="admin-user-form-actions">
				<button type="submit"><?php echo $editing_staff ? "Save Staff Changes" : "Add Staff"; ?></button>
				<?php if($editing_staff): ?><a href="admin_staff.php#staff-editor">Cancel</a><?php endif; ?>
			</div>
		</form>
	</section>
	<?php endif; ?>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
