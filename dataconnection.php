<?php

//start the session here as every database page includes this file before any html output
if(session_status() == PHP_SESSION_NONE)
{
	session_start();
}

$connect = mysqli_connect("localhost","root","","easyorder");

// Keep PHP and MariaDB order timestamps in Malaysian local time.
date_default_timezone_set("Asia/Kuala_Lumpur");
if($connect)
{
	mysqli_query($connect,"SET time_zone = '+08:00'");
}

// Compare the submitted password with the plain-text value stored in the
// demonstration database for both customer and administrator accounts.
if(!function_exists("easyorder_password_verify"))
{
	function easyorder_password_verify($plain_password,$stored_password)
	{
		return hash_equals((string)$stored_password,(string)$plain_password);
	}
}

//check that a logged in customer or staff account still exists and has not been deleted
//only log the account out after a successful database check, so a connection problem is not mistaken for a deleted account
if($connect)
{
	$invalid_customer = false;
	$invalid_admin = false;

	if(isset($_SESSION["customer_id"]))
	{
		$customer_check = mysqli_prepare($connect,"SELECT customer_id FROM customer WHERE customer_id=? AND customer_isDelete=0 LIMIT 1");
		if($customer_check)
		{
			mysqli_stmt_bind_param($customer_check,"i",$_SESSION["customer_id"]);
			if(mysqli_stmt_execute($customer_check))
			{
				mysqli_stmt_store_result($customer_check);
				if(mysqli_stmt_num_rows($customer_check) == 0)
				{
					$invalid_customer = true;
				}
			}
			mysqli_stmt_close($customer_check);
		}
	}

	if(isset($_SESSION["admin_id"]))
	{
		$admin_check = mysqli_prepare($connect,"SELECT staff_id FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
		if($admin_check)
		{
			mysqli_stmt_bind_param($admin_check,"s",$_SESSION["admin_id"]);
			if(mysqli_stmt_execute($admin_check))
			{
				mysqli_stmt_store_result($admin_check);
				if(mysqli_stmt_num_rows($admin_check) == 0)
				{
					$invalid_admin = true;
				}
			}
			mysqli_stmt_close($admin_check);
		}
	}

	$current_page = basename($_SERVER["PHP_SELF"] ?? "");
	$admin_page = substr($current_page,0,6) == "admin_";
	$customer_pages = array("profile.php","change_password.php","cart.php","checkout.php","payment.php","order_history.php","order_details.php","review.php","reward.php","view_review.php","wallet.php","wallet_topup.php","wallet_pin_recovery.php");

	if($invalid_customer)
	{
		unset($_SESSION["customer_id"],$_SESSION["customer_name"]);
	}

	if($invalid_admin)
	{
		// Remove every administrator identity value when the account is no longer valid.
		unset($_SESSION["admin_id"],$_SESSION["admin_name"],$_SESSION["admin_role"]);
	}

	//redirect only when the current page needs the account that became invalid
	//this keeps a valid customer session and a valid admin session independent in the same browser
	if($invalid_admin && $admin_page && $current_page != "admin_login.php")
	{
		header("location:admin_login.php");
		exit();
	}
	else if($invalid_customer && in_array($current_page,$customer_pages,true))
	{
		header("location:login.php");
		exit();
	}
}
