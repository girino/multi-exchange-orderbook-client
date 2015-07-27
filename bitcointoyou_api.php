<?php
require_once('query_functions.php');
require_once('fees.php');

function b2u_api_query($method, array $req = array()) {
	$url = "http://bitcointoyou.com/API/$method.aspx";
	return api_query($url, $req);
}

// cache
$orderbook_b2u = false;
function b2u_orderbook() {
	global $orderbook_b2u;
	if ($orderbook_b2u === false) {
		$orderbook_b2u = b2u_api_query('orderbook');
	}
	return $orderbook_b2u;
}

function b2u_bids() {
	$ret = b2u_orderbook();
	return $ret['bids'];
}

function b2u_asks() {
	$ret = b2u_orderbook();
	return $ret['asks'];
}

?>
