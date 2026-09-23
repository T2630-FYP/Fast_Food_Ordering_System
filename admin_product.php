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

<head><!--Manage product page-->
<title>Manage Products</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="admin_style.css">

<script type="text/javascript">
function confirmation()//JavaScript confirm box shown before a product record is deleted
{
	let option;
	option=confirm("Are you sure you want to delete this product?");
	return option;
}

function save_product()//Validate the product form before it is submitted to the server
{
	let id,name,description,category,price,stock,status;
	let id_status=false,name_status=false,description_status=false,category_status=false,price_status=false,stock_status=false,status_status=false;

	id=document.productfrm.product_id.value;
	name=document.productfrm.product_name.value;
	description=document.productfrm.product_desc.value;
	category=document.productfrm.product_category.value;
	price=document.productfrm.product_price.value;
	stock=document.productfrm.product_stock.value;
	status=document.productfrm.product_status.value;

	if(id=="")
	{
		document.getElementById("err_id").innerHTML="Please enter the product ID";
	}
	else
	{
		document.getElementById("err_id").innerHTML="";
		id_status=true;
	}

	if(name=="")
	{
		document.getElementById("err_name").innerHTML="Please enter the product name";
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

	if(category=="0")
	{
		document.getElementById("err_category").innerHTML="Please select a category";
	}
	else
	{
		document.getElementById("err_category").innerHTML="";
		category_status=true;
	}

	if(price==""||isNaN(price)||Number(price)<0.01||Number(price)>999.99)
	{
		document.getElementById("err_price").innerHTML="Please enter a price from RM0.01 to RM999.99";
	}
	else
	{
		document.getElementById("err_price").innerHTML="";
		price_status=true;
	}

	if(stock==""||isNaN(stock))
	{
		document.getElementById("err_stock").innerHTML="Please enter a valid stock quantity";
	}
	else
	{
		document.getElementById("err_stock").innerHTML="";
		stock_status=true;
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

	if(id_status==true&&name_status==true&&description_status==true&&category_status==true&&price_status==true&&stock_status==true&&status_status==true)
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

<?php easyorder_admin_shell_start("admin_product.php"); ?>

<h2 class="section-title">Manage Products</h2>
<p class="intro">View, add, update and delete menu items from this page. Click <b>Update</b> on any row to edit a product record, or <b>Delete</b> to remove it.</p>

<h2 class="section-title">Existing Products</h2>
<table class="manage-table" border="1"><!--Table section for displaying product records from the database-->
<tr>
<th>Product ID</th>
<th>Product Name</th>
<th>Category</th>
<th>Price (RM)</th>
<th>Stock</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php
$result = mysqli_query($connect,"SELECT * FROM product WHERE product_isDelete=0");

while($row = mysqli_fetch_assoc($result))
{
?>

<tr>
<td><?php echo $row['product_id']; ?></td>
<td><?php echo $row['product_name']; ?></td>
<td><?php echo $row['product_category']; ?></td>
<td><?php echo number_format($row['product_price'],2); ?></td>
<td><?php echo $row['product_stock']; ?></td>
<td><?php echo $row['product_status']; ?></td>
<td>
<input type="button" class="update-btn" value="Update" onclick="location='admin_product.php?edit&id=<?php echo $row['product_id']; ?>'">
<input type="button" class="delete-btn" value="Delete" onclick="if(confirmation()==true){location='admin_product.php?del=1&id=<?php echo $row['product_id']; ?>'}">
</td>
</tr>

<?php
}
?>

</table>

<hr>

<?php
//if an Update button was clicked, get the chosen product and fill in the form below
$pid="";
$pname="";
$pdesc="";
$pcategory="";
$pprice="";
$pstock="";
$pstatus="";
$form_title="Product Details";
$btn_label="Save Product";

if(isset($_GET["edit"]))
{
	$pid = mysqli_real_escape_string($connect,$_GET["id"]);
	$result = mysqli_query($connect,"SELECT * FROM product WHERE product_id='$pid'");
	$row = mysqli_fetch_assoc($result);
	$pname = $row["product_name"];
	$pdesc = $row["product_desc"];
	$pcategory = $row["product_category"];
	$pprice = $row["product_price"];
	$pstock = $row["product_stock"];
	$pstatus = $row["product_status"];
	$form_title="Update Product (".$pid.")";
	$btn_label="Update Product";
}
?>

<h2 class="section-title">Add / Update Product</h2>
<p class="intro">Fill in the details below to add a new product. To edit an existing product, click <b>Update</b> on the table above and the form will be filled in for you.</p>

<div class="manage-form-box"><!--Form section for user input-->
<form name="productfrm" method="post" action="" onsubmit="return save_product()">
<fieldset>
<legend id="form_title"><?php echo htmlspecialchars($form_title,ENT_QUOTES,'UTF-8'); ?></legend>

<label>Product ID</label>
<input type="text" name="product_id" value="<?php echo htmlspecialchars($pid,ENT_QUOTES,'UTF-8'); ?>" <?php if(isset($_GET["edit"])) echo "disabled"; ?> placeholder="e.g. P022">
<br><span class="error" id="err_id"></span>

<br><br><label>Product Name</label>
<input type="text" name="product_name" value="<?php echo htmlspecialchars($pname,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. Beef Burger">
<br><span class="error" id="err_name"></span>

<br><br><label>Description</label>
<input type="text" name="product_desc" value="<?php echo htmlspecialchars($pdesc,ENT_QUOTES,'UTF-8'); ?>" placeholder="Short description shown to customers">
<br><span class="error" id="err_desc"></span>

<br><br><label>Category</label>
<select name="product_category">
<option value="0">Select a category</option>
<?php
//load the category list straight from the database so newly added categories appear here too
$cat_result = mysqli_query($connect,"SELECT * FROM category WHERE category_isDelete=0");
while($cat_row = mysqli_fetch_assoc($cat_result))
{
	$catname = $cat_row["category_name"];
?>
<option value="<?php echo htmlspecialchars($catname,ENT_QUOTES,'UTF-8'); ?>" <?php if($pcategory==$catname) echo "selected"; ?>><?php echo htmlspecialchars($catname,ENT_QUOTES,'UTF-8'); ?></option>
<?php
}
?>
</select>
<br><span class="error" id="err_category"></span>

<br><br><label>Price (RM)</label>
<input type="number" name="product_price" min="0.01" max="999.99" step="0.01" value="<?php echo htmlspecialchars($pprice,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. 12.90">
<br><span class="error" id="err_price"></span>

<br><br><label>Stock</label>
<input type="number" name="product_stock" min="0" value="<?php echo htmlspecialchars($pstock,ENT_QUOTES,'UTF-8'); ?>" placeholder="e.g. 100">
<br><span class="error" id="err_stock"></span>

<br><br><label>Status</label>
<select name="product_status">
<option value="0">Select a status</option>
<option value="Active" <?php if($pstatus=="Active") echo "selected"; ?>>Active</option>
<option value="Out of Stock" <?php if($pstatus=="Out of Stock") echo "selected"; ?>>Out of Stock</option>
<option value="Inactive" <?php if($pstatus=="Inactive") echo "selected"; ?>>Inactive</option>
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

//save the product - decide whether to INSERT a new record or UPDATE an existing one
if(isset($_POST["savebtn"]))
{
	$pname = mysqli_real_escape_string($connect,$_POST["product_name"]);
	$pdesc = mysqli_real_escape_string($connect,$_POST["product_desc"]);
	$pcategory = mysqli_real_escape_string($connect,$_POST["product_category"]);
	$pprice = $_POST["product_price"];
	$pstock = mysqli_real_escape_string($connect,$_POST["product_stock"]);
	$pstatus = mysqli_real_escape_string($connect,$_POST["product_status"]);

	//repeat the price check on the server because browser validation can be bypassed
	if(!is_numeric($pprice)||!is_finite((float)$pprice)||(float)$pprice<0.01||(float)$pprice>999.99)
	{
		?>
		<script>
		alert("Please enter a product price from RM0.01 to RM999.99.");
		</script>
		<?php
	}
	else
	{
		//store a normal two-decimal value after it passes validation
		$pprice = number_format((float)$pprice,2,'.','');

		if(isset($_GET["edit"]))
		{
			//UPDATE mode - the product id comes from the url
			$pid = mysqli_real_escape_string($connect,$_GET["id"]);

			mysqli_query($connect,"UPDATE product SET product_name='$pname',product_desc='$pdesc',
											  product_category='$pcategory',
											  product_price='$pprice',
											  product_stock='$pstock',
											  product_status='$pstatus'
											  WHERE product_id='$pid'");

			//order items keep their own product id, so no name syncing is needed here
			?>
			<script>
			alert("Product updated!");
			window.location="admin_product.php";
			</script>
			<?php
		}
		else
		{
			//ADD mode - the product id comes from the form, check it is not already used
			$pid = mysqli_real_escape_string($connect,$_POST["product_id"]);

			$check = mysqli_query($connect,"SELECT * FROM product WHERE product_id='$pid'");
			$count = mysqli_num_rows($check);

			if($count != 0)
			{
			?>
			<script>
			alert("The product ID is already in use. Please change.");
			</script>
			<?php
			}
			else
			{
				mysqli_query($connect,"INSERT INTO product(product_id,product_name,product_desc,product_category,product_price,product_stock,product_status)VALUES('$pid','$pname','$pdesc','$pcategory','$pprice','$pstock','$pstatus')");
				?>
				<script>
				alert("Product saved!");
				window.location="admin_product.php";
				</script>
				<?php
			}
		}
	}
}

//remove a product from the list (soft delete - set product_isDelete to 1)
if(isset($_GET["del"]))
{
	$pid = mysqli_real_escape_string($connect,(string)$_GET["id"]);

	mysqli_query($connect,"UPDATE product SET product_isDelete=1 WHERE product_id='$pid'");

	//any reward linked to this product can no longer be offered.
	//the product row still exists (this is only a soft delete), so we keep the
	//reward_product link intact - clearing it to '' would break the foreign key
	//fk_reward_product, since no product has an id of ''. Instead we deactivate the
	//reward, which hides it from customers (reward.php only shows Active rewards
	//whose linked product is also active and not deleted).
	mysqli_query($connect,"UPDATE reward SET reward_status='Inactive' WHERE reward_product='$pid'");
	?>
	<script>
	alert("Product removed!");
	window.location="admin_product.php";
	</script>
	<?php
}

?>
