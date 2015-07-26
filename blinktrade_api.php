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
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BiAffNet API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
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

function foxbit_bids($brl_depth = -1) {
	$ret = foxbit_orderbook();
	$bids = $ret['bids'];
	if ($brl_depth < 0) {
		return $bids;
	}
	$curr_depth = 0;
	$depth_bids = array();
	foreach ($bids as $bid) {
		$bid[3] = $bid[0] * $bid[1]; // volume in BRL
		$curr_depth += $bid[3];
		$bid[4] = $curr_depth;
		array_push($depth_bids, $bid);
		if ($curr_depth >= $brl_depth) {
			return $depth_bids;
		}
	}
}

function foxbit_asks($brl_depth = -1) {
	$ret = foxbit_orderbook();
	$asks = $ret['asks'];
	if ($brl_depth < 0) {
		return $asks;
	}
	$curr_depth = 0;
	$depth_asks = array();
	foreach ($asks as $ask) {
		$ask[3] = $ask[0] * $ask[1]; // volume in BRL
		$curr_depth += $ask[3];
		$ask[4] = $curr_depth;
		array_push($depth_asks, $ask);
		if ($curr_depth >= $brl_depth) {
			return $depth_asks;
		}
	}
}

print_r (foxbit_bids(10000));
print_r (foxbit_asks(100));

?>
