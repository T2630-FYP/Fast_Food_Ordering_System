<?php

if(!function_exists("easyorder_reset_h"))
{
	function easyorder_reset_h($value)
	{
		return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
	}
}

if(!function_exists("easyorder_reset_csrf"))
{
	function easyorder_reset_csrf($key)
	{
		if(!isset($_SESSION[$key]))
		{
			$_SESSION[$key] = bin2hex(random_bytes(32));
		}

		return $_SESSION[$key];
	}
}

if(!function_exists("easyorder_reset_csrf_valid"))
{
	function easyorder_reset_csrf_valid($key,$submitted_token)
	{
		return isset($_SESSION[$key]) && $submitted_token!=="" && hash_equals($_SESSION[$key],$submitted_token);
	}
}

if(!function_exists("easyorder_reset_code_hash"))
{
	function easyorder_reset_code_hash($code)
	{
		//A deployment can provide its own secret through the Apache environment.
		//The fallback keeps the verification code out of the database in this local FYP build.
		$key = getenv("EASYORDER_RESET_HMAC_KEY");
		if($key===false || strlen($key)<32)
		{
			$key = "EasyOrder-FYP-Reset-Code-Key-2026-Local-XAMPP";
		}

		return hash_hmac("sha256",(string)$code,$key);
	}
}

if(!function_exists("easyorder_clear_verified_reset"))
{
	function easyorder_clear_verified_reset()
	{
		unset($_SESSION["password_reset_id"],$_SESSION["password_reset_member_id"],$_SESSION["password_reset_verified_at"]);
	}
}

if(!function_exists("easyorder_send_reset_email"))
{
	function easyorder_send_reset_email($recipient_email,$member_name,$code)
	{
		$clean_name = trim(str_replace(array("\r","\n")," ",(string)$member_name));
		$clean_email = str_replace(array("\r","\n"),"",(string)$recipient_email);
		$subject = "EasyOrder Password Reset Verification Code";
		$message = "Hello ".$clean_name.",\r\n\r\n";
		$message .= "We received a request to reset the password for your EasyOrder account.\r\n\r\n";
		$message .= "Your verification code is:\r\n\r\n".$code."\r\n\r\n";
		$message .= "This code will expire in 10 minutes and can only be used once.\r\n";
		$message .= "Please do not share this code with anyone.\r\n\r\n";
		$message .= "If you did not request a password reset, you may safely ignore this email. Your account password will remain unchanged.\r\n\r\n";
		$message .= "Regards,\r\nEasyOrder Support Team\r\n\r\n";
		$message .= "This is an automated email. Please do not reply.";

		$headers = array(
			"MIME-Version: 1.0",
			"Content-Type: text/plain; charset=UTF-8",
			"From: EasyOrder <easyorder.noreply@gmail.com>",
			"Reply-To: easyorder.noreply@gmail.com",
			"X-Mailer: PHP/".phpversion()
		);

		return mail($clean_email,$subject,$message,implode("\r\n",$headers));
	}
}

if(!function_exists("easyorder_create_password_reset"))
{
	function easyorder_create_password_reset($connect,$submitted_email)
	{
		$member = false;
		$stmt = mysqli_prepare($connect,"SELECT member_id,member_name,member_email FROM member WHERE member_email=? AND member_isDelete=0 LIMIT 1");
		if(!$stmt)
		{
			return false;
		}

		mysqli_stmt_bind_param($stmt,"s",$submitted_email);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		$member = mysqli_fetch_assoc($result);
		mysqli_stmt_close($stmt);

		//Return the same successful result when the account does not exist so the
		//public response does not reveal which email addresses are registered.
		if(!$member)
		{
			return true;
		}

		$code = (string)random_int(100000,999999);
		$code_hash = easyorder_reset_code_hash($code);
		$member_id = (int)$member["member_id"];
		$request_id = 0;
		$database_ok = true;

		mysqli_begin_transaction($connect);

		$stmt = mysqli_prepare($connect,"UPDATE password_reset SET used_at=NOW() WHERE member_id=? AND used_at IS NULL");
		if(!$stmt)
		{
			$database_ok = false;
		}
		else
		{
			mysqli_stmt_bind_param($stmt,"i",$member_id);
			$database_ok = mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}

		if($database_ok)
		{
			$stmt = mysqli_prepare($connect,"INSERT INTO password_reset(member_id,reset_code_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))");
			if(!$stmt)
			{
				$database_ok = false;
			}
			else
			{
				mysqli_stmt_bind_param($stmt,"is",$member_id,$code_hash);
				$database_ok = mysqli_stmt_execute($stmt);
				$request_id = (int)mysqli_insert_id($connect);
				mysqli_stmt_close($stmt);
			}
		}

		if(!$database_ok)
		{
			mysqli_rollback($connect);
			return false;
		}

		mysqli_commit($connect);
		if(!easyorder_send_reset_email($member["member_email"],$member["member_name"],$code))
		{
			$stmt = mysqli_prepare($connect,"UPDATE password_reset SET used_at=NOW() WHERE reset_id=?");
			if($stmt)
			{
				mysqli_stmt_bind_param($stmt,"i",$request_id);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}
			error_log("EasyOrder password reset email could not be sent.");
		}

		return true;
	}
}
