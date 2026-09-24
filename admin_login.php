<?php

// Start the administrator login flow before any HTML is sent to the browser.
session_start();
include("dataconnection.php");

$login_id = trim((string)($_POST["admin_id"] ?? ""));
$login_error = "";

// Look up one active account by Staff ID, then compare its stored password safely.
if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["adminloginbtn"]))
{
	$submitted_password = (string)($_POST["admin_password"] ?? "");
	if($login_id==="" || $submitted_password==="")
	{
		$login_error = "Enter your Staff ID and password.";
	}
	else
	{
		$login_stmt = mysqli_prepare($connect,"SELECT staff_id,staff_name,staff_role,staff_password FROM staff WHERE staff_id=? AND staff_isDelete=0 LIMIT 1");
		if($login_stmt)
		{
			mysqli_stmt_bind_param($login_stmt,"s",$login_id);
			mysqli_stmt_execute($login_stmt);
			$login_result = mysqli_stmt_get_result($login_stmt);
			$staff_account = mysqli_fetch_assoc($login_result) ?: null;
			mysqli_stmt_close($login_stmt);
		}
		else
		{
			$staff_account = null;
		}

		if($staff_account && easyorder_password_verify($submitted_password,$staff_account["staff_password"]))
		{
			// Rotate the session identifier after authentication and store only display identity data.
			session_regenerate_id(true);
			$_SESSION["admin_id"] = $staff_account["staff_id"];
			$_SESSION["admin_name"] = $staff_account["staff_name"];
			$_SESSION["admin_role"] = $staff_account["staff_role"];
			header("Location: admin_dashboard.php");
			exit();
		}
		$login_error = "Invalid Staff ID or password. Please try again.";
	}
}
?>

<!DOCTYPE html>
<html>

<head><!--Administrator login page-->
<title>Admin Login</title>
<link rel="stylesheet" href="style.css">

<style>
#login-box
{width:420px;
margin:auto;}

#login-box fieldset
{background-color:#FFFFFF;
border:2px solid #2B2B2B;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
padding:20px 25px 25px 25px;}

#login-box legend
{color:#2B2B2B;
font-family:"Arial Narrow";
font-weight:bold;
font-size:16pt;
padding:0px 10px 0px 10px;}

#login-box label
{display:block;
color:#2B2B2B;
font-weight:bold;
font-size:0.9em;
margin-top:12px;
margin-bottom:5px;}

#login-box input[type="text"],
#login-box input[type="password"]
{width:95%;
border:1px solid #CCCCCC;
border-radius:4px;
padding:8px 10px 8px 10px;}

#login-box span.error
{color:#C8102E;
font-weight:bold;
font-size:0.75em;}
</style>

<script>
function admin_login_check()//Validate admin login form
{
	let id,password;
	let id_status=false,password_status=false;

	id=document.adminloginfrm.admin_id.value;
	password=document.adminloginfrm.admin_password.value;

	if(id=="")
	{
		document.getElementById("err_id").innerHTML="Please enter your admin ID";
	}
	else
	{
		document.getElementById("err_id").innerHTML="";
		id_status=true;
	}

	if(password=="")
	{
		document.getElementById("err_password").innerHTML="Please enter your password";
	}
	else
	{
		document.getElementById("err_password").innerHTML="";
		password_status=true;
	}

	if(id_status==true&&password_status==true)
	{
		return true;
	}
	else
	{
		return false;
	}
}
</script>

</head>

<body>

<div id="header"><!--Header section for logo, website name and slogan-->
<img src="image/logo.png" width="80px" height="80px" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="navbar"><!--Customer navigation bar-->
<a href="./">Home</a>
<a href="login.php">Member Login</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Administrator Login</h2>
<p class="intro">This page is for EasyOrder staff only. Please login with your staff ID and password to manage the system.</p>

<div id="login-box"><!--Form section for user input-->
<form name="adminloginfrm" method="post" action="" onsubmit="return admin_login_check()">
<fieldset>
<legend>Admin Login</legend>

<label>Admin ID</label>
<input type="text" name="admin_id" value="<?php echo htmlspecialchars($login_id,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. S001">
<span class="error" id="err_id"></span>

<label>Password</label>
<input type="password" name="admin_password" placeholder="Your password">
<span class="error" id="err_password"></span>

<?php if($login_error!==""): ?>
<p class="msg" role="alert"><?php echo htmlspecialchars($login_error,ENT_QUOTES,'UTF-8'); ?></p>
<?php endif; ?>

<p style="text-align:center; margin-top:18px;">
<input type="submit" name="adminloginbtn" value="Login">
</p>

</fieldset>
</form>
</div>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="login.php">User Login</a></p>
</footer>

</body>

</html>
