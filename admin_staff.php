<?php
//block this page if the admin is not logged in
session_start();
if(!isset($_SESSION["admin_id"]))
{
	header("location:admin_login.php");
	exit();
}
include("dataconnection.php");
?>

<!DOCTYPE html>
<html>

<head><!--Manage staff page-->
<title>Manage Staff</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">

<script type="text/javascript">
function confirmation()//JavaScript confirm box shown before a staff record is deleted
{
	let option;
	option=confirm("Are you sure you want to delete this staff member?");
	return option;
}

function save_staff()//Validate the staff form before it is submitted to the server
{
	let id,name,role,email,phone,password;
	let id_status=false,name_status=false,role_status=false,email_status=false,phone_status=false,password_status=false;

	id=document.stafffrm.staff_id.value;
	name=document.stafffrm.staff_name.value;
	role=document.stafffrm.staff_role.value;
	email=document.stafffrm.staff_email.value;
	phone=document.stafffrm.staff_phone.value;
	password=document.stafffrm.staff_password.value;

	if(id=="")
	{
		document.getElementById("err_id").innerHTML="Please enter the staff ID";
	}
	else
	{
		document.getElementById("err_id").innerHTML="";
		id_status=true;
	}

	if(name=="")
	{
		document.getElementById("err_name").innerHTML="Please enter the staff name";
	}
	else
	{
		document.getElementById("err_name").innerHTML="";
		name_status=true;
	}

	if(role=="0")
	{
		document.getElementById("err_role").innerHTML="Please select a role";
	}
	else
	{
		document.getElementById("err_role").innerHTML="";
		role_status=true;
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

	if(password=="")
	{
		document.getElementById("err_password").innerHTML="Please enter a password";
	}
	else
	{
		document.getElementById("err_password").innerHTML="";
		password_status=true;
	}

	if(id_status==true&&name_status==true&&role_status==true&&email_status==true&&phone_status==true&&password_status==true)
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

<body>

<div id="header"><!--Header section for logo, website name and slogan-->
<img src="image/logo.png" width="80px" height="80px" alt="EasyOrder Logo" title="EasyOrder">
<h1>EasyOrder</h1>
<p>Your Favourite Fast Food, Just A Few Clicks Away</p>
</div>

<div id="admin-navbar"><!--Administrator navigation bar-->
<a href="admin_dashboard.php">Dashboard</a>
<a href="admin_member.php">Manage Members</a>
<a href="admin_category.php">Manage Categories</a>
<a href="admin_product.php">Manage Products</a>
<a href="admin_order.php">Manage Orders</a>
<a href="admin_reward.php">Manage Rewards</a>
<a href="admin_report.php">Sales Report</a>
<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
</div>

<div id="main"><!--Main content section-->

<h2 class="section-title">Manage Staff</h2>
<p class="intro">View, add, update and delete EasyOrder staff accounts from this page. Click <b>Update</b> on any row to edit a staff record, or <b>Delete</b> to remove it.</p>

<h2 class="section-title">Existing Staff</h2>
<table class="manage-table" border="1"><!--Table section for displaying staff records from the database-->
<tr>
<th>Staff ID</th>
<th>Name</th>
<th>Role</th>
<th>Email</th>
<th>Phone</th>
<th>Actions</th>
</tr>

<?php
$result = mysqli_query($connect,"SELECT * FROM staff WHERE staff_isDelete=0");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td><?php echo $row['staff_id']; ?></td>
<td><?php echo $row['staff_name']; ?></td>
<td><?php echo $row['staff_role']; ?></td>
<td><?php echo $row['staff_email']; ?></td>
<td><?php echo $row['staff_phone']; ?></td>
<td>
<input type="button" class="update-btn" value="Update" onclick="location='admin_staff.php?edit&id=<?php echo $row['staff_id']; ?>'">
<input type="button" class="delete-btn" value="Delete" onclick="if(confirmation()==true){location='admin_staff.php?del=1&id=<?php echo $row['staff_id']; ?>'}">
</td>
</tr>

<?php
}
?>

</table>

<hr>

<?php
//if an Update button was clicked, get the chosen staff and fill in the form below
$sid="";
$sname="";
$srole="";
$semail="";
$sphone="";
$spassword="";
$form_title="Staff Details";
$btn_label="Save Staff";

if(isset($_GET["edit"]))
{
	$sid = mysqli_real_escape_string($connect,$_GET["id"]);
	$result = mysqli_query($connect,"SELECT * FROM staff WHERE staff_id='$sid'");
	$row = mysqli_fetch_assoc($result);
	$sname = $row["staff_name"];
	$srole = $row["staff_role"];
	$semail = $row["staff_email"];
	$sphone = $row["staff_phone"];
	$spassword = $row["staff_password"];
	$form_title="Update Staff (".$sid.")";
	$btn_label="Update Staff";
}
?>

<h2 class="section-title">Add / Update Staff</h2>
<p class="intro">Fill in the details below to add a new staff member. To edit an existing staff, click <b>Update</b> on the table above and the form will be filled in for you.</p>

<div class="manage-form-box"><!--Form section for user input-->
<form name="stafffrm" method="post" action="" onsubmit="return save_staff()">
<fieldset>
<legend id="form_title"><?php echo htmlspecialchars($form_title,ENT_QUOTES,'UTF-8'); ?></legend>

<label>Staff ID</label>
<input type="text" name="staff_id" value="<?php echo htmlspecialchars($sid,ENT_QUOTES,'UTF-8'); ?>" <?php if(isset($_GET["edit"])) echo "disabled"; ?> placeholder="e.g. S005">
<br><span class="error" id="err_id"></span>

<br><br><label>Full Name</label>
<input type="text" name="staff_name" value="<?php echo htmlspecialchars($sname,ENT_QUOTES,'UTF-8'); ?>" placeholder="Staff full name">
<br><span class="error" id="err_name"></span>

<br><br><label>Role</label>
<select name="staff_role">
<option value="0">Select a role</option>
<option value="Manager" <?php if($srole=="Manager") echo "selected"; ?>>Manager</option>
<option value="Cashier" <?php if($srole=="Cashier") echo "selected"; ?>>Cashier</option>
<option value="Chef" <?php if($srole=="Chef") echo "selected"; ?>>Chef</option>
<option value="Delivery" <?php if($srole=="Delivery") echo "selected"; ?>>Delivery</option>
</select>
<br><span class="error" id="err_role"></span>

<br><br><label>Email</label>
<input type="email" name="staff_email" value="<?php echo htmlspecialchars($semail,ENT_QUOTES,'UTF-8'); ?>" placeholder="staff@easyorder.com">
<br><span class="error" id="err_email"></span>

<br><br><label>Phone</label>
<input type="text" name="staff_phone" value="<?php echo htmlspecialchars($sphone,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. 0123456789">
<br><span class="error" id="err_phone"></span>

<br><br><label>Password</label>
<input type="password" name="staff_password" value="<?php echo htmlspecialchars($spassword,ENT_QUOTES,'UTF-8'); ?>" placeholder="Set a password">
<br><span class="error" id="err_password"></span>

<div style="clear:both"></div>

<p style="text-align:center;">
<input type="submit" class="save-btn" id="savebtn" name="savebtn" value="<?php echo htmlspecialchars($btn_label,ENT_QUOTES,'UTF-8'); ?>">
<input type="button" class="save-btn" name="clearbtn" value="Clear" onclick="clear_form(this.form)">
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

<?php

//save the staff - decide whether to INSERT a new record or UPDATE an existing one
if(isset($_POST["savebtn"]))
{
	$sname = mysqli_real_escape_string($connect,$_POST["staff_name"]);
	$srole = mysqli_real_escape_string($connect,$_POST["staff_role"]);
	$semail = mysqli_real_escape_string($connect,$_POST["staff_email"]);
	$sphone = mysqli_real_escape_string($connect,$_POST["staff_phone"]);
	$spassword = mysqli_real_escape_string($connect,$_POST["staff_password"]);

	if(isset($_GET["edit"]))
	{
		//UPDATE mode - the staff id comes from the url
		$sid = mysqli_real_escape_string($connect,$_GET["id"]);

		//staff_email is unique in the whole table, including soft-deleted records
		$emailcheck = mysqli_query($connect,"SELECT * FROM staff WHERE staff_email='$semail' AND staff_id!='$sid'");

		if(mysqli_num_rows($emailcheck) != 0)
		{
			//another staff already uses this email - do not update
			?>
			<script>
			alert("Staff email already exists! Please use a different email.");
			</script>
			<?php
		}
		else
		{
			//email is free - run the update and only report success if it actually worked
			$ok = mysqli_query($connect,"UPDATE staff SET staff_name='$sname',
												  staff_role='$srole',
												  staff_email='$semail',
												  staff_phone='$sphone',
												  staff_password='$spassword'
												  WHERE staff_id='$sid'");

			if($ok)
			{
				?>
				<script>
				alert("Staff updated!");
				window.location="admin_staff.php";
				</script>
				<?php
			}
			else
			{
				//the update failed (e.g. blocked by the database) - do NOT show success
				$err = addslashes(str_replace(array("\r","\n")," ",mysqli_error($connect)));
				?>
				<script>
				alert("Save failed! The staff could not be updated. Database error: <?php echo $err; ?>");
				</script>
				<?php
			}
		}
	}
	else
	{
		//ADD mode - the staff id comes from the form
		$sid = mysqli_real_escape_string($connect,$_POST["staff_id"]);

		//check the staff ID is not already used (staff_id is the primary key)
		$idcheck = mysqli_query($connect,"SELECT * FROM staff WHERE staff_id='$sid'");

		//staff_email is unique in the whole table, including soft-deleted records
		$emailcheck = mysqli_query($connect,"SELECT * FROM staff WHERE staff_email='$semail'");

		if(mysqli_num_rows($idcheck) != 0)
		{
			//the staff ID is taken
			?>
			<script>
			alert("The staff ID is already in use. Please change.");
			</script>
			<?php
		}
		else if(mysqli_num_rows($emailcheck) != 0)
		{
			//the email is taken
			?>
			<script>
			alert("Staff email already exists! Please use a different email.");
			</script>
			<?php
		}
		else
		{
			//both ID and email are free - run the insert and only report success if it actually worked
			$ok = mysqli_query($connect,"INSERT INTO staff(staff_id,staff_name,staff_role,staff_email,staff_phone,staff_password)VALUES('$sid','$sname','$srole','$semail','$sphone','$spassword')");

			if($ok)
			{
				?>
				<script>
				alert("Staff saved!");
				window.location="admin_staff.php";
				</script>
				<?php
			}
			else
			{
				//the insert failed (e.g. blocked by the database) - do NOT show success
				$err = addslashes(str_replace(array("\r","\n")," ",mysqli_error($connect)));
				?>
				<script>
				alert("Save failed! The staff could not be saved. Database error: <?php echo $err; ?>");
				</script>
				<?php
			}
		}
	}
}

//remove a staff member from the list (soft delete - set staff_isDelete to 1)
if(isset($_GET["del"]))
{
	$sid = mysqli_real_escape_string($connect,(string)$_GET["id"]);

	mysqli_query($connect,"UPDATE staff SET staff_isDelete=1 WHERE staff_id='$sid'");
	?>
	<script>
	alert("Staff removed!");
	window.location="admin_staff.php";
	</script>
	<?php
}

?>
