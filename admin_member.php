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

$member_states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");

// Use one token for every member-management change made during this session.
if(empty($_SESSION["admin_member_csrf"]))
{
	$_SESSION["admin_member_csrf"] = bin2hex(random_bytes(32));
}
$member_csrf = $_SESSION["admin_member_csrf"];

// Escape database and form values before placing them in HTML.
function admin_member_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Store feedback in the session and redirect after a successful or rejected POST.
function admin_member_redirect($type,$message,$location="admin_member.php")
{
	$_SESSION["admin_member_flash"] = array("type"=>$type,"message"=>$message);
	header("Location: ".$location,true,303);
	exit();
}

// Preserve the existing member CRUD flow while protecting every change with POST and CSRF.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if($submitted_token==="" || !hash_equals($member_csrf,$submitted_token))
	{
		admin_member_redirect("error","The request expired. Please refresh the page and try again.");
	}

	$action = (string)($_POST["action"] ?? "");
	if($action==="save_member")
	{
		$mode = (string)($_POST["mode"] ?? "add");
		$member_id = (int)($_POST["member_id"] ?? 0);
		$member_name = trim((string)($_POST["member_name"] ?? ""));
		$member_email = trim((string)($_POST["member_email"] ?? ""));
		$member_phone = trim((string)($_POST["member_phone"] ?? ""));
		$member_state = trim((string)($_POST["member_state"] ?? ""));
		$member_join_date = trim((string)($_POST["member_joindate"] ?? ""));

		$join_date = DateTime::createFromFormat("!Y-m-d",$member_join_date);
		if(!in_array($mode,array("add","update"),true) || ($mode==="update" && $member_id<1))
		{
			admin_member_redirect("error","The selected member is invalid.");
		}
		$editor_location = $mode==="update" ? "admin_member.php?edit=".$member_id."#member-editor" : "admin_member.php#member-editor";
		if(strlen($member_name)<2 || strlen($member_name)>100)
		{
			admin_member_redirect("error","Enter a member name between 2 and 100 characters.",$editor_location);
		}
		if(!filter_var($member_email,FILTER_VALIDATE_EMAIL) || strlen($member_email)>100)
		{
			admin_member_redirect("error","Enter a valid member email address.",$editor_location);
		}
		if(preg_match("/^\d{9,15}$/",$member_phone)!==1 || !in_array($member_state,$member_states,true))
		{
			admin_member_redirect("error","Enter a valid phone number and state.",$editor_location);
		}
		if(!$join_date || $join_date->format("Y-m-d")!==$member_join_date)
		{
			admin_member_redirect("error","Enter a valid join date.",$editor_location);
		}

		// The unique email rule includes soft-deleted rows, matching the database index.
		if($mode==="update")
		{
			$email_stmt = mysqli_prepare($connect,"SELECT member_id FROM member WHERE member_email=? AND member_id<>? LIMIT 1");
			mysqli_stmt_bind_param($email_stmt,"si",$member_email,$member_id);
		}
		else
		{
			$email_stmt = mysqli_prepare($connect,"SELECT member_id FROM member WHERE member_email=? LIMIT 1");
			mysqli_stmt_bind_param($email_stmt,"s",$member_email);
		}
		mysqli_stmt_execute($email_stmt);
		$email_result = mysqli_stmt_get_result($email_stmt);
		$email_exists = mysqli_fetch_assoc($email_result)!==null;
		mysqli_stmt_close($email_stmt);
		if($email_exists)
		{
			admin_member_redirect("error","This member email is already registered.",$editor_location);
		}

		if($mode==="update")
		{
			$save_stmt = mysqli_prepare($connect,"UPDATE member SET member_name=?,member_email=?,member_phone=?,member_state=?,member_joindate=? WHERE member_id=? AND member_isDelete=0");
			mysqli_stmt_bind_param($save_stmt,"sssssi",$member_name,$member_email,$member_phone,$member_state,$member_join_date,$member_id);
			$member_saved = mysqli_stmt_execute($save_stmt);
			mysqli_stmt_close($save_stmt);
			admin_member_redirect($member_saved ? "success" : "error",$member_saved ? "Member updated successfully." : "The member could not be updated.");
		}

		// New members receive the same FYP demonstration defaults used by the existing system.
		$default_password = "member123";
		$default_gender = "";
		$default_dob = "2000-01-01";
		$default_address = "";
		$default_city = "";
		$default_postcode = "";
		$save_stmt = mysqli_prepare($connect,"INSERT INTO member(member_name,member_email,member_password,member_phone,member_gender,member_dob,member_address,member_state,member_city,member_postcode,member_joindate,member_isDelete) VALUES(?,?,?,?,?,?,?,?,?,?,?,0)");
		mysqli_stmt_bind_param($save_stmt,"sssssssssss",$member_name,$member_email,$default_password,$member_phone,$default_gender,$default_dob,$default_address,$member_state,$default_city,$default_postcode,$member_join_date);
		$member_saved = mysqli_stmt_execute($save_stmt);
		mysqli_stmt_close($save_stmt);
		admin_member_redirect($member_saved ? "success" : "error",$member_saved ? "Member added successfully." : "The member could not be added.");
	}

	if($action==="delete_member")
	{
		$member_id = (int)($_POST["member_id"] ?? 0);
		if($member_id<1)
		{
			admin_member_redirect("error","The selected member is invalid.");
		}
		$delete_stmt = mysqli_prepare($connect,"UPDATE member SET member_isDelete=1 WHERE member_id=? AND member_isDelete=0");
		mysqli_stmt_bind_param($delete_stmt,"i",$member_id);
		mysqli_stmt_execute($delete_stmt);
		$member_removed = mysqli_stmt_affected_rows($delete_stmt)===1;
		mysqli_stmt_close($delete_stmt);
		admin_member_redirect($member_removed ? "success" : "error",$member_removed ? "Member removed successfully." : "The member could not be removed.");
	}

	admin_member_redirect("error","The requested member action is not supported.");
}

