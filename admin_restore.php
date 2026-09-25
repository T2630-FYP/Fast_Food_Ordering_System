<?php

// Block the recycle bin unless a valid administrator session is available.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("Location: admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");
require_once("product_catalog_helpers.php");

// Refresh the role from the database before applying restore permissions.
$current_staff_id = (string)$_SESSION["admin_id"];
$role_stmt = mysqli_prepare($connect,"SELECT staff_role FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
$current_staff_role = "";
if($role_stmt)
{
	mysqli_stmt_bind_param($role_stmt,"s",$current_staff_id);
	mysqli_stmt_execute($role_stmt);
	$role_result = mysqli_stmt_get_result($role_stmt);
	$role_row = mysqli_fetch_assoc($role_result) ?: null;
	$current_staff_role = (string)($role_row["staff_role"] ?? "");
	mysqli_stmt_close($role_stmt);
}
$_SESSION["admin_role"] = $current_staff_role;
$can_restore_staff = $current_staff_role==="Manager";

// Use one session token for all restore actions on this page.
if(empty($_SESSION["admin_restore_csrf"]))
{
	$_SESSION["admin_restore_csrf"] = bin2hex(random_bytes(32));
}
$restore_csrf = $_SESSION["admin_restore_csrf"];

// Escape database and request values before displaying them in HTML.
function admin_restore_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Store feedback in the session and redirect so refresh never repeats a restore.
function admin_restore_redirect($type,$message,$resource_filter="all",$search="")
{
	$_SESSION["admin_restore_flash"] = array("type"=>$type,"message"=>$message);
	$query = array();
	if($resource_filter!=="all")
	{
		$query["type"] = $resource_filter;
	}
	if($search!=="")
	{
		$query["search"] = $search;
	}
	$location = "admin_restore.php".($query ? "?".http_build_query($query) : "");
	header("Location: ".$location,true,303);
	exit();
}

$allowed_filters = array("all","member","product","staff");
$resource_filter = strtolower(trim((string)($_GET["type"] ?? $_POST["return_type"] ?? "all")));
if(!in_array($resource_filter,$allowed_filters,true))
{
	$resource_filter = "all";
}
$search = trim((string)($_GET["search"] ?? $_POST["return_search"] ?? ""));
if(strlen($search)>100)
{
	$search = substr($search,0,100);
}

// Restore only allowlisted resources through a validated POST request.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if($submitted_token==="" || !hash_equals($restore_csrf,$submitted_token))
	{
		admin_restore_redirect("error","The request expired. Please refresh the page and try again.",$resource_filter,$search);
	}

	$action = (string)($_POST["action"] ?? "");
	$resource_type = strtolower(trim((string)($_POST["resource_type"] ?? "")));
	$resource_id = strtoupper(trim((string)($_POST["resource_id"] ?? "")));
	if($action!=="restore_record" || !in_array($resource_type,array("member","product","staff"),true))
	{
		admin_restore_redirect("error","The requested restore action is not supported.",$resource_filter,$search);
	}

	if($resource_type==="member")
	{
		$member_id = filter_var($resource_id,FILTER_VALIDATE_INT,array("options"=>array("min_range"=>1)));
		if($member_id===false)
		{
			admin_restore_redirect("error","The selected member is invalid.",$resource_filter,$search);
		}

		$restore_stmt = mysqli_prepare($connect,"UPDATE member SET member_isDelete=0 WHERE member_id=? AND member_isDelete=1");
		if($restore_stmt)
		{
			mysqli_stmt_bind_param($restore_stmt,"i",$member_id);
			mysqli_stmt_execute($restore_stmt);
			$restored = mysqli_stmt_affected_rows($restore_stmt)===1;
			mysqli_stmt_close($restore_stmt);
		}
		else
		{
			$restored = false;
		}
		admin_restore_redirect($restored ? "success" : "error",$restored ? "Member restored successfully." : "The member is no longer available for restore.",$resource_filter,$search);
	}

	if(preg_match("/^[A-Z0-9]{1,5}$/",$resource_id)!==1)
	{
		admin_restore_redirect("error","The selected record is invalid.",$resource_filter,$search);
	}

	if($resource_type==="staff")
	{
		// Normal administrators may view deleted staff but cannot restore an account.
		if(!$can_restore_staff)
		{
			admin_restore_redirect("error","Only a Manager can restore staff accounts.",$resource_filter,$search);
		}

		$restore_stmt = mysqli_prepare($connect,"UPDATE staff SET staff_isDelete=0 WHERE staff_id=? AND staff_isDelete=1");
		if($restore_stmt)
		{
			mysqli_stmt_bind_param($restore_stmt,"s",$resource_id);
			mysqli_stmt_execute($restore_stmt);
			$restored = mysqli_stmt_affected_rows($restore_stmt)===1;
			mysqli_stmt_close($restore_stmt);
		}
		else
		{
			$restored = false;
		}
		admin_restore_redirect($restored ? "success" : "error",$restored ? "Staff account restored successfully." : "The staff account is no longer available for restore.",$resource_filter,$search);
	}

	// Restored products remain Inactive until an administrator reviews and publishes them.
	$restore_stmt = mysqli_prepare($connect,"UPDATE product SET product_isDelete=0,product_status='Inactive' WHERE product_id=? AND product_isDelete=1");
	if($restore_stmt)
	{
		mysqli_stmt_bind_param($restore_stmt,"s",$resource_id);
		mysqli_stmt_execute($restore_stmt);
		$restored = mysqli_stmt_affected_rows($restore_stmt)===1;
		mysqli_stmt_close($restore_stmt);
	}
	else
	{
		$restored = false;
	}
	admin_restore_redirect($restored ? "success" : "error",$restored ? "Product restored as Inactive. Review it in Products before publishing." : "The product is no longer available for restore.",$resource_filter,$search);
}

