<?php
//block this page if the admin is not logged in
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("location:admin_login.php");
	exit();
}
include("dataconnection.php");
require_once("admin_shell.php");
?>

<!DOCTYPE html>
<html>

<head><!--Manage member page-->
<title>Manage Members</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">

<script type="text/javascript">
function confirmation()//JavaScript confirm box shown before a member record is deleted
{
	let option;
	option=confirm("Are you sure you want to delete this member?");
	return option;
}

function save_member()//Validate the member form before it is submitted to the server
{
	let name,email,phone,state,joindate;
	let name_status=false,email_status=false,phone_status=false,state_status=false,joindate_status=false;

	name=document.memberfrm.member_name.value;
	email=document.memberfrm.member_email.value;
	phone=document.memberfrm.member_phone.value;
	state=document.memberfrm.member_state.value;
	joindate=document.memberfrm.member_joindate.value;

	if(name=="")
	{
		document.getElementById("err_name").innerHTML="Please enter the member name";
	}
	else
	{
		document.getElementById("err_name").innerHTML="";
		name_status=true;
	}

	if(email=="")
	{
		document.getElementById("err_email").innerHTML="Please enter the email";
	}
	else
	{
		document.getElementById("err_email").innerHTML="";
		email_status=true;
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

	if(state=="0")
	{
		document.getElementById("err_state").innerHTML="Please select a state";
	}
	else
	{
		document.getElementById("err_state").innerHTML="";
		state_status=true;
	}

	if(joindate=="")
	{
		document.getElementById("err_joindate").innerHTML="Please select the join date";
	}
	else
	{
		document.getElementById("err_joindate").innerHTML="";
		joindate_status=true;
	}

	if(name_status==true&&email_status==true&&phone_status==true&&state_status==true&&joindate_status==true)
	{
		return true;
	}
	else
	{
		return false;
	}
}

function clear_form(frm)//empty every field in the form (works in both add and update mode)
{
	var i;
	for(i=0;i<frm.elements.length;i++)
	{
		var t=frm.elements[i].type;
		if(t=="text"||t=="email"||t=="password"||t=="number"||t=="date"||t=="textarea")
		{
			frm.elements[i].value="";
		}
		else if(t=="select-one")
		{
			frm.elements[i].selectedIndex=0;
		}
		else if(t=="radio"||t=="checkbox")
		{
			frm.elements[i].checked=false;
		}
	}
}
</script>

</head>

<body class="admin-body">

<?php easyorder_admin_shell_start("admin_member.php"); ?>

<h2 class="section-title">Manage Members</h2>
<p class="intro">View, add, update and delete registered customer members from this page. Click <b>Update</b> on any row to edit a member record, or <b>Delete</b> to remove it.</p>

<h2 class="section-title">Registered Members</h2>
<table class="manage-table" border="1"><!--Table section for displaying member records from the database-->
<tr>
<th>Member ID</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>State</th>
<th>Join Date</th>
<th>Actions</th>
</tr>

<?php
$result = mysqli_query($connect,"SELECT * FROM member WHERE member_isDelete=0");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td><?php echo $row['member_id']; ?></td>
<td><?php echo $row['member_name']; ?></td>
<td><?php echo $row['member_email']; ?></td>
<td><?php echo $row['member_phone']; ?></td>
<td><?php echo $row['member_state']; ?></td>
<td><?php echo $row['member_joindate']; ?></td>
<td>
<input type="button" class="update-btn" value="Update" onclick="location='admin_member.php?edit&id=<?php echo $row['member_id']; ?>'">
<input type="button" class="delete-btn" value="Delete" onclick="if(confirmation()==true){location='admin_member.php?del=1&id=<?php echo $row['member_id']; ?>'}">
</td>
</tr>

<?php
}
?>

</table>

<hr>

<?php
//if an Update button was clicked, get the chosen member and fill in the form below
$mid="";
$mname="";
$memail="";
$mphone="";
$mstate="";
$mjoindate="";
$form_title="Member Details";
$btn_label="Save Member";

if(isset($_GET["edit"]))
{
	$mid = mysqli_real_escape_string($connect,$_GET["id"]);
	$result = mysqli_query($connect,"SELECT * FROM member WHERE member_id='$mid'");
	$row = mysqli_fetch_assoc($result);
	$mname = $row["member_name"];
	$memail = $row["member_email"];
	$mphone = $row["member_phone"];
	$mstate = $row["member_state"];
	$mjoindate = $row["member_joindate"];
	$form_title="Update Member (".$mid.")";
	$btn_label="Update Member";
}
?>

<h2 class="section-title">Add / Update Member</h2>
<p class="intro">Fill in the details below to add a new member. The Member ID is generated automatically. To edit an existing member, click <b>Update</b> on the table above and the form will be filled in for you.</p>

<div class="manage-form-box"><!--Form section for user input-->
<form name="memberfrm" method="post" action="" onsubmit="return save_member()">
<fieldset>
<legend id="form_title"><?php echo htmlspecialchars($form_title,ENT_QUOTES,'UTF-8'); ?></legend>

<label>Member ID</label>
<input type="text" name="member_id" value="<?php echo htmlspecialchars($mid,ENT_QUOTES,'UTF-8'); ?>" disabled placeholder="(auto-generated when saved)">

<br><br><label>Full Name</label>
<input type="text" name="member_name" value="<?php echo htmlspecialchars($mname,ENT_QUOTES,'UTF-8'); ?>" placeholder="Member full name">
<br><span class="error" id="err_name"></span>

<br><br><label>Email</label>
<input type="email" name="member_email" value="<?php echo htmlspecialchars($memail,ENT_QUOTES,'UTF-8'); ?>" placeholder="member@email.com">
<br><span class="error" id="err_email"></span>

