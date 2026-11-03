<?php

function catalog_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

function catalog_image_placeholder()
{
	return "data:image/svg+xml,".rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120"><rect width="160" height="120" rx="10" fill="#F5F0EB"/><g fill="none" stroke="#A89B90" stroke-width="3"><rect x="45" y="31" width="70" height="58" rx="6"/><circle cx="65" cy="49" r="6"/><path d="m48 83 23-23 14 14 11-11 16 20"/></g></svg>');
}

function catalog_category_image($stored_path)
{
	$path = catalog_local_image_path($stored_path);
	if($path!==null && is_file(__DIR__."/".$path))
	{
		return "image/".rawurlencode(substr($path,6));
	}

	// A neutral placeholder protects legacy or missing files; new categories require an upload.
	return catalog_image_placeholder();
}

function catalog_product_image($stored_path)
{
	$path = catalog_local_image_path($stored_path);
	if($path!==null)
	{
		return "image/".rawurlencode(substr($path,6));
	}

	return "image/logo.jpg";
}

// Keep the original filename rather than silently renaming it. Reject names that
// cannot safely be used as one image file on Windows or by the web server.
function catalog_valid_image_filename($name)
{
	return is_string($name) && $name!=="" && strlen($name)<=249 && preg_match('//u',$name)===1
		&& preg_match('/[\x00-\x1F\x7F\\\\\/:*?"<>|]/u',$name)!==1
		&& rtrim($name," .")===$name
		&& preg_match('/^(?:CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9])(?:\.|$)/i',ltrim($name))!==1
		&& preg_match('/\.(?:php[0-9]*|phtml|pht|phar|cgi|pl|py|asp|aspx|shtml|sh|bat|cmd|exe|com|dll)(?:\.|$)/i',$name)!==1
		&& preg_match('/\.(?:jpe?g|png|webp)$/iD',$name)===1;
}

function catalog_local_image_path($stored_path)
{
	$path = str_replace("\\","/",(string)$stored_path);
	return substr($path,0,6)==="image/" && catalog_valid_image_filename(substr($path,6)) ? $path : null;
}

// Serialize saves in both editors so shared filenames cannot be reused while
// another request is rolling back or removing an obsolete upload.
function catalog_lock_image_uploads($connect)
{
	$name = "easyorder-image-files:".mysqli_fetch_row(mysqli_query($connect,"SELECT DATABASE()"))[0];
	$stmt = mysqli_prepare($connect,"SELECT GET_LOCK(?,10)");
	mysqli_stmt_bind_param($stmt,"s",$name);
	mysqli_stmt_execute($stmt);
	$locked = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
	mysqli_stmt_close($stmt);
	if((int)$locked!==1) throw new RuntimeException("Another image is being saved. Please try again.");
	return $name;
}

