<?php

// Block this page if the administrator is not logged in.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("location:admin_login.php");
	exit();
}

include("dataconnection.php");
require_once("admin_shell.php");

// Share the catalog token with product management actions.
if(empty($_SESSION["admin_catalog_csrf"]))
{
	$_SESSION["admin_catalog_csrf"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["admin_catalog_csrf"];

// Escape category values before displaying them in the page.
function admin_category_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

// Store one result message and redirect to prevent duplicate submissions.
function admin_category_redirect($type,$message,$location="admin_category.php")
{
	$_SESSION["admin_category_flash"] = array("type"=>$type,"message"=>$message);
	header("Location: ".$location,true,303);
	exit();
}

// Process category changes only through validated POST requests.
if($_SERVER["REQUEST_METHOD"]==="POST")
{
	$submitted_token = (string)($_POST["csrf_token"] ?? "");
	if(!hash_equals($csrf_token,$submitted_token))
	{
		admin_category_redirect("error","The request expired. Please try again.");
	}

	$action = (string)($_POST["action"] ?? "");
	if($action==="save_category")
	{
		$mode = (string)($_POST["mode"] ?? "add");
		$category_id = strtoupper(trim((string)($_POST["category_id"] ?? "")));
		$category_name = trim((string)($_POST["category_name"] ?? ""));
		$category_desc = trim((string)($_POST["category_desc"] ?? ""));
		$category_status = trim((string)($_POST["category_status"] ?? ""));

		if(!in_array($mode,array("add","update"),true) || preg_match("/^[A-Z0-9]{1,5}$/",$category_id)!==1)
		{
			admin_category_redirect("error","Please enter a valid category ID using up to 5 letters or numbers.");
		}
		if($category_name==="" || strlen($category_name)>50 || $category_desc==="" || strlen($category_desc)>255)
		{
			admin_category_redirect("error","Please complete the category name and description within their character limits.");
		}
		if(!in_array($category_status,array("Active","Inactive"),true))
		{
			admin_category_redirect("error","Please select a valid category status.");
		}

		mysqli_begin_transaction($connect);
		try
		{
			if($mode==="add")
			{
				$save_stmt = mysqli_prepare($connect,"INSERT INTO category(category_id,category_name,category_desc,category_status,category_isDelete) VALUES(?,?,?,?,0)");
				if(!$save_stmt)
				{
					throw new RuntimeException("The category could not be prepared.");
				}
				mysqli_stmt_bind_param($save_stmt,"ssss",$category_id,$category_name,$category_desc,$category_status);
			}
			else
			{
				$save_stmt = mysqli_prepare($connect,"UPDATE category SET category_name=?,category_desc=?,category_status=? WHERE category_id=? AND category_isDelete=0");
				if(!$save_stmt)
				{
					throw new RuntimeException("The category could not be prepared.");
				}
				mysqli_stmt_bind_param($save_stmt,"ssss",$category_name,$category_desc,$category_status,$category_id);
			}

			if(!mysqli_stmt_execute($save_stmt))
			{
				throw new RuntimeException("The category ID or name already exists.");
			}
			if($mode==="update" && mysqli_stmt_affected_rows($save_stmt)===0)
			{
				// A no-change save is valid only if the category still exists.
				$exists_stmt = mysqli_prepare($connect,"SELECT category_id FROM category WHERE category_id=? AND category_isDelete=0 LIMIT 1");
				if(!$exists_stmt)
				{
					throw new RuntimeException("The category could not be verified.");
				}
				mysqli_stmt_bind_param($exists_stmt,"s",$category_id);
				mysqli_stmt_execute($exists_stmt);
				$exists_result = mysqli_stmt_get_result($exists_stmt);
				$category_exists = mysqli_fetch_assoc($exists_result)!==null;
				mysqli_stmt_close($exists_stmt);
				if(!$category_exists)
				{
					throw new RuntimeException("The selected category is unavailable.");
				}
			}
			mysqli_stmt_close($save_stmt);
			mysqli_commit($connect);
			admin_category_redirect("success",$mode==="add" ? "Category added successfully." : "Category updated successfully.");
		}
		catch(Throwable $error)
		{
			mysqli_rollback($connect);
			admin_category_redirect("error",$error->getMessage());
		}
	}

	if($action==="delete_category")
	{
		$category_id = strtoupper(trim((string)($_POST["category_id"] ?? "")));
		if(preg_match("/^[A-Z0-9]{1,5}$/",$category_id)!==1)
		{
			admin_category_redirect("error","Please select a valid category.");
		}

		mysqli_begin_transaction($connect);
		try
		{
			$category_stmt = mysqli_prepare($connect,"SELECT category_name FROM category WHERE category_id=? AND category_isDelete=0 FOR UPDATE");
			if(!$category_stmt)
			{
				throw new RuntimeException("The category could not be locked.");
			}
			mysqli_stmt_bind_param($category_stmt,"s",$category_id);
			mysqli_stmt_execute($category_stmt);
			$category_result = mysqli_stmt_get_result($category_stmt);
			$category_row = mysqli_fetch_assoc($category_result) ?: null;
			mysqli_stmt_close($category_stmt);
			if(!$category_row)
			{
				throw new RuntimeException("The selected category is unavailable.");
			}

			// Prevent orphaned menu items; move or remove products before deleting a category.
			$product_stmt = mysqli_prepare($connect,"SELECT COUNT(*) AS product_count FROM product WHERE product_category=? AND product_isDelete=0");
			if(!$product_stmt)
			{
				throw new RuntimeException("Linked products could not be checked.");
			}
			mysqli_stmt_bind_param($product_stmt,"s",$category_row["category_name"]);
			mysqli_stmt_execute($product_stmt);
			$product_result = mysqli_stmt_get_result($product_stmt);
			$product_count = (int)(mysqli_fetch_assoc($product_result)["product_count"] ?? 0);
			mysqli_stmt_close($product_stmt);
			if($product_count>0)
			{
				throw new RuntimeException("This category still contains ".$product_count." product".($product_count===1 ? "" : "s").". Move or remove them first.");
			}

			$delete_stmt = mysqli_prepare($connect,"UPDATE category SET category_isDelete=1,category_status='Inactive' WHERE category_id=? AND category_isDelete=0");
			if(!$delete_stmt)
			{
				throw new RuntimeException("The category could not be prepared for removal.");
			}
			mysqli_stmt_bind_param($delete_stmt,"s",$category_id);
			if(!mysqli_stmt_execute($delete_stmt) || mysqli_stmt_affected_rows($delete_stmt)!==1)
			{
				throw new RuntimeException("The category could not be removed.");
			}
			mysqli_stmt_close($delete_stmt);
			mysqli_commit($connect);
			admin_category_redirect("success","Category removed successfully.");
		}
		catch(Throwable $error)
		{
			mysqli_rollback($connect);
			admin_category_redirect("error",$error->getMessage());
		}
	}

	admin_category_redirect("error","The requested category action is not supported.");
}

$flash = $_SESSION["admin_category_flash"] ?? null;
unset($_SESSION["admin_category_flash"]);

$search = trim((string)($_GET["search"] ?? ""));
$status_filter = trim((string)($_GET["status"] ?? ""));
if(!in_array($status_filter,array("","Active","Inactive"),true))
{
	$status_filter = "";
}

// Search categories and include their current product totals.
$like_search = "%".$search."%";
$category_stmt = mysqli_prepare($connect,
	"SELECT c.category_id,c.category_name,c.category_desc,c.category_status,COUNT(p.product_id) AS product_count
	 FROM category c
	 LEFT JOIN product p ON p.product_category=c.category_name AND p.product_isDelete=0
	 WHERE c.category_isDelete=0
	 AND (?='' OR c.category_id LIKE ? OR c.category_name LIKE ? OR c.category_desc LIKE ?)
	 AND (?='' OR c.category_status=?)
	 GROUP BY c.category_id,c.category_name,c.category_desc,c.category_status
	 ORDER BY c.category_name");
$categories = array();
if($category_stmt)
{
	mysqli_stmt_bind_param($category_stmt,"ssssss",$search,$like_search,$like_search,$like_search,$status_filter,$status_filter);
	mysqli_stmt_execute($category_stmt);
	$category_result = mysqli_stmt_get_result($category_stmt);
	while($category_row = mysqli_fetch_assoc($category_result))
	{
		$categories[] = $category_row;
	}
	mysqli_stmt_close($category_stmt);
}

// Load the selected category into the editor using a prepared query.
$editing_category = null;
$edit_id = strtoupper(trim((string)($_GET["edit"] ?? "")));
if($edit_id!=="")
{
	$edit_stmt = mysqli_prepare($connect,"SELECT category_id,category_name,category_desc,category_status FROM category WHERE category_id=? AND category_isDelete=0 LIMIT 1");
	if($edit_stmt)
	{
		mysqli_stmt_bind_param($edit_stmt,"s",$edit_id);
		mysqli_stmt_execute($edit_stmt);
		$edit_result = mysqli_stmt_get_result($edit_stmt);
		$editing_category = mysqli_fetch_assoc($edit_result) ?: null;
		mysqli_stmt_close($edit_stmt);
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Categories - EasyOrder</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">
<?php easyorder_admin_shell_start("admin_category.php"); ?>

	<!-- Category management heading and primary action. -->
	<section class="admin-page-heading">
		<div>
			<p class="admin-eyebrow">CATALOG MANAGEMENT</p>
			<h1>Categories</h1>
			<p>Organise products into clear menu sections and control their visibility.</p>
		</div>
		<a class="admin-primary-link" href="#category-editor">Add Category</a>
	</section>

	<?php if($flash): ?>
		<div class="admin-alert admin-alert-<?php echo admin_category_html($flash["type"]); ?>" role="status">
			<?php echo admin_category_html($flash["message"]); ?>
		</div>
	<?php endif; ?>

	<!-- Category search and status filter. -->
	<section class="admin-catalog-filter-panel">
		<form class="admin-catalog-filter-form admin-category-filter-form" method="get" action="admin_category.php">
			<label class="admin-catalog-search">
				<span>Search categories</span>
				<input type="search" name="search" value="<?php echo admin_category_html($search); ?>" placeholder="Category ID, name or description">
			</label>
			<label>
				<span>Status</span>
				<select name="status">
					<option value="">All statuses</option>
					<option value="Active"<?php echo $status_filter==="Active" ? " selected" : ""; ?>>Active</option>
					<option value="Inactive"<?php echo $status_filter==="Inactive" ? " selected" : ""; ?>>Inactive</option>
				</select>
			</label>
			<div class="admin-catalog-filter-actions">
				<button type="submit">Apply Filters</button>
				<a href="admin_category.php">Reset</a>
			</div>
		</form>
	</section>

	<!-- Filtered category records with live product counts. -->
	<section class="admin-catalog-results">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow">CATEGORY LIST</p>
				<h2>Menu categories</h2>
			</div>
			<span class="admin-catalog-result-meta"><?php echo count($categories); ?> result<?php echo count($categories)===1 ? "" : "s"; ?></span>
		</header>
		<div class="admin-catalog-table-wrap">
			<table class="admin-catalog-table admin-category-table">
				<thead>
					<tr><th>Category</th><th>Description</th><th>Products</th><th>Status</th><th>Actions</th></tr>
				</thead>
				<tbody>
				<?php if(!$categories): ?>
					<tr><td class="admin-catalog-empty" colspan="5">No categories match the selected filters.</td></tr>
				<?php else: ?>
					<?php foreach($categories as $category): ?>
						<tr>
							<td><strong><?php echo admin_category_html($category["category_name"]); ?></strong><small><?php echo admin_category_html($category["category_id"]); ?></small></td>
							<td class="admin-category-description"><?php echo admin_category_html($category["category_desc"]); ?></td>
							<td><?php echo (int)$category["product_count"]; ?></td>
							<td><span class="admin-status-badge <?php echo $category["category_status"]==="Active" ? "status-success" : "status-danger"; ?>"><?php echo admin_category_html($category["category_status"]); ?></span></td>
							<td>
								<div class="admin-catalog-row-actions">
									<a href="admin_category.php?edit=<?php echo rawurlencode($category["category_id"]); ?>#category-editor">Edit</a>
									<form method="post" action="admin_category.php" onsubmit="return confirm('Remove this category? Categories containing products cannot be removed.');">
										<input type="hidden" name="csrf_token" value="<?php echo admin_category_html($csrf_token); ?>">
										<input type="hidden" name="action" value="delete_category">
										<input type="hidden" name="category_id" value="<?php echo admin_category_html($category["category_id"]); ?>">
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

	<!-- Add or edit one category in the live database. -->
	<section id="category-editor" class="admin-catalog-editor">
		<header class="admin-section-heading">
			<div>
				<p class="admin-eyebrow"><?php echo $editing_category ? "UPDATE CATEGORY" : "NEW CATEGORY"; ?></p>
				<h2><?php echo $editing_category ? "Edit ".admin_category_html($editing_category["category_name"]) : "Add a menu category"; ?></h2>
			</div>
			<?php if($editing_category): ?><a class="admin-secondary-link" href="admin_category.php#category-editor">Cancel Edit</a><?php endif; ?>
		</header>

		<form class="admin-catalog-editor-form admin-category-editor-form" name="categoryfrm" method="post" action="admin_category.php">
			<input type="hidden" name="csrf_token" value="<?php echo admin_category_html($csrf_token); ?>">
			<input type="hidden" name="action" value="save_category">
			<input type="hidden" name="mode" value="<?php echo $editing_category ? "update" : "add"; ?>">

			<div class="admin-catalog-fields">
				<label class="admin-form-field">
					<span>Category ID</span>
					<input type="text" name="category_id" maxlength="5" pattern="[A-Za-z0-9]{1,5}" value="<?php echo admin_category_html($editing_category["category_id"] ?? ""); ?>"<?php echo $editing_category ? " readonly" : ""; ?> required>
				</label>
				<label class="admin-form-field">
					<span>Category name</span>
					<input type="text" name="category_name" maxlength="50" value="<?php echo admin_category_html($editing_category["category_name"] ?? ""); ?>" required>
				</label>
				<label class="admin-form-field admin-field-wide">
					<span>Description</span>
					<textarea name="category_desc" maxlength="255" rows="3" required><?php echo admin_category_html($editing_category["category_desc"] ?? ""); ?></textarea>
				</label>
				<label class="admin-form-field">
					<span>Status</span>
					<select name="category_status" required>
						<?php $selected_status = $editing_category["category_status"] ?? "Active"; ?>
						<option value="Active"<?php echo $selected_status==="Active" ? " selected" : ""; ?>>Active</option>
						<option value="Inactive"<?php echo $selected_status==="Inactive" ? " selected" : ""; ?>>Inactive</option>
					</select>
				</label>
			</div>

			<div class="admin-catalog-form-actions">
				<button type="submit"><?php echo $editing_category ? "Save Category Changes" : "Add Category"; ?></button>
				<?php if($editing_category): ?><a href="admin_category.php#category-editor">Cancel</a><?php endif; ?>
			</div>
		</form>
	</section>

<?php easyorder_admin_shell_end(); ?>
</body>
</html>
