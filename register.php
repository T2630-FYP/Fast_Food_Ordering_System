<?php include("dataconnection.php"); ?>

<!DOCTYPE html>
<html>

<head><!--Customer sign up page with registration form-->
<title>Sign Up</title>
<link rel="stylesheet" href="style.css">

<style>
#register-box
{width:640px;
margin:auto;}

#register-box fieldset
{background-color:#FFFFFF;
border:2px solid #C8102E;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
padding:15px 25px 20px 25px;}

#register-box legend
{color:#C8102E;
font-family:"Arial Narrow";
font-weight:bold;
font-size:16pt;
padding:0px 10px 0px 10px;}

#register-box label
{float:left;
width:190px;
text-align:right;
color:#9E0B22;
font-weight:bold;
font-size:0.85em;
margin-right:10px;}

#register-box span.hint
{display:block;
color:#999999;
font-weight:normal;
font-size:0.7em;}

#register-box span.error
{color:#C8102E;
font-weight:bold;
font-size:0.75em;}

#register-box select
{border:1px solid #CCCCCC;
border-radius:4px;
padding:7px 10px 7px 10px;}
</style>

<script>
function register_check()//Validate customer registration form
{
	let name,email,confirm_email,password,confirm_password,phone,gender="",dob,state,city,postcode;
	let i;
	let name_status=false,email_status=false,cemail_status=false,password_status=false,cpassword_status=false;
	let phone_status=false,gender_status=false,dob_status=false,state_status=false,city_status=false,postcode_status=false;

	name=document.registerfrm.cust_name.value;
	email=document.registerfrm.cust_email.value;
	confirm_email=document.registerfrm.cust_confirm_email.value;
	password=document.registerfrm.cust_password.value;
	confirm_password=document.registerfrm.cust_confirm_password.value;
	phone=document.registerfrm.cust_phone.value;
	dob=document.registerfrm.cust_dob.value;
	state=document.registerfrm.state.value;
	city=document.registerfrm.cust_city.value;
	postcode=document.registerfrm.cust_postcode.value;

	for(i=0;i<document.registerfrm.gender.length;i++)
	{
		if(document.registerfrm.gender[i].checked)
		{
			gender=document.registerfrm.gender[i].value;
		}
	}

	if(name=="")
	{
		document.getElementById("err_name").innerHTML="Please enter your full name";
	}
	else
	{
		document.getElementById("err_name").innerHTML="";
		name_status=true;
	}

	if(email=="")
	{
		document.getElementById("err_email").innerHTML="Please enter your email";
	}
	else
	{
		document.getElementById("err_email").innerHTML="";
		email_status=true;
	}

	if(confirm_email=="")
	{
		document.getElementById("err_cemail").innerHTML="Please re-enter your email";
	}
	else if(confirm_email!=email)
	{
		document.getElementById("err_cemail").innerHTML="Email does not match";
	}
	else
	{
		document.getElementById("err_cemail").innerHTML="";
		cemail_status=true;
	}

	if(password=="")
	{
		document.getElementById("err_password").innerHTML="Please enter a password";
	}
	else if(password.length<6)
	{
		document.getElementById("err_password").innerHTML="Password must be at least 6 characters";
	}
	else
	{
		document.getElementById("err_password").innerHTML="";
		password_status=true;
	}

	if(confirm_password=="")
	{
		document.getElementById("err_cpassword").innerHTML="Please re-enter your password";
	}
	else if(confirm_password!=password)
	{
		document.getElementById("err_cpassword").innerHTML="Password does not match";
	}
	else
	{
		document.getElementById("err_cpassword").innerHTML="";
		cpassword_status=true;
	}

	if(phone==""||isNaN(phone))
	{
		document.getElementById("err_phone").innerHTML="Please enter a valid phone number";
	}
	else
	{
		document.getElementById("err_phone").innerHTML="";
		phone_status=true;
	}

	if(gender=="")
	{
		document.getElementById("err_gender").innerHTML="Please select your gender";
	}
	else
	{
		document.getElementById("err_gender").innerHTML="";
		gender_status=true;
	}

	if(dob=="")
	{
		document.getElementById("err_dob").innerHTML="Please select your date of birth";
	}
	else
	{
		document.getElementById("err_dob").innerHTML="";
		dob_status=true;
	}

	if(state=="0")
	{
		document.getElementById("err_state").innerHTML="Please select your state";
	}
	else
	{
		document.getElementById("err_state").innerHTML="";
		state_status=true;
	}

	if(city=="")
	{
		document.getElementById("err_city").innerHTML="Please enter your city";
	}
	else
	{
		document.getElementById("err_city").innerHTML="";
		city_status=true;
	}

	if(postcode==""||isNaN(postcode))
	{
		document.getElementById("err_postcode").innerHTML="Please enter a valid postcode";
	}
	else
	{
		document.getElementById("err_postcode").innerHTML="";
		postcode_status=true;
	}

	if(name_status==true&&email_status==true&&cemail_status==true&&password_status==true&&cpassword_status==true&&phone_status==true&&gender_status==true&&dob_status==true&&state_status==true&&city_status==true&&postcode_status==true)
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
<a href="login.php">Login</a>
<a href="index.html">Home</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Create Your Account</h2>
<p class="intro">Sign up for a free EasyOrder account to start ordering your favourite fast food. Fields marked with * are required.</p>

<div id="register-box"><!--Form section for user input-->
<form name="registerfrm" method="post" action="" onsubmit="return register_check()">
<fieldset>
<legend>Registration Form</legend>

<label>Full Name *<br><span class="hint">Your full name</span></label>
<input type="text" name="cust_name" size="30" maxlength="50" placeholder="e.g. Ali bin Ahmad">
<br><span class="error" id="err_name"></span>

<br><br><label>Email *<br><span class="hint">Used for login</span></label>
<input type="email" name="cust_email" size="30" placeholder="e.g. ali@email.com">
<br><span class="error" id="err_email"></span>

<br><br><label>Confirm Email *<br><span class="hint">Re-enter your email</span></label>
<input type="email" name="cust_confirm_email" size="30">
<br><span class="error" id="err_cemail"></span>

<br><br><label>Password *<br><span class="hint">At least 6 characters</span></label>
<input type="password" name="cust_password">
<br><span class="error" id="err_password"></span>

<br><br><label>Confirm Password *<br><span class="hint">Re-enter your password</span></label>
<input type="password" name="cust_confirm_password">
<br><span class="error" id="err_cpassword"></span>

<br><br><label>Phone Number *<br><span class="hint">Digits only</span></label>
<input type="text" name="cust_phone" size="30" placeholder="e.g. 0123456789">
<br><span class="error" id="err_phone"></span>

<br><br><label>Gender *</label>
<input type="radio" name="gender" value="Male">Male
<input type="radio" name="gender" value="Female">Female
<br><span class="error" id="err_gender"></span>

<br><br><label>Date of Birth *</label>
<input type="date" name="cust_dob" max="2008-12-31">
<br><span class="error" id="err_dob"></span>

<br><br><label>State *</label>
<select name="state">
<option value="0">Select your state</option>
<option value="Johor">Johor</option>
<option value="Kedah">Kedah</option>
<option value="Kelantan">Kelantan</option>
<option value="Melaka">Melaka</option>
<option value="Negeri Sembilan">Negeri Sembilan</option>
<option value="Pahang">Pahang</option>
<option value="Perak">Perak</option>
<option value="Perlis">Perlis</option>
<option value="Pulau Pinang">Pulau Pinang</option>
<option value="Sabah">Sabah</option>
<option value="Sarawak">Sarawak</option>
<option value="Selangor">Selangor</option>
<option value="Terengganu">Terengganu</option>
<option value="Kuala Lumpur">Kuala Lumpur</option>
<option value="Labuan">Labuan</option>
<option value="Putrajaya">Putrajaya</option>
</select>
<br><span class="error" id="err_state"></span>

<br><br><label>City *<br><span class="hint">Your city or town</span></label>
<input type="text" name="cust_city" size="30" placeholder="e.g. Muar">
<br><span class="error" id="err_city"></span>

<br><br><label>Postcode *<br><span class="hint">5-digit postcode</span></label>
<input type="text" name="cust_postcode" size="30" maxlength="5" placeholder="e.g. 84000">
<br><span class="error" id="err_postcode"></span>

<div style="clear:both"></div>

<p style="text-align:center;">
<input type="submit" name="signupbtn" value="Sign Up">
<input type="reset" name="clearbtn" value="Clear">
</p>

</fieldset>
</form>
</div>

<p class="login-note">Already have an account? <a href="login.php">Login here</a>.</p>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>

<?php

//save the new member into the database when the form is submitted
if(isset($_POST["signupbtn"]))
{
	//escape submitted text before it is placed in an SQL string. This keeps
	//ordinary characters such as apostrophes and backslashes as part of the value.
	$name = mysqli_real_escape_string($connect,$_POST["cust_name"]);
	$email = mysqli_real_escape_string($connect,$_POST["cust_email"]);
	$password = mysqli_real_escape_string($connect,$_POST["cust_password"]);
	$phone = mysqli_real_escape_string($connect,$_POST["cust_phone"]);
	$gender = mysqli_real_escape_string($connect,$_POST["gender"]);
	$dob = mysqli_real_escape_string($connect,$_POST["cust_dob"]);
	$state = mysqli_real_escape_string($connect,$_POST["state"]);
	$city = mysqli_real_escape_string($connect,$_POST["cust_city"]);
	$postcode = mysqli_real_escape_string($connect,$_POST["cust_postcode"]);
	$joindate = date("Y-m-d");//join date is set to today automatically

	//check the email is not already registered
	$check = mysqli_query($connect,"SELECT * FROM member WHERE member_email='$email'");
	$count = mysqli_num_rows($check);

	if($count != 0)
	{
		?>
		<script>
		alert("This email is already registered. Please use another email or login.");
		</script>
		<?php
	}
	else
	{
		mysqli_query($connect,"INSERT INTO member(member_name,member_email,member_password,member_phone,member_gender,member_dob,member_state,member_city,member_postcode,member_joindate)VALUES('$name','$email','$password','$phone','$gender','$dob','$state','$city','$postcode','$joindate')");
		?>
		<script>
		alert("Registration successful! Welcome to EasyOrder. You may now login.");
		window.location="login.php";
		</script>
		<?php
	}
}

?>
