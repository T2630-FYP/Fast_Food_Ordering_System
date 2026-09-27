<?php

// Send a plain-text EasyOrder email without allowing header injection.
if(!function_exists("easyorder_send_plain_email"))
{
	function easyorder_send_plain_email($recipient_email,$subject,$message)
	{
		$clean_email = str_replace(array("\r","\n"),"",trim((string)$recipient_email));
		$clean_subject = trim(str_replace(array("\r","\n")," ",(string)$subject));
		if(!filter_var($clean_email,FILTER_VALIDATE_EMAIL) || $clean_subject==="")
		{
			return false;
		}

		$headers = array(
			"MIME-Version: 1.0",
			"Content-Type: text/plain; charset=UTF-8",
			"From: EasyOrder <easyorder.noreply@gmail.com>",
			"Reply-To: easyorder.noreply@gmail.com",
			"X-Mailer: PHP/".phpversion()
		);

		// Email delivery is optional in local XAMPP; failure must never undo a saved account.
		return @mail($clean_email,$clean_subject,(string)$message,implode("\r\n",$headers));
	}
}

if(!function_exists("easyorder_send_welcome_email"))
{
	function easyorder_send_welcome_email($recipient_email,$account_name,$account_type="Customer")
	{
		$clean_name = trim(str_replace(array("\r","\n")," ",(string)$account_name));
		$clean_type = strtolower(trim((string)$account_type))==="administrator" ? "administrator" : "customer";
		$subject = "Welcome to EasyOrder";
		$message = "Hello ".$clean_name.",\r\n\r\n";
		$message .= "Your EasyOrder ".$clean_type." account has been created successfully.\r\n";
		$message .= $clean_type==="administrator"
			? "You can now sign in through the EasyOrder administrator login page.\r\n"
			: "You can now sign in, browse the menu and place your first order.\r\n";
		$message .= "\r\nFor your security, this email does not include your password.\r\n\r\n";
		$message .= "Regards,\r\nEasyOrder Support Team\r\n\r\n";
		$message .= "This is an automated email. Please do not reply.";

		return easyorder_send_plain_email($recipient_email,$subject,$message);
	}
}

?>