function catalog_release_image_uploads($connect,$name)
{
	if($name===null) return;
	try
	{
		$stmt = mysqli_prepare($connect,"SELECT RELEASE_LOCK(?)");
		mysqli_stmt_bind_param($stmt,"s",$name);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	catch(Throwable $error) { error_log("Catalog image lock: ".$error->getMessage()); }
}

function catalog_discard_new_image($stored_path,$created)
{
	$path = catalog_local_image_path($stored_path);
	if(!$created || $path===null) return;
	$directory = realpath(__DIR__."/image");
	$file = __DIR__."/".$path;
	if($directory!==false && realpath(dirname($file))===$directory && is_file($file) && !is_link($file)) @unlink($file);
}

// Called inside the editor's transaction and image lock. Ownership is recorded
// only for newly created files; existing bundled images are never adopted.
function catalog_store_uploaded_image($connect,$file,$required,$label,&$created)
{
	$created = false;
	if($file===null || (is_array($file) && ($file["error"] ?? null)===UPLOAD_ERR_NO_FILE))
	{
		if($required) throw new RuntimeException("Please choose a ".$label." image.");
		return null;
	}
	if(!is_array($file) || !isset($file["error"],$file["size"],$file["tmp_name"],$file["name"])
		|| !is_int($file["error"]) || !is_int($file["size"]) || !is_string($file["name"]) || !is_string($file["tmp_name"]) || $file["error"]!==UPLOAD_ERR_OK)
	{
		throw new RuntimeException("The ".$label." image could not be uploaded.");
	}
	if($file["size"]<=0 || $file["size"]>5*1024*1024) throw new RuntimeException("The ".$label." image must be 5 MB or smaller.");
	if(!is_uploaded_file($file["tmp_name"])) throw new RuntimeException("The uploaded image is invalid.");
	$name = $file["name"];
	if(!catalog_valid_image_filename($name)) throw new RuntimeException("Please use a valid JPG, PNG or WebP filename of 249 bytes or fewer, without path characters or executable extensions.");
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime = $finfo ? finfo_file($finfo,$file["tmp_name"]) : false;
	if($finfo) finfo_close($finfo);
	$extensions = array("image/jpeg"=>array("jpg","jpeg"),"image/png"=>array("png"),"image/webp"=>array("webp"));
	if(!isset($extensions[$mime])) throw new RuntimeException("The selected file is not a supported image.");
	if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),$extensions[$mime],true)) throw new RuntimeException("The filename extension does not match the image format.");
	$dimensions = getimagesize($file["tmp_name"]);
	if($dimensions===false || min($dimensions[0],$dimensions[1])<100 || max($dimensions[0],$dimensions[1])>6000)
	{
		throw new RuntimeException("The ".$label." image dimensions must be between 100 and 6000 pixels.");
	}
	$directory = __DIR__."/image";
	if(!is_dir($directory) || !is_writable($directory)) throw new RuntimeException("The image folder is not available for uploads.");
	$path = "image/".$name;
	$absolute = __DIR__."/".$path;
	if(file_exists($absolute) || is_link($absolute))
	{
		if(!is_link($absolute) && is_file($absolute) && hash_file("sha256",$absolute)===hash_file("sha256",$file["tmp_name"]))
		{
			// Resolve the existing spelling on case-insensitive Windows filesystems.
			if(DIRECTORY_SEPARATOR==="\\")
			{
				foreach(scandir($directory) as $existing)
				{
					$matches = function_exists("mb_strtolower") ? mb_strtolower($existing,"UTF-8")===mb_strtolower($name,"UTF-8") : strcasecmp($existing,$name)===0;
					if($matches) return "image/".$existing;
				}
			}
			return $path;
		}
		throw new RuntimeException('An image named "'.$name.'" already exists with different content. Rename your file and upload it again.');
	}
	$destination = @fopen($absolute,"x+b");
	if($destination===false) throw new RuntimeException("The image filename is already in use or could not be created. Please try another filename.");
	$source = null;
	try
	{
		$source = fopen($file["tmp_name"],"rb");
		if($source===false || stream_copy_to_stream($source,$destination)!==$file["size"] || !fflush($destination)) throw new RuntimeException("The ".$label." image could not be saved.");
		fclose($source);$source = null;fclose($destination);$destination = null;
		$created = true;
		$stmt = mysqli_prepare($connect,"INSERT INTO catalog_image_upload(image_path) VALUES(?) ON DUPLICATE KEY UPDATE image_path=VALUES(image_path)");
		mysqli_stmt_bind_param($stmt,"s",$path);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		return $path;
	}
	catch(Throwable $error)
	{
		if(is_resource($source)) fclose($source);
		if(is_resource($destination)) fclose($destination);
		catalog_discard_new_image($path,true);
		$created = false;
		throw $error;
	}
}

