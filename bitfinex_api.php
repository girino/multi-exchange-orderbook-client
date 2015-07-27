<?php
require_once('query_functions.php');
require_once('fees.php');

function bitfinex_api_query($currency, $method, array $req = array()) {

	$api_ver = 'v1';
	// generate the POST data string
	$post_data = http_build_query($req, '', '&');
	$url = "https://api.bitfinex.com/$api_ver/$method/$currency";

	return api_query($url, $req);
}

// cache
$orderbook_bitfinex = false;
function bitfinex_orderbook() {
	global $orderbook_bitfinex;
	if ($orderbook_bitfinex === false) {
		$orderbook_bitfinex = bitfinex_api_query('BTCUSD', 'book');
	}
	return $orderbook_bitfinex;
}

function bitfinex_bids() {
	$ret = bitfinex_orderbook();
	return $ret['bids'];
}

function bitfinex_asks() {
	$ret = bitfinex_orderbook();
	return $ret['asks'];
}

function bitfinex_prune_orders($orders, $btc_depth=-1) {
	if ($btc_depth < 0) {
		return $orders;
	}

	$curr_depth = 0;
	$depth_orders = array();
	foreach ($orders as $order) {
		//print_r($order);
		$curr_depth += $order['amount'];
		$order['cumul'] = $curr_depth;
		array_push($depth_orders, $order);
		if ($curr_depth >= $btc_depth) {
			return $depth_orders;
		}
	}
}

function total_amount_sold($btc_depth) {
	$orders = bitfinex_prune_orders(bitfinex_bids(), $btc_depth);
	$current_usd = 0;
	$curr_depth = 0;
	foreach ($orders as $order) {
		$curr_depth += $order['amount'];
		if ($curr_depth <= $btc_depth) {
			$current_usd += ($order['amount'] * $order['price']);
		} else {
			$remaining_btc = $btc_depth - ($curr_depth - $order['amount']);
			$remaining_usd = $remaining_btc * $order['price'];
			$current_usd += $remaining_usd;
			break;
		}
	}
	return $current_usd;
}

?>