<br><br><label>Phone</label>
<input type="text" name="member_phone" value="<?php echo htmlspecialchars($mphone,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. 0123456789">
<br><span class="error" id="err_phone"></span>

<br><br><label>State</label>
<select name="member_state">
<option value="0">Select a state</option>
<option value="Johor" <?php if($mstate=="Johor") echo "selected"; ?>>Johor</option>
<option value="Kedah" <?php if($mstate=="Kedah") echo "selected"; ?>>Kedah</option>
<option value="Kelantan" <?php if($mstate=="Kelantan") echo "selected"; ?>>Kelantan</option>
<option value="Melaka" <?php if($mstate=="Melaka") echo "selected"; ?>>Melaka</option>
<option value="Negeri Sembilan" <?php if($mstate=="Negeri Sembilan") echo "selected"; ?>>Negeri Sembilan</option>
<option value="Pahang" <?php if($mstate=="Pahang") echo "selected"; ?>>Pahang</option>
<option value="Perak" <?php if($mstate=="Perak") echo "selected"; ?>>Perak</option>
<option value="Perlis" <?php if($mstate=="Perlis") echo "selected"; ?>>Perlis</option>
<option value="Pulau Pinang" <?php if($mstate=="Pulau Pinang") echo "selected"; ?>>Pulau Pinang</option>
<option value="Sabah" <?php if($mstate=="Sabah") echo "selected"; ?>>Sabah</option>
<option value="Sarawak" <?php if($mstate=="Sarawak") echo "selected"; ?>>Sarawak</option>
<option value="Selangor" <?php if($mstate=="Selangor") echo "selected"; ?>>Selangor</option>
<option value="Terengganu" <?php if($mstate=="Terengganu") echo "selected"; ?>>Terengganu</option>
<option value="Kuala Lumpur" <?php if($mstate=="Kuala Lumpur") echo "selected"; ?>>Kuala Lumpur</option>
<option value="Labuan" <?php if($mstate=="Labuan") echo "selected"; ?>>Labuan</option>
<option value="Putrajaya" <?php if($mstate=="Putrajaya") echo "selected"; ?>>Putrajaya</option>
</select>
<br><span class="error" id="err_state"></span>

<br><br><label>Join Date</label>
<input type="date" name="member_joindate" value="<?php echo htmlspecialchars($mjoindate,ENT_QUOTES,'UTF-8'); ?>">
<br><span class="error" id="err_joindate"></span>

<div style="clear:both"></div>

<p style="text-align:center;">
<input type="submit" class="save-btn" id="savebtn" name="savebtn" value="<?php echo htmlspecialchars($btn_label,ENT_QUOTES,'UTF-8'); ?>">
<input type="button" class="save-btn" name="clearbtn" value="Clear" onclick="clear_form(this.form)">
</p>

</fieldset>
</form>
</div>

<?php easyorder_admin_shell_end(); ?>

</body>

</html>

<?php

//save the member - decide whether to INSERT a new record or UPDATE an existing one
if(isset($_POST["savebtn"]))
{
	$mname = mysqli_real_escape_string($connect,$_POST["member_name"]);
	$memail = mysqli_real_escape_string($connect,$_POST["member_email"]);
	$mphone = mysqli_real_escape_string($connect,$_POST["member_phone"]);
	$mstate = mysqli_real_escape_string($connect,$_POST["member_state"]);
	$mjoindate = mysqli_real_escape_string($connect,$_POST["member_joindate"]);

	if(isset($_GET["edit"]))
	{
		//UPDATE mode - the member id comes from the url
		$mid = mysqli_real_escape_string($connect,$_GET["id"]);

		//make sure the email is not already used by another member
		$echeck = mysqli_query($connect,"SELECT * FROM member WHERE member_email='$memail' AND member_id!='$mid'");
		if(mysqli_num_rows($echeck) != 0)
		{
			?>
			<script>
			alert("This email is already used by another member. Please use a different email.");
			</script>
			<?php
		}
		else
		{
			mysqli_query($connect,"UPDATE member SET member_name='$mname',
												  member_email='$memail',
												  member_phone='$mphone',
												  member_state='$mstate',
												  member_joindate='$mjoindate'
												  WHERE member_id='$mid'");
			?>
			<script>
			alert("Member updated!");
			window.location="admin_member.php";
			</script>
			<?php
		}
	}
	else
	{
		//ADD mode - member id is auto generated. Fields not asked here are given defaults
		//(default password is member123; the member can update the rest after logging in)

		//make sure the email is not already registered
		$echeck = mysqli_query($connect,"SELECT * FROM member WHERE member_email='$memail'");
		if(mysqli_num_rows($echeck) != 0)
		{
			?>
			<script>
			alert("This email is already registered. Please use a different email.");
			</script>
			<?php
		}
		else
		{
			mysqli_query($connect,"INSERT INTO member(member_name,member_email,member_password,member_phone,member_gender,member_dob,member_state,member_city,member_postcode,member_joindate)VALUES('$mname','$memail','member123','$mphone','','2000-01-01','$mstate','','','$mjoindate')");
			?>
			<script>
			alert("Member saved!");
			window.location="admin_member.php";
			</script>
			<?php
		}
	}
}

//remove a member from the list (soft delete - set member_isDelete to 1)
if(isset($_GET["del"]))
{
	$mid = (int)$_GET["id"];

	mysqli_query($connect,"UPDATE member SET member_isDelete=1 WHERE member_id='$mid'");
	?>
	<script>
	alert("Member removed!");
	window.location="admin_member.php";
	</script>
	<?php
}

?>