function catalog_remove_unused_image($connect,$stored_path)
{
	$path = catalog_local_image_path($stored_path);
	if($path===null) return;
	try
	{
		$stmt = mysqli_prepare($connect,"SELECT image_path FROM catalog_image_upload WHERE image_path=?");
		mysqli_stmt_bind_param($stmt,"s",$path);mysqli_stmt_execute($stmt);
		$managed = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))!==null;mysqli_stmt_close($stmt);
		if(!$managed) return;
		$stmt = mysqli_prepare($connect,"SELECT (SELECT COUNT(*) FROM product WHERE product_image=?)+(SELECT COUNT(*) FROM category WHERE category_image=?)+(SELECT COUNT(*) FROM reward WHERE reward_image=?)");
		mysqli_stmt_bind_param($stmt,"sss",$path,$path,$path);mysqli_stmt_execute($stmt);
		$references = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];mysqli_stmt_close($stmt);
		if($references>0) return;
		$file = __DIR__."/".$path;
		if(is_link($file) || realpath(dirname($file))!==realpath(__DIR__."/image")) return;
		if(is_file($file) && !@unlink($file)) return;
		$stmt = mysqli_prepare($connect,"DELETE FROM catalog_image_upload WHERE image_path=?");
		mysqli_stmt_bind_param($stmt,"s",$path);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
	}
	catch(Throwable $error) { error_log("Catalog image cleanup: ".$error->getMessage()); }
}