$member_flash = $_SESSION["admin_member_flash"] ?? null;
unset($_SESSION["admin_member_flash"]);

$search = trim((string)($_GET["search"] ?? ""));
$state_filter = trim((string)($_GET["state"] ?? ""));
if($state_filter!=="" && !in_array($state_filter,$member_states,true))
{
	$state_filter = "";
}

// Search active members by the identifying details an administrator can see in the table.
$like_search = "%".$search."%";
$member_stmt = mysqli_prepare($connect,
	"SELECT member_id,member_name,member_email,member_phone,member_state,member_joindate
	 FROM member
	 WHERE member_isDelete=0
	 AND (?='' OR CAST(member_id AS CHAR) LIKE ? OR member_name LIKE ? OR member_email LIKE ? OR member_phone LIKE ?)
	 AND (?='' OR member_state=?)
	 ORDER BY member_id DESC");
$members = array();
if($member_stmt)
{
	mysqli_stmt_bind_param($member_stmt,"sssssss",$search,$like_search,$like_search,$like_search,$like_search,$state_filter,$state_filter);
	mysqli_stmt_execute($member_stmt);
	$member_result = mysqli_stmt_get_result($member_stmt);
	while($member_row = mysqli_fetch_assoc($member_result))
	{
		$members[] = $member_row;
	}
	mysqli_stmt_close($member_stmt);
}

// Load an existing member into the editor through a prepared query.
$editing_member = null;
$edit_id = (int)($_GET["edit"] ?? 0);
if($edit_id>0)
{
	$edit_stmt = mysqli_prepare($connect,"SELECT member_id,member_name,member_email,member_phone,member_state,member_joindate FROM member WHERE member_id=? AND member_isDelete=0 LIMIT 1");
	mysqli_stmt_bind_param($edit_stmt,"i",$edit_id);
	mysqli_stmt_execute($edit_stmt);
	$edit_result = mysqli_stmt_get_result($edit_stmt);
	$editing_member = mysqli_fetch_assoc($edit_result) ?: null;
	mysqli_stmt_close($edit_stmt);
}

// Keep CSV output aligned with the current Member search and state filter.
$member_export_params = array("type"=>"members");
if($search!=="") $member_export_params["search"] = $search;
if($state_filter!=="") $member_export_params["state"] = $state_filter;
$member_export_url = "admin_export.php?".http_build_query($member_export_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Members - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_member.php"); ?>

	<!-- Member management heading and direct editor shortcut. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">CUSTOMER MANAGEMENT</p>
			<h1>Members</h1>
			<p>Search registered customers and maintain their essential account details.</p>
			<p class="admin-print-context">Filters: Search <?php echo admin_member_html($search!=="" ? $search : "All"); ?> · State <?php echo admin_member_html($state_filter!=="" ? $state_filter : "All states"); ?></p>
		</div>
		<!-- Output actions use the current filtered list; editing controls do not print. -->
		<div class="admin-output-actions">
			<a class="admin-secondary-link" href="<?php echo admin_member_html($member_export_url); ?>">Export CSV</a>
			<button type="button" class="admin-secondary-button" onclick="window.print()">Print List</button>
			<a class="admin-primary-link" href="#member-editor">Add Member</a>
		</div>
	</section>

	<?php if($member_flash): ?>
		<div class="admin-alert admin-alert-<?php echo admin_member_html($member_flash["type"]); ?>" role="status">
			<?php echo admin_member_html($member_flash["message"]); ?>
		</div>
	<?php endif; ?>

	<!-- Search and state filters stay in the URL so the result can be bookmarked or reset. -->
	<section class="admin-user-filter-panel">
		<form class="admin-user-filter-form" method="get" action="admin_member.php">
			<label class="admin-user-search">
				<span>Search members</span>
				<input type="search" name="search" value="<?php echo admin_member_html($search); ?>" placeholder="Member ID, name, email or phone">
			</label>
			<label>
				<span>State</span>
				<select name="state">
					<option value="">All states</option>
					<?php foreach($member_states as $state_name): ?>
						<option value="<?php echo admin_member_html($state_name); ?>"<?php echo $state_filter===$state_name ? " selected" : ""; ?>><?php echo admin_member_html($state_name); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="admin-user-filter-actions">
				<button type="submit">Search Members</button>
				<a href="admin_member.php">Reset</a>
			</div>
		</form>
	</section>

	<!-- Member results are always generated from the live database query above. -->
	<section class="admin-user-results">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow">MEMBER LIST</p>
				<h2>Registered customers</h2>
			</div>
			<span class="admin-user-result-meta"><?php echo count($members); ?> result<?php echo count($members)===1 ? "" : "s"; ?></span>
		</header>
		<div class="admin-user-table-wrap">
			<table class="admin-user-table admin-member-table">
				<thead>
					<tr><th>Member</th><th>Contact</th><th>State</th><th>Join Date</th><th class="admin-print-hide">Actions</th></tr>
				</thead>
				<tbody>
				<?php if(!$members): ?>
					<tr><td class="admin-list-empty" colspan="5">No members match the current search.</td></tr>
				<?php else: ?>
					<?php foreach($members as $member): ?>
						<tr>
							<td><strong><?php echo admin_member_html($member["member_name"]); ?></strong><small>Member #<?php echo (int)$member["member_id"]; ?></small></td>
							<td><strong><?php echo admin_member_html($member["member_email"]); ?></strong><small><?php echo admin_member_html($member["member_phone"]); ?></small></td>
							<td><?php echo admin_member_html($member["member_state"]); ?></td>
							<td><?php echo admin_member_html($member["member_joindate"]); ?></td>
							<td class="admin-print-hide">
								<div class="admin-user-row-actions">
									<a href="admin_member.php?edit=<?php echo (int)$member["member_id"]; ?>#member-editor">Edit</a>
									<form method="post" action="admin_member.php" onsubmit="return confirm('Remove this member?');">
										<input type="hidden" name="csrf_token" value="<?php echo admin_member_html($member_csrf); ?>">
										<input type="hidden" name="action" value="delete_member">
										<input type="hidden" name="member_id" value="<?php echo (int)$member["member_id"]; ?>">
										<button type="submit">Remove</button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<!-- Add or update one member while preserving the existing administrator workflow. -->
	<section id="member-editor" class="admin-user-editor">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow"><?php echo $editing_member ? "UPDATE MEMBER" : "NEW MEMBER"; ?></p>
				<h2><?php echo $editing_member ? "Edit ".admin_member_html($editing_member["member_name"]) : "Add a customer member"; ?></h2>
			</div>
			<?php if($editing_member): ?><a class="admin-secondary-link" href="admin_member.php#member-editor">Cancel Edit</a><?php endif; ?>
		</header>

		<form class="admin-user-editor-form" method="post" action="admin_member.php">
			<input type="hidden" name="csrf_token" value="<?php echo admin_member_html($member_csrf); ?>">
			<input type="hidden" name="action" value="save_member">
			<input type="hidden" name="mode" value="<?php echo $editing_member ? "update" : "add"; ?>">
			<input type="hidden" name="member_id" value="<?php echo (int)($editing_member["member_id"] ?? 0); ?>">

			<div class="admin-user-fields">
				<?php if($editing_member): ?>
					<label class="admin-form-field"><span>Member ID</span><input type="text" value="<?php echo (int)$editing_member["member_id"]; ?>" readonly></label>
				<?php endif; ?>
				<label class="admin-form-field"><span>Full name</span><input type="text" name="member_name" minlength="2" maxlength="100" value="<?php echo admin_member_html($editing_member["member_name"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>Email address</span><input type="email" name="member_email" maxlength="100" value="<?php echo admin_member_html($editing_member["member_email"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>Phone number</span><input type="text" name="member_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" value="<?php echo admin_member_html($editing_member["member_phone"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>State</span><select name="member_state" required><option value="">Select state</option><?php foreach($member_states as $state_name): ?><option value="<?php echo admin_member_html($state_name); ?>"<?php echo ($editing_member["member_state"] ?? "")===$state_name ? " selected" : ""; ?>><?php echo admin_member_html($state_name); ?></option><?php endforeach; ?></select></label>
				<label class="admin-form-field"><span>Join date</span><input type="date" name="member_joindate" value="<?php echo admin_member_html($editing_member["member_joindate"] ?? date("Y-m-d")); ?>" required></label>
			</div>

			<div class="admin-user-form-actions">
				<button type="submit"><?php echo $editing_member ? "Save Member Changes" : "Add Member"; ?></button>
				<?php if($editing_member): ?><a href="admin_member.php#member-editor">Cancel</a><?php endif; ?>
			</div>
		</form>
	</section>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
