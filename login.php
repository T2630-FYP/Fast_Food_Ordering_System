<?php session_start(); include("dataconnection.php"); ?>

<!DOCTYPE html>
<html>

<head><!--Customer login page-->
<title>Login</title>
<link rel="stylesheet" href="style.css">

<style>
#login-box
{width:420px;
margin:auto;}

#login-box fieldset
{background-color:#FFFFFF;
border:2px solid #C8102E;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
padding:20px 25px 25px 25px;}

#login-box legend
{color:#C8102E;
font-family:"Arial Narrow";
font-weight:bold;
font-size:16pt;
padding:0px 10px 0px 10px;}

#login-box label
{display:block;
color:#9E0B22;
font-weight:bold;
font-size:0.9em;
margin-top:12px;
margin-bottom:5px;}

#login-box input[type="email"],
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
function login_check()//Validate customer login form
{
	let email,password;
	let email_status=false,password_status=false;

	email=document.loginfrm.user_email.value;
	password=document.loginfrm.user_password.value;

	if(email=="")
	{
		document.getElementById("err_email").innerHTML="Please enter your email";
	}
	else
	{
		document.getElementById("err_email").innerHTML="";
		email_status=true;
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

	if(email_status==true&&password_status==true)
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
<a href="register.php">Sign Up</a>
<a href="index.html">Home</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Member Login</h2>
<p class="intro">Login with your registered email and password to place orders and view your dashboard.</p>

<div id="login-box"><!--Form section for user input-->
<form name="loginfrm" method="post" action="" onsubmit="return login_check()">
<fieldset>
<legend>Login</legend>

<label>Email</label>
<input type="email" name="user_email" placeholder="e.g. ali@email.com">
<span class="error" id="err_email"></span>

<label>Password</label>
<input type="password" name="user_password" placeholder="Your password">
<span class="error" id="err_password"></span>

<p style="text-align:center; margin-top:18px;">
<input type="submit" name="loginbtn" value="Login">
</p>

</fieldset>
</form>
</div>

<p class="login-note">Don't have an account? <a href="register.php">Sign up here</a>.</p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>

<?php

//check the login details against the member table
if(isset($_POST["loginbtn"]))
{
	$email = mysqli_real_escape_string($connect,$_POST["user_email"]);
	$password = mysqli_real_escape_string($connect,$_POST["user_password"]);

	//a failed query must not be passed to mysqli_num_rows()
	try
	{
		$result = mysqli_query($connect,"SELECT * FROM member WHERE member_email='$email' AND member_password='$password' AND member_isDelete=0");
	}
	catch(Throwable $error)
	{
		$result = false;
	}
	$count = $result ? mysqli_num_rows($result) : 0;

	if($count==1)
	{
		//login success - remember the member in the session and go to the dashboard
		$row = mysqli_fetch_assoc($result);
		$_SESSION["member_id"] = $row["member_id"];
		$_SESSION["member_name"] = $row["member_name"];
		?>
		<script>
		window.location="dashboard.php";
		</script>
		<?php
	}
	else
	{
		//login failed
		?>
		<script>
		alert("Invalid email or password. Please try again.");
		</script>
		<?php
	}
}

?>
