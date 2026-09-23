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

<head><!--Manage order page-->
<title>Manage Orders</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">

<script type="text/javascript">
function confirmation()//JavaScript confirm box shown before an order record is deleted
{
	let option;
	option=confirm("Are you sure you want to delete this order?");
	return option;
}

function update_status()//Validate the order status form before it is submitted to the server
{
	let status;
	let status_status=false;

	status=document.orderfrm.order_status.value;

	if(status=="0")
	{
		document.getElementById("err_status").innerHTML="Please select an order status";
	}
	else
	{
		document.getElementById("err_status").innerHTML="";
		status_status=true;
	}

	if(status_status==true)
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

<body class="admin-body">

<?php easyorder_admin_shell_start("admin_order.php"); ?>

<h2 class="section-title">Manage Orders</h2>
<p class="intro">View, update and delete customer orders from this page. Click <b>Update</b> on any row to change an order status, or <b>Delete</b> to remove it.</p>

<h2 class="section-title">Customer Orders</h2>
<table class="manage-table" border="1"><!--Table section for displaying order records (joined with member) from the database-->
<tr>
<th>Order ID</th>
<th>Member</th>
<th>Order Date</th>
<th>Total (RM)</th>
<th>Payment</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php
//select from 2 tables - match each order to its member to show the member name
$result = mysqli_query($connect,"SELECT * FROM orders,member WHERE order_member=member_id AND order_isDelete=0 ORDER BY order_id DESC");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td><?php echo $row['order_id']; ?></td>
<td><?php echo $row['member_name']; ?></td>
<td><?php echo $row['order_date']; ?></td>
<td><?php echo number_format($row['order_total'],2); ?></td>
<td><?php echo $row['order_payment']; ?></td>
<td><?php echo $row['order_status']; ?></td>
<td>
<input type="button" class="update-btn" value="Update" onclick="location='admin_order.php?edit&id=<?php echo $row['order_id']; ?>'">
<input type="button" class="delete-btn" value="Delete" onclick="if(confirmation()==true){location='admin_order.php?del=1&id=<?php echo $row['order_id']; ?>'}">
</td>
</tr>

<?php
}
?>

</table>

<hr>

<?php
//if an Update button was clicked, get the chosen order and fill in the status form below
$oid="";
$ostatus="";
$current_order="No order selected yet. Click <b>Update</b> on an order above.";

if(isset($_GET["edit"]))
{
	$oid = (int)$_GET["id"];
	$result = mysqli_query($connect,"SELECT * FROM orders WHERE order_id='$oid'");
	$row = mysqli_fetch_assoc($result);
	$ostatus = $row["order_status"];
	$current_order="Updating status for Order ".$oid;
}
?>

<h2 class="section-title">Update Order Status</h2>
<p class="intro">Orders are created by customers when they check out, so they cannot be added manually here. Click <b>Update</b> on any order above to change its status.</p>

<div class="manage-form-box"><!--Form section for user input-->
<form name="orderfrm" method="post" action="" onsubmit="return update_status()">
<fieldset>
<legend>Order Status</legend>

<p style="text-align:center; color:#9E0B22; font-weight:bold;"><?php echo $current_order; ?></p>

<label>Order ID</label>
<input type="text" name="order_id" value="<?php echo htmlspecialchars((string)$oid,ENT_QUOTES,'UTF-8'); ?>" disabled placeholder="Select from table">

<br><br><label>Order Status</label>
<select name="order_status">
<option value="0">Select an order status</option>
<option value="Preparing" <?php if($ostatus=="Preparing") echo "selected"; ?>>Preparing</option>
<option value="Ready for Pickup" <?php if($ostatus=="Ready for Pickup") echo "selected"; ?>>Ready for Pickup</option>
<option value="Picked Up" <?php if($ostatus=="Picked Up") echo "selected"; ?>>Picked Up</option>
<option value="Out for Delivery" <?php if($ostatus=="Out for Delivery") echo "selected"; ?>>Out for Delivery</option>
<option value="Delivered" <?php if($ostatus=="Delivered") echo "selected"; ?>>Delivered</option>
</select>
<br><span class="error" id="err_status"></span>

<div style="clear:both"></div>

<p style="text-align:center;">
<input type="submit" class="save-btn" id="savebtn" name="savebtn" value="Update Status">
</p>

</fieldset>
</form>
</div>

<?php easyorder_admin_shell_end(); ?>

</body>

</html>

<?php

//update the status of the chosen order (the order id comes from the url)
if(isset($_POST["savebtn"]) && isset($_GET["edit"]))
{
	$oid = (int)$_GET["id"];
	$ostatus = (string)($_POST["order_status"] ?? "");
	$allowed_statuses = array("Preparing","Ready for Pickup","Picked Up","Out for Delivery","Delivered");

	if(!in_array($ostatus,$allowed_statuses,true))
	{
	?>
	<script>
	alert("Please select a valid order status.");
	</script>
	<?php
	}
	else
	{
		mysqli_query($connect,"UPDATE orders SET order_status='$ostatus' WHERE order_id='$oid'");
	?>
	<script>
	alert("Order status updated!");
	window.location="admin_order.php";
	</script>
	<?php
	}
}

//remove an order from the list (soft delete - set order_isDelete to 1)
if(isset($_GET["del"]))
{
	$oid = (int)$_GET["id"];

	//take back the loyalty points this order had earned, then soft-delete the order
	$ordq = mysqli_query($connect,"SELECT order_member,order_total FROM orders WHERE order_id='$oid' AND order_isDelete=0");
	if(mysqli_num_rows($ordq)>0)
	{
		$ordrow = mysqli_fetch_assoc($ordq);
		$order_member = $ordrow["order_member"];
		$refund_earned = floor($ordrow["order_total"] * 10);
		mysqli_query($connect,"UPDATE member SET member_points=member_points-'$refund_earned' WHERE member_id='$order_member'");
	}

	mysqli_query($connect,"UPDATE orders SET order_isDelete=1 WHERE order_id='$oid'");
	?>
	<script>
	alert("Order removed!");
	window.location="admin_order.php";
	</script>
	<?php
}

?>
