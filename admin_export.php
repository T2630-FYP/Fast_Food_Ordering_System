<?php

// Allow CSV downloads only for an active administrator session.
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("Location: admin_login.php");
	exit();
}

include("dataconnection.php");

// Return a plain error response instead of a partial or misleading CSV file.
function admin_export_fail($status,$message)
{
	http_response_code($status);
	header("Content-Type: text/plain; charset=UTF-8");
	header("X-Content-Type-Options: nosniff");
	echo $message;
	exit();
}

// Prevent spreadsheet software from interpreting exported text as a formula.
function admin_export_cell($value)
{
	$text = (string)$value;
	if(preg_match("/^[\x00-\x20]*[=+\-@]/",$text)===1)
	{
		return "'".$text;
	}
	return $text;
}

// Stream a UTF-8 CSV attachment after all rows have been loaded successfully.
function admin_export_download($filename,$headers,$rows)
{
	header("Content-Type: text/csv; charset=UTF-8");
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Pragma: no-cache");
	header("X-Content-Type-Options: nosniff");

	$output = fopen("php://output","w");
	if($output===false)
	{
		admin_export_fail(500,"The CSV file could not be created.");
	}

	echo "\xEF\xBB\xBF";
	fputcsv($output,$headers);
	foreach($rows as $row)
	{
		fputcsv($output,array_map("admin_export_cell",$row));
	}
	fclose($output);
	exit();
}

if(!$connect)
{
	admin_export_fail(503,"The database is temporarily unavailable.");
}
mysqli_set_charset($connect,"utf8mb4");

$dataset = strtolower(trim((string)($_GET["type"] ?? "")));
if(!in_array($dataset,array("members","products","orders","staff"),true))
{
	admin_export_fail(400,"Select a supported export type.");
}

$headers = array();
$rows = array();
$filename = "";

