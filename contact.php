<?php include("dataconnection.php"); ?>

<!DOCTYPE html>
<html>

<head><!--Contact information, map and message form page-->
<title>Contact Us</title>
<link rel="stylesheet" href="style.css">

<style>
.contact-card
{width:250px;
background-color:#FFFFFF;
border:1px solid #EEEEEE;
border-radius:8px;
box-shadow:2px 2px 5px #CCCCCC;
margin:10px;
padding:15px;
float:left;
text-align:center;}

.contact-card h4
{color:#C8102E;
margin:10px 0px 8px 0px;}

.contact-card p
{color:#666666;
font-size:10pt;
margin:5px 0px 5px 0px;}

.contact-card p.label
{color:#FF7A00;
font-weight:bold;
font-style:italic;
font-size:11pt;
margin:5px 0px 8px 0px;}

.map-box
{text-align:center;
margin:20px 0px 10px 0px;}

.map-box img
{border:2px solid #C8102E;
border-radius:8px;}

#contact-area
{text-align:center;}

#contact-area p.field-label
{color:#9E0B22;
font-weight:bold;
font-size:0.9em;
margin:14px 0px 5px 0px;}

#contact-area input[type="text"],#contact-area input[type="email"]
{width:300px;}

#contact-area select
{border:1px solid #CCCCCC;
border-radius:4px;
padding:8px 10px 8px 10px;}

#contact-area span.error
{color:#C8102E;
font-weight:bold;
font-size:0.8em;}
</style>

<script>
function contact_check()//Validate customer contact form
{
	let name,email,subject,message;
	let name_status=false,email_status=false,subject_status=false,message_status=false;

	name=document.contactfrm.cust_name.value;
	email=document.contactfrm.cust_email.value;
	subject=document.contactfrm.subject.value;
	message=document.contactfrm.cust_message.value;

	if(name=="")
	{
		document.getElementById("err_name").innerHTML="Please enter your name";
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

	if(subject=="0")
	{
		document.getElementById("err_subject").innerHTML="Please select a subject";
	}
	else
	{
		document.getElementById("err_subject").innerHTML="";
		subject_status=true;
	}

	if(message=="")
	{
		document.getElementById("err_message").innerHTML="Please enter your message";
	}
	else
	{
		document.getElementById("err_message").innerHTML="";
		message_status=true;
	}

	if(name_status==true&&email_status==true&&subject_status==true&&message_status==true)
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
<a href="category.php">Menu</a>
<a href="cart.php">Cart</a>
<a href="dashboard.php">My Dashboard</a>
<a href="order_history.php">Order History</a>
<a href="reward.php">Rewards</a>
<a href="view_review.php">View Reviews</a>
<a href="about.html">About Us</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Contact Us</h2>
<p class="intro">Have a question, feedback or just want to say hello? We would love to hear from you.</p>

<div class="contact-card">
<h4>Visit Us</h4>
<p class="label">Our Store</p>
<p>Level 5, EasyOrder Tower,<br>Jalan Tun Abdul Razak,<br>80000 Johor Bahru, Johor, Malaysia</p>
</div>

<div class="contact-card">
<h4>Call Us</h4>
<p class="label">Customer Hotline</p>
<p>Phone: +60 7-1234 5678<br>Fax: +60 7-1234 5679<br>Monday - Sunday: 10am - 10pm</p>
</div>

<div class="contact-card">
<h4>Email Us</h4>
<p class="label">Drop Us A Line</p>
<p>Enquiries: support@easyorder.com<br>Feedback: feedback@easyorder.com<br>Reply within 24 hours, Mon - Sun</p>
</div>

<div style="clear:both"></div>

<hr>

<h2 class="section-title">Find Us Here</h2>
<p class="intro">Drop by our store or use the map below to locate us.</p>
<p class="intro">Open Monday - Sunday, 10am - 10pm.</p>

<div class="map-box"><!--Location map section-->
<img src="image/map.jpg" width="700px" height="525px" alt="EasyOrder Location Map" title="EasyOrder in Johor Bahru">
</div>

<hr>

<h2 class="section-title">Send Us A Message</h2>
<p class="intro">Fill in the form below and our team will get back to you as soon as possible.</p>

<form name="contactfrm" method="post" action="" onsubmit="return contact_check()"><!--Form section for user input-->
<div class="form-box" id="contact-area">

<h3>Contact Form</h3>

<p class="field-label">Full Name</p>
<input type="text" name="cust_name" size="40" placeholder="Enter your name">
<br><span class="error" id="err_name"></span>

<p class="field-label">Email Address</p>
<input type="email" name="cust_email" size="40" placeholder="Enter your email">
<br><span class="error" id="err_email"></span>

<p class="field-label">Subject</p>
<select name="subject">
<option value="0">Select a subject</option>
<option value="General Enquiry">General Enquiry</option>
<option value="Feedback">Feedback</option>
<option value="Complaint">Complaint</option>
<option value="Partnership">Partnership</option>
<option value="Others">Others</option>
</select>
<br><span class="error" id="err_subject"></span>

<p class="field-label">Message</p>
<textarea name="cust_message" rows="5" cols="45"></textarea>
<br><span class="error" id="err_message"></span>

<p>
<input type="submit" name="sendbtn" value="Send Message">
<input type="reset" name="clearbtn" value="Clear">
</p>

<p class="msg"><span id="msg"></span></p>

</div>
</form>

</div>

<footer><!--Footer section-->
<p>Copyright &copy; 2026 EasyOrder Website. All Rights Reserved.</p>
<p><a href="admin_login.php">Admin Login</a></p>
</footer>

</body>

</html>

<?php

//save the contact message when the form is submitted
if(isset($_POST["sendbtn"]))
{
	$name = mysqli_real_escape_string($connect,$_POST["cust_name"]);
	$email = mysqli_real_escape_string($connect,$_POST["cust_email"]);
	$subject = mysqli_real_escape_string($connect,$_POST["subject"]);
	$message = mysqli_real_escape_string($connect,$_POST["cust_message"]);
	$date = date("Y-m-d");

	mysqli_query($connect,"INSERT INTO contact_msg(msg_name,msg_email,msg_subject,msg_message,msg_date)VALUES('$name','$email','$subject','$message','$date')");
	?>
	<script>
	alert("Thank you for contacting EasyOrder. We have received your message and will reply shortly.");
	window.location="contact.php";
	</script>
	<?php
}

?>
