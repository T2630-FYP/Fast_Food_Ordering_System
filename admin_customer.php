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

$customer_states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");

// Use one token for every customer-management change made during this session.
if(empty($_SESSION["admin_customer_csrf"]))
{
	$_SESSION["admin_customer_csrf"] = bin2hex(random_bytes(32));
}
$customer_csrf = $_SESSION["admin_customer_csrf"];

// Escape database and form values before placing them in HTML.
function admin_customer_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Store feedback in the session and redirect after a successful or rejected POST.
function admin_customer_redirect($type,$message,$location="admin_customer.php")
{
	$_SESSION["admin_customer_flash"] = array("type"=>$type,"message"=>$message);
	header("Location: ".$location,true,303);
	exit();
}

// Preserve the existing customer CRUD flow while protecting every change with POST and CSRF.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if($submitted_token==="" || !hash_equals($customer_csrf,$submitted_token))
	{
		admin_customer_redirect("error","The request expired. Please refresh the page and try again.");
	}

	$action = (string)($_POST["action"] ?? "");
	if($action==="save_customer")
	{
		$mode = (string)($_POST["mode"] ?? "add");
		$customer_id = (int)($_POST["customer_id"] ?? 0);
		$customer_name = trim((string)($_POST["customer_name"] ?? ""));
		$customer_email = trim((string)($_POST["customer_email"] ?? ""));
		$customer_phone = trim((string)($_POST["customer_phone"] ?? ""));
		$customer_state = trim((string)($_POST["customer_state"] ?? ""));
		$customer_join_date = trim((string)($_POST["customer_joindate"] ?? ""));

		$join_date = DateTime::createFromFormat("!Y-m-d",$customer_join_date);
		if(!in_array($mode,array("add","update"),true) || ($mode==="update" && $customer_id<1))
		{
			admin_customer_redirect("error","The selected customer is invalid.");
		}
		$editor_location = $mode==="update" ? "admin_customer.php?edit=".$customer_id."#customer-editor" : "admin_customer.php#customer-editor";
		if(strlen($customer_name)<2 || strlen($customer_name)>100)
		{
			admin_customer_redirect("error","Enter a customer name between 2 and 100 characters.",$editor_location);
		}
		if(!filter_var($customer_email,FILTER_VALIDATE_EMAIL) || strlen($customer_email)>100)
		{
			admin_customer_redirect("error","Enter a valid customer email address.",$editor_location);
		}
		if(preg_match("/^\d{9,15}$/",$customer_phone)!==1 || !in_array($customer_state,$customer_states,true))
		{
			admin_customer_redirect("error","Enter a valid phone number and state.",$editor_location);
		}
		if(!$join_date || $join_date->format("Y-m-d")!==$customer_join_date)
		{
			admin_customer_redirect("error","Enter a valid join date.",$editor_location);
		}

		// The unique email rule includes soft-deleted rows, matching the database index.
		if($mode==="update")
		{
			$email_stmt = mysqli_prepare($connect,"SELECT customer_id FROM customer WHERE customer_email=? AND customer_id<>? LIMIT 1");
			mysqli_stmt_bind_param($email_stmt,"si",$customer_email,$customer_id);
		}
		else
		{
			$email_stmt = mysqli_prepare($connect,"SELECT customer_id FROM customer WHERE customer_email=? LIMIT 1");
			mysqli_stmt_bind_param($email_stmt,"s",$customer_email);
		}
		mysqli_stmt_execute($email_stmt);
		$email_result = mysqli_stmt_get_result($email_stmt);
		$email_exists = mysqli_fetch_assoc($email_result)!==null;
		mysqli_stmt_close($email_stmt);
		if($email_exists)
		{
			admin_customer_redirect("error","This customer email is already registered.",$editor_location);
		}

		if($mode==="update")
		{
			$save_stmt = mysqli_prepare($connect,"UPDATE customer SET customer_name=?,customer_email=?,customer_phone=?,customer_state=?,customer_joindate=? WHERE customer_id=? AND customer_isDelete=0");
			mysqli_stmt_bind_param($save_stmt,"sssssi",$customer_name,$customer_email,$customer_phone,$customer_state,$customer_join_date,$customer_id);
			$customer_saved = mysqli_stmt_execute($save_stmt);
			mysqli_stmt_close($save_stmt);
			admin_customer_redirect($customer_saved ? "success" : "error",$customer_saved ? "Customer updated successfully." : "The customer could not be updated.");
		}

		// New customers receive the same FYP demonstration defaults used by the existing system.
		$default_password = "customer123";
		$default_gender = "";
		$default_dob = "2000-01-01";
		$default_address = "";
		$default_city = "";
		$default_postcode = "";
		$save_stmt = mysqli_prepare($connect,"INSERT INTO customer(customer_name,customer_email,customer_password,customer_phone,customer_gender,customer_dob,customer_address,customer_state,customer_city,customer_postcode,customer_joindate,customer_isDelete) VALUES(?,?,?,?,?,?,?,?,?,?,?,0)");
		mysqli_stmt_bind_param($save_stmt,"sssssssssss",$customer_name,$customer_email,$default_password,$customer_phone,$default_gender,$default_dob,$default_address,$customer_state,$default_city,$default_postcode,$customer_join_date);
		$customer_saved = mysqli_stmt_execute($save_stmt);
		mysqli_stmt_close($save_stmt);
		admin_customer_redirect($customer_saved ? "success" : "error",$customer_saved ? "Customer added successfully." : "The customer could not be added.");
	}

	if($action==="delete_customer")
	{
		$customer_id = (int)($_POST["customer_id"] ?? 0);
		if($customer_id<1)
		{
			admin_customer_redirect("error","The selected customer is invalid.");
		}
		$delete_stmt = mysqli_prepare($connect,"UPDATE customer SET customer_isDelete=1 WHERE customer_id=? AND customer_isDelete=0");
		mysqli_stmt_bind_param($delete_stmt,"i",$customer_id);
		mysqli_stmt_execute($delete_stmt);
		$customer_removed = mysqli_stmt_affected_rows($delete_stmt)===1;
		mysqli_stmt_close($delete_stmt);
		admin_customer_redirect($customer_removed ? "success" : "error",$customer_removed ? "Customer removed successfully." : "The customer could not be removed.");
	}

	admin_customer_redirect("error","The requested customer action is not supported.");
}

