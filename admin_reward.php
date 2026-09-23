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

<head><!--Manage reward page-->
<title>Manage Rewards</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">

<script type="text/javascript">
function confirmation()//JavaScript confirm box shown before a reward record is deleted
{
	let option;
	option=confirm("Are you sure you want to delete this reward?");
	return option;
}

function save_reward()//Validate the reward form before it is submitted to the server
{
	let id,name,description,points,product,status;
	let id_status=false,name_status=false,description_status=false,points_status=false,product_status=false,status_status=false;

	id=document.rewardfrm.reward_id.value;
	name=document.rewardfrm.reward_name.value;
	description=document.rewardfrm.reward_desc.value;
	points=document.rewardfrm.reward_points.value;
	product=document.rewardfrm.reward_product.value;
	status=document.rewardfrm.reward_status.value;

	if(id=="")
	{
		document.getElementById("err_id").innerHTML="Please enter the reward ID";
	}
	else
	{
		document.getElementById("err_id").innerHTML="";
		id_status=true;
	}

	if(name=="")
	{
		document.getElementById("err_name").innerHTML="Please enter the reward name";
	}
	else
	{
		document.getElementById("err_name").innerHTML="";
		name_status=true;
	}

	if(description=="")
	{
		document.getElementById("err_desc").innerHTML="Please enter a description";
	}
	else
	{
		document.getElementById("err_desc").innerHTML="";
		description_status=true;
	}

	if(points==""||isNaN(points)||points<=0)
	{
		document.getElementById("err_points").innerHTML="Please enter a valid points cost";
	}
	else
	{
		document.getElementById("err_points").innerHTML="";
		points_status=true;
	}

	if(product=="")
	{
		document.getElementById("err_product").innerHTML="Please select a linked product";
	}
	else
	{
		document.getElementById("err_product").innerHTML="";
		product_status=true;
	}

	if(status=="0")
	{
		document.getElementById("err_status").innerHTML="Please select a status";
	}
	else
	{
		document.getElementById("err_status").innerHTML="";
		status_status=true;
	}

	if(id_status==true&&name_status==true&&description_status==true&&points_status==true&&product_status==true&&status_status==true)
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

<?php easyorder_admin_shell_start("admin_reward.php"); ?>

<h2 class="section-title">Manage Rewards</h2>
<p class="intro">View, add, update and delete loyalty rewards from this page. Click <b>Update</b> on any row to edit a reward record, or <b>Delete</b> to remove it. The <b>Stock</b> column is taken from the linked product, so it always matches the stock in <b>Manage Products</b>.</p>

<h2 class="section-title">Existing Rewards</h2>
<table class="manage-table" border="1"><!--Table section for displaying reward records from the database-->
<tr>
<th>Reward ID</th>
<th>Reward Name</th>
<th>Points</th>
<th>Linked Product</th>
<th>Stock</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php
$result = mysqli_query($connect,"SELECT * FROM reward WHERE reward_isDelete=0");

while($row = mysqli_fetch_assoc($result))
{
	//the stock and product name come from the linked product (same source as Manage Products)
	$linked_product = "&mdash;";
	$stock_display = "N/A";

	$pid = $row['reward_product'];
	$product_stmt = mysqli_prepare($connect,"SELECT * FROM product WHERE product_id=?");
	mysqli_stmt_bind_param($product_stmt,"s",$pid);
	mysqli_stmt_execute($product_stmt);
	$presult = mysqli_stmt_get_result($product_stmt);
	if(mysqli_num_rows($presult) > 0)
	{
		$prow = mysqli_fetch_assoc($presult);
		$linked_product = $prow['product_name'];
		$stock_display = $prow['product_stock'];
	}
	mysqli_stmt_close($product_stmt);
?>

<tr>
<td><?php echo $row['reward_id']; ?></td>
<td><?php echo $row['reward_name']; ?></td>
<td><?php echo $row['reward_points']; ?></td>
<td><?php echo $linked_product; ?></td>
<td><?php echo $stock_display; ?></td>
<td><?php echo $row['reward_status']; ?></td>
<td>
<input type="button" class="update-btn" value="Update" onclick="location='admin_reward.php?edit&id=<?php echo $row['reward_id']; ?>'">
<input type="button" class="delete-btn" value="Delete" onclick="if(confirmation()==true){location='admin_reward.php?del=1&id=<?php echo $row['reward_id']; ?>'}">
</td>
</tr>

<?php
}
?>

</table>

<hr>

<?php
//if an Update button was clicked, get the chosen reward and fill in the form below
$rid="";
$rname="";
$rdesc="";
$rpoints="";
$rproduct="";
$rimage="";
$rstatus="";
$form_title="Reward Details";
$btn_label="Save Reward";

if(isset($_GET["edit"]))
{
	$rid = mysqli_real_escape_string($connect,$_GET["id"]);
	$result = mysqli_query($connect,"SELECT * FROM reward WHERE reward_id='$rid'");
	$row = mysqli_fetch_assoc($result);
	$rname = $row["reward_name"];
	$rdesc = $row["reward_desc"];
	$rpoints = $row["reward_points"];
	$rproduct = $row["reward_product"];
	$rimage = $row["reward_image"];
	$rstatus = $row["reward_status"];
	$form_title="Update Reward (".$rid.")";
	$btn_label="Update Reward";
}
?>

<h2 class="section-title">Add / Update Reward</h2>
<p class="intro">Fill in the details below to add a new reward. Every reward must be linked to a product so it can show that product's stock. To edit an existing reward, click <b>Update</b> on the table above.</p>

<div class="manage-form-box"><!--Form section for user input-->
<form name="rewardfrm" method="post" action="" onsubmit="return save_reward()">
<fieldset>
<legend id="form_title"><?php echo htmlspecialchars($form_title,ENT_QUOTES,'UTF-8'); ?></legend>

<label>Reward ID</label>
<input type="text" name="reward_id" value="<?php echo htmlspecialchars($rid,ENT_QUOTES,'UTF-8'); ?>" <?php if(isset($_GET["edit"])) echo "disabled"; ?> placeholder="e.g. R010">
<br><span class="error" id="err_id"></span>

<br><br><label>Reward Name</label>
<input type="text" name="reward_name" value="<?php echo htmlspecialchars($rname,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. Free Apple Pie">
<br><span class="error" id="err_name"></span>

<br><br><label>Description</label>
<input type="text" name="reward_desc" value="<?php echo htmlspecialchars($rdesc,ENT_QUOTES,'UTF-8'); ?>" placeholder="Short description of the reward">
<br><span class="error" id="err_desc"></span>

<br><br><label>Points Cost</label>
<input type="number" name="reward_points" min="1" value="<?php echo htmlspecialchars($rpoints,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. 200">
<br><span class="error" id="err_points"></span>

<br><br><label>Linked Product</label>
<select name="reward_product">
<option value="">-- Select a product --</option>
<?php
//load the product list so the reward can be linked to a real product (for its stock)
$prod_result = mysqli_query($connect,"SELECT * FROM product WHERE product_isDelete=0");
while($prod_row = mysqli_fetch_assoc($prod_result))
{
	$this_pid = $prod_row['product_id'];
?>
<option value="<?php echo htmlspecialchars($this_pid,ENT_QUOTES,'UTF-8'); ?>" <?php if($rproduct==$this_pid) echo "selected"; ?>><?php echo htmlspecialchars($prod_row['product_name'],ENT_QUOTES,'UTF-8'); ?> (Stock: <?php echo $prod_row['product_stock']; ?>)</option>
<?php
}
?>
</select>
<br><span class="error" id="err_product"></span>

<br><br><label>Image File</label>
<input type="text" name="reward_image" value="<?php echo htmlspecialchars($rimage,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. dessert-applepie.jpg">

<br><br><label>Status</label>
<select name="reward_status">
<option value="0">Select a status</option>
<option value="Active" <?php if($rstatus=="Active") echo "selected"; ?>>Active</option>
<option value="Inactive" <?php if($rstatus=="Inactive") echo "selected"; ?>>Inactive</option>
</select>
<br><span class="error" id="err_status"></span>

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

//save the reward - decide whether to INSERT a new record or UPDATE an existing one
if(isset($_POST["savebtn"]))
{
	$rname = mysqli_real_escape_string($connect,$_POST["reward_name"]);
	$rdesc = mysqli_real_escape_string($connect,$_POST["reward_desc"]);
	$rpoints = mysqli_real_escape_string($connect,$_POST["reward_points"]);
	$rproduct = mysqli_real_escape_string($connect,$_POST["reward_product"]);
	$rimage = mysqli_real_escape_string($connect,$_POST["reward_image"]);
	$rstatus = mysqli_real_escape_string($connect,$_POST["reward_status"]);

	//if no image was given, fall back to the logo
	if($rimage == "")
	{
		$rimage = "logo.png";
	}

	if(isset($_GET["edit"]))
	{
		//UPDATE mode - the reward id comes from the url
		$rid = mysqli_real_escape_string($connect,$_GET["id"]);

		mysqli_query($connect,"UPDATE reward SET reward_name='$rname',
											  reward_desc='$rdesc',
											  reward_points='$rpoints',
											  reward_product='$rproduct',
											  reward_image='$rimage',
											  reward_status='$rstatus'
											  WHERE reward_id='$rid'");
		?>
		<script>
		alert("Reward updated!");
		window.location="admin_reward.php";
		</script>
		<?php
	}
	else
	{
		//ADD mode - the reward id comes from the form, check it is not already used
		$rid = mysqli_real_escape_string($connect,$_POST["reward_id"]);

		$check = mysqli_query($connect,"SELECT * FROM reward WHERE reward_id='$rid'");
		$count = mysqli_num_rows($check);

		if($count != 0)
		{
		?>
			<script>
			alert("The reward ID is already in use. Please change.");
			</script>
		<?php
		}
		else
		{
			mysqli_query($connect,"INSERT INTO reward(reward_id,reward_name,reward_desc,reward_points,reward_product,reward_image,reward_status)VALUES('$rid','$rname','$rdesc','$rpoints','$rproduct','$rimage','$rstatus')");
			?>
			<script>
			alert("Reward saved!");
			window.location="admin_reward.php";
			</script>
			<?php
		}
	}
}

//remove a reward from the list (soft delete - set reward_isDelete to 1)
if(isset($_GET["del"]))
{
	$rid = mysqli_real_escape_string($connect,(string)$_GET["id"]);

	mysqli_query($connect,"UPDATE reward SET reward_isDelete=1 WHERE reward_id='$rid'");
	?>
	<script>
	alert("Reward removed!");
	window.location="admin_reward.php";
	</script>
	<?php
}

?>
