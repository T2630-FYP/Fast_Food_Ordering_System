<?php

// Wallet PINs protect wallet access and are deliberately separate from the
// demonstration member password used to sign in to the website.
function easyorder_wallet_pin_valid($pin)
{
	return preg_match("/^\d{6}$/",(string)$pin)===1;
}

// The legacy column name is retained. New PINs are six-digit text; an existing
// hash is replaced only after the customer supplies the matching original PIN.
function easyorder_wallet_verify_pin($connect,$wallet,$pin)
{
	$pin = (string)$pin;
	if(!easyorder_wallet_pin_valid($pin))
	{
		return false;
	}

	$stored_pin = (string)$wallet["wallet_pin_hash"];
	if(easyorder_wallet_pin_valid($stored_pin))
	{
		return hash_equals($stored_pin,$pin);
	}
	if(!password_verify($pin,$stored_pin))
	{
		return false;
	}

	$stmt = mysqli_prepare($connect,"UPDATE wallets SET wallet_pin_hash=? WHERE wallet_id=? AND wallet_member=? AND BINARY wallet_pin_hash=?");
	if(!$stmt)
	{
		throw new Exception("The wallet service is temporarily unavailable.");
	}
	mysqli_stmt_bind_param($stmt,"siis",$pin,$wallet["wallet_id"],$wallet["wallet_member"],$stored_pin);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		throw new Exception("The wallet service is temporarily unavailable.");
	}
	$updated = mysqli_stmt_affected_rows($stmt)===1;
	mysqli_stmt_close($stmt);

	// A concurrent PIN change must not be overwritten by this conversion.
	return $updated;
}

function easyorder_wallet_html($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"ISO-8859-1");
}

// Load only the wallet owned by the requested member. A row lock is used by
// balance-changing operations so two requests cannot spend the same balance.
function easyorder_wallet_load($connect,$member_id,$lock=false)
{
	$sql = "SELECT wallet_id,wallet_member,wallet_pin_hash,wallet_balance,wallet_created_at,wallet_updated_at FROM wallets WHERE wallet_member=? LIMIT 1";
	if($lock)
	{
		$sql .= " FOR UPDATE";
	}

	$stmt = mysqli_prepare($connect,$sql);
	if(!$stmt)
	{
		throw new Exception("The wallet service is temporarily unavailable.");
	}
	mysqli_stmt_bind_param($stmt,"i",$member_id);
	if(!mysqli_stmt_execute($stmt))
	{
		mysqli_stmt_close($stmt);
		throw new Exception("The wallet service is temporarily unavailable.");
	}
	$result = mysqli_stmt_get_result($stmt);
	$wallet = mysqli_fetch_assoc($result);
	mysqli_stmt_close($stmt);

	return $wallet ?: null;
}

// Wallet access is time-limited and bound to the current member. The raw PIN
// is never placed in the session.
function easyorder_wallet_is_unlocked($member_id)
{
	return isset($_SESSION["wallet_unlocked_member"],$_SESSION["wallet_unlocked_until"])
		&& (int)$_SESSION["wallet_unlocked_member"]===(int)$member_id
		&& (int)$_SESSION["wallet_unlocked_until"]>=time();
}

function easyorder_wallet_unlock($member_id)
{
	$_SESSION["wallet_unlocked_member"] = (int)$member_id;
	$_SESSION["wallet_unlocked_until"] = time()+900;
}

function easyorder_wallet_lock()
{
	unset($_SESSION["wallet_unlocked_member"],$_SESSION["wallet_unlocked_until"]);
}

// Transaction references contain no customer or card data.
function easyorder_wallet_reference($prefix)
{
	return strtoupper((string)$prefix)."-".date("YmdHis")."-".strtoupper(bin2hex(random_bytes(5)));
}