$customer_flash = $_SESSION["admin_customer_flash"] ?? null;
unset($_SESSION["admin_customer_flash"]);

$search = trim((string)($_GET["search"] ?? ""));
$state_filter = trim((string)($_GET["state"] ?? ""));
if($state_filter!=="" && !in_array($state_filter,$customer_states,true))
{
	$state_filter = "";
}

// Search active customers by the identifying details an administrator can see in the table.
$like_search = "%".$search."%";
$customer_stmt = mysqli_prepare($connect,
	"SELECT customer_id,customer_name,customer_email,customer_phone,customer_state,customer_joindate
	 FROM customer
	 WHERE customer_isDelete=0
	 AND (?='' OR CAST(customer_id AS CHAR) LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)
	 AND (?='' OR customer_state=?)
	 ORDER BY customer_id DESC");
$customers = array();
if($customer_stmt)
{
	mysqli_stmt_bind_param($customer_stmt,"sssssss",$search,$like_search,$like_search,$like_search,$like_search,$state_filter,$state_filter);
	mysqli_stmt_execute($customer_stmt);
	$customer_result = mysqli_stmt_get_result($customer_stmt);
	while($customer_row = mysqli_fetch_assoc($customer_result))
	{
		$customers[] = $customer_row;
	}
	mysqli_stmt_close($customer_stmt);
}

// Load an existing customer into the editor through a prepared query.
$editing_customer = null;
$edit_id = (int)($_GET["edit"] ?? 0);
if($edit_id>0)
{
	$edit_stmt = mysqli_prepare($connect,"SELECT customer_id,customer_name,customer_email,customer_phone,customer_state,customer_joindate FROM customer WHERE customer_id=? AND customer_isDelete=0 LIMIT 1");
	mysqli_stmt_bind_param($edit_stmt,"i",$edit_id);
	mysqli_stmt_execute($edit_stmt);
	$edit_result = mysqli_stmt_get_result($edit_stmt);
	$editing_customer = mysqli_fetch_assoc($edit_result) ?: null;
	mysqli_stmt_close($edit_stmt);
}

// Keep CSV output aligned with the current Customer search and state filter.
$customer_export_params = array("type"=>"customers");
if($search!=="") $customer_export_params["search"] = $search;
if($state_filter!=="") $customer_export_params["state"] = $state_filter;
$customer_export_url = "admin_export.php?".http_build_query($customer_export_params);
$customer_pdf_params = $customer_export_params;
$customer_pdf_params["format"] = "pdf";
$customer_pdf_url = "admin_export.php?".http_build_query($customer_pdf_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" href="image/logo.png">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Customers - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_customer.php"); ?>

	<!-- Customer management heading and direct editor shortcut. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">CUSTOMER MANAGEMENT</p>
			<h1>Customers</h1>
			<p>Search registered customers and maintain their essential account details.</p>
			<p class="admin-print-context">Filters: Search <?php echo admin_customer_html($search!=="" ? $search : "All"); ?> · State <?php echo admin_customer_html($state_filter!=="" ? $state_filter : "All states"); ?></p>
		</div>
		<!-- Output actions use the current filtered list; editing controls do not print. -->
		<div class="admin-output-actions">
			<a class="admin-secondary-link" href="<?php echo admin_customer_html($customer_export_url); ?>">Export CSV</a>
			<a class="admin-secondary-link" href="<?php echo admin_customer_html($customer_pdf_url); ?>">Download PDF</a>
			<button type="button" class="admin-secondary-button" onclick="window.print()">Print List</button>
			<a class="admin-primary-link" href="#customer-editor">Add Customer</a>
		</div>
	</section>

	<?php if($customer_flash): ?>
		<div class="admin-alert admin-alert-<?php echo admin_customer_html($customer_flash["type"]); ?>" role="status">
			<?php echo admin_customer_html($customer_flash["message"]); ?>
		</div>
	<?php endif; ?>

	<!-- Search and state filters stay in the URL so the result can be bookmarked or reset. -->
	<section class="admin-user-filter-panel">
		<form class="admin-user-filter-form" method="get" action="admin_customer.php">
			<label class="admin-user-search">
				<span>Search customers</span>
				<input type="search" name="search" value="<?php echo admin_customer_html($search); ?>" placeholder="Customer ID, name, email or phone">
			</label>
			<label>
				<span>State</span>
				<select name="state">
					<option value="">All states</option>
					<?php foreach($customer_states as $state_name): ?>
						<option value="<?php echo admin_customer_html($state_name); ?>"<?php echo $state_filter===$state_name ? " selected" : ""; ?>><?php echo admin_customer_html($state_name); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="admin-user-filter-actions">
				<button type="submit">Search Customers</button>
				<a href="admin_customer.php">Reset</a>
			</div>
		</form>
	</section>

	<!-- Customer results are always generated from the live database query above. -->
	<section class="admin-user-results">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow">CUSTOMER LIST</p>
				<h2>Registered customers</h2>
			</div>
			<span class="admin-user-result-meta"><?php echo count($customers); ?> result<?php echo count($customers)===1 ? "" : "s"; ?></span>
		</header>
		<div class="admin-user-table-wrap">
			<table class="admin-user-table admin-customer-table">
				<thead>
					<tr><th>Customer</th><th>Contact</th><th>State</th><th>Join Date</th><th class="admin-print-hide">Actions</th></tr>
				</thead>
				<tbody>
				<?php if(!$customers): ?>
					<tr><td class="admin-list-empty" colspan="5">No customers match the current search.</td></tr>
				<?php else: ?>
					<?php foreach($customers as $customer): ?>
						<tr>
							<td><strong><?php echo admin_customer_html($customer["customer_name"]); ?></strong><small>Customer #<?php echo (int)$customer["customer_id"]; ?></small></td>
							<td><strong><?php echo admin_customer_html($customer["customer_email"]); ?></strong><small><?php echo admin_customer_html($customer["customer_phone"]); ?></small></td>
							<td><?php echo admin_customer_html($customer["customer_state"]); ?></td>
							<td><?php echo admin_customer_html($customer["customer_joindate"]); ?></td>
							<td class="admin-print-hide">
								<div class="admin-user-row-actions">
									<a href="admin_customer.php?edit=<?php echo (int)$customer["customer_id"]; ?>#customer-editor">Edit</a>
									<form method="post" action="admin_customer.php" onsubmit="return confirm('Remove this customer?');">
										<input type="hidden" name="csrf_token" value="<?php echo admin_customer_html($customer_csrf); ?>">
										<input type="hidden" name="action" value="delete_customer">
										<input type="hidden" name="customer_id" value="<?php echo (int)$customer["customer_id"]; ?>">
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

	<!-- Add or update one customer while preserving the existing administrator workflow. -->
	<section id="customer-editor" class="admin-user-editor">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow"><?php echo $editing_customer ? "UPDATE CUSTOMER" : "NEW CUSTOMER"; ?></p>
				<h2><?php echo $editing_customer ? "Edit ".admin_customer_html($editing_customer["customer_name"]) : "Add a customer customer"; ?></h2>
			</div>
			<?php if($editing_customer): ?><a class="admin-secondary-link" href="admin_customer.php#customer-editor">Cancel Edit</a><?php endif; ?>
		</header>

		<form class="admin-user-editor-form" method="post" action="admin_customer.php">
			<input type="hidden" name="csrf_token" value="<?php echo admin_customer_html($customer_csrf); ?>">
			<input type="hidden" name="action" value="save_customer">
			<input type="hidden" name="mode" value="<?php echo $editing_customer ? "update" : "add"; ?>">
			<input type="hidden" name="customer_id" value="<?php echo (int)($editing_customer["customer_id"] ?? 0); ?>">

			<div class="admin-user-fields">
				<?php if($editing_customer): ?>
					<label class="admin-form-field"><span>Customer ID</span><input type="text" value="<?php echo (int)$editing_customer["customer_id"]; ?>" readonly></label>
				<?php endif; ?>
				<label class="admin-form-field"><span>Full name</span><input type="text" name="customer_name" minlength="2" maxlength="100" value="<?php echo admin_customer_html($editing_customer["customer_name"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>Email address</span><input type="email" name="customer_email" maxlength="100" value="<?php echo admin_customer_html($editing_customer["customer_email"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>Phone number</span><input type="text" name="customer_phone" inputmode="numeric" pattern="[0-9]{9,15}" minlength="9" maxlength="15" value="<?php echo admin_customer_html($editing_customer["customer_phone"] ?? ""); ?>" required></label>
				<label class="admin-form-field"><span>State</span><select name="customer_state" required><option value="">Select state</option><?php foreach($customer_states as $state_name): ?><option value="<?php echo admin_customer_html($state_name); ?>"<?php echo ($editing_customer["customer_state"] ?? "")===$state_name ? " selected" : ""; ?>><?php echo admin_customer_html($state_name); ?></option><?php endforeach; ?></select></label>
				<label class="admin-form-field"><span>Join date</span><input type="date" name="customer_joindate" value="<?php echo admin_customer_html($editing_customer["customer_joindate"] ?? date("Y-m-d")); ?>" required></label>
			</div>

			<div class="admin-user-form-actions">
				<button type="submit"><?php echo $editing_customer ? "Save Customer Changes" : "Add Customer"; ?></button>
				<?php if($editing_customer): ?><a href="admin_customer.php#customer-editor">Cancel</a><?php endif; ?>
			</div>
		</form>
	</section>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
