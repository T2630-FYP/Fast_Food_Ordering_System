<?php

// Keep every ordering page on the same SST and delivery-fee calculation.
if(!defined("EASYORDER_SST_RATE"))
{
	define("EASYORDER_SST_RATE",0.06);
}
if(!defined("EASYORDER_DELIVERY_FEE"))
{
	define("EASYORDER_DELIVERY_FEE",5.00);
}

if(!function_exists("easyorder_money"))
{
	function easyorder_money($amount)
	{
		return round((float)$amount,2);
	}
}

if(!function_exists("easyorder_sst_amount"))
{
	function easyorder_sst_amount($subtotal)
	{
		return easyorder_money(max(0.00,(float)$subtotal)*EASYORDER_SST_RATE);
	}
}

if(!function_exists("easyorder_delivery_amount"))
{
	function easyorder_delivery_amount($delivery_method)
	{
		return in_array((string)$delivery_method,array("Delivery","Yes"),true) ? EASYORDER_DELIVERY_FEE : 0.00;
	}
}

if(!function_exists("easyorder_order_pricing"))
{
	function easyorder_order_pricing($subtotal,$delivery_method)
	{
		$subtotal = easyorder_money(max(0.00,(float)$subtotal));
		$sst = easyorder_sst_amount($subtotal);
		$delivery_fee = easyorder_delivery_amount($delivery_method);

		return array(
			"subtotal"=>$subtotal,
			"sst"=>$sst,
			"delivery_fee"=>$delivery_fee,
			"total"=>easyorder_money($subtotal+$sst+$delivery_fee)
		);
	}
}

?>