if($dataset==="members")
{
	$member_states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");
	$search = trim((string)($_GET["search"] ?? ""));
	$state_filter = trim((string)($_GET["state"] ?? ""));
	if($state_filter!=="" && !in_array($state_filter,$member_states,true))
	{
		$state_filter = "";
	}
	$like_search = "%".$search."%";

	// Match the Member page search and state filter without exporting passwords.
	$stmt = mysqli_prepare($connect,
		"SELECT member_id,member_name,member_email,member_phone,member_state,member_joindate
		 FROM member
		 WHERE member_isDelete=0
		 AND (?='' OR CAST(member_id AS CHAR) LIKE ? OR member_name LIKE ? OR member_email LIKE ? OR member_phone LIKE ?)
		 AND (?='' OR member_state=?)
		 ORDER BY member_id DESC");
	if(!$stmt)
	{
		admin_export_fail(500,"The member export could not be prepared.");
	}
	mysqli_stmt_bind_param($stmt,"sssssss",$search,$like_search,$like_search,$like_search,$like_search,$state_filter,$state_filter);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		admin_export_fail(500,"The member export could not be loaded.");
	}
	$result = mysqli_stmt_get_result($stmt);
	while($row = mysqli_fetch_assoc($result))
	{
		$rows[] = array($row["member_id"],$row["member_name"],$row["member_email"],$row["member_phone"],$row["member_state"],$row["member_joindate"]);
	}
	mysqli_stmt_close($stmt);
	$headers = array("Member ID","Name","Email","Phone","State","Join Date");
	$filename = "easyorder-members-".date("Ymd-His").".csv";
}
else if($dataset==="products")
{
	$search = trim((string)($_GET["search"] ?? ""));
	$category_filter = trim((string)($_GET["category"] ?? ""));
	$status_filter = trim((string)($_GET["status"] ?? ""));
	$stock_filter = trim((string)($_GET["stock"] ?? ""));
	if(!in_array($status_filter,array("","Active","Inactive","Out of Stock"),true))
	{
		$status_filter = "";
	}
	if(!in_array($stock_filter,array("","in_stock","out_of_stock"),true))
	{
		$stock_filter = "";
	}

	// Reject a deleted or unknown category rather than applying a different filter.
	if($category_filter!=="")
	{
		$category_stmt = mysqli_prepare($connect,"SELECT category_name FROM category WHERE category_name=? AND category_isDelete=0 LIMIT 1");
		if(!$category_stmt)
		{
			admin_export_fail(500,"The product category could not be checked.");
		}
		mysqli_stmt_bind_param($category_stmt,"s",$category_filter);
		mysqli_stmt_execute($category_stmt);
		$category_result = mysqli_stmt_get_result($category_stmt);
		$category_filter = mysqli_fetch_assoc($category_result) ? $category_filter : "";
		mysqli_stmt_close($category_stmt);
	}

	$like_search = "%".$search."%";
	$stmt = mysqli_prepare($connect,
		"SELECT product_id,product_name,product_desc,product_image,product_category,product_price,product_stock,product_status
		 FROM product
		 WHERE product_isDelete=0
		 AND (?='' OR product_id LIKE ? OR product_name LIKE ? OR product_desc LIKE ?)
		 AND (?='' OR product_category=?)
		 AND (?='' OR product_status=?)
		 AND (?='' OR (?='in_stock' AND product_stock>0) OR (?='out_of_stock' AND product_stock<=0))
		 ORDER BY product_name");
	if(!$stmt)
	{
		admin_export_fail(500,"The product export could not be prepared.");
	}
	mysqli_stmt_bind_param($stmt,"sssssssssss",$search,$like_search,$like_search,$like_search,$category_filter,$category_filter,$status_filter,$status_filter,$stock_filter,$stock_filter,$stock_filter);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		admin_export_fail(500,"The product export could not be loaded.");
	}
	$result = mysqli_stmt_get_result($stmt);
	while($row = mysqli_fetch_assoc($result))
	{
		$rows[] = array($row["product_id"],$row["product_name"],$row["product_desc"],$row["product_category"],number_format((float)$row["product_price"],2,".",""),$row["product_stock"],$row["product_status"],$row["product_image"]);
	}
	mysqli_stmt_close($stmt);
	$headers = array("Product ID","Name","Description","Category","Price (RM)","Stock","Status","Image Path");
	$filename = "easyorder-products-".date("Ymd-His").".csv";
}
else if($dataset==="orders")
{
	$payment_options = array("Paid","Pending","Unpaid","Failed");
	$status_options = array("Preparing","Ready for Pickup","Picked Up","Out for Delivery","Delivered","Completed","Cancelled");
	$delivery_options = array("Yes","No");
	$search = trim((string)($_GET["search"] ?? ""));
	$payment_filter = (string)($_GET["payment_status"] ?? "");
	$status_filter = (string)($_GET["order_status"] ?? "");
	$delivery_filter = (string)($_GET["delivery"] ?? "");
	$payment_filter = in_array($payment_filter,$payment_options,true) ? $payment_filter : "";
	$status_filter = in_array($status_filter,$status_options,true) ? $status_filter : "";
	$delivery_filter = in_array($delivery_filter,$delivery_options,true) ? $delivery_filter : "";
	$search_like = "%".$search."%";

	// Export the same active order rows and effective payment status as the list page.
	$stmt = mysqli_prepare($connect,
		"SELECT o.order_id,m.member_name,m.member_email,o.order_date,o.order_delivery,o.order_payment,o.order_total,
		        COALESCE(p.payment_status,o.order_payment_status) AS display_payment_status,o.order_status
		 FROM orders o
		 INNER JOIN member m ON m.member_id=o.order_member
		 LEFT JOIN payments p ON p.payment_order=o.order_id
		 WHERE o.order_isDelete=0
		 AND (?='' OR CAST(o.order_id AS CHAR) LIKE ? OR m.member_name LIKE ? OR m.member_email LIKE ?)
		 AND (?='' OR LOWER(COALESCE(p.payment_status,o.order_payment_status))=LOWER(?))
		 AND (?='' OR LOWER(o.order_status)=LOWER(?))
		 AND (?='' OR o.order_delivery=?)
		 ORDER BY o.order_date DESC,o.order_id DESC");
	if(!$stmt)
	{
		admin_export_fail(500,"The order export could not be prepared.");
	}
	mysqli_stmt_bind_param($stmt,"ssssssssss",$search,$search_like,$search_like,$search_like,$payment_filter,$payment_filter,$status_filter,$status_filter,$delivery_filter,$delivery_filter);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		admin_export_fail(500,"The order export could not be loaded.");
	}
	$result = mysqli_stmt_get_result($stmt);
	while($row = mysqli_fetch_assoc($result))
	{
		$rows[] = array($row["order_id"],$row["member_name"],$row["member_email"],$row["order_date"],$row["order_delivery"]==="Yes" ? "Delivery" : "Pickup",$row["order_payment"],number_format((float)$row["order_total"],2,".",""),$row["display_payment_status"],$row["order_status"]);
	}
	mysqli_stmt_close($stmt);
	$headers = array("Order ID","Customer","Customer Email","Order Date & Time","Fulfilment","Payment Method","Total (RM)","Payment Status","Order Status");
	$filename = "easyorder-orders-".date("Ymd-His").".csv";
}
else
{
	$staff_roles = array("Manager","Cashier","Chef","Delivery");
	$current_staff_id = (string)$_SESSION["admin_id"];
	$role_stmt = mysqli_prepare($connect,"SELECT staff_role FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
	if(!$role_stmt)
	{
		admin_export_fail(500,"The administrator permission could not be checked.");
	}
	mysqli_stmt_bind_param($role_stmt,"s",$current_staff_id);
	mysqli_stmt_execute($role_stmt);
	$role_result = mysqli_stmt_get_result($role_stmt);
	$role_row = mysqli_fetch_assoc($role_result) ?: null;
	mysqli_stmt_close($role_stmt);
	if(($role_row["staff_role"] ?? "")!=="Manager")
	{
		admin_export_fail(403,"Only a Manager can export staff accounts.");
	}

	$search = trim((string)($_GET["search"] ?? ""));
	$role_filter = trim((string)($_GET["role"] ?? ""));
	if($role_filter!=="" && !in_array($role_filter,$staff_roles,true))
	{
		$role_filter = "";
	}
	$like_search = "%".$search."%";

	// Password fields are intentionally excluded from the administrator export.
	$stmt = mysqli_prepare($connect,
		"SELECT staff_id,staff_name,staff_role,staff_email,staff_phone
		 FROM staff
		 WHERE staff_isDelete=0
		 AND (?='' OR staff_id LIKE ? OR staff_name LIKE ? OR staff_role LIKE ? OR staff_email LIKE ? OR staff_phone LIKE ?)
		 AND (?='' OR staff_role=?)
		 ORDER BY staff_name");
	if(!$stmt)
	{
		admin_export_fail(500,"The staff export could not be prepared.");
	}
	mysqli_stmt_bind_param($stmt,"ssssssss",$search,$like_search,$like_search,$like_search,$like_search,$like_search,$role_filter,$role_filter);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		admin_export_fail(500,"The staff export could not be loaded.");
	}
	$result = mysqli_stmt_get_result($stmt);
	while($row = mysqli_fetch_assoc($result))
	{
		$rows[] = array($row["staff_id"],$row["staff_name"],$row["staff_role"],$row["staff_email"],$row["staff_phone"]);
	}
	mysqli_stmt_close($stmt);
	$headers = array("Staff ID","Name","Role","Email","Phone");
	$filename = "easyorder-staff-".date("Ymd-His").".csv";
}

admin_export_download($filename,$headers,$rows);
