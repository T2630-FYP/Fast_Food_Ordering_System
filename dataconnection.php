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

//Compare the submitted customer password with the plain-text value stored in
//the member table. This mirrors the password-storage format requested for the
//FYP demonstration database.
if(!function_exists("easyorder_password_verify"))
{
	function easyorder_password_verify($plain_password,$stored_password)
	{
		return hash_equals((string)$stored_password,(string)$plain_password);
	}
}

//check that a logged in member or staff account still exists and has not been deleted
//only log the account out after a successful database check, so a connection problem is not mistaken for a deleted account
if($connect)
{
	$invalid_member = false;
	$invalid_admin = false;

	if(isset($_SESSION["member_id"]))
	{
		$member_check = mysqli_prepare($connect,"SELECT member_id FROM member WHERE member_id=? AND member_isDelete=0 LIMIT 1");
		if($member_check)
		{
			mysqli_stmt_bind_param($member_check,"i",$_SESSION["member_id"]);
			if(mysqli_stmt_execute($member_check))
			{
				mysqli_stmt_store_result($member_check);
				if(mysqli_stmt_num_rows($member_check) == 0)
				{
					$invalid_member = true;
				}
			}
			mysqli_stmt_close($member_check);
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
	$member_pages = array("dashboard.php","change_password.php","cart.php","checkout.php","payment.php","order_history.php","order_details.php","review.php","reward.php","view_review.php");

	if($invalid_member)
	{
		unset($_SESSION["member_id"],$_SESSION["member_name"]);
	}

	if($invalid_admin)
	{
		unset($_SESSION["admin_id"],$_SESSION["admin_name"]);
	}

	//redirect only when the current page needs the account that became invalid
	//this keeps a valid member session and a valid admin session independent in the same browser
	if($invalid_admin && $admin_page && $current_page != "admin_login.php")
	{
		header("location:admin_login.php");
		exit();
	}
	else if($invalid_member && in_array($current_page,$member_pages,true))
	{
		header("location:login.php");
		exit();
	}
}
