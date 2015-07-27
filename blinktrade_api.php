<?php

function blinktrade_api_query($currency, $method, array $req = array()) {

	$api_ver = 'v1';
	// generate the POST data string
	$post_data = http_build_query($req, '', '&');
	$url = "https://api.blinktrade.com/api/$api_ver/$currency/$method?$post_data";

	// our curl handle (initialize if required)
	static $ch = null;
	if (is_null($ch)) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Blinktrade API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

	// run the query
	$res = curl_exec($ch);

	if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
	$dec = json_decode($res, true);
	if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
	return $dec;
}

function foxbit_api_query($method, array $req = array()) {
	return blinktrade_api_query("BRL", $method, $req);
}

// cache
$orderbook = false;
function foxbit_orderbook() {
	global $orderbook;
	if ($orderbook === false) {
		$orderbook = foxbit_api_query("orderbook");
	}
	return $orderbook;
}

function blinktrade_prune_orders($orders, $brl_depth = -1) {
	if ($brl_depth < 0) {
                return $orders;
        }

	$curr_depth = 0;
	$depth_orders = array();
	foreach ($orders as $order) {
		$order[3] = $order[0] * $order[1]; // volume in BRL
		$curr_depth += $order[3];
		$order[4] = $curr_depth;
		array_push($depth_orders, $order);
		if ($curr_depth >= $brl_depth) {
			return $depth_orders;
		}
	}
}

function foxbit_bids($brl_depth = -1) {
	$ret = foxbit_orderbook();
	$bids = $ret['bids'];
	return blinktrade_prune_orders($bids, $brl_depth);
}

function foxbit_asks($brl_depth = -1) {
	$ret = foxbit_orderbook();
	$asks = $ret['asks'];
	return blinktrade_prune_orders($asks, $brl_depth);
}

function total_volume_buy($brl_depth) {
	$orders = foxbit_asks($brl_depth);
	$current_volume = 0;
	$curr_depth = 0;
	foreach ($orders as $order) {
		$curr_depth += $order[3];
		if ($curr_depth <= $brl_depth) {
			$current_volume += $order[1];
		} else {
			$remaining_brl = $brl_depth - ($curr_depth - $order[3]);
			$remaining_btc = $remaining_brl / $order[0];
			$current_volume += $remaining_btc;
			break;
		}
	}
	return $current_volume;
}

?>