$restore_flash = $_SESSION["admin_restore_flash"] ?? null;
unset($_SESSION["admin_restore_flash"]);

// Count all deleted records separately from the current search results.
$count_result = mysqli_query($connect,
	"SELECT
	 (SELECT COUNT(*) FROM member WHERE member_isDelete=1) AS member_total,
	 (SELECT COUNT(*) FROM product WHERE product_isDelete=1) AS product_total,
	 (SELECT COUNT(*) FROM staff WHERE staff_isDelete=1) AS staff_total");
$count_row = $count_result ? mysqli_fetch_assoc($count_result) : array();
$deleted_counts = array(
	"member"=>(int)($count_row["member_total"] ?? 0),
	"product"=>(int)($count_row["product_total"] ?? 0),
	"staff"=>(int)($count_row["staff_total"] ?? 0)
);

$like_search = "%".$search."%";
$deleted_members = array();
$member_stmt = mysqli_prepare($connect,
	"SELECT member_id,member_name,member_email,member_phone,member_joindate
	 FROM member
	 WHERE member_isDelete=1
	 AND (?='' OR CAST(member_id AS CHAR) LIKE ? OR member_name LIKE ? OR member_email LIKE ? OR member_phone LIKE ?)
	 ORDER BY member_id DESC");
if($member_stmt)
{
	mysqli_stmt_bind_param($member_stmt,"sssss",$search,$like_search,$like_search,$like_search,$like_search);
	mysqli_stmt_execute($member_stmt);
	$member_result = mysqli_stmt_get_result($member_stmt);
	while($member_row = mysqli_fetch_assoc($member_result))
	{
		$deleted_members[] = $member_row;
	}
	mysqli_stmt_close($member_stmt);
}

$deleted_products = array();
$product_stmt = mysqli_prepare($connect,
	"SELECT p.product_id,p.product_name,p.product_image,p.product_category,p.product_price,p.product_stock,
	        COALESCE(c.category_isDelete,1) AS category_is_deleted
	 FROM product p
	 LEFT JOIN category c ON c.category_name=p.product_category
	 WHERE p.product_isDelete=1
	 AND (?='' OR p.product_id LIKE ? OR p.product_name LIKE ? OR p.product_category LIKE ?)
	 ORDER BY p.product_name");
if($product_stmt)
{
	mysqli_stmt_bind_param($product_stmt,"ssss",$search,$like_search,$like_search,$like_search);
	mysqli_stmt_execute($product_stmt);
	$product_result = mysqli_stmt_get_result($product_stmt);
	while($product_row = mysqli_fetch_assoc($product_result))
	{
		$deleted_products[] = $product_row;
	}
	mysqli_stmt_close($product_stmt);
}

$deleted_staff = array();
$staff_stmt = mysqli_prepare($connect,
	"SELECT staff_id,staff_name,staff_role,staff_email,staff_phone
	 FROM staff
	 WHERE staff_isDelete=1
	 AND (?='' OR staff_id LIKE ? OR staff_name LIKE ? OR staff_role LIKE ? OR staff_email LIKE ? OR staff_phone LIKE ?)
	 ORDER BY staff_name");
if($staff_stmt)
{
	mysqli_stmt_bind_param($staff_stmt,"ssssss",$search,$like_search,$like_search,$like_search,$like_search,$like_search);
	mysqli_stmt_execute($staff_stmt);
	$staff_result = mysqli_stmt_get_result($staff_stmt);
	while($staff_row = mysqli_fetch_assoc($staff_result))
	{
		$deleted_staff[] = $staff_row;
	}
	mysqli_stmt_close($staff_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Recycle Bin - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_restore.php"); ?>

	<!-- Recycle bin heading and restore permission summary. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">RECOVERY MANAGEMENT</p>
			<h1>Recycle Bin</h1>
			<p>Review and restore deleted member, product and staff records.</p>
		</div>
		<span class="admin-restore-role"><?php echo $can_restore_staff ? "Manager restore access" : "Standard restore access"; ?></span>
	</section>

	<?php if($restore_flash): ?>
		<div class="admin-alert admin-alert-<?php echo admin_restore_html($restore_flash["type"]); ?>" role="status">
			<?php echo admin_restore_html($restore_flash["message"]); ?>
		</div>
	<?php endif; ?>

	<!-- Restore totals make the remaining soft-deleted records visible at a glance. -->
	<section class="admin-restore-stat-grid" aria-label="Deleted record totals">
		<article><span>ME</span><div><strong><?php echo $deleted_counts["member"]; ?></strong><small>Deleted members</small></div></article>
		<article><span>PR</span><div><strong><?php echo $deleted_counts["product"]; ?></strong><small>Deleted products</small></div></article>
		<article><span>ST</span><div><strong><?php echo $deleted_counts["staff"]; ?></strong><small>Deleted staff</small></div></article>
	</section>

	<!-- Type and search filters use GET so the selected recycle-bin view is shareable. -->
	<section class="admin-restore-filter-panel">
		<form class="admin-restore-filter-form" method="get" action="admin_restore.php">
			<label><span>Record type</span><select name="type">
				<option value="all"<?php echo $resource_filter==="all" ? " selected" : ""; ?>>All records</option>
				<option value="member"<?php echo $resource_filter==="member" ? " selected" : ""; ?>>Members</option>
				<option value="product"<?php echo $resource_filter==="product" ? " selected" : ""; ?>>Products</option>
				<option value="staff"<?php echo $resource_filter==="staff" ? " selected" : ""; ?>>Staff</option>
			</select></label>
			<label class="admin-restore-search"><span>Search deleted records</span><input type="search" name="search" value="<?php echo admin_restore_html($search); ?>" placeholder="ID, name, email, phone, category or role"></label>
			<div class="admin-restore-filter-actions"><button type="submit">Apply</button><a href="admin_restore.php">Reset</a></div>
		</form>
	</section>

	<p class="admin-restore-note"><strong>Restore rule:</strong> Products return as Inactive for review. Staff restoration is restricted to Managers and is checked again on the server.</p>

	<?php if($resource_filter==="all" || $resource_filter==="member"): ?>
	<section class="admin-restore-panel" aria-labelledby="restore-members-title">
		<div class="admin-section-heading"><div><p>MEMBER RECORDS</p><h2 id="restore-members-title">Deleted Members</h2></div><span><?php echo count($deleted_members); ?> result<?php echo count($deleted_members)===1 ? "" : "s"; ?></span></div>
		<?php if(!$deleted_members): ?>
			<div class="admin-empty-state"><span>0</span><strong>No deleted members found</strong><p>Deleted member accounts that match the search will appear here.</p></div>
		<?php else: ?>
			<div class="admin-restore-table-wrap"><table class="admin-restore-table"><thead><tr><th>Member</th><th>Email</th><th>Phone</th><th>Joined</th><th>Action</th></tr></thead><tbody>
			<?php foreach($deleted_members as $member): ?>
				<tr><td><strong><?php echo admin_restore_html($member["member_name"]); ?></strong><small>#<?php echo (int)$member["member_id"]; ?></small></td><td><?php echo admin_restore_html($member["member_email"]); ?></td><td><?php echo admin_restore_html($member["member_phone"]); ?></td><td><?php echo admin_restore_html($member["member_joindate"]); ?></td><td>
					<form method="post" action="admin_restore.php" onsubmit="return confirm('Restore this member account?')"><input type="hidden" name="csrf_token" value="<?php echo admin_restore_html($restore_csrf); ?>"><input type="hidden" name="action" value="restore_record"><input type="hidden" name="resource_type" value="member"><input type="hidden" name="resource_id" value="<?php echo (int)$member["member_id"]; ?>"><input type="hidden" name="return_type" value="<?php echo admin_restore_html($resource_filter); ?>"><input type="hidden" name="return_search" value="<?php echo admin_restore_html($search); ?>"><button type="submit">Restore</button></form>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if($resource_filter==="all" || $resource_filter==="product"): ?>
	<section class="admin-restore-panel" aria-labelledby="restore-products-title">
		<div class="admin-section-heading"><div><p>CATALOG RECORDS</p><h2 id="restore-products-title">Deleted Products</h2></div><span><?php echo count($deleted_products); ?> result<?php echo count($deleted_products)===1 ? "" : "s"; ?></span></div>
		<?php if(!$deleted_products): ?>
			<div class="admin-empty-state"><span>0</span><strong>No deleted products found</strong><p>Deleted products that match the search will appear here.</p></div>
		<?php else: ?>
			<div class="admin-restore-table-wrap"><table class="admin-restore-table"><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Action</th></tr></thead><tbody>
			<?php foreach($deleted_products as $product): ?>
				<tr><td><div class="admin-restore-product"><img src="<?php echo admin_restore_html(catalog_product_image($product["product_image"])); ?>" width="52" height="42" alt=""><div><strong><?php echo admin_restore_html($product["product_name"]); ?></strong><small><?php echo admin_restore_html($product["product_id"]); ?></small></div></div></td><td><?php echo admin_restore_html($product["product_category"]); ?><?php if((int)$product["category_is_deleted"]===1): ?><small class="admin-restore-warning">Category inactive</small><?php endif; ?></td><td>RM <?php echo number_format((float)$product["product_price"],2); ?></td><td><?php echo (int)$product["product_stock"]; ?></td><td>
					<form method="post" action="admin_restore.php" onsubmit="return confirm('Restore this product as Inactive?')"><input type="hidden" name="csrf_token" value="<?php echo admin_restore_html($restore_csrf); ?>"><input type="hidden" name="action" value="restore_record"><input type="hidden" name="resource_type" value="product"><input type="hidden" name="resource_id" value="<?php echo admin_restore_html($product["product_id"]); ?>"><input type="hidden" name="return_type" value="<?php echo admin_restore_html($resource_filter); ?>"><input type="hidden" name="return_search" value="<?php echo admin_restore_html($search); ?>"><button type="submit">Restore</button></form>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if($resource_filter==="all" || $resource_filter==="staff"): ?>
	<section class="admin-restore-panel" aria-labelledby="restore-staff-title">
		<div class="admin-section-heading"><div><p>ADMINISTRATOR RECORDS</p><h2 id="restore-staff-title">Deleted Staff</h2></div><span><?php echo count($deleted_staff); ?> result<?php echo count($deleted_staff)===1 ? "" : "s"; ?></span></div>
		<?php if(!$deleted_staff): ?>
			<div class="admin-empty-state"><span>0</span><strong>No deleted staff found</strong><p>Deleted staff accounts that match the search will appear here.</p></div>
		<?php else: ?>
			<div class="admin-restore-table-wrap"><table class="admin-restore-table"><thead><tr><th>Staff</th><th>Role</th><th>Email</th><th>Phone</th><th>Action</th></tr></thead><tbody>
			<?php foreach($deleted_staff as $staff): ?>
				<tr><td><strong><?php echo admin_restore_html($staff["staff_name"]); ?></strong><small><?php echo admin_restore_html($staff["staff_id"]); ?></small></td><td><span class="admin-status-badge status-info"><?php echo admin_restore_html($staff["staff_role"]); ?></span></td><td><?php echo admin_restore_html($staff["staff_email"]); ?></td><td><?php echo admin_restore_html($staff["staff_phone"]); ?></td><td>
					<?php if($can_restore_staff): ?><form method="post" action="admin_restore.php" onsubmit="return confirm('Restore this staff account?')"><input type="hidden" name="csrf_token" value="<?php echo admin_restore_html($restore_csrf); ?>"><input type="hidden" name="action" value="restore_record"><input type="hidden" name="resource_type" value="staff"><input type="hidden" name="resource_id" value="<?php echo admin_restore_html($staff["staff_id"]); ?>"><input type="hidden" name="return_type" value="<?php echo admin_restore_html($resource_filter); ?>"><input type="hidden" name="return_search" value="<?php echo admin_restore_html($search); ?>"><button type="submit">Restore</button></form><?php else: ?><span class="admin-restore-locked">Manager only</span><?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