// This helper runs inside one transaction. Completed redemptions retain their
// reward snapshot; the product foreign key becomes NULL when its row is removed.
function catalog_delete_product_rows($connect,$product_id)
{
	$stmt = mysqli_prepare($connect,"SELECT product_image,product_category FROM product WHERE product_id=? FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"s",$product_id);mysqli_stmt_execute($stmt);
	$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);
	if(!$product) throw new RuntimeException("The selected product no longer exists.");
	$images = array($product["product_image"]);
	$stmt = mysqli_prepare($connect,"SELECT redeem_customer,redeem_points FROM redemption WHERE redeem_product=? AND redeem_status='Cart' ORDER BY redeem_customer,redeem_id FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"s",$product_id);mysqli_stmt_execute($stmt);
	$refunds = array();$result = mysqli_stmt_get_result($stmt);
	while($row = mysqli_fetch_assoc($result))
	{
		$customer_id = (int)$row["redeem_customer"];
		$refunds[$customer_id] = ($refunds[$customer_id] ?? 0)+(int)$row["redeem_points"];
	}
	mysqli_stmt_close($stmt);
	foreach($refunds as $customer_id=>$points)
	{
		$stmt = mysqli_prepare($connect,"UPDATE customer SET customer_points=customer_points+? WHERE customer_id=?");
		mysqli_stmt_bind_param($stmt,"ii",$points,$customer_id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
	}
	$stmt = mysqli_prepare($connect,"DELETE FROM redemption WHERE redeem_product=? AND redeem_status='Cart'");
	mysqli_stmt_bind_param($stmt,"s",$product_id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
	$stmt = mysqli_prepare($connect,"SELECT reward_image FROM reward WHERE reward_product=? FOR UPDATE");
	mysqli_stmt_bind_param($stmt,"s",$product_id);mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);while($row = mysqli_fetch_assoc($result)) $images[] = $row["reward_image"];
	mysqli_stmt_close($stmt);
	foreach(array("DELETE FROM reward WHERE reward_product=?","DELETE FROM cart WHERE cart_product=?") as $sql)
	{
		$stmt = mysqli_prepare($connect,$sql);mysqli_stmt_bind_param($stmt,"s",$product_id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
	}
	// Wait for existing reservations before filling snapshots from older checkouts.
	$stmt = mysqli_prepare($connect,"UPDATE order_items SET item_category=? WHERE item_product=? AND item_category=''");
	mysqli_stmt_bind_param($stmt,"ss",$product["product_category"],$product_id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
	$stmt = mysqli_prepare($connect,"DELETE FROM product WHERE product_id=?");
	mysqli_stmt_bind_param($stmt,"s",$product_id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
	return $images;
}

// Product and category removal share the image lock and a single transaction.
// Retry a rolled-back deadlock so cart/reward/checkout actions remain atomic.
function catalog_permanently_remove($connect,$resource,$id)
{
	if(!in_array($resource,array("product","category"),true) || preg_match('/^[A-Z0-9]{1,5}$/D',$id)!==1)
	{
		throw new RuntimeException("Please select a valid catalog record.");
	}
	$image_lock = catalog_lock_image_uploads($connect);
	try
	{
		for($attempt=0;$attempt<3;$attempt++)
		{
			$transaction_started = false;
			try
			{
				mysqli_begin_transaction($connect);$transaction_started = true;
				$images = array();$product_count = 0;
				if($resource==="category")
				{
					$stmt = mysqli_prepare($connect,"SELECT category_name,category_image FROM category WHERE category_id=? FOR UPDATE");
					mysqli_stmt_bind_param($stmt,"s",$id);mysqli_stmt_execute($stmt);
					$category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);
					if(!$category) throw new RuntimeException("The selected category no longer exists.");
					$images[] = $category["category_image"];
					$stmt = mysqli_prepare($connect,"SELECT product_id FROM product WHERE product_category=? ORDER BY product_id FOR UPDATE");
					mysqli_stmt_bind_param($stmt,"s",$category["category_name"]);mysqli_stmt_execute($stmt);
					$products = mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC);mysqli_stmt_close($stmt);
					foreach($products as $product)
					{
						$images = array_merge($images,catalog_delete_product_rows($connect,$product["product_id"]));$product_count++;
					}
					$stmt = mysqli_prepare($connect,"DELETE FROM category WHERE category_id=?");
					mysqli_stmt_bind_param($stmt,"s",$id);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
				}
				else
				{
					$images = catalog_delete_product_rows($connect,$id);$product_count = 1;
				}
				mysqli_commit($connect);$transaction_started = false;
				foreach(array_unique($images) as $image) catalog_remove_unused_image($connect,$image);
				return $product_count;
			}
			catch(Throwable $error)
			{
				if($transaction_started) mysqli_rollback($connect);
				if($error instanceof mysqli_sql_exception && in_array($error->getCode(),array(1205,1213),true) && $attempt<2)
				{
					usleep(20000);continue;
				}
				throw $error;
			}
		}
	}
	finally { catalog_release_image_uploads($connect,$image_lock); }
}

function catalog_product_state($product)
{
	if((int)$product["product_stock"]<=0 || $product["product_status"]==="Out of Stock")
	{
		return array("label" => "Out of Stock", "orderable" => false);
	}

	if($product["product_status"]!=="Active")
	{
		return array("label" => "Unavailable", "orderable" => false);
	}

	return array(
		"label" => "",
		"orderable" => true
	);
}

// Cart and redemption writers must hold the same customer row lock before
// allocating a position. Both kinds of item share this per-customer sequence.
function catalog_next_cart_position($connect,$customer_id,$include_rewards=true)
{
	$sql = "SELECT COALESCE(MAX(cart_position),0)+1 AS next_position FROM cart WHERE cart_customer=?";
	if($include_rewards)
	{
		$sql = "SELECT GREATEST(COALESCE((SELECT MAX(cart_position) FROM cart WHERE cart_customer=?),0),COALESCE((SELECT MAX(redeem_cart_position) FROM redemption WHERE redeem_customer=? AND redeem_status='Cart'),0))+1 AS next_position";
	}
	$stmt = mysqli_prepare($connect,$sql);
	if(!$stmt) throw new RuntimeException("The cart order could not be prepared.");
	if($include_rewards) mysqli_stmt_bind_param($stmt,"ii",$customer_id,$customer_id);
	else mysqli_stmt_bind_param($stmt,"i",$customer_id);
	mysqli_stmt_execute($stmt);
	$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
	mysqli_stmt_close($stmt);
	$position = (int)($row["next_position"] ?? 0);
	if($position<1) throw new RuntimeException("The cart order could not be allocated.");
	return $position;
}

function catalog_public_categories($connect)
{
	$descriptions = array(
		"Fried Chicken" => "Golden, crispy goodness.",
		"Burger" => "Big flavour in every bite.",
		"Side Dishes" => "The perfect sides to complete your meal.",
		"Dessert" => "A sweet way to finish.",
		"Beverage" => "Cold drinks to keep you refreshed."
	);
	$result = mysqli_query($connect,"SELECT category_id,category_name,category_image FROM category
		WHERE category_status='Active'
		ORDER BY category_id");
	$categories = array();
	while($category = mysqli_fetch_assoc($result))
	{
		$category["image"] = catalog_category_image($category["category_image"]);
		$category["description"] = $descriptions[$category["category_name"]] ?? "Discover your next favourite.";
		$categories[] = $category;
	}
	mysqli_free_result($result);
	return $categories;
}
